<?php
/**
 * Add-on registry and catalog.
 *
 * Add-ons are separate packages that plug into the core seams:
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
	 * Catalog of additional add-ons shown on the Add-ons screen. Add-ons
	 * that ship in addons/ are bundled and excluded here (they are toggled
	 * from the "Included add-ons" table). Filter 'simple_pos_addons_catalog'
	 * to advertise external add-ons without touching core.
	 *
	 * @return array[] Each: slug, name, description, url [, badge ].
	 */
	public static function get_catalog() {
		$catalog = array(
			array(
				'slug'        => 'simple-pos-multi-outlet',
				'name'        => __( 'Multi-Outlet', 'wp-pos-plugin' ),
				'description' => __( 'Run several shops from one install: per-outlet stock, registers and sales reports.', 'wp-pos-plugin' ),
				'url'         => '#',
				'badge'       => __( 'Popular', 'wp-pos-plugin' ),
			),
			array(
				'slug'        => 'simple-pos-loyalty',
				'name'        => __( 'Loyalty & Store Credit', 'wp-pos-plugin' ),
				'description' => __( 'Points per purchase, redeem at checkout, gift-card style store credit balances.', 'wp-pos-plugin' ),
				'url'         => '#',
			),
			array(
				'slug'        => 'simple-pos-table-service',
				'name'        => __( 'Table Service', 'wp-pos-plugin' ),
				'description' => __( 'Restaurant mode: floor plan, open tabs, split bills and kitchen tickets.', 'wp-pos-plugin' ),
				'url'         => '#',
			),
			array(
				'slug'        => 'simple-pos-online-ordering',
				'name'        => __( 'Online Ordering & QR Menu', 'wp-pos-plugin' ),
				'description' => __( 'Customers order from a QR link; orders drop straight into the POS queue.', 'wp-pos-plugin' ),
				'url'         => '#',
				'badge'       => __( 'New', 'wp-pos-plugin' ),
			),
			array(
				'slug'        => 'simple-pos-kitchen-display',
				'name'        => __( 'Kitchen Display System', 'wp-pos-plugin' ),
				'description' => __( 'Second-screen prep queue with item states and timers for kitchens.', 'wp-pos-plugin' ),
				'url'         => '#',
				'badge'       => __( 'New', 'wp-pos-plugin' ),
			),
			array(
				'slug'        => 'simple-pos-gift-cards',
				'name'        => __( 'Gift Cards', 'wp-pos-plugin' ),
				'description' => __( 'Sell and redeem plastic or digital gift cards as a payment method.', 'wp-pos-plugin' ),
				'url'         => '#',
				'badge'       => __( 'New', 'wp-pos-plugin' ),
			),
			array(
				'slug'        => 'simple-pos-multi-currency',
				'name'        => __( 'Multi-Currency & FX', 'wp-pos-plugin' ),
				'description' => __( 'Sell in foreign currencies; pairs with the per-country tax engine.', 'wp-pos-plugin' ),
				'url'         => '#',
				'badge'       => __( 'New', 'wp-pos-plugin' ),
			),
			array(
				'slug'        => 'simple-pos-customer-display',
				'name'        => __( 'Customer Displays', 'wp-pos-plugin' ),
				'description' => __( 'Counter-facing second screen mirroring the running cart and totals.', 'wp-pos-plugin' ),
				'url'         => '#',
			),
			array(
				'slug'        => 'simple-pos-offline-mode',
				'name'        => __( 'Offline Mode', 'wp-pos-plugin' ),
				'description' => __( 'Terminal keeps selling without internet and syncs sales when back online.', 'wp-pos-plugin' ),
				'url'         => '#',
			),
			array(
				'slug'        => 'simple-pos-time-clock',
				'name'        => __( 'Time Clock & Shifts', 'wp-pos-plugin' ),
				'description' => __( 'Cashier shifts, hours and per-shift cash reconciliation.', 'wp-pos-plugin' ),
				'url'         => '#',
			),
		);
		$catalog = apply_filters( 'simple_pos_addons_catalog', $catalog );
		$catalog = is_array( $catalog ) ? $catalog : array();

		// Drop anything already bundled and toggleable on the Add-ons screen.
		$bundled = array();
		foreach ( self::get_bundled() as $addon ) {
			$bundled[] = isset( $addon['slug'] ) ? $addon['slug'] : '';
		}
		if ( $bundled ) {
			$catalog = array_values(
				array_filter(
					$catalog,
					function ( $item ) use ( $bundled ) {
						return empty( $item['slug'] ) || ! in_array( $item['slug'], $bundled, true );
					}
				)
			);
		}
		return $catalog;
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
			if ( 'simple-pos-example' === $slug ) {
				continue; // Reference skeleton, not a real add-on.
			}
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
	 * Includes version compatibility checks to prevent fatal errors.
	 */
	public static function load_enabled() {
		if ( ! defined( 'SIMPLE_POS_PLUGIN_DIR' ) ) {
			return;
		}

		$core_version = defined( 'SIMPLE_POS_VERSION' ) ? SIMPLE_POS_VERSION : '0.0.0';

		foreach ( self::get_bundled() as $addon ) {
			if ( ! self::is_enabled( $addon['slug'] ) ) {
				continue;
			}

			$addon_file = SIMPLE_POS_PLUGIN_DIR . 'addons/' . $addon['slug'] . '/' . $addon['slug'] . '.php';

		// Check if file exists.
		if ( ! file_exists( $addon_file ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'POS: Addon ' . $addon['slug'] . ' file missing, skipping' );
			}
			continue;
		}

		// Check version compatibility.
		$headers = get_file_data(
			$addon_file,
			array(
				'requires_core' => 'Requires POS Core',
				'tested_up_to'  => 'Tested up to',
			)
		);

		if ( ! empty( $headers['requires_core'] ) && version_compare( $core_version, $headers['requires_core'], '<' ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'POS: Addon ' . $addon['slug'] . ' requires core ' . $headers['requires_core'] . ', have ' . $core_version . '. Skipping.' );
			}
				set_transient(
					'simple_pos_addon_compat_error_' . $addon['slug'],
					array(
						'addon'    => $addon['name'],
						'requires' => $headers['requires_core'],
						'current'  => $core_version,
					),
					WEEK_IN_SECONDS
				);
				continue;
			}

			require_once $addon_file;
		}
	}

	/**
	 * Raw enabled map for all add-ons (slug => 0|1), as stored in the
	 * 'simple_pos_addons_enabled' option. Missing slugs are treated as
	 * enabled by {@see is_enabled()}; this getter returns only what is stored.
	 *
	 * @return array<string,int>
	 */
	public static function get_enabled() {
		$enabled = get_option( self::OPTION_KEY_ENABLED, array() );
		return is_array( $enabled ) ? $enabled : array();
	}

	/**
	 * Show admin notice for incompatible add-ons.
	 */
	public static function show_compatibility_notices() {
		$enabled = array_filter( self::get_enabled() );
		foreach ( array_keys( $enabled ) as $slug ) {
			$error = get_transient( 'simple_pos_addon_compat_error_' . $slug );
			if ( $error ) {
				echo '<div class="notice notice-error"><p>';
				printf(
					/* translators: 1: add-on name, 2: required core version, 3: current core version. */
					esc_html__( 'Add-on "%1$s" requires POS Core version %2$s or higher. You have version %3$s. The add-on has been disabled.', 'wp-pos-plugin' ),
					esc_html( $error['addon'] ),
					esc_html( $error['requires'] ),
					esc_html( $error['current'] )
				);
				echo '</p></div>';
			}
		}
	}
}
