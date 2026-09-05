<?php
/**
 * Fired during plugin deactivation.
 *
 * Deliberately does NOT delete tables or data — deactivation is often
 * temporary (plugin conflict testing, updates). Data removal only ever
 * happens in uninstall.php, and only when the store owner has opted in
 * via Settings > Advanced > "Delete all data on uninstall".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simple_POS_Deactivator {

	/**
	 * Runs on register_deactivation_hook.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
