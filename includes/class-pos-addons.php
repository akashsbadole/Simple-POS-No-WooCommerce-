<?php
/**
 * Paid add-on registry and catalog.
 *
 * Add-ons are separate WordPress plugins that plug into the core seams:
 *
 *   simple_pos_init          (action, plugins_loaded:20) — register everything
 *   simple_pos_cart_data     (filter) — modify the cart before totals
 *   simple_pos_order_calc    (filter) — modify computed totals
 *   simple_pos_sale_created  (action) — react to a stored sale
 *
 * Add-ons announce themselves on the Add-ons screen via the
 * 'simple_pos_registered_addons' filter (see addons/simple-pos-example
 * for the reference skeleton).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simple_POS_Addons {

	const OPTION_KEY_ENABLED = 'simple_pos_addons_enabled';

	/**
	 * Add-ons currently installed & running (self-reported via filter).
	 *
	 * @return array[] Each: slug, name, version, description.
	 */
	public static function get_registered() {
		$addons = apply_filters( 'simple_pos_registered_addons', array() );
		return is_array( $addons ) ? $addons : array();
	}

	/**
	 * Are add-ons enabled? Option stores slug => 0|1; anything missing is
	 * enabled by default so a fresh install behaves like before.
	 *
	 * @param string $slug Add-on slug.
	 * @return bool
	 */
	public static function is_enabled( $slug ) {
		$slug    = sanitize_key( $slug );
		$enabled = get_option( self::OPTION_KEY_ENABLED, array() );
		$enabled = is_array( $enabled ) ? $enabled : array();
		return ! isset( $enabled[ $slug ] ) || ! empty( $enabled[ $slug ] );
	}

	/**
	 * Persist an add-on's enabled flag.
	 *
	 * @param string $slug Add-on slug.
	 * @param bool   $on   True to enable.
	 */
	public static function set_enabled( $slug, $on ) {
		$slug    = sanitize_key( $slug );
		$enabled = get_option( self::OPTION_KEY_ENABLED, array() );
		$enabled = is_array( $enabled ) ? $enabled : array();
		$enabled[ $slug ] = $on ? 1 : 0;
		update_option( self::OPTION_KEY_ENABLED, $enabled );
	}

	/**
	 * Catalog of available (free) add-ons shown on the Add-ons screen.
	 * ponytail: external buy URLs are placeholders — point them at your site;
	 * filter 'simple_pos_addons_catalog' to change them without touching core.
	 * Add-ons that ship in addons/ link straight to their built .zip.
	 *
	 * @return array[] Each: name, description, url [, badge ].
	 */
	public static function get_catalog() {
		$catalog = array(
			array(
				'name'        => __( 'Multi-Outlet', 'simple-pos' ),
				'description' => __( 'Run several shops from one install: per-outlet stock, registers and sales reports.', 'simple-pos' ),
				'url'         => '#',
				'badge'       => __( 'Popular', 'simple-pos' ),
			),
			array(
				'name'        => __( 'Loyalty & Store Credit', 'simple-pos' ),
				'description' => __( 'Points per purchase, redeem at checkout, gift-card style store credit balances.', 'simple-pos' ),
				'url'         => '#',
			),
			array(
				'name'        => __( 'Table Service', 'simple-pos' ),
				'description' => __( 'Restaurant mode: floor plan, open tabs, split bills and kitchen tickets.', 'simple-pos' ),
				'url'         => '#',
			),
			array(
				'name'        => __( 'Online Ordering & QR Menu', 'simple-pos' ),
				'description' => __( 'Customers order from a QR link; orders drop straight into the POS queue.', 'simple-pos' ),
				'url'         => '#',
				'badge'       => __( 'New', 'simple-pos' ),
			),
			array(
				'name'        => __( 'Kitchen Display System', 'simple-pos' ),
				'description' => __( 'Second-screen prep queue with item states and timers for kitchens.', 'simple-pos' ),
				'url'         => '#',
				'badge'       => __( 'New', 'simple-pos' ),
			),
			array(
				'name'        => __( 'Gift Cards', 'simple-pos' ),
				'description' => __( 'Sell and redeem plastic or digital gift cards as a payment method.', 'simple-pos' ),
				'url'         => '#',
				'badge'       => __( 'New', 'simple-pos' ),
			),
			array(
				'name'        => __( 'Multi-Currency & FX', 'simple-pos' ),
				'description' => __( 'Sell in foreign currencies; pairs with the per-country tax engine.', 'simple-pos' ),
				'url'         => '#',
				'badge'       => __( 'New', 'simple-pos' ),
			),
			array(
				'name'        => __( 'Customer Displays', 'simple-pos' ),
				'description' => __( 'Counter-facing second screen mirroring the running cart and totals.', 'simple-pos' ),
				'url'         => '#',
				'badge'       => __( 'Coming soon', 'simple-pos' ),
			),
			array(
				'name'        => __( 'Offline Mode', 'simple-pos' ),
				'description' => __( 'Terminal keeps selling without internet and syncs sales when back online.', 'simple-pos' ),
				'url'         => '#',
				'badge'       => __( 'Coming soon', 'simple-pos' ),
			),
			array(
				'name'        => __( 'Time Clock & Shifts', 'simple-pos' ),
				'description' => __( 'Cashier shifts, hours and per-shift cash reconciliation.', 'simple-pos' ),
				'url'         => '#',
				'badge'       => __( 'Coming soon', 'simple-pos' ),
			),
		);
		$catalog = apply_filters( 'simple_pos_addons_catalog', $catalog );
		return is_array( $catalog ) ? $catalog : array();
	}

	/**
	 * Add-ons bundled with the core (addons/<slug>/<slug>.php). They are
	 * enabled/disabled on the Add-ons screen — not installed as separate
	 * plugins.
	 *
	 * @return array[] Each: slug, name, version, description.
	 */
	public static function get_bundled() {
		if ( ! defined( 'SIMPLE_POS_PLUGIN_DIR' ) ) {
			return array();
		}
		$out = array();
		foreach ( glob( SIMPLE_POS_PLUGIN_DIR . 'addons/simple-pos-*', GLOB_ONLYDIR ) as $addon_dir ) {
			$slug = basename( $addon_dir );
			$file = $addon_dir . '/' . $slug . '.php';
			if ( ! file_exists( $file ) ) {
				continue;
			}
			$data = get_file_data(
				$file,
				array(
					'name'        => 'Plugin Name',
					'version'     => 'Version',
					'description' => 'Description',
				)
			);
			$out[] = array(
				'slug'        => $slug,
				'name'        => '' !== $data['name'] ? $data['name'] : $slug,
				'version'     => $data['version'],
				'description' => $data['description'],
			);
		}
		return $out;
	}

	/**
	 * Load the bundled add-ons that are enabled. Runs during core includes —
	 * before simple_pos_init fires — so each addon's hooks land normally.
	 * Disabled addons are never loaded; their files just sit on disk.
	 */
	public static function load_enabled() {
		if ( ! defined( 'SIMPLE_POS_PLUGIN_DIR' ) ) {
			return;
		}
		foreach ( self::get_bundled() as $addon ) {
			if ( self::is_enabled( $addon['slug'] ) ) {
				require_once SIMPLE_POS_PLUGIN_DIR . 'addons/' . $addon['slug'] . '/' . $addon['slug'] . '.php';
			}
		}
	}
}
