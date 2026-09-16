<?php
/**
 * Loyalty points: award on sale, revoke on void, redeem at checkout.
 * Points are stored per-customer in a simple ledger table.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SLOY_Loyalty {

	const TABLE_SUFFIX = 'loyalty_points';

	/**
	 * Create ledger table if missing.
	 */
	public static function ensure_schema() {
		global $wpdb;
		static $done = false;
		if ( $done ) {
			return;
		}
		$done    = true;
		$prefix  = $wpdb->prefix . SIMPLE_POS_TABLE_PREFIX;
		$charset = $wpdb->get_charset_collate();
		$wpdb->query( "CREATE TABLE IF NOT EXISTS `{$prefix}" . self::TABLE_SUFFIX . "` ( id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, customer_id BIGINT UNSIGNED NOT NULL, sale_id BIGINT UNSIGNED NULL, points INT NOT NULL, reason VARCHAR(60) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_customer (customer_id) ) {$charset}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * @return array
	 */
	public static function get_settings() {
		$defaults = array(
			'points_per_currency' => 1,
			'currency_per_point'  => 0.01,
			'min_points_redeem'   => 10,
			'max_discount_pct'    => 50,
			'enabled'             => false,
		);
		$saved = get_option( 'simple_pos_loyalty', array() );
		return is_array( $saved ) ? array_merge( $defaults, $saved ) : $defaults;
	}

	/**
	 * Get the net loyalty balance for a customer.
	 *
	 * @param int $customer_id
	 * @return int
	 */
	public static function get_balance( $customer_id ) {
		global $wpdb;
		self::ensure_schema();
		$table  = Simple_POS_DB::table( self::TABLE_SUFFIX );
		$result = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(points),0) FROM {$table} WHERE customer_id = %d", $customer_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return max( 0, $result );
	}

	/**
	 * Award points for a completed sale.
	 *
	 * @param int   $sale_id
	 * @param array $calc
	 * @param array $line_items
	 * @param array $cart_data
	 */
	public static function award_points_for_sale( $sale_id, $calc, $line_items, $cart_data ) {
		$settings = self::get_settings();
		if ( ! $settings['enabled'] ) {
			return;
		}

		$customer_id = isset( $cart_data['customer_id'] ) ? (int) $cart_data['customer_id'] : 0;
		if ( $customer_id <= 0 ) {
			return;
		}

		// Points on the pre-discount total.
		$total     = isset( $calc['total'] ) ? (float) $calc['total'] : 0;
		$points    = (int) floor( $total * $settings['points_per_currency'] );
		$points    = max( 0, $points );

		if ( $points <= 0 ) {
			return;
		}

		global $wpdb;
		$table = Simple_POS_DB::table( self::TABLE_SUFFIX );
		$now   = current_time( 'mysql' );

		// Redeemed points are debited from the same ledger, so a void can
		// reverse both sides symmetrically.
		$redeemed = isset( $cart_data['loyalty_points_redeemed'] ) ? (int) $cart_data['loyalty_points_redeemed'] : 0;
		if ( $redeemed > 0 && $customer_id === (int) ( isset( $cart_data['loyalty_redemption_customer'] ) ? $cart_data['loyalty_redemption_customer'] : 0 ) ) {
			$wpdb->insert(
				$table,
				array(
					'customer_id' => $customer_id,
					'sale_id'     => $sale_id,
					'points'      => 0 - $redeemed,
					'reason'      => 'redeemed',
					'created_at'  => $now,
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		$wpdb->insert(
			$table,
			array(
				'customer_id' => $customer_id,
				'sale_id'     => $sale_id,
				'points'      => $points,
				'reason'      => 'earned',
				'created_at'  => $now,
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Revoke points when a sale is voided.
	 *
	 * @param int $sale_id
	 */
	public static function revoke_points_for_sale( $sale_id ) {
		global $wpdb;
		$table = Simple_POS_DB::table( self::TABLE_SUFFIX );

		// Reverse symmetrically: earned points are revoked, redeemed points
		// are credited back.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT customer_id, points, reason FROM {$table} WHERE sale_id = %d", $sale_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $rows ) {
			return;
		}

		$now = current_time( 'mysql' );
		foreach ( $rows as $row ) {
			if ( 'void_reversal' === $row->reason ) {
				continue;
			}
			$wpdb->insert(
				Simple_POS_DB::table( self::TABLE_SUFFIX ),
				array(
					'customer_id' => (int) $row->customer_id,
					'sale_id'     => $sale_id,
					'points'      => 0 - (int) $row->points,
					'reason'      => 'void_reversal',
					'created_at'  => $now,
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		}
	}

	/**
	 * Compute the pre-tax cart subtotal the same way the core engine does,
	 * so the redemption cap can be enforced as a pre-tax discount.
	 *
	 * @param array $cart_data
	 * @return float
	 */
	private static function cart_subtotal( $cart_data ) {
		$subtotal = 0.0;
		foreach ( (array) ( isset( $cart_data['items'] ) ? $cart_data['items'] : array() ) as $raw ) {
			$product_id = isset( $raw['product_id'] ) ? (int) $raw['product_id'] : 0;
			$variant_id = isset( $raw['variant_id'] ) ? (int) $raw['variant_id'] : 0;
			$qty        = isset( $raw['qty'] ) ? (int) $raw['qty'] : 0;
			if ( $product_id <= 0 || $qty <= 0 ) {
				continue;
			}
			$product = Simple_POS_Products::get_product( $product_id );
			if ( ! $product ) {
				continue;
			}
			if ( $variant_id ) {
				$variant = Simple_POS_Variants::get_variant( $variant_id );
				if ( ! $variant || (int) $variant->parent_product_id !== $product_id ) {
					continue;
				}
				$price = null !== ( $variant->price ?? null ) ? (float) $variant->price : (float) $product->price;
			} else {
				$price = (float) $product->price;
			}
			$subtotal += $price * $qty;
		}
		return $subtotal;
	}

	/**
	 * Apply loyalty discount via the simple_pos_cart_data filter.
	 * Client sends `loyalty_points_to_redeem` in the cart payload.
	 *
	 * @param array $cart_data
	 * @return array
	 */
	public static function apply_cart_discount( $cart_data ) {
		$settings = self::get_settings();
		if ( ! $settings['enabled'] ) {
			return $cart_data;
		}

		$points = isset( $cart_data['loyalty_points_to_redeem'] ) ? (int) $cart_data['loyalty_points_to_redeem'] : 0;
		if ( $points < $settings['min_points_redeem'] ) {
			return $cart_data;
		}

		$customer_id = isset( $cart_data['customer_id'] ) ? (int) $cart_data['customer_id'] : 0;
		if ( $customer_id <= 0 ) {
			return $cart_data;
		}

		// Cap by balance, then by the max-discount policy (of pre-tax subtotal).
		$balance  = self::get_balance( $customer_id );
		$points   = min( $points, $balance );
		$discount = round( $points * $settings['currency_per_point'], Simple_POS_DB::currency_decimals() );

		$subtotal = self::cart_subtotal( $cart_data );
		$max_amount = round( $subtotal * $settings['max_discount_pct'] / 100, Simple_POS_DB::currency_decimals() );
		$discount   = min( $discount, $max_amount );

		if ( $discount > 0 ) {
			$cart_data['discount_type']               = 'fixed';
			$cart_data['discount_amount']             = $discount;
			$cart_data['loyalty_points_redeemed']     = (int) floor( $discount / $settings['currency_per_point'] );
			$cart_data['loyalty_redemption_customer'] = $customer_id;
		}

		return $cart_data;
	}

	/**
	 * REST: Get loyalty balance.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public static function rest_balance( $request ) {
		$customer_id = $request->get_param( 'customer_id' );
		if ( ! $customer_id ) {
			return new WP_REST_Response( array( 'balance' => 0 ), 200 );
		}
		return new WP_REST_Response(
			array(
				'balance'   => self::get_balance( (int) $customer_id ),
				'settings'  => self::get_settings(),
			),
			200
		);
	}
}
