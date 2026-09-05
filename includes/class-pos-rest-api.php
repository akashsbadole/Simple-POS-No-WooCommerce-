<?php
/**
 * REST API endpoints under the `pos/v1` namespace.
 *
 * Auth model: this API is only ever called from logged-in, cookie-authenticated
 * admin screens (the POS terminal lives inside wp-admin), so every route relies
 * on WordPress's built-in cookie auth + the X-WP-Nonce header that
 * wp_localize_script() supplies, checked via each route's permission_callback.
 * There is no public/unauthenticated route.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simple_POS_REST_API {

	const NS = 'pos/v1';

	/**
	 * Register all routes. Hooked to rest_api_init.
	 */
	public static function register_routes() {

		// ---- Products -------------------------------------------------
		register_rest_route( self::NS, '/products', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_products' ),
				'permission_callback' => array( __CLASS__, 'can_view_products' ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_product' ),
				'permission_callback' => array( __CLASS__, 'can_manage_products' ),
			),
		) );

		register_rest_route( self::NS, '/products/lookup/(?P<code>[a-zA-Z0-9\-_]+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'lookup_product' ),
			'permission_callback' => array( __CLASS__, 'can_view_products' ),
		) );

		register_rest_route( self::NS, '/products/(?P<id>\d+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_product' ),
				'permission_callback' => array( __CLASS__, 'can_view_products' ),
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'update_product' ),
				'permission_callback' => array( __CLASS__, 'can_manage_products' ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'delete_product' ),
				'permission_callback' => array( __CLASS__, 'can_manage_products' ),
			),
		) );

		register_rest_route( self::NS, '/products/(?P<id>\d+)/stock', array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => array( __CLASS__, 'adjust_stock' ),
			'permission_callback' => array( __CLASS__, 'can_manage_products' ),
		) );

		register_rest_route( self::NS, '/products/(?P<id>\d+)/variants', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_variants' ),
				'permission_callback' => array( __CLASS__, 'can_view_products' ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_variant' ),
				'permission_callback' => array( __CLASS__, 'can_manage_products' ),
			),
		) );
		register_rest_route( self::NS, '/products/(?P<id>\d+)/variants/(?P<vid>\d+)', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'update_variant' ),
				'permission_callback' => array( __CLASS__, 'can_manage_products' ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'delete_variant' ),
				'permission_callback' => array( __CLASS__, 'can_manage_products' ),
			),
		) );
		register_rest_route( self::NS, '/products/import', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'import_products' ),
			'permission_callback' => array( __CLASS__, 'can_manage_products' ),
		) );
		register_rest_route( self::NS, '/products/export', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'export_products' ),
			'permission_callback' => array( __CLASS__, 'can_manage_products' ),
		) );

		// ---- Categories -------------------------------------------------
		register_rest_route( self::NS, '/categories', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_categories' ),
				'permission_callback' => array( __CLASS__, 'can_view_products' ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_category' ),
				'permission_callback' => array( __CLASS__, 'can_manage_products' ),
			),
		) );

		register_rest_route( self::NS, '/categories/(?P<id>\d+)', array(
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => array( __CLASS__, 'delete_category' ),
			'permission_callback' => array( __CLASS__, 'can_manage_products' ),
		) );

		// ---- Customers -------------------------------------------------
		register_rest_route( self::NS, '/customers', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_customers' ),
				'permission_callback' => array( __CLASS__, 'can_operate_pos' ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_customer' ),
				'permission_callback' => array( __CLASS__, 'can_operate_pos' ),
			),
		) );

		register_rest_route( self::NS, '/customers/(?P<id>\d+)', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'update_customer' ),
				'permission_callback' => array( __CLASS__, 'can_manage_customers' ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'delete_customer' ),
				'permission_callback' => array( __CLASS__, 'can_manage_customers' ),
			),
		) );

		// ---- Sales -------------------------------------------------
		register_rest_route( self::NS, '/sales', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_sales' ),
				'permission_callback' => array( __CLASS__, 'can_view_sales' ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'checkout' ),
				'permission_callback' => array( __CLASS__, 'can_operate_pos' ),
			),
		) );

		register_rest_route( self::NS, '/sales/(?P<id>\d+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'get_sale' ),
			'permission_callback' => array( __CLASS__, 'can_view_sales' ),
		) );

		register_rest_route( self::NS, '/sales/(?P<id>\d+)/void', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'void_sale' ),
			'permission_callback' => array( __CLASS__, 'can_void_sales' ),
		) );

		// ---- Tax -------------------------------------------------
		register_rest_route( self::NS, '/tax/classes', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_tax_classes' ),
				'permission_callback' => array( __CLASS__, 'can_manage_products' ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_tax_class' ),
				'permission_callback' => array( __CLASS__, 'can_manage_products' ),
			),
		) );
		register_rest_route( self::NS, '/tax/classes/(?P<id>\d+)', array(
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => array( __CLASS__, 'delete_tax_class' ),
			'permission_callback' => array( __CLASS__, 'can_manage_products' ),
		) );
		register_rest_route( self::NS, '/tax/rates', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_tax_rates' ),
				'permission_callback' => array( __CLASS__, 'can_manage_products' ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_tax_rate' ),
				'permission_callback' => array( __CLASS__, 'can_manage_products' ),
			),
		) );
		register_rest_route( self::NS, '/tax/rates/(?P<id>\d+)', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'update_tax_rate' ),
				'permission_callback' => array( __CLASS__, 'can_manage_products' ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'delete_tax_rate' ),
				'permission_callback' => array( __CLASS__, 'can_manage_products' ),
			),
		) );
		register_rest_route( self::NS, '/tax/calculate', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'tax_calculate' ),
			'permission_callback' => array( __CLASS__, 'can_view_products' ),
		) );

		// ---- Suppliers / POs -------------------------------------------------
		register_rest_route( self::NS, '/suppliers', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_suppliers' ),
				'permission_callback' => array( __CLASS__, 'can_manage_products' ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_supplier' ),
				'permission_callback' => array( __CLASS__, 'can_manage_products' ),
			),
		) );
		register_rest_route( self::NS, '/suppliers/(?P<id>\d+)', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'update_supplier' ),
				'permission_callback' => array( __CLASS__, 'can_manage_products' ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'delete_supplier' ),
				'permission_callback' => array( __CLASS__, 'can_manage_products' ),
			),
		) );
		register_rest_route( self::NS, '/purchase-orders', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_purchase_orders' ),
				'permission_callback' => array( __CLASS__, 'can_manage_products' ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_purchase_order' ),
				'permission_callback' => array( __CLASS__, 'can_manage_products' ),
			),
		) );
		register_rest_route( self::NS, '/purchase-orders/(?P<id>\d+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_purchase_order' ),
				'permission_callback' => array( __CLASS__, 'can_manage_products' ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'delete_purchase_order' ),
				'permission_callback' => array( __CLASS__, 'can_manage_products' ),
			),
		) );
		register_rest_route( self::NS, '/purchase-orders/(?P<id>\d+)/receive', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'receive_purchase_order' ),
			'permission_callback' => array( __CLASS__, 'can_manage_products' ),
		) );
		register_rest_route( self::NS, '/purchase-orders/(?P<id>\d+)/status', array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => array( __CLASS__, 'update_po_status' ),
			'permission_callback' => array( __CLASS__, 'can_manage_products' ),
		) );

		// ---- Reports -------------------------------------------------
		register_rest_route( self::NS, '/reports/summary', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'reports_summary' ),
			'permission_callback' => array( __CLASS__, 'can_view_reports' ),
		) );

		register_rest_route( self::NS, '/reports/sales-by-day', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'reports_sales_by_day' ),
			'permission_callback' => array( __CLASS__, 'can_view_reports' ),
		) );

		register_rest_route( self::NS, '/reports/top-products', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'reports_top_products' ),
			'permission_callback' => array( __CLASS__, 'can_view_reports' ),
		) );

		register_rest_route( self::NS, '/reports/low-stock', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'reports_low_stock' ),
			'permission_callback' => array( __CLASS__, 'can_view_reports' ),
		) );
	}

	/* ---------------------------------------------------------------
	 * Permission callbacks
	 * ------------------------------------------------------------- */

	public static function can_view_products() {
		return current_user_can( 'view_pos_products' ) || current_user_can( 'manage_pos_products' );
	}
	public static function can_manage_products() {
		return current_user_can( 'manage_pos_products' );
	}
	public static function can_manage_customers() {
		return current_user_can( 'manage_pos_customers' );
	}
	public static function can_operate_pos() {
		return current_user_can( 'operate_pos' );
	}
	public static function can_view_sales() {
		return current_user_can( 'view_pos_sales' );
	}
	public static function can_void_sales() {
		return current_user_can( 'void_pos_sales' );
	}
	public static function can_view_reports() {
		return current_user_can( 'view_pos_reports' );
	}

	/* ---------------------------------------------------------------
	 * Products
	 * ------------------------------------------------------------- */

	public static function get_products( WP_REST_Request $request ) {
		$result = Simple_POS_Products::get_products( array(
			'search'      => $request->get_param( 'search' ),
			'category_id' => $request->get_param( 'category_id' ),
			'status'      => $request->get_param( 'status' ) ?: 'active',
			'per_page'    => $request->get_param( 'per_page' ) ?: 20,
			'page'        => $request->get_param( 'page' ) ?: 1,
			'orderby'     => $request->get_param( 'orderby' ) ?: 'name',
			'order'       => $request->get_param( 'order' ) ?: 'ASC',
		) );
		return rest_ensure_response( $result );
	}

	public static function get_product( WP_REST_Request $request ) {
		$product = Simple_POS_Products::get_product_with_variants( (int) $request['id'] );
		if ( ! $product ) {
			return new WP_Error( 'pos_not_found', __( 'Product not found.', 'simple-pos' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $product );
	}
	public static function get_variants( WP_REST_Request $request ) {
		$vars = Simple_POS_Variants::get_variants( (int)$request['id'] );
		return rest_ensure_response( $vars );
	}
	public static function create_variant( WP_REST_Request $request ) {
		$result = Simple_POS_Variants::create_variant( (int)$request['id'], $request->get_json_params() );
		return self::respond_or_error($result, array('id'=> is_int($result)?$result:null));
	}
	public static function update_variant( WP_REST_Request $request ) {
		$result = Simple_POS_Variants::update_variant( (int)$request['vid'], $request->get_json_params() );
		return self::respond_or_error($result, array('success'=>true));
	}
	public static function delete_variant( WP_REST_Request $request ) {
		$result = Simple_POS_Variants::delete_variant( (int)$request['vid'] );
		return self::respond_or_error($result, array('success'=>true));
	}
	public static function import_products( WP_REST_Request $request ) {
		$files = $request->get_file_params();
		if ( empty($files['file']['tmp_name']) ) return new WP_Error('pos_invalid_input',__('No file uploaded.', 'simple-pos'), array('status'=>400));
		$result = Simple_POS_CSV::import_products($files['file']['tmp_name']);
		return self::respond_or_error($result, $result);
	}
	public static function export_products() {
		$csv = Simple_POS_CSV::export_products();
		$response = new WP_REST_Response($csv,200);
		$response->header('Content-Type','text/csv');
		$response->header('Content-Disposition','attachment; filename="pos-products.csv"');
		return $response;
	}

	public static function lookup_product( WP_REST_Request $request ) {
		$product = Simple_POS_Products::find_by_code( $request['code'] );
		if ( ! $product ) {
			return new WP_Error( 'pos_not_found', __( 'No product matches that code.', 'simple-pos' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $product );
	}

	public static function create_product( WP_REST_Request $request ) {
		$result = Simple_POS_Products::create_product( $request->get_json_params() );
		return self::respond_or_error( $result, array( 'id' => is_int( $result ) ? $result : null ) );
	}

	public static function update_product( WP_REST_Request $request ) {
		$result = Simple_POS_Products::update_product( (int) $request['id'], $request->get_json_params() );
		return self::respond_or_error( $result, array( 'success' => true ) );
	}

	public static function delete_product( WP_REST_Request $request ) {
		$result = Simple_POS_Products::delete_product( (int) $request['id'] );
		return self::respond_or_error( $result, array( 'success' => true ) );
	}

	public static function adjust_stock( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$delta  = isset( $params['delta'] ) ? (int) $params['delta'] : 0;
		$reason = isset( $params['reason'] ) ? sanitize_text_field( $params['reason'] ) : 'restock';
		$note   = isset( $params['note'] ) ? sanitize_text_field( $params['note'] ) : '';

		$result = Simple_POS_Products::adjust_stock( (int) $request['id'], $delta, $reason, null, $note );
		return self::respond_or_error( $result, array( 'success' => true ) );
	}

	/* ---------------------------------------------------------------
	 * Categories
	 * ------------------------------------------------------------- */

	public static function get_categories() {
		return rest_ensure_response( Simple_POS_Products::get_categories() );
	}

	public static function create_category( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$result = Simple_POS_Products::create_category(
			isset( $params['name'] ) ? $params['name'] : '',
			isset( $params['description'] ) ? $params['description'] : ''
		);
		return self::respond_or_error( $result, array( 'id' => is_int( $result ) ? $result : null ) );
	}

	public static function delete_category( WP_REST_Request $request ) {
		Simple_POS_Products::delete_category( (int) $request['id'] );
		return rest_ensure_response( array( 'success' => true ) );
	}

	/* ---------------------------------------------------------------
	 * Customers
	 * ------------------------------------------------------------- */

	public static function get_customers( WP_REST_Request $request ) {
		$result = Simple_POS_Customers::get_customers(
			$request->get_param( 'search' ) ?: '',
			$request->get_param( 'per_page' ) ?: 20,
			$request->get_param( 'page' ) ?: 1
		);
		return rest_ensure_response( $result );
	}

	public static function create_customer( WP_REST_Request $request ) {
		$result = Simple_POS_Customers::create_customer( $request->get_json_params() );
		return self::respond_or_error( $result, array( 'id' => is_int( $result ) ? $result : null ) );
	}

	public static function update_customer( WP_REST_Request $request ) {
		$result = Simple_POS_Customers::update_customer( (int) $request['id'], $request->get_json_params() );
		return self::respond_or_error( $result, array( 'success' => true ) );
	}

	public static function delete_customer( WP_REST_Request $request ) {
		Simple_POS_Customers::delete_customer( (int) $request['id'] );
		return rest_ensure_response( array( 'success' => true ) );
	}

	/* ---------------------------------------------------------------
	 * Sales
	 * ------------------------------------------------------------- */

	public static function get_sales( WP_REST_Request $request ) {
		$result = Simple_POS_Sales::get_sales( array(
			'date_from'  => $request->get_param( 'date_from' ),
			'date_to'    => $request->get_param( 'date_to' ),
			'cashier_id' => $request->get_param( 'cashier_id' ),
			'status'     => $request->get_param( 'status' ) ?: 'any',
			'per_page'   => $request->get_param( 'per_page' ) ?: 20,
			'page'       => $request->get_param( 'page' ) ?: 1,
		) );
		return rest_ensure_response( $result );
	}

	public static function get_sale( WP_REST_Request $request ) {
		$sale = Simple_POS_Sales::get_sale( (int) $request['id'] );
		if ( ! $sale ) {
			return new WP_Error( 'pos_not_found', __( 'Sale not found.', 'simple-pos' ), array( 'status' => 404 ) );
		}
		$sale->items = Simple_POS_Sales::get_sale_items( $sale->id );
		return rest_ensure_response( $sale );
	}

	public static function checkout( WP_REST_Request $request ) {
		$result = Simple_POS_Sales::checkout( $request->get_json_params() );
		if ( is_wp_error( $result ) ) {
			return self::respond_or_error( $result, null );
		}
		$sale        = Simple_POS_Sales::get_sale( $result );
		$sale->items = Simple_POS_Sales::get_sale_items( $result );
		return rest_ensure_response( $sale );
	}

	public static function void_sale( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$note   = isset( $params['note'] ) ? sanitize_text_field( $params['note'] ) : '';
		$result = Simple_POS_Sales::void_sale( (int) $request['id'], $note );
		return self::respond_or_error( $result, array( 'success' => true ) );
	}

	/* ---------------------------------------------------------------
	 * Tax
	 * ------------------------------------------------------------- */
	public static function get_tax_classes(){ return rest_ensure_response(Simple_POS_Tax::get_classes()); }
	public static function create_tax_class(WP_REST_Request $r){
		$p=$r->get_json_params();
		$res=Simple_POS_Tax::create_class($p['name']??'', $p['description']??'');
		return self::respond_or_error($res,array('id'=>is_int($res)?$res:null));
	}
	public static function delete_tax_class(WP_REST_Request $r){
		$res=Simple_POS_Tax::delete_class((int)$r['id']);
		return self::respond_or_error($res,array('success'=>true));
	}
	public static function get_tax_rates(WP_REST_Request $r){
		$cid=$r->get_param('class_id')? (int)$r->get_param('class_id'):0;
		return rest_ensure_response(Simple_POS_Tax::get_rates($cid));
	}
	public static function create_tax_rate(WP_REST_Request $r){
		$res=Simple_POS_Tax::create_rate($r->get_json_params());
		return self::respond_or_error($res,array('id'=>is_int($res)?$res:null));
	}
	public static function update_tax_rate(WP_REST_Request $r){
		$res=Simple_POS_Tax::update_rate((int)$r['id'],$r->get_json_params());
		return self::respond_or_error($res,array('success'=>true));
	}
	public static function delete_tax_rate(WP_REST_Request $r){
		$res=Simple_POS_Tax::delete_rate((int)$r['id']);
		return self::respond_or_error($res,array('success'=>true));
	}
	public static function tax_calculate(WP_REST_Request $r){
		$p=$r->get_json_params();
		$lines=$p['lines']?? array();
		$country=$p['country']?? Simple_POS_Settings::get('tax_country','US');
		$state=$p['state']?? Simple_POS_Settings::get('tax_state','');
		$discount_type=$p['discount_type']??'fixed';
		$discount_amount=$p['discount_amount']??0;
		// Normalize lines to tax engine format.
		$calc_lines=array();
		foreach($lines as $l){
			$pid=(int)($l['product_id']??0);
			$prod=$pid? Simple_POS_Products::get_product($pid):null;
			$calc_lines[]=array('price'=> isset($l['price'])? (float)$l['price']: ( $prod? (float)$prod->price:0 ),'qty'=>(int)($l['qty']??1),'class_id'=>$prod? (int)$prod->tax_class_id:0);
		}
		$res=Simple_POS_Tax::calculate_order($calc_lines,$country,$state,$discount_type,$discount_amount);
		return rest_ensure_response($res);
	}
	/* Suppliers / POs */
	public static function get_suppliers(){ return rest_ensure_response(Simple_POS_Suppliers::get_suppliers()); }
	public static function create_supplier(WP_REST_Request $r){ $res=Simple_POS_Suppliers::create_supplier($r->get_json_params()); return self::respond_or_error($res,array('id'=>is_int($res)?$res:null)); }
	public static function update_supplier(WP_REST_Request $r){ $res=Simple_POS_Suppliers::update_supplier((int)$r['id'],$r->get_json_params()); return self::respond_or_error($res,array('success'=>true)); }
	public static function delete_supplier(WP_REST_Request $r){ $res=Simple_POS_Suppliers::delete_supplier((int)$r['id']); return self::respond_or_error($res,array('success'=>true)); }
	public static function get_purchase_orders(WP_REST_Request $r){
		$res=Simple_POS_Purchase_Orders::get_orders(array('status'=>$r->get_param('status')?:'any','per_page'=>$r->get_param('per_page')?:20,'page'=>$r->get_param('page')?:1));
		return rest_ensure_response($res);
	}
	public static function get_purchase_order(WP_REST_Request $r){
		$po=Simple_POS_Purchase_Orders::get_order((int)$r['id']); if(!$po) return new WP_Error('pos_not_found',__('PO not found.','simple-pos'),array('status'=>404));
		$po->items=Simple_POS_Purchase_Orders::get_items($po->id); return rest_ensure_response($po);
	}
	public static function create_purchase_order(WP_REST_Request $r){ $res=Simple_POS_Purchase_Orders::create_order($r->get_json_params()); return self::respond_or_error($res,array('id'=>is_int($res)?$res:null)); }
	public static function delete_purchase_order(WP_REST_Request $r){ $res=Simple_POS_Purchase_Orders::delete_order((int)$r['id']); return self::respond_or_error($res,array('success'=>true)); }
	public static function receive_purchase_order(WP_REST_Request $r){
		$p=$r->get_json_params(); $items=$p['items']?? $p;
		$res=Simple_POS_Purchase_Orders::receive((int)$r['id'], $items);
		return self::respond_or_error($res,array('success'=>true));
	}
	public static function update_po_status(WP_REST_Request $r){
		$p=$r->get_json_params();
		$res=Simple_POS_Purchase_Orders::update_status((int)$r['id'], $p['status']??'ordered');
		return self::respond_or_error($res,array('success'=>true));
	}

	/* ---------------------------------------------------------------
	 * Reports
	 * ------------------------------------------------------------- */

	public static function reports_summary( WP_REST_Request $request ) {
		list( $from, $to ) = self::resolve_date_range( $request );
		return rest_ensure_response( Simple_POS_Reports::get_summary( $from, $to ) );
	}

	public static function reports_sales_by_day( WP_REST_Request $request ) {
		list( $from, $to ) = self::resolve_date_range( $request );
		return rest_ensure_response( Simple_POS_Reports::get_sales_by_day( $from, $to ) );
	}

	public static function reports_top_products( WP_REST_Request $request ) {
		list( $from, $to ) = self::resolve_date_range( $request );
		$limit = $request->get_param( 'limit' ) ?: 10;
		return rest_ensure_response( Simple_POS_Reports::get_top_products( $from, $to, $limit ) );
	}

	public static function reports_low_stock( WP_REST_Request $request ) {
		$limit = $request->get_param( 'limit' ) ?: 50;
		return rest_ensure_response( Simple_POS_Products::get_low_stock_products( $limit ) );
	}

	/* ---------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------- */

	/**
	 * Resolve date_from/date_to params, defaulting to "today".
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array [ $from, $to ]
	 */
	private static function resolve_date_range( WP_REST_Request $request ) {
		$today = current_time( 'Y-m-d' );
		$from  = $request->get_param( 'date_from' ) ?: $today;
		$to    = $request->get_param( 'date_to' ) ?: $today;
		return array( $from, $to );
	}

	/**
	 * Turn a WP_Error into a REST error response, otherwise return the
	 * given success payload.
	 *
	 * @param mixed $result       Result from a data-layer call.
	 * @param mixed $success_body Payload to return on success.
	 * @return WP_REST_Response|WP_Error
	 */
	private static function respond_or_error( $result, $success_body ) {
		if ( is_wp_error( $result ) ) {
			$status = 'pos_not_found' === $result->get_error_code() ? 404 : 400;
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => $status ) );
		}
		return rest_ensure_response( $success_body );
	}
}
