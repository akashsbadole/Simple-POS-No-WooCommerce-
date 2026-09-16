<?php
/**
 * Time clock: tracks cashier clock-in/out shifts.
 * One open shift per cashier at a time; a new clock-in auto-closes the prior shift.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class STC_Clock {

	const TABLE_SUFFIX = 'clock_shifts';

	public static function ensure_schema() {
		global $wpdb;
		static $done = false;
		if ( $done ) {
			return;
		}
		$done    = true;
		$prefix  = $wpdb->prefix . SIMPLE_POS_TABLE_PREFIX;
		$charset = $wpdb->get_charset_collate();
		$wpdb->query( "CREATE TABLE IF NOT EXISTS `{$prefix}" . self::TABLE_SUFFIX . "` ( id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, cashier_id BIGINT UNSIGNED NOT NULL, clock_in DATETIME NOT NULL, clock_out DATETIME NULL, duration_minutes INT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_cashier (cashier_id) ) {$charset}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Get the currently-open shift for a cashier, or null.
	 *
	 * @param int $user_id
	 * @return object|null
	 */
	public static function open_shift( $user_id ) {
		global $wpdb;
		self::ensure_schema();
		$table = Simple_POS_DB::table( self::TABLE_SUFFIX );
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE cashier_id = %d AND clock_out IS NULL LIMIT 1", $user_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Clock the cashier in. Auto-closes any prior open shift.
	 *
	 * @param int $user_id
	 * @return int New shift ID.
	 */
	public static function clock_in( $user_id ) {
		global $wpdb;
		self::ensure_schema();
		$table = Simple_POS_DB::table( self::TABLE_SUFFIX );
		$now   = current_time( 'mysql' );

		// Close any prior open shift first.
		$prior = self::open_shift( $user_id );
		if ( $prior ) {
			self::close_shift( (int) $prior->id );
		}

		$wpdb->insert(
			$table,
			array(
				'cashier_id' => $user_id,
				'clock_in'   => $now,
				'created_at' => $now,
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->insert_id;
	}

	/**
	 * Close the open shift.
	 *
	 * @param int $shift_id
	 */
	public static function clock_out( $shift_id ) {
		global $wpdb;
		self::ensure_schema();
		self::close_shift( $shift_id );
		return true;
	}

	private static function close_shift( $shift_id ) {
		global $wpdb;
		$table  = Simple_POS_DB::table( self::TABLE_SUFFIX );
		$shift  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $shift_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $shift || null !== $shift->clock_out ) {
			return;
		}
		$now = current_time( 'mysql' );
		$mins = (int) ( ( strtotime( $now ) - strtotime( $shift->clock_in ) ) / 60 );
		$wpdb->update(
			$table,
			array(
				'clock_out'         => $now,
				'duration_minutes'  => max( 0, $mins ),
			),
			array( 'id' => (int) $shift_id )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * @param int|null $user_id
	 * @param int      $limit
	 * @return object[]
	 */
	public static function get_shifts( $user_id = null, $limit = 100 ) {
		global $wpdb;
		self::ensure_schema();
		$table = Simple_POS_DB::table( self::TABLE_SUFFIX );
		if ( $user_id ) {
			$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE cashier_id = %d ORDER BY created_at DESC LIMIT %d", $user_id, $limit ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$sql = $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", $limit ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		return $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Get summary of shifts within a date range.
	 *
	 * @param string $date_from Y-m-d
	 * @param string $date_to   Y-m-d
	 * @return object[]
	 */
	public static function get_summary( $date_from = '', $date_to = '' ) {
		global $wpdb;
		self::ensure_schema();
		$table  = Simple_POS_DB::table( self::TABLE_SUFFIX );
		$where  = array( '1=1' );
		$params = array();

		if ( $date_from ) {
			$where[]  = 'clock_in >= %s';
			$params[] = $date_from . ' 00:00:00';
		}
		if ( $date_to ) {
			$where[]  = 'clock_in <= %s';
			$params[] = $date_to . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where );
		$prepared  = $wpdb->prepare( "SELECT cashier_id, COUNT(*) as shift_count, COALESCE(SUM(duration_minutes),0) as total_minutes FROM {$table} WHERE {$where_sql} GROUP BY cashier_id ORDER BY total_minutes DESC", ...$params ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $prepared ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	// --- REST ---

	public static function rest_clock_in( $request ) {
		$user_id = get_current_user_id();
		$shift_id = self::clock_in( $user_id );
		$shift = self::open_shift( $user_id );
		return new WP_REST_Response(
			array(
				'ok'      => true,
				'shift'   => $shift,
				'message' => __( 'You are clocked in.', 'wp-pos-plugin' ),
			),
			200
		);
	}

	public static function rest_clock_out( $request ) {
		$user_id = get_current_user_id();
		$shift   = self::open_shift( $user_id );
		if ( ! $shift ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'No open shift found.', 'wp-pos-plugin' ) ), 200 );
		}
		self::clock_out( (int) $shift->id );
		$minutes = (int) $shift->duration_minutes;
		return new WP_REST_Response(
			array(
				'ok'      => true,
				'message' => sprintf(
					/* translators: %d: minutes worked. */
					__( 'Clock-out recorded (%d min).', 'wp-pos-plugin' ),
					$minutes
				),
			),
			200
		);
	}

	public static function rest_status( $request ) {
		$user_id  = get_current_user_id();
		$shift    = self::open_shift( $user_id );
		$clocked  = null !== $shift;
		return new WP_REST_Response(
			array(
				'clocked_in' => $clocked,
				'shift'      => $shift,
			),
			200
		);
	}
}