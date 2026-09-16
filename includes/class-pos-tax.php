<?php
/**
 * Tax classes / rates and calculation engine.
 * Supports per-country (+state) rates, inclusive vs exclusive, compound flag, priority ordering.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simple_POS_Tax {

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
			return new WP_Error( 'pos_invalid_input', __( 'Tax class name is required.', 'wp-pos-plugin' ) );
		}
		$slug     = sanitize_title( $name );
		$table    = Simple_POS_DB::table( 'tax_classes' );
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s", $slug ) );
		if ( $existing ) {
			return new WP_Error( 'pos_duplicate', __( 'Tax class already exists.', 'wp-pos-plugin' ) );
		}
		$wpdb->insert(
			$table,
			array(
				'name'        => $name,
				'slug'        => $slug,
				'description' => sanitize_text_field( $description ),
				'created_at'  => current_time( 'mysql' ),
			)
		);
		if ( false === $wpdb->insert_id ) {
			return new WP_Error( 'pos_db_error', __( 'Could not create tax class.', 'wp-pos-plugin' ) );
		}
		return (int) $wpdb->insert_id;
	}

	public static function delete_class( $id ) {
		global $wpdb;
		$ct = Simple_POS_DB::table( 'tax_classes' );
		$rt = Simple_POS_DB::table( 'tax_rates' );
		$pt = Simple_POS_DB::table( 'products' );
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
		return $wpdb->get_results( $wpdb->prepare( "SELECT r.*, c.name as class_name, c.slug as class_slug FROM %s r LEFT JOIN %s c ON c.id=r.class_id ORDER BY r.class_id, r.priority, r.country_code", $table, Simple_POS_DB::table( 'tax_classes' ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function get_rate( $id ) {
		global $wpdb;
		$table = Simple_POS_DB::table( 'tax_rates' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", $id ) );
	}

	public static function create_rate( $data ) {
		global $wpdb;
		$table    = Simple_POS_DB::table( 'tax_rates' );
		$class_id = isset( $data['class_id'] ) ? (int) $data['class_id'] : 0;
		if ( ! $class_id || ! self::get_class( $class_id ) ) {
			return new WP_Error( 'pos_invalid_input', __( 'Invalid tax class.', 'wp-pos-plugin' ) );
		}
		$country = isset( $data['country_code'] ) ? strtoupper( sanitize_text_field( $data['country_code'] ) ) : '';
		if ( empty( $country ) ) {
			$country = '*';
		}
		$state = isset( $data['state_code'] ) && $data['state_code'] !== '' ? strtoupper( sanitize_text_field( $data['state_code'] ) ) : null;
		$rate  = isset( $data['rate'] ) ? (float) $data['rate'] : 0;
		if ( $rate < 0 || $rate > 100 ) {
			return new WP_Error( 'pos_invalid_input', __( 'Rate must be 0-100.', 'wp-pos-plugin' ) );
		}
		$res = $wpdb->insert(
			$table,
			array(
				'class_id'     => $class_id,
				'country_code' => $country,
				'state_code'   => $state,
				'rate'         => $rate,
				'is_compound'  => ! empty( $data['is_compound'] ) ? 1 : 0,
				'is_inclusive' => ! empty( $data['is_inclusive'] ) ? 1 : 0,
				'priority'     => isset( $data['priority'] ) ? (int) $data['priority'] : 0,
				'name'         => isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '',
				'gst_split'    => ! empty( $data['gst_split'] ) ? 1 : 0,
				'created_at'   => current_time( 'mysql' ),
			)
		);
		if ( false === $res ) {
			return new WP_Error( 'pos_db_error', __( 'Could not create rate.', 'wp-pos-plugin' ) );
		}
		return (int) $wpdb->insert_id;
	}

	public static function update_rate( $id, $data ) {
		global $wpdb;
		$table    = Simple_POS_DB::table( 'tax_rates' );
		$existing = self::get_rate( $id );
		if ( ! $existing ) {
			return new WP_Error( 'pos_not_found', __( 'Rate not found.', 'wp-pos-plugin' ) );
		}
		$upd = array();
		if ( isset( $data['country_code'] ) ) {
			$upd['country_code'] = strtoupper( sanitize_text_field( $data['country_code'] ) ) ?: '*';
		}
		if ( array_key_exists( 'state_code', $data ) ) {
			$upd['state_code'] = $data['state_code'] !== '' ? strtoupper( sanitize_text_field( $data['state_code'] ) ) : null;
		}
		if ( isset( $data['rate'] ) ) {
			$rate = (float) $data['rate'];
			if ( $rate < 0 || $rate > 100 ) {
				return new WP_Error( 'pos_invalid_input', __( 'Rate must be 0-100.', 'wp-pos-plugin' ) );
			}
			$upd['rate'] = $rate;
		}
		if ( isset( $data['is_compound'] ) ) {
			$upd['is_compound'] = $data['is_compound'] ? 1 : 0;
		}
		if ( isset( $data['is_inclusive'] ) ) {
			$upd['is_inclusive'] = $data['is_inclusive'] ? 1 : 0;
		}
		if ( isset( $data['priority'] ) ) {
			$upd['priority'] = (int) $data['priority'];
		}
		if ( isset( $data['name'] ) ) {
			$upd['name'] = sanitize_text_field( $data['name'] );
		}
		if ( isset( $data['gst_split'] ) ) {
			$upd['gst_split'] = $data['gst_split'] ? 1 : 0;
		}
		if ( isset( $data['class_id'] ) ) {
			$cid = (int) $data['class_id'];
			if ( ! self::get_class( $cid ) ) {
				return new WP_Error( 'pos_invalid_input', __( 'Invalid tax class.', 'wp-pos-plugin' ) );
			}
			$upd['class_id'] = $cid;
		}
		if ( empty( $upd ) ) {
			return true;
		}
		$wpdb->update( $table, $upd, array( 'id' => $id ) );
		return true;
	}

	public static function delete_rate( $id ) {
		global $wpdb;
		$table = Simple_POS_DB::table( 'tax_rates' );
		$wpdb->delete( $table, array( 'id' => $id ) );
		return true;
	}

	/**
	 * Distinct country codes that have at least one rate configured — the
	 * valid choices for the terminal's country selector. Excludes the '*'
	 * wildcard (a wildcard rate applies everywhere, it is not a country).
	 *
	 * @return string[] Uppercase ISO2 codes, sorted.
	 */
	public static function get_configured_countries() {
		global $wpdb;
		$table = Simple_POS_DB::table( 'tax_rates' );
		$rows  = $wpdb->get_col( "SELECT DISTINCT country_code FROM {$table} ORDER BY country_code ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$out   = array();
		foreach ( (array) $rows as $code ) {
			$code = strtoupper( trim( (string) $code ) );
			if ( '' !== $code && '*' !== $code && ! in_array( $code, $out, true ) ) {
				$out[] = $code;
			}
		}
		sort( $out );
		return $out;
	}

	const COUNTRY_CODES_A = 'AD:Andorra,AE:United Arab Emirates,AF:Afghanistan,AG:Antigua and Barbuda,AI:Anguilla,AL:Albania,AM:Armenia,AO:Angola,AQ:Antarctica,AR:Argentina,AS:American Samoa,AT:Austria,AU:Australia,AW:Aruba,AX:Aland Islands,AZ:Azerbaijan,BA:Bosnia and Herzegovina,BB:Barbados,BD:Bangladesh,BE:Belgium,BF:Burkina Faso,BG:Bulgaria,BH:Bahrain,BI:Burundi,BJ:Benin,BL:Saint Barthelemy,BM:Bermuda,BN:Brunei,BO:Bolivia,BQ:Bonaire Sint Eustatius and Saba,BR:Brazil,BS:Bahamas,BT:Bhutan,BV:Bouvet Island,BW:Botswana,BY:Belarus,BZ:Belize,CA:Canada,CC:Cocos (Keeling) Islands,CD:Congo (Democratic Republic),CF:Central African Republic,CG:Congo,CH:Switzerland,CI:Cote d Ivoire,CK:Cook Islands,CL:Chile,CM:Cameroon,CN:China,CO:Colombia,CR:Costa Rica,CU:Cuba,CV:Cabo Verde,CW:Curacao,CX:Christmas Island,CY:Cyprus,CZ:Czechia,DE:Germany,DJ:Djibouti,DK:Denmark,DM:Dominica,DO:Dominican Republic,DZ:Algeria,EC:Ecuador,EE:Estonia,EG:Egypt,EH:Western Sahara,ER:Eritrea,ES:Spain,ET:Ethiopia,FI:Finland,FJ:Fiji,FK:Falkland Islands,FM:Micronesia,FO:Faroe Islands,FR:France,GA:Gabon,GB:United Kingdom,GD:Grenada,GE:Georgia,GF:French Guiana,GG:Guernsey,GH:Ghana,GI:Gibraltar,GL:Greenland,GM:Gambia,GN:Guinea,GP:Guadeloupe,GQ:Equatorial Guinea,GR:Greece,GS:South Georgia and the South Sandwich Islands,GT:Guatemala,GU:Guam,GW:Guinea-Bissau,GY:Guyana';

	const COUNTRY_CODES_B = 'HK:Hong Kong,HM:Heard Island and McDonald Islands,HN:Honduras,HR:Croatia,HT:Haiti,HU:Hungary,ID:Indonesia,IE:Ireland,IL:Israel,IM:Isle of Man,IN:India,IO:British Indian Ocean Territory,IQ:Iraq,IR:Iran,IS:Iceland,IT:Italy,JE:Jersey,JM:Jamaica,JO:Jordan,JP:Japan,KE:Kenya,KG:Kyrgyzstan,KH:Cambodia,KI:Kiribati,KM:Comoros,KN:Saint Kitts and Nevis,KP:North Korea,KR:South Korea,KW:Kuwait,KY:Cayman Islands,KZ:Kazakhstan,LA:Laos,LB:Lebanon,LC:Saint Lucia,LI:Liechtenstein,LK:Sri Lanka,LR:Liberia,LS:Lesotho,LT:Lithuania,LU:Luxembourg,LV:Latvia,LY:Libya,MA:Morocco,MC:Monaco,MD:Moldova,ME:Montenegro,MF:Saint Martin (French part),MG:Madagascar,MH:Marshall Islands,MK:North Macedonia,ML:Mali,MM:Myanmar,MN:Mongolia,MO:Macao,MP:Northern Mariana Islands,MQ:Martinique,MR:Mauritania,MS:Montserrat,MT:Malta,MU:Mauritius,MV:Maldives,MW:Malawi,MX:Mexico,MY:Malaysia,MZ:Mozambique,NA:Namibia,NC:New Caledonia,NE:Niger,NF:Norfolk Island,NG:Nigeria,NI:Nicaragua,NL:Netherlands,NO:Norway,NP:Nepal,NR:Nauru,NU:Niue,NZ:New Zealand,OM:Oman,PA:Panama,PE:Peru,PF:French Polynesia,PG:Papua New Guinea,PH:Philippines,PK:Pakistan,PL:Poland,PM:Saint Pierre and Miquelon,PN:Pitcairn,PR:Puerto Rico,PS:Palestine,PT:Portugal,PW:Palau,PY:Paraguay,QA:Qatar,RE:Reunion,RO:Romania,RS:Serbia,RU:Russia,RW:Rwanda,SA:Saudi Arabia,SB:Solomon Islands,SC:Seychelles,SD:Sudan,SE:Sweden,SG:Singapore,SH:Saint Helena,SI:Slovenia,SJ:Svalbard and Jan Mayen,SK:Slovakia,SL:Sierra Leone,SM:San Marino,SN:Senegal,SO:Somalia,SR:Suriname,SS:South Sudan,ST:Sao Tome and Principe,SV:El Salvador,SX:Sint Maarten,SY:Syria,SZ:Eswatini,TC:Turks and Caicos Islands,TD:Chad,TF:French Southern Territories,TG:Togo,TH:Thailand,TJ:Tajikistan,TK:Tokelau,TL:Timor-Leste,TM:Turkmenistan,TN:Tunisia,TO:Tonga,TR:Turkey,TT:Trinidad and Tobago,TV:Tuvalu,TW:Taiwan,TZ:Tanzania,UA:Ukraine,UG:Uganda,UM:United States Minor Outlying Islands,US:United States,UY:Uruguay,UZ:Uzbekistan,VA:Holy See (Vatican),VC:Saint Vincent and the Grenadines,VE:Venezuela,VG:Virgin Islands (British),VI:Virgin Islands (U.S.),VN:Viet Nam,VU:Vanuatu,WF:Wallis and Futuna,WS:Samoa,YE:Yemen,YT:Mayotte,ZA:South Africa,ZM:Zambia,ZW:Zimbabwe';

	/**
	 * Canonical ISO 3166-1 alpha-2 country codes for selects (rate editor,
	 * terminal labels). Keeps stored country codes consistent so per-country
	 * rate matching always hits.
	 *
	 * @return array code => label
	 */
	public static function country_list() {
		static $list = null;
		if ( null !== $list ) {
			return $list;
		}
		$list = array();
		foreach ( explode( ',', self::COUNTRY_CODES_A . ',' . self::COUNTRY_CODES_B ) as $pair ) {
			$parts = explode( ':', $pair, 2 );
			if ( 2 === count( $parts ) ) {
				$list[ $parts[0] ] = $parts[1];
			}
		}
		return $list;
	}

	/**
	 * Decimal-correct half-up rounding for money.
	 *
	 * PHP's round() works on the binary float, so exact-half decimal
	 * values (5.715, 2.675, 0.855 …) are stored a hair below the half
	 * and wrongly round down, silently losing paise. Rounding on the
	 * decimal expansion instead makes 5.715 → 5.72, every time.
	 *
	 * @param float $value
	 * @param int   $decimals
	 * @return float
	 */
	public static function pos_round( $value, $decimals = 2 ) {
		$decimals = max( 0, (int) $decimals );
		$neg      = (float) $value < 0 ? -1 : 1;
		// 12 decimal places: money-scale inputs carry at most ~8
		// significant decimals, so binary float dust (which lives
		// ~15dp out) can never flip the half-up decision.
		$expanded = sprintf( '%.12F', abs( (float) $value ) );
		$dot      = strpos( $expanded, '.' );
		$int_part = false === $dot ? $expanded : substr( $expanded, 0, $dot );
		$frac     = false === $dot ? '' : substr( $expanded, $dot + 1 );
		$frac     = str_pad( $frac, $decimals + 1, '0' );
		$keep     = 0 === $decimals ? '' : substr( $frac, 0, $decimals );
		$next     = (int) substr( $frac, $decimals, 1 );
		if ( $next >= 5 ) {
			if ( '' === $keep ) {
				// decimals = 0: rounding up from x.5+ means +1 on the integer.
				$int_part = (string) ( (int) $int_part + 1 );
			} else {
				$keep  = str_pad( (string) ( (int) $keep + 1 ), $decimals, '0', STR_PAD_LEFT );
				$carry = strlen( $keep ) > $decimals;
				if ( $carry ) {
					$keep     = str_repeat( '0', $decimals );
					$int_part = (string) ( (int) $int_part + 1 );
				}
			}
		}
		$result = 0 === $decimals ? $int_part : ( $int_part . '.' . $keep );
		$float  = (float) $result;
		return 0.0 === $float ? 0.0 : $neg * $float;
	}

	/**
	 * A tax component computed from its taxable base with paise-exact
	 * half-up rounding (e.g. 9% of ₹63.50 → ₹5.72, never ₹5.71).
	 *
	 * @param float $base_rupees Taxable base in rupees.
	 * @param float $rate_pct    Component rate, e.g. 9 for 9%.
	 * @return float Component amount in rupees, 2dp.
	 */
	public static function component_from_base( $base_rupees, $rate_pct ) {
		$base_paise = (int) self::pos_round( (float) $base_rupees * 100, 0 );
		$comp_paise = (int) self::pos_round( $base_paise * (float) $rate_pct / 100, 0 );
		// Normalize through pos_round so the return is always a float
		// (int/int with an even division would otherwise yield an int).
		return self::pos_round( $comp_paise / 100, 2 );
	}

	/**
	 * Tax class for a cart line: the variant's override when set,
	 * otherwise the parent product's class. Single source of truth —
	 * checkout, previews and scanner lookups must all agree.
	 *
	 * @param int $product_id
	 * @param int $variant_id 0 = no variant.
	 * @return int Tax class ID (0 = untaxed).
	 */
	public static function resolve_line_tax_class( $product_id, $variant_id = 0 ) {
		$product = $product_id ? Simple_POS_Products::get_product( $product_id ) : null;
		$class   = $product ? (int) ( $product->tax_class_id ?: 0 ) : 0;
		if ( $variant_id && class_exists( 'Simple_POS_Variants' ) ) {
			$variant = Simple_POS_Variants::get_variant( $variant_id );
			if ( $variant && (int) $variant->parent_product_id === (int) $product_id && ! empty( $variant->tax_class_id ) ) {
				$class = (int) $variant->tax_class_id;
			}
		}
		return $class;
	}

	/**
	 * Combined rate of a (possibly GST-split) breakdown, e.g. 9 + 9 = 18.
	 * Used for the stored per-line applied rate.
	 *
	 * @param array $breakdown Breakdown entries with 'rate' keys.
	 * @return float
	 */
	public static function breakdown_rate_total( $breakdown ) {
		$sum = 0.0;
		foreach ( (array) $breakdown as $b ) {
			if ( isset( $b['rate'] ) && is_numeric( $b['rate'] ) ) {
				$sum += (float) $b['rate'];
			}
		}
		return self::pos_round( $sum, 4 );
	}

	/**
	 * Split an Indian GST rate into CGST + SGST (intrastate) or IGST.
	 *
	 * Each half is derived from the component's taxable base with
	 * paise-exact rounding — never by halving the already-rounded full
	 * amount (that loses a paise whenever the full amount is odd, e.g.
	 * 18% of ₹63.50 = ₹11.43 → 5.71 + 5.72 instead of 5.72 + 5.72).
	 * Split components are therefore always line-rounded to 2dp, even
	 * in per-order rounding mode (GST law rounds per line item).
	 *
	 * @param array  $breakdown Breakdown entries (each carries 'base').
	 * @param string $country
	 * @param string $state
	 * @param array|null $settings Settings override (uses live settings when null).
	 * @return array { breakdown, split, split_exclusive }
	 */
	private static function apply_india_gst_split( $breakdown, $country, $state, $settings = null ) {
		$none = array( 'breakdown' => $breakdown, 'split' => false, 'split_exclusive' => false );
		if ( 'IN' !== strtoupper( $country ) ) {
			return $none;
		}

		// Seller = store state; buyer = sale state (fallback to seller).
		// Intrastate (CGST+SGST) needs buyer and seller in the SAME state;
		// anything cross-state is IGST. Amounts are identical either way —
		// only the labels (and thus the invoice) differ.
		if ( null === $settings ) {
			$settings = Simple_POS_Settings::get_all();
		}
		$seller_state = strtoupper( trim( (string) ( isset( $settings['tax_state'] ) ? $settings['tax_state'] : '' ) ) );
		$buyer_state  = strtoupper( trim( (string) $state ) );
		if ( '' === $buyer_state ) {
			$buyer_state = $seller_state;
		}

		$out             = array();
		$split           = false;
		$split_exclusive = false;
		foreach ( $breakdown as $b ) {
			if ( ! empty( $b['gst_split'] ) ) {
				$rate_state = strtoupper( trim( $b['state_code'] ?? '' ) );

				// Determine if intrastate (same state) or interstate.
				$seller_agrees = ( '' === $seller_state || $seller_state === $buyer_state );
				if ( '' !== $rate_state ) {
					// State-specific rate: applies to this buyer and the
					// seller must be local too (unknown seller = assume local).
					$is_intrastate = ( $rate_state === $buyer_state ) && $seller_agrees;
				} else {
					// Country-level rate: intrastate when buyer and seller agree.
					$is_intrastate = ( '' !== $buyer_state ) && $seller_agrees;
				}

				if ( $is_intrastate ) {
					// Intrastate: CGST + SGST, each derived from the base.
					$split     = true;
					$full_rate = (float) $b['rate'];
					$half_rate = self::pos_round( $full_rate / 2, 2 );
					$base      = isset( $b['base'] ) && is_numeric( $b['base'] )
						? (float) $b['base']
						: ( $full_rate > 0 ? (float) $b['amount'] * 100 / $full_rate : 0 );
					if ( ! empty( $b['inclusive'] ) ) {
						// MRP-preserving plug: CGST from the base, SGST takes
						// the rest of the extracted amount so the gross total
						// never drifts away from the inclusive price.
						$cgst = self::component_from_base( $base, $half_rate );
						$sgst = self::pos_round( (float) $b['amount'] - $cgst, 2 );
					} else {
						$split_exclusive = true;
						$cgst = self::component_from_base( $base, $half_rate );
						$sgst = self::component_from_base( $base, $half_rate );
					}
					$out[] = array(
						'name'      => 'CGST',
						'rate'      => $half_rate,
						'amount'    => $cgst,
						'inclusive' => $b['inclusive'] ?? 0,
						'compound'  => $b['compound'] ?? 0,
						'state_code' => $rate_state,
					);
					$out[] = array(
						'name'      => 'SGST',
						'rate'      => $half_rate,
						'amount'    => $sgst,
						'inclusive' => $b['inclusive'] ?? 0,
						'compound'  => $b['compound'] ?? 0,
						'state_code' => $rate_state,
					);
				} else {
					// Interstate: Apply IGST (full rate).
					$out[] = array(
						'name'      => 'IGST',
						'rate'      => (float) $b['rate'],
						'amount'    => $b['amount'],
						'inclusive' => $b['inclusive'] ?? 0,
						'compound'  => $b['compound'] ?? 0,
						'state_code' => $rate_state,
					);
				}
			} else {
				$kept = $b;
				unset( $kept['base'] );
				$out[] = $kept;
			}
		}
		return array( 'breakdown' => $out, 'split' => $split, 'split_exclusive' => $split_exclusive );
	}

	/**
	 * Resolve applicable rates for a class + destination country/state.
	 * Returns array of rate objects ordered by priority.
	 * Falls back: exact state -> country wildcard -> '*' wildcard.
	 */
	public static function resolve_rates( $class_id, $country, $state = '' ) {
		global $wpdb;
		$table   = Simple_POS_DB::table( 'tax_rates' );
		$country = $country ? strtoupper( $country ) : '*';
		$state   = $state ? strtoupper( $state ) : '';
		$rates = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE class_id=%d ORDER BY priority ASC, id ASC", $class_id ) );
		if ( empty( $rates ) ) {
			return array();
		}
		$matched = array();
		foreach ( $rates as $r ) {
			$r_country = strtoupper( $r->country_code );
			$r_state   = $r->state_code ? strtoupper( $r->state_code ) : '';
			if ( $r_country === '*' ) {
				$matched[] = $r;
				continue;
			}
			if ( $r_country !== $country ) {
				continue;
			}
			if ( $r_state !== '' && $r_state !== $state ) {
				continue;
			}
			$matched[] = $r;
		}
		$by_priority = array();
		foreach ( $matched as $r ) {
			$by_priority[ $r->priority ][] = $r; }
		$result = array();
		foreach ( $by_priority as $prio => $list ) {
			$has_state_specific = false;
			foreach ( $list as $r ) {
				if ( ! empty( $r->state_code ) ) {
					$has_state_specific = true;
				}
			}
			foreach ( $list as $r ) {
				if ( $has_state_specific && empty( $r->state_code ) && strtoupper( $r->country_code ) !== '*' ) {
					continue;
				}
				if ( $has_state_specific || count(
					array_filter(
						$list,
						function ( $x ) {
							return strtoupper( $x->country_code ) !== '*';}
					)
				) > 0 ) {
					if ( strtoupper( $r->country_code ) === '*' ) {
						continue;
					}
				}
				$result[] = $r;
			}
		}
		if ( empty( $result ) && ! empty( $matched ) ) {
			foreach ( $matched as $r ) {
				if ( strtoupper( $r->country_code ) === '*' ) {
					$result[] = $r;
				}
			}
		}
		return $result;
	}

	/**
	 * Calculate tax for a single line.
	 *
	 * @param float  $unit_price
	 * @param int    $qty
	 * @param int    $class_id
	 * @param string $country
	 * @param string $state
	 * @param float  $discount_share share of order discount allocated to this line (absolute amount)
	 * @param array  $settings allows overriding inclusive/rounding
	 * @param bool   $round       round to 2dp when true; leave exact when false for per-order rounding
	 * @return array { taxable, tax_amount, gross, breakdown: [{name,rate,amount,inclusive,compound,gst_split,state_code}] }
	 */
	public static function calculate_line( $unit_price, $qty, $class_id, $country, $state, $discount_share = 0, $settings = null, $round = true ) {
		if ( null === $settings ) {
			$settings = Simple_POS_Settings::get_all();
		}
		$rounding            = isset( $settings['tax_rounding'] ) ? $settings['tax_rounding'] : 'line';
		$qty                 = max( 1, (int) $qty );
		$base                = (float) $unit_price * $qty;
		$discount_share      = max( 0, (float) $discount_share );
		$discount_share      = min( $discount_share, $base );
		$taxable_before      = $base - $discount_share;
		$discount_before_tax = ! empty( $settings['discount_before_tax'] );
		$taxable             = $discount_before_tax ? $taxable_before : $base;
		$rates               = $class_id ? self::resolve_rates( $class_id, $country, $state ) : array();
		if ( empty( $rates ) ) {
			$gross = $taxable;
			if ( ! $discount_before_tax && $discount_share > 0 ) {
				$gross = max( 0, $gross - $discount_share );
			}
			return array(
				'taxable'    => $round ? self::pos_round( $taxable, 2 ) : $taxable,
				'tax_amount' => 0,
				'gross'      => $round ? self::pos_round( $gross, 2 ) : $gross,
				'breakdown'  => array(),
				'rate'       => 0,
			);
		}
		$force_inclusive = ! empty( $settings['tax_inclusive'] );
		$has_inclusive   = false;
		foreach ( $rates as $r ) {
			if ( $force_inclusive || $r->is_inclusive ) {
				$has_inclusive = true;
			}
		}
		$breakdown       = array();
		$total_tax       = 0;
		$running_taxable = $taxable;
		if ( $has_inclusive ) {
			$exclusive_rates = array_filter(
				$rates,
				function ( $r ) use ( $force_inclusive ) {
					return ! $force_inclusive && ! $r->is_inclusive;
				}
			);
			$inclusive_rates = array_filter(
				$rates,
				function ( $r ) use ( $force_inclusive ) {
					return $force_inclusive || $r->is_inclusive;
				}
			);
		$incl_tax        = 0;
		$net = $taxable;
		foreach ( $inclusive_rates as $r ) {
			$rate = (float) $r->rate;
			$tax         = $net - ( $net / ( 1 + $rate / 100 ) );
			$tax         = $round ? self::pos_round( $tax, 2 ) : $tax;
			$incl_tax   += $tax;
			$net         = $net - $tax;
			// Base AFTER extracting this rate (the net it applies to) —
			// the GST splitter derives CGST/SGST from it, never by
			// halving the already-rounded amount.
			$breakdown[] = array(
				'name'      => $r->name ?: $r->country_code,
				'rate'      => $rate,
				'amount'    => $tax,
				'base'      => $round ? self::pos_round( $net, 2 ) : $net,
				'inclusive' => 1,
				'compound'  => (int) $r->is_compound,
				'gst_split' => ! empty( $r->gst_split ) ? 1 : 0,
				'state_code' => $r->state_code ?: '',
			);
		}
			$running_taxable = $net;
			$total_tax      += $incl_tax;
		foreach ( $exclusive_rates as $r ) {
			$rate          = (float) $r->rate;
			$base_for_this = $r->is_compound ? ( $running_taxable + $total_tax ) : $running_taxable;
			$tax           = $round ? self::pos_round( $base_for_this * $rate / 100, 2 ) : ( $base_for_this * $rate / 100 );
			$total_tax    += $tax;
			$breakdown[]   = array(
				'name'      => $r->name ?: $r->country_code,
				'rate'      => $rate,
				'amount'    => $tax,
				'base'      => $round ? self::pos_round( $base_for_this, 2 ) : $base_for_this,
				'inclusive' => 0,
				'compound'  => (int) $r->is_compound,
				'gst_split' => ! empty( $r->gst_split ) ? 1 : 0,
				'state_code' => $r->state_code ?: '',
			);
		}
			$gross = $taxable + array_sum(
				array_column(
					array_filter(
						$breakdown,
						function ( $b ) {
							return ! $b['inclusive'];
						}
					),
					'amount'
				)
			);
			if ( ! $discount_before_tax && $discount_share > 0 ) {
				$gross = max( 0, $gross - $discount_share );
			}
		$split_res = self::apply_india_gst_split( $breakdown, $country, $state, $settings );
		$breakdown = $split_res['breakdown'];
		if ( $split_res['split_exclusive'] ) {
			// A stacked exclusive leg was split: recompute it from the
			// CGST/SGST components. Inclusive legs preserve their extracted
			// totals via the plug, so the MRP gross is untouched otherwise.
			$exclusive_sum = 0.0;
			foreach ( $breakdown as $split_b ) {
				if ( empty( $split_b['inclusive'] ) ) {
					$exclusive_sum += (float) $split_b['amount'];
				}
			}
			$exclusive_sum = self::pos_round( $exclusive_sum, 2 );
			$total_tax     = $incl_tax + $exclusive_sum;
			$gross         = $taxable + $exclusive_sum;
			if ( ! $discount_before_tax && $discount_share > 0 ) {
				$gross = max( 0, $gross - $discount_share );
			}
		}
		return array(
			'taxable'    => $round ? self::pos_round( $running_taxable, 2 ) : $running_taxable,
			'tax_amount' => $round ? self::pos_round( $total_tax, 2 ) : $total_tax,
			'gross'      => $round ? self::pos_round( $gross, 2 ) : $gross,
			'breakdown'  => $breakdown,
			'rate'       => null,
		);
		} else {
		foreach ( $rates as $r ) {
			$rate          = (float) $r->rate;
			$base_for_this = $r->is_compound ? ( $running_taxable + $total_tax ) : $running_taxable;
			$tax           = $round ? self::pos_round( $base_for_this * $rate / 100, 2 ) : ( $base_for_this * $rate / 100 );
			$total_tax    += $tax;
			$breakdown[]   = array(
				'name'      => $r->name ?: $r->country_code,
				'rate'      => $rate,
				'amount'    => $tax,
				'base'      => $round ? self::pos_round( $base_for_this, 2 ) : $base_for_this,
				'inclusive' => 0,
				'compound'  => (int) $r->is_compound,
				'gst_split' => ! empty( $r->gst_split ) ? 1 : 0,
				'state_code' => $r->state_code ?: '',
			);
		}
			$gross = $running_taxable + $total_tax;
			if ( ! $discount_before_tax && $discount_share > 0 ) {
				$gross = max( 0, $gross - $discount_share );
			}
		$split_res = self::apply_india_gst_split( $breakdown, $country, $state, $settings );
		$breakdown = $split_res['breakdown'];
		if ( $split_res['split'] ) {
			// Recompute the line from the CGST/SGST components so the
			// stored tax and line total match the displayed split.
			$total_tax = 0.0;
			foreach ( $breakdown as $split_b ) {
				$total_tax += (float) $split_b['amount'];
			}
			$total_tax = self::pos_round( $total_tax, 2 );
			$gross     = $running_taxable + $total_tax;
			if ( ! $discount_before_tax && $discount_share > 0 ) {
				$gross = max( 0, $gross - $discount_share );
			}
		}
		return array(
			'taxable'    => $round ? self::pos_round( $running_taxable, 2 ) : $running_taxable,
			'tax_amount' => $round ? self::pos_round( $total_tax, 2 ) : $total_tax,
			'gross'      => $round ? self::pos_round( $gross, 2 ) : $gross,
			'breakdown'  => $breakdown,
			'rate'       => $rates ? (float) $rates[0]->rate : 0,
		);
		}
	}

	/**
	 * Calculate order totals from lines.
	 *
	 * @param array  $lines each {price,qty,class_id}
	 * @param string $country
	 * @param string $state
	 * @param string $discount_type fixed|percent
	 * @param float  $discount_input
	 * @return array {subtotal, discount, tax, total, breakdown, lines: [calc]}
	 */
	public static function calculate_order( $lines, $country, $state, $discount_type = 'fixed', $discount_input = 0 ) {
		$settings = Simple_POS_Settings::get_all();
		$subtotal = 0;
		foreach ( $lines as $l ) {
			$subtotal += (float) $l['price'] * (int) $l['qty'];
		}
		$subtotal = self::pos_round( $subtotal, 2 );
		$discount = 0;
		if ( 'percent' === $discount_type ) {
			$discount = self::pos_round( $subtotal * min( 100, (float) $discount_input ) / 100, 2 );
		} else {
			$discount = self::pos_round( min( (float) $discount_input, $subtotal ), 2 );
		}
		$total_tax  = 0;
		$line_calcs = array();
		$rounding   = isset( $settings['tax_rounding'] ) ? $settings['tax_rounding'] : 'line';
		$round      = 'line' === $rounding;
		foreach ( $lines as $idx => $l ) {
			$line_base = (float) $l['price'] * (int) $l['qty'];
			$share     = $subtotal > 0 ? self::pos_round( $discount * ( $line_base / $subtotal ), 2 ) : 0;
			if ( $idx === count( $lines ) - 1 ) {
				$allocated = array_sum( array_column( $line_calcs, 'discount_share' ) );
				// Plug the last line so shares sum to the discount exactly
				// (rounded — never leave binary float dust in the totals).
				$share     = self::pos_round( $discount - $allocated, 2 );
			}
			$class_id               = isset( $l['class_id'] ) ? (int) $l['class_id'] : 0;
			$calc                   = self::calculate_line( (float) $l['price'], (int) $l['qty'], $class_id, $country, $state, $share, $settings, $round );
			$calc['discount_share'] = $share;
			$line_calcs[]           = $calc;
			$total_tax             += $calc['tax_amount'];
		}
		if ( ! $round ) {
			$total_tax_unrounded = $total_tax;
			$total_tax           = self::pos_round( $total_tax_unrounded, 2 );
			$delta               = $total_tax - $total_tax_unrounded;
			$last_idx            = count( $line_calcs ) - 1;
			if ( $last_idx >= 0 && $delta !== 0.0 ) {
				$line_calcs[ $last_idx ]['tax_amount'] = self::pos_round( $line_calcs[ $last_idx ]['tax_amount'] + $delta, 2 );
				$line_calcs[ $last_idx ]['gross']      = self::pos_round( $line_calcs[ $last_idx ]['gross'] + $delta, 2 );
				$last_bd                                = count( $line_calcs[ $last_idx ]['breakdown'] ) - 1;
				if ( $last_bd >= 0 ) {
					$line_calcs[ $last_idx ]['breakdown'][ $last_bd ]['amount'] = self::pos_round( $line_calcs[ $last_idx ]['breakdown'][ $last_bd ]['amount'] + $delta, 2 );
				}
			}
		}
		$total_tax = self::pos_round( $total_tax, 2 );
		$gross_sum = 0;
		foreach ( $line_calcs as $c ) {
			$gross_sum += $c['gross'];
		}
		$total = self::pos_round( $gross_sum, 2 );
		if ( $total < 0 ) {
			$total = 0;
		}
		$agg = array();
		foreach ( $line_calcs as $c ) {
			foreach ( $c['breakdown'] as $b ) {
				$key = $b['name'] . '|' . $b['rate'];
				if ( ! isset( $agg[ $key ] ) ) {
					$agg[ $key ] = array(
						'name'   => $b['name'],
						'rate'   => $b['rate'],
						'amount' => 0,
					);
				} $agg[ $key ]['amount'] += $b['amount']; }
		}
		foreach ( $agg as &$a ) {
			$a['amount'] = self::pos_round( $a['amount'], 2 );
		}
		return array(
			'subtotal'      => $subtotal,
			'discount'      => $discount,
			'discount_type' => $discount_type,
			'tax'           => self::pos_round( $total_tax, 2 ),
			'total'         => $total,
			'breakdown'     => array_values( $agg ),
			'lines'         => $line_calcs,
		);
	}
}
