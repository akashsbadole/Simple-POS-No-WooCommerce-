<?php
/**
 * Gift card codes with stored balance, redeemable at checkout.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGC_Gift_Cards {

	const TABLE_SUFFIX = 'gift_cards';

	public static function ensure_schema() {
		global $wpdb;
		static $done = false;
		if ( $done ) {
			return;
		}
		$done    = true;
		$prefix  = $wpdb->prefix . SIMPLE_POS_TABLE_PREFIX;
		$charset = $wpdb->get_charset_collate();
		$wpdb->query( "CREATE TABLE IF NOT EXISTS `{$prefix}" . self::TABLE_SUFFIX . "` ( id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, code VARCHAR(64) NOT NULL, balance DECIMAL(12,2) NOT NULL DEFAULT 0, status VARCHAR(20) NOT NULL DEFAULT 'active', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL, PRIMARY KEY (id), UNIQUE KEY uniq_code (code) ) {$charset}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * @param string $code
	 * @param float  $amount
	 * @return int New card ID.
	 */
	public static function create_card( $code, $amount ) {
		global $wpdb;
		self::ensure_schema();
		$amount = max( 0, (float) $amount );
		$wpdb->insert(
			Simple_POS_DB::table( self::TABLE_SUFFIX ),
			array(
				'code'       => strtoupper( sanitize_text_field( $code ) ),
				'balance'    => $amount,
				'status'     => 'active',
				'created_at' => current_time( 'mysql' ),
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->insert_id;
	}

	public static function get_cards() {
		global $wpdb;
		self::ensure_schema();
		$table = Simple_POS_DB::table( self::TABLE_SUFFIX );
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * @param string $code
	 * @return object|null
	 */
	public static function get_by_code( $code ) {
		global $wpdb;
		self::ensure_schema();
		$table = Simple_POS_DB::table( self::TABLE_SUFFIX );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE code = %s AND status = 'active'", strtoupper( trim( $code ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function top_up( $card_id, $amount ) {
		global $wpdb;
		self::ensure_schema();
		$amount = max( 0, (float) $amount );
		if ( $amount <= 0 ) {
			return;
		}
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE %s SET balance = balance - %f, updated_at = %s WHERE id = %d',
				Simple_POS_DB::table( self::TABLE_SUFFIX ),
				$amount,
				current_time( 'mysql' ),
				(int) $card_id
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Apply a gift card to a checkout when client sends `gift_card_code`.
	 *
	 * @param array $cart_data
	 * @return array
	 */
	public static function apply_gift_card( $cart_data ) {
		$code = isset( $cart_data['gift_card_code'] ) ? (string) $cart_data['gift_card_code'] : '';
		if ( '' === trim( $code ) ) {
			return $cart_data;
		}

		$card = self::get_by_code( $code );
		if ( ! $card ) {
			$cart_data['gift_card_error'] = __( 'Invalid or inactive gift card code.', 'wp-pos-plugin' );
			return $cart_data;
		}

		$remaining = isset( $cart_data['total_after_payment'] ) ? (float) $cart_data['total_after_payment'] : ( isset( $cart_data['total'] ) ? (float) $cart_data['total'] : 0 );
		$remaining = max( 0, $remaining );
		if ( $remaining <= 0 ) {
			$cart_data['gift_card_error'] = __( 'Nothing left to pay with the gift card.', 'wp-pos-plugin' );
			return $cart_data;
		}

		$use = min( (float) $card->balance, $remaining );

		if ( $use > 0 ) {
			$cart_data['gift_card_applied'] = $use;
			// Recompute amount paid in the base currency (the client already converts).
			if ( isset( $cart_data['amount_paid'] ) ) {
				$cart_data['amount_paid'] = (float) $cart_data['amount_paid'] - $use;
			}
			$cart_data['gift_card_code'] = $card->code;
		}

		return $cart_data;
	}

	/**
	 * Consume a gift card balance after a successful sale.
	 * Hooked by the terminal via {@see simple_pos_sale_created}.
	 *
	 * @param string $code
	 * @param float  $amount
	 */
	public static function redeem( $code, $amount ) {
		global $wpdb;
		$card = self::get_by_code( $code );
		if ( ! $card ) {
			return;
		}
		$amount = max( 0, (float) $amount );
		if ( $amount <= 0 ) {
			return;
		}
		self::ensure_schema();
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE %s SET balance = balance - %f, updated_at = %s WHERE id = %d',
				Simple_POS_DB::table( self::TABLE_SUFFIX ),
				$amount,
				current_time( 'mysql' ),
				(int) $card->id
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Register custom REST args for product creation/update so a gift-card
	 * product can carry its initial value. Filter name:
	 * simple_pos_rest_product_args.
	 *
	 * @param array $args
	 * @return array
	 */
	public static function register_gift_card_product_arg( $args ) {
		if ( ! isset( $args['gift_card_value'] ) ) {
			$args['gift_card_value'] = array(
				'type'              => 'number',
				'default'           => 0,
				'sanitize_callback' => 'floatval',
			);
		}
		return $args;
	}

	/**
	 * REST lookup for the terminal.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public static function rest_lookup( $request ) {
		$code = $request->get_param( 'code' );
		$card = $code ? self::get_by_code( $code ) : null;
		if ( ! $card ) {
			return new WP_REST_Response( array( 'found' => false ), 200 );
		}
		return new WP_REST_Response(
			array(
				'found'      => true,
				'code'       => $card->code,
				'balance'    => (float) $card->balance,
				'formatted'  => Simple_POS_DB::format_currency( $card->balance ),
			),
			200
		);
	}
}