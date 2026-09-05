<?php
/**
 * Plugin settings, stored as a single option (keeps autoloaded options light).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simple_POS_Settings {

	const OPTION_KEY = 'simple_pos_settings';

	/**
	 * Get all settings, merged with defaults so new keys added in later
	 * versions always have a sane fallback.
	 *
	 * @return array
	 */
	public static function get_all() {
		$currency_map = self::currency_list();
		$defaults = array(
			'currency_code'            => 'USD',
			'currency_symbol'          => '$',
			'currency_position'        => 'before',
			'default_tax_rate'         => 0,
			'default_tax_class_id'     => 0,
			'tax_country'              => 'US',
			'tax_state'                => '',
			'tax_inclusive'            => 0,
			'discount_before_tax'      => 1,
			'tax_rounding'             => 'line',
			'store_name'               => get_bloginfo( 'name' ),
			'receipt_header'           => '',
			'receipt_footer'           => 'Thank you for your purchase!',
			'low_stock_threshold'      => 5,
			'sale_number_prefix'       => 'POS-',
			'po_number_prefix'         => 'PO-',
			'allow_negative_stock'     => 0,
			'decimal_places'           => 2,
			'paper_width'              => '80mm',
			'auto_kick_drawer'         => 0,
			'printer_type'             => 'browser',
			'network_printer_ip'       => '',
			'barcode_symbology'        => 'CODE128',
			'barcode_label_format'     => 'a4_30',
			'delete_data_on_uninstall' => 0,
		);

		$saved = get_option( self::OPTION_KEY, array() );
		$merged = wp_parse_args( $saved, $defaults );
		// Auto-fill symbol if code changed and symbol still default.
		if ( isset($currency_map[$merged['currency_code']]) && empty($saved['currency_symbol']) ) {
			// keep existing if manually set; else use map
		}
		return $merged;
	}

	public static function currency_list() {
		return array(
			'USD'=>array('symbol'=>'$','name'=>'US Dollar'),
			'EUR'=>array('symbol'=>'€','name'=>'Euro'),
			'GBP'=>array('symbol'=>'£','name'=>'British Pound'),
			'INR'=>array('symbol'=>'₹','name'=>'Indian Rupee'),
			'JPY'=>array('symbol'=>'¥','name'=>'Japanese Yen'),
			'CAD'=>array('symbol'=>'$','name'=>'Canadian Dollar'),
			'AUD'=>array('symbol'=>'$','name'=>'Australian Dollar'),
			'CHF'=>array('symbol'=>'CHF','name'=>'Swiss Franc'),
			'CNY'=>array('symbol'=>'¥','name'=>'Chinese Yuan'),
			'SEK'=>array('symbol'=>'kr','name'=>'Swedish Krona'),
			'NOK'=>array('symbol'=>'kr','name'=>'Norwegian Krone'),
			'DKK'=>array('symbol'=>'kr','name'=>'Danish Krone'),
			'PLN'=>array('symbol'=>'zł','name'=>'Polish Zloty'),
			'NZD'=>array('symbol'=>'$','name'=>'NZ Dollar'),
			'SGD'=>array('symbol'=>'$','name'=>'Singapore Dollar'),
			'HKD'=>array('symbol'=>'$','name'=>'Hong Kong Dollar'),
			'AED'=>array('symbol'=>'AED','name'=>'UAE Dirham'),
			'ZAR'=>array('symbol'=>'R','name'=>'South African Rand'),
			'BRL'=>array('symbol'=>'R$','name'=>'Brazilian Real'),
			'MXN'=>array('symbol'=>'$','name'=>'Mexican Peso'),
		);
	}

	/**
	 * Get a single setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback if not set.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::get_all();
		return isset( $all[ $key ] ) ? $all[ $key ] : $default;
	}

	/**
	 * Sanitize and save settings from a raw input array (e.g. $_POST).
	 *
	 * @param array $input Raw settings.
	 * @return array Sanitized settings that were saved.
	 */
	public static function update( $input ) {
		$current = self::get_all();
		$currency_list = self::currency_list();
		$code = isset($input['currency_code']) ? strtoupper(sanitize_text_field($input['currency_code'])) : $current['currency_code'];
		if (!isset($currency_list[$code])) $code = $current['currency_code'];
		// Auto-fill symbol from map if user didn't override or switched code
		$symbol = isset($input['currency_symbol']) && $input['currency_symbol']!=='' ? sanitize_text_field($input['currency_symbol']) : $currency_list[$code]['symbol'];

		$sanitized = array(
			'currency_code'            => $code,
			'currency_symbol'          => $symbol,
			'currency_position'        => isset( $input['currency_position'] ) ? ( 'after' === $input['currency_position'] ? 'after' : 'before' ) : $current['currency_position'],
			'default_tax_rate'         => isset( $input['default_tax_rate'] ) ? max( 0, (float) $input['default_tax_rate'] ) : $current['default_tax_rate'],
			'default_tax_class_id'     => isset( $input['default_tax_class_id'] ) ? (int) $input['default_tax_class_id'] : $current['default_tax_class_id'],
			'tax_country'              => isset( $input['tax_country'] ) ? strtoupper(sanitize_text_field($input['tax_country'])) : $current['tax_country'],
			'tax_state'                => isset( $input['tax_state'] ) ? strtoupper(sanitize_text_field($input['tax_state'])) : $current['tax_state'],
			'tax_inclusive'            => ! empty( $input['tax_inclusive'] ) ? 1 : 0,
			'discount_before_tax'      => isset($input['discount_before_tax']) ? (!empty($input['discount_before_tax'])?1:0) : $current['discount_before_tax'],
			'tax_rounding'             => (isset($input['tax_rounding']) && 'total'===$input['tax_rounding'])? 'total':'line',
			'store_name'               => isset( $input['store_name'] ) ? sanitize_text_field( $input['store_name'] ) : $current['store_name'],
			'receipt_header'           => isset( $input['receipt_header'] ) ? sanitize_textarea_field( $input['receipt_header'] ) : $current['receipt_header'],
			'receipt_footer'           => isset( $input['receipt_footer'] ) ? sanitize_textarea_field( $input['receipt_footer'] ) : $current['receipt_footer'],
			'low_stock_threshold'      => isset( $input['low_stock_threshold'] ) ? max( 0, (int) $input['low_stock_threshold'] ) : $current['low_stock_threshold'],
			'sale_number_prefix'       => isset( $input['sale_number_prefix'] ) ? sanitize_text_field( $input['sale_number_prefix'] ) : $current['sale_number_prefix'],
			'po_number_prefix'         => isset( $input['po_number_prefix'] ) ? sanitize_text_field( $input['po_number_prefix'] ) : $current['po_number_prefix'],
			'allow_negative_stock'     => ! empty( $input['allow_negative_stock'] ) ? 1 : 0,
			'decimal_places'           => isset( $input['decimal_places'] ) ? min( 4, max( 0, (int) $input['decimal_places'] ) ) : $current['decimal_places'],
			'paper_width'              => isset($input['paper_width']) && in_array($input['paper_width'],array('58mm','80mm'),true)? $input['paper_width']:$current['paper_width'],
			'auto_kick_drawer'         => ! empty( $input['auto_kick_drawer'] ) ? 1 : 0,
			'printer_type'             => isset($input['printer_type']) && in_array($input['printer_type'],array('browser','usb','network'),true)? $input['printer_type']:$current['printer_type'],
			'network_printer_ip'       => isset($input['network_printer_ip'])? sanitize_text_field($input['network_printer_ip']): $current['network_printer_ip'],
			'barcode_symbology'        => isset($input['barcode_symbology']) && in_array($input['barcode_symbology'],array('CODE128','EAN13','QR'),true)? $input['barcode_symbology']:$current['barcode_symbology'],
			'barcode_label_format'     => isset($input['barcode_label_format'])? sanitize_text_field($input['barcode_label_format']): $current['barcode_label_format'],
			'delete_data_on_uninstall' => ! empty( $input['delete_data_on_uninstall'] ) ? 1 : 0,
		);

		// Keep autoload light (<1KB effective). Option stays small; 'yes' is fine for perf.
		update_option( self::OPTION_KEY, $sanitized, 'yes' );

		return $sanitized;
	}
}
