<?php
/**
 * Customer display state: terminal pushes cart updates via REST,
 * the public display polls the latest state.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simple_POS_Scd_Display {

	const OPTION_KEY = 'simple_pos_customer_display';

	/**
	 * Current display state stored in wp_options.
	 *
	 * @return array
	 */
	public static function get_state() {
		$saved = get_option( self::OPTION_KEY, array() );
		return is_array( $saved ) ? $saved : array();
	}

	/**
	 * @param array $state
	 */
	public static function set_state( $state ) {
		$state['updated_at'] = time();
		update_option( self::OPTION_KEY, $state ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	/**
	 * REST: Terminal pushes new cart state.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public static function rest_update( $request ) {
		$items = array();
		foreach ( (array) $request->get_param( 'items' ) as $raw ) {
			$name = isset( $raw['name'] ) ? sanitize_text_field( $raw['name'] ) : '';
			if ( '' === $name ) {
				continue;
			}
			$items[] = array(
				'name' => $name,
				'qty'  => isset( $raw['qty'] ) ? max( 1, (int) $raw['qty'] ) : 1,
				'price'=> isset( $raw['price'] ) ? (float) $raw['price'] : 0,
				'total'=> isset( $raw['total'] ) ? (float) $raw['total'] : ( ( isset( $raw['price'] ) ? (float) $raw['price'] : 0 ) * ( isset( $raw['qty'] ) ? max( 1, (int) $raw['qty'] ) : 1 ) ),
			);
		}

		$state = array(
			'items'    => $items,
			'subtotal' => (float) $request->get_param( 'subtotal' ),
			'discount' => (float) $request->get_param( 'discount' ),
			'tax'      => (float) $request->get_param( 'tax' ),
			'total'    => (float) $request->get_param( 'total' ),
			'cashier'  => sanitize_text_field( $request->get_param( 'cashier' ) ),
		);

		self::set_state( $state );

		return new WP_REST_Response( array( 'ok' => true, 'items' => count( $items ) ), 200 );
	}

	/**
	 * REST: Public read (no auth required).
	 *
	 * @return WP_REST_Response
	 */
	public static function rest_current() {
		return new WP_REST_Response( self::get_state(), 200 );
	}

	/**
	 * REST: Terminal clears the display after checkout.
	 *
	 * @return WP_REST_Response
	 */
	public static function rest_clear() {
		self::set_state(
			array(
				'items'    => array(),
				'subtotal' => 0,
				'discount' => 0,
				'tax'      => 0,
				'total'    => 0,
			)
		);
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}
}