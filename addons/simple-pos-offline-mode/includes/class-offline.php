<?php
/**
 * Offline mode helper: stores whether the feature is enabled, and provides
 * REST endpoints for the terminal to list and force-sync queued sales.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simple_POS_Som_Offline {

	const OPTION_KEY = 'simple_pos_offline';

	/**
	 * @return bool
	 */
	public static function is_enabled() {
		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) || empty( $saved['enabled'] ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Toggle the feature on/off.
	 */
	public static function toggle_enabled() {
		$current = self::is_enabled();
		update_option( self::OPTION_KEY, array( 'enabled' => ! $current ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
	}
}