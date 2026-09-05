<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Simple_POS_Suppliers {
	public static function get_suppliers() {
		global $wpdb;
		$table=Simple_POS_DB::table('suppliers');
		return $wpdb->get_results("SELECT * FROM {$table} ORDER BY name ASC"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
	public static function get_supplier($id){
		global $wpdb;
		$table=Simple_POS_DB::table('suppliers');
		return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d",$id));
	}
	public static function create_supplier($data){
		global $wpdb;
		$name=isset($data['name'])? sanitize_text_field($data['name']):'';
		if(empty($name)) return new WP_Error('pos_invalid_input',__('Supplier name required.','simple-pos'));
		$table=Simple_POS_DB::table('suppliers');
		$wpdb->insert($table,array(
			'name'=>$name,
			'contact_name'=> isset($data['contact_name'])? sanitize_text_field($data['contact_name']):'',
			'phone'=> isset($data['phone'])? sanitize_text_field($data['phone']):'',
			'email'=> isset($data['email'])? sanitize_email($data['email']):'',
			'address'=> isset($data['address'])? sanitize_textarea_field($data['address']):'',
			'created_at'=> current_time('mysql'),
		));
		return (int)$wpdb->insert_id;
	}
	public static function update_supplier($id,$data){
		global $wpdb;
		$table=Simple_POS_DB::table('suppliers');
		$existing=self::get_supplier($id);
		if(!$existing) return new WP_Error('pos_not_found',__('Supplier not found.','simple-pos'));
		$upd=array(
			'name'=> isset($data['name'])? sanitize_text_field($data['name']): $existing->name,
			'contact_name'=> isset($data['contact_name'])? sanitize_text_field($data['contact_name']): $existing->contact_name,
			'phone'=> isset($data['phone'])? sanitize_text_field($data['phone']): $existing->phone,
			'email'=> isset($data['email'])? sanitize_email($data['email']): $existing->email,
			'address'=> isset($data['address'])? sanitize_textarea_field($data['address']): $existing->address,
		);
		if(empty($upd['name'])) return new WP_Error('pos_invalid_input',__('Supplier name required.','simple-pos'));
		$wpdb->update($table,$upd,array('id'=>$id));
		return true;
	}
	public static function delete_supplier($id){
		global $wpdb;
		$wpdb->delete(Simple_POS_DB::table('suppliers'),array('id'=>$id));
		return true;
	}
}

class Simple_POS_Purchase_Orders {
	public static function next_po_number(){
		global $wpdb;
		$settings=Simple_POS_Settings::get_all();
		$prefix= isset($settings['po_number_prefix'])? $settings['po_number_prefix']:'PO-';
		$table=Simple_POS_DB::table('purchase_orders');
		$max = $wpdb->get_var("SELECT MAX(id) FROM {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$next = $max ? ((int)$max+1) : 1;
		// try AUTO_INCREMENT as fallback but MAX is safer
		return $prefix . str_pad($next,6,'0',STR_PAD_LEFT);
	}

	public static function get_orders($args=array()){
		global $wpdb;
		$defaults=array('status'=>'any','per_page'=>20,'page'=>1);
		$args=wp_parse_args($args,$defaults);
		$table=Simple_POS_DB::table('purchase_orders');
		$where='1=1'; $params=array();
		if('any'!==$args['status']){ $where.=' AND status=%s'; $params[]=$args['status']; }
		$total_sql="SELECT COUNT(*) FROM {$table} WHERE {$where}";
		$total=(int)$wpdb->get_var($params? $wpdb->prepare($total_sql,$params): $total_sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$per_page=max(1,min(100,(int)$args['per_page'])); $page=max(1,(int)$args['page']); $offset=($page-1)*$per_page;
		$sql="SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$qparams=array_merge($params,array($per_page,$offset));
		$items=$wpdb->get_results($wpdb->prepare($sql,$qparams)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array('items'=>$items,'total'=>$total);
	}
	public static function get_order($id){
		global $wpdb;
		$table=Simple_POS_DB::table('purchase_orders');
		return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d",$id));
	}
	public static function get_items($po_id){
		global $wpdb;
		$table=Simple_POS_DB::table('po_items');
		return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE po_id=%d",$po_id));
	}
	public static function create_order($data){
		global $wpdb;
		$supplier_id = !empty($data['supplier_id'])? (int)$data['supplier_id']: null;
		$note = isset($data['note'])? sanitize_textarea_field($data['note']):'';
		$items = isset($data['items']) && is_array($data['items'])? $data['items']: array();
		if(empty($items)) return new WP_Error('pos_invalid_input',__('Add at least one item.','simple-pos'));
		$po_table=Simple_POS_DB::table('purchase_orders');
		$item_table=Simple_POS_DB::table('po_items');
		$po_number = self::next_po_number();
		$total_cost=0;
		foreach($items as $it){ $total_cost+= (float)($it['cost_price']??0) * (int)($it['qty']??0); }
		$result = Simple_POS_DB::transaction(function() use ($po_table,$item_table,$supplier_id,$note,$items,$po_number,$total_cost){
			global $wpdb;
			$wpdb->insert($po_table,array('po_number'=>$po_number,'supplier_id'=>$supplier_id,'status'=>'draft','total_cost'=>round($total_cost,2),'note'=>$note,'created_at'=>current_time('mysql'),'created_by'=>get_current_user_id()));
			$po_id=(int)$wpdb->insert_id;
			if(!$po_id) return new WP_Error('pos_db_error',__('Could not create PO.','simple-pos'));
			foreach($items as $it){
				$product_id = !empty($it['product_id'])? (int)$it['product_id']: null;
				$variant_id = !empty($it['variant_id'])? (int)$it['variant_id']: null;
				if(!$product_id && !$variant_id) continue;
				$qty=(int)($it['qty']??1); if($qty<=0) $qty=1;
				$cost=(float)($it['cost_price']??0);
				$wpdb->insert($item_table,array('po_id'=>$po_id,'product_id'=>$product_id,'variant_id'=>$variant_id,'qty'=>$qty,'cost_price'=>$cost,'received_qty'=>0));
			}
			return $po_id;
		});
		return $result;
	}
	public static function update_status($id,$status){
		global $wpdb;
		$allowed=array('draft','ordered','partial','received','cancelled');
		if(!in_array($status,$allowed,true)) return new WP_Error('pos_invalid_input',__('Invalid status.','simple-pos'));
		$table=Simple_POS_DB::table('purchase_orders');
		$upd=array('status'=>$status);
		if('ordered'===$status) $upd['ordered_at']=current_time('mysql');
		if('received'===$status) $upd['received_at']=current_time('mysql');
		$wpdb->update($table,$upd,array('id'=>$id));
		return true;
	}
	public static function receive($id,$receive_data=array()){
		global $wpdb;
		$order=self::get_order($id);
		if(!$order) return new WP_Error('pos_not_found',__('PO not found.','simple-pos'));
		if(in_array($order->status,array('cancelled','received'),true)) return new WP_Error('pos_invalid_state',__('PO already closed.','simple-pos'));
		$items=self::get_items($id);
		$map=array();
		foreach($items as $it) $map[$it->id]=$it;
		return Simple_POS_DB::transaction(function() use ($id,$receive_data,$map,$order){
			global $wpdb;
			$po_items_table=Simple_POS_DB::table('po_items');
			$all_received=true;
			foreach($receive_data as $r){
				$item_id=(int)($r['item_id']??0);
				$qty=(int)($r['received_qty']??0);
				if(!isset($map[$item_id])) continue;
				$item=$map[$item_id];
				$to_receive = $qty>0? $qty : ($item->qty - $item->received_qty);
				$to_receive = max(0, min($to_receive, $item->qty - $item->received_qty));
				if($to_receive<=0) continue;
				$wpdb->query($wpdb->prepare("UPDATE {$po_items_table} SET received_qty = received_qty + %d WHERE id=%d",$to_receive,$item_id));
				if($item->variant_id){
					Simple_POS_Variants::adjust_stock($item->variant_id, $to_receive, 'purchase', $id, 'PO '.$order->po_number);
					if($item->cost_price) {
						// optionally update cost_price on variant? keep product cost_price
					}
				} elseif($item->product_id){
					Simple_POS_Products::adjust_stock($item->product_id, $to_receive, 'purchase', $id, 'PO '.$order->po_number);
				}
			}
			// Check if fully received
			$remaining = $wpdb->get_var($wpdb->prepare("SELECT SUM(qty - received_qty) FROM {$po_items_table} WHERE po_id=%d",$id));
			$status = ((int)$remaining<=0) ? 'received' : 'partial';
			$wpdb->update(Simple_POS_DB::table('purchase_orders'), array('status'=>$status, 'received_at'=> current_time('mysql')), array('id'=>$id));
			return true;
		});
	}
	public static function delete_order($id){
		global $wpdb;
		$wpdb->delete(Simple_POS_DB::table('po_items'),array('po_id'=>$id));
		$wpdb->delete(Simple_POS_DB::table('purchase_orders'),array('id'=>$id));
		return true;
	}
}
