<?php
/**
 * Tax classes / rates and calculation engine.
 * Supports per-country (+state) rates, inclusive vs exclusive, compound flag, priority ordering.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simple_POS_Tax {

	/**
	 * Get all tax classes.
	 * @return array objects id, name, slug, description
	 */
	public static function get_classes() {
		global $wpdb;
		$table = Simple_POS_DB::table( 'tax_classes' );
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC" );
	}

	public static function get_class( $id ) {
		global $wpdb;
		$table = Simple_POS_DB::table( 'tax_classes' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
	}

	public static function create_class( $name, $description = '' ) {
		global $wpdb;
		$name = sanitize_text_field( $name );
		if ( empty( $name ) ) {
			return new WP_Error( 'pos_invalid_input', __( 'Tax class name is required.', 'simple-pos' ) );
		}
		$slug  = sanitize_title( $name );
		$table = Simple_POS_DB::table( 'tax_classes' );
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s", $slug ) );
		if ( $existing ) {
			return new WP_Error( 'pos_duplicate', __( 'Tax class already exists.', 'simple-pos' ) );
		}
		$wpdb->insert( $table, array( 'name' => $name, 'slug' => $slug, 'description' => sanitize_text_field( $description ), 'created_at' => current_time( 'mysql' ) ) );
		if ( false === $wpdb->insert_id ) {
			return new WP_Error( 'pos_db_error', __( 'Could not create tax class.', 'simple-pos' ) );
		}
		return (int) $wpdb->insert_id;
	}

	public static function delete_class( $id ) {
		global $wpdb;
		$ct = Simple_POS_DB::table( 'tax_classes' );
		$rt = Simple_POS_DB::table( 'tax_rates' );
		$pt = Simple_POS_DB::table( 'products' );
		// Don't delete if products still reference it - reassign to standard.
		$std = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$ct} WHERE slug = %s", 'standard' ) );
		if ( $std && (int) $id !== (int) $std ) {
			$wpdb->update( $pt, array( 'tax_class_id' => $std ), array( 'tax_class_id' => $id ) );
		}
		$wpdb->delete( $rt, array( 'class_id' => $id ) );
		$wpdb->delete( $ct, array( 'id' => $id ) );
		return true;
	}

	public static function get_rates( $class_id = 0 ) {
		global $wpdb;
		$table = Simple_POS_DB::table( 'tax_rates' );
		if ( $class_id ) {
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE class_id = %d ORDER BY priority ASC, country_code ASC", $class_id ) );
		}
		return $wpdb->get_results( "SELECT r.*, c.name as class_name, c.slug as class_slug FROM {$table} r LEFT JOIN " . Simple_POS_DB::table('tax_classes') . " c ON c.id=r.class_id ORDER BY r.class_id, r.priority, r.country_code" );
	}

	public static function get_rate( $id ) {
		global $wpdb;
		$table = Simple_POS_DB::table( 'tax_rates' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", $id ) );
	}

	public static function create_rate( $data ) {
		global $wpdb;
		$table = Simple_POS_DB::table( 'tax_rates' );
		$class_id = isset($data['class_id']) ? (int) $data['class_id'] : 0;
		if ( ! $class_id || ! self::get_class($class_id) ) {
			return new WP_Error('pos_invalid_input', __('Invalid tax class.','simple-pos'));
		}
		$country = isset($data['country_code']) ? strtoupper(sanitize_text_field($data['country_code'])) : '';
		if ( empty($country) ) $country = '*';
		$state = isset($data['state_code']) && $data['state_code'] !== '' ? strtoupper(sanitize_text_field($data['state_code'])) : null;
		$rate = isset($data['rate']) ? (float) $data['rate'] : 0;
		if ( $rate < 0 || $rate > 100 ) return new WP_Error('pos_invalid_input', __('Rate must be 0-100.','simple-pos'));
		$res = $wpdb->insert($table, array(
			'class_id' => $class_id,
			'country_code' => $country,
			'state_code' => $state,
			'rate' => $rate,
			'is_compound' => !empty($data['is_compound'])?1:0,
			'is_inclusive' => !empty($data['is_inclusive'])?1:0,
			'priority' => isset($data['priority'])? (int)$data['priority']:0,
			'name' => isset($data['name'])? sanitize_text_field($data['name']):'',
			'created_at' => current_time('mysql'),
		));
		if ( false === $res ) return new WP_Error('pos_db_error',__('Could not create rate.','simple-pos'));
		return (int) $wpdb->insert_id;
	}

	public static function update_rate( $id, $data ) {
		global $wpdb;
		$table = Simple_POS_DB::table('tax_rates');
		$existing = self::get_rate($id);
		if ( ! $existing ) return new WP_Error('pos_not_found',__('Rate not found.','simple-pos'));
		$upd = array();
		if ( isset($data['country_code']) ) $upd['country_code'] = strtoupper(sanitize_text_field($data['country_code'])) ?: '*';
		if ( array_key_exists('state_code',$data) ) $upd['state_code'] = $data['state_code']!==''? strtoupper(sanitize_text_field($data['state_code'])):null;
		if ( isset($data['rate']) ) {
			$rate = (float) $data['rate'];
			if ( $rate<0||$rate>100) return new WP_Error('pos_invalid_input',__('Rate must be 0-100.','simple-pos'));
			$upd['rate']=$rate;
		}
		if ( isset($data['is_compound']) ) $upd['is_compound']= $data['is_compound']?1:0;
		if ( isset($data['is_inclusive']) ) $upd['is_inclusive']= $data['is_inclusive']?1:0;
		if ( isset($data['priority']) ) $upd['priority']=(int)$data['priority'];
		if ( isset($data['name']) ) $upd['name']=sanitize_text_field($data['name']);
		if ( isset($data['class_id']) ) {
			$cid=(int)$data['class_id'];
			if ( ! self::get_class($cid)) return new WP_Error('pos_invalid_input',__('Invalid tax class.','simple-pos'));
			$upd['class_id']=$cid;
		}
		if ( empty($upd)) return true;
		$wpdb->update($table,$upd,array('id'=>$id));
		return true;
	}

	public static function delete_rate( $id ) {
		global $wpdb;
		$table = Simple_POS_DB::table('tax_rates');
		$wpdb->delete($table,array('id'=>$id));
		return true;
	}

	/**
	 * Resolve applicable rates for a class + destination country/state.
	 * Returns array of rate objects ordered by priority.
	 * Falls back: exact state -> country wildcard -> '*' wildcard.
	 */
	public static function resolve_rates( $class_id, $country, $state = '' ) {
		global $wpdb;
		$table = Simple_POS_DB::table('tax_rates');
		$country = $country ? strtoupper($country) : '*';
		$state = $state ? strtoupper($state) : '';
		// Get all rates for class, then filter in PHP for fallback semantics.
		$rates = $wpdb->get_results( $wpdb->prepare("SELECT * FROM {$table} WHERE class_id=%d ORDER BY priority ASC, id ASC", $class_id) );
		if ( empty($rates) ) return array();
		// Bucket by priority — we need to allow compounding across priorities.
		// Simplified: return rates matching (country= exact and state exact) OR (country exact and state NULL) OR (country='*' )
		$matched = array();
		foreach ( $rates as $r ) {
			$r_country = strtoupper($r->country_code);
			$r_state = $r->state_code ? strtoupper($r->state_code) : '';
			if ( $r_country === '*' ) {
				// wildcard always matches as fallback if no better match at same priority
				$matched[] = $r;
				continue;
			}
			if ( $r_country !== $country ) continue;
			if ( $r_state !== '' && $r_state !== $state ) continue;
			// country matches, state either wildcard or exact
			$matched[] = $r;
		}
		// If we have both wildcard and specific at same priority, prefer specific (remove wildcard dup)
		// Group by priority, keep most specific
		$by_priority = array();
		foreach ($matched as $r) { $by_priority[$r->priority][] = $r; }
		$result = array();
		foreach ($by_priority as $prio => $list) {
			// If list contains a state-specific and a wildcard for same country, drop wildcard
			$has_state_specific = false;
			foreach ($list as $r) { if (!empty($r->state_code)) $has_state_specific = true; }
			foreach ($list as $r) {
				if ($has_state_specific && empty($r->state_code) && strtoupper($r->country_code) !== '*') {
					// skip country-wildcard when state-specific exists
					continue;
				}
				// If we have country-specific at this priority, wildcard '*' should be skipped
				if ($has_state_specific || count(array_filter($list, function($x){ return strtoupper($x->country_code)!=='*';} ))>0) {
					if (strtoupper($r->country_code)==='*') continue;
				}
				$result[] = $r;
			}
		}
		// If no country-specific found, keep wildcards
		if (empty($result) && !empty($matched)) {
			foreach ($matched as $r) if (strtoupper($r->country_code)==='*') $result[]=$r;
		}
		return $result;
	}

	/**
	 * Calculate tax for a single line.
	 * @param float $unit_price
	 * @param int $qty
	 * @param int $class_id
	 * @param string $country
	 * @param string $state
	 * @param float $discount_share share of order discount allocated to this line (absolute amount)
	 * @param array $settings allows overriding inclusive/rounding
	 * @return array { taxable, tax_amount, gross, breakdown: [{name,rate,amount,inclusive,compound}] }
	 */
	public static function calculate_line( $unit_price, $qty, $class_id, $country, $state, $discount_share = 0, $settings = null ) {
		if ( null === $settings ) $settings = Simple_POS_Settings::get_all();
		$rounding = isset($settings['tax_rounding']) ? $settings['tax_rounding'] : 'line';
		$qty = max(1,(int)$qty);
		$base = (float)$unit_price * $qty;
		$discount_share = max(0,(float)$discount_share);
		$discount_share = min($discount_share, $base);
		$taxable_before = $base - $discount_share; // after discount if discount_before_tax
		$discount_before_tax = !empty($settings['discount_before_tax']);
		$taxable = $discount_before_tax ? $taxable_before : $base;
		$rates = $class_id ? self::resolve_rates($class_id, $country, $state) : array();
		if ( empty($rates) ) {
			// fallback to legacy 0% if no rate
			return array( 'taxable'=> round($taxable,2), 'tax_amount'=>0, 'gross'=> round($taxable,2), 'breakdown'=>array(), 'rate'=>0 );
		}
		// Determine if inclusive: if any rate is inclusive, treat whole line as inclusive (rare mix). For mixed, inclusive rates are extracted first.
		$has_inclusive = false;
		foreach ($rates as $r) if ($r->is_inclusive) $has_inclusive = true;
		$breakdown = array();
		$total_tax = 0;
		$running_taxable = $taxable;
		// If inclusive, we need to extract tax from taxable which is gross.
		if ($has_inclusive) {
			// For inclusive, taxable is gross inclusive of inclusive taxes. Extract them.
			// For compound inclusive, order matters. We process in priority order, exclusive compounds on top.
			$exclusive_rates = array_filter($rates, function($r){ return !$r->is_inclusive; });
			$inclusive_rates = array_filter($rates, function($r){ return $r->is_inclusive; });
			$incl_tax = 0;
			// Extract inclusive: gross = net * (1+sum inclusive) approx; for single inclusive: net = gross/(1+rate)
			// With multiple inclusive plus compound, simplified: sum inclusive sequentially.
			$net = $taxable;
			foreach ($inclusive_rates as $r) {
				$rate = (float)$r->rate;
				// Extract: tax = gross - gross/(1+rate) if single; for multiple need iterative.
				// We do: net = net / (1+rate/100)
				$tax = $net - ($net / (1 + $rate/100));
				$tax = round($tax,2);
				$incl_tax += $tax;
				$net = $net - $tax;
				$breakdown[] = array('name'=>$r->name ?: $r->country_code, 'rate'=>$rate, 'amount'=>$tax, 'inclusive'=>1, 'compound'=> (int)$r->is_compound);
			}
			$running_taxable = $net;
			$total_tax += $incl_tax;
			// Now add exclusive on net
			foreach ($exclusive_rates as $r) {
				$rate = (float)$r->rate;
				$base_for_this = $r->is_compound ? ($running_taxable + $total_tax) : $running_taxable;
				$tax = round($base_for_this * $rate/100,2);
				$total_tax += $tax;
				$breakdown[] = array('name'=>$r->name ?: $r->country_code, 'rate'=>$rate, 'amount'=>$tax, 'inclusive'=>0, 'compound'=> (int)$r->is_compound);
			}
			$gross = $taxable + array_sum(array_column(array_filter($breakdown,function($b){return !$b['inclusive'];}),'amount'));
			if (!$discount_before_tax && $discount_share>0) {
				$gross = max(0, $gross - $discount_share);
			}
			return array('taxable'=> round($running_taxable,2), 'tax_amount'=> round($total_tax,2), 'gross'=> round($gross,2), 'breakdown'=>$breakdown, 'rate'=> null);
		} else {
			// All exclusive
			foreach ($rates as $r) {
				$rate = (float)$r->rate;
				$base_for_this = $r->is_compound ? ($running_taxable + $total_tax) : $running_taxable;
				$tax = round($base_for_this * $rate/100,2);
				$total_tax += $tax;
				$breakdown[] = array('name'=>$r->name ?: $r->country_code, 'rate'=>$rate, 'amount'=>$tax, 'inclusive'=>0, 'compound'=> (int)$r->is_compound);
			}
			$gross = $running_taxable + $total_tax;
			if (!$discount_before_tax && $discount_share>0) {
				$gross = max(0, $gross - $discount_share);
			}
			return array('taxable'=> round($running_taxable,2), 'tax_amount'=> round($total_tax,2), 'gross'=> round($gross,2), 'breakdown'=>$breakdown, 'rate'=> $rates? (float)$rates[0]->rate:0);
		}
	}

	/**
	 * Calculate order totals from lines.
	 * @param array $lines each {price,qty,class_id}
	 * @param string $country
	 * @param string $state
	 * @param string $discount_type fixed|percent
	 * @param float $discount_input
	 * @return array {subtotal, discount, tax, total, breakdown, lines: [calc]}
	 */
	public static function calculate_order( $lines, $country, $state, $discount_type='fixed', $discount_input=0 ) {
		$settings = Simple_POS_Settings::get_all();
		$subtotal = 0;
		foreach($lines as $l) $subtotal += (float)$l['price'] * (int)$l['qty'];
		$subtotal = round($subtotal,2);
		$discount = 0;
		if ('percent' === $discount_type) $discount = round($subtotal * ((float)$discount_input/100),2);
		else $discount = round(min((float)$discount_input, $subtotal),2);
		// Allocate discount proportionally if before tax
		$total_tax = 0;
		$line_calcs = array();
		foreach($lines as $idx=>$l){
			$line_base = (float)$l['price'] * (int)$l['qty'];
			$share = $subtotal>0 ? round($discount * ($line_base/$subtotal),2) : 0;
			// fix rounding drift on last line
			if ($idx === count($lines)-1) {
				$allocated = array_sum(array_column($line_calcs,'discount_share'));
				$share = round($discount - $allocated,2);
			}
			$class_id = isset($l['class_id'])? (int)$l['class_id']:0;
			$calc = self::calculate_line((float)$l['price'], (int)$l['qty'], $class_id, $country, $state, $share, $settings);
			$calc['discount_share']=$share;
			$line_calcs[]=$calc;
			$total_tax += $calc['tax_amount'];
		}
		$total_tax = round($total_tax,2);
		// ponytail: total = sum of line gross (covers exclusive and inclusive uniformly; avoids double-counting inclusive tax)
		$gross_sum = 0;
		foreach($line_calcs as $c) $gross_sum += $c['gross'];
		$total = round($gross_sum,2);
		if ($total<0) $total=0;
		// aggregate breakdown by rate name
		$agg = array();
		foreach($line_calcs as $c){ foreach($c['breakdown'] as $b){ $key=$b['name'].'|'.$b['rate']; if(!isset($agg[$key])) $agg[$key]=array('name'=>$b['name'],'rate'=>$b['rate'],'amount'=>0); $agg[$key]['amount']+= $b['amount']; } }
		foreach($agg as &$a) $a['amount']=round($a['amount'],2);
		return array('subtotal'=>$subtotal,'discount'=>$discount,'discount_type'=>$discount_type,'tax'=> round($total_tax,2),'total'=>$total,'breakdown'=> array_values($agg),'lines'=>$line_calcs);
	}
}
