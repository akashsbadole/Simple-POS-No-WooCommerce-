<?php
// phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- View template included inside a render method; locals are function-scoped, not globals.
/**
 * Shifts report screen.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : gmdate( 'Y-m-d', strtotime( '-7 days' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : gmdate( 'Y-m-d' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$summary   = Simple_POS_Stc_Clock::get_summary( $date_from, $date_to );
$shifts    = Simple_POS_Stc_Clock::get_shifts( null, 100 );
?>
<div class="wrap simple-pos-wrap">
	<div class="simple-pos-page-header">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Shifts', 'simple-pos' ); ?></h1>
	</div>

	<div class="simple-pos-card simple-pos-filter-bar">
		<form method="get" class="simple-pos-filters">
			<input type="hidden" name="page" value="simple-pos-shifts" />
			<div class="simple-pos-filter-field">
				<label for="shift-from"><?php esc_html_e( 'From', 'simple-pos' ); ?></label>
				<input id="shift-from" type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" />
			</div>
			<div class="simple-pos-filter-field">
				<label for="shift-to"><?php esc_html_e( 'To', 'simple-pos' ); ?></label>
				<input id="shift-to" type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" />
			</div>
			<div class="simple-pos-filter-actions">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'simple-pos' ); ?></button>
			</div>
		</form>
	</div>

	<div class="simple-pos-columns">
		<div class="simple-pos-col-main">
			<div class="simple-pos-card simple-pos-table-card">
				<div class="simple-pos-card-head"><h2 class="simple-pos-section-title"><?php esc_html_e( 'By cashier', 'simple-pos' ); ?></h2></div>
				<table class="wp-list-table widefat striped simple-pos-table">
					<thead><tr>
						<th><?php esc_html_e( 'Cashier', 'simple-pos' ); ?></th>
						<th class="num"><?php esc_html_e( 'Shifts', 'simple-pos' ); ?></th>
						<th class="num"><?php esc_html_e( 'Total hours', 'simple-pos' ); ?></th>
					</tr></thead>
					<tbody>
						<?php if ( empty( $summary ) ) : ?>
							<tr><td colspan="3" class="simple-pos-empty"><?php esc_html_e( 'No shifts in this range.', 'simple-pos' ); ?></td></tr>
						<?php endif; ?>
						<?php foreach ( $summary as $s ) :
							$user = get_userdata( $s->cashier_id );
							?>
							<tr>
								<td><?php echo esc_html( $user ? $user->display_name : 'User #' . $s->cashier_id ); ?></td>
								<td class="num"><?php echo esc_html( $s->shift_count ); ?></td>
								<td class="num"><?php echo esc_html( number_format( (float) $s->total_minutes / 60, 1 ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<div class="simple-pos-card simple-pos-table-card" style="margin-top:16px">
				<div class="simple-pos-card-head"><h2 class="simple-pos-section-title"><?php esc_html_e( 'Recent shifts', 'simple-pos' ); ?></h2></div>
				<table class="wp-list-table widefat striped simple-pos-table">
					<thead><tr>
						<th><?php esc_html_e( 'Cashier', 'simple-pos' ); ?></th>
						<th><?php esc_html_e( 'Clock in', 'simple-pos' ); ?></th>
						<th><?php esc_html_e( 'Clock out', 'simple-pos' ); ?></th>
						<th class="num"><?php esc_html_e( 'Duration', 'simple-pos' ); ?></th>
					</tr></thead>
					<tbody>
						<?php if ( empty( $shifts ) ) : ?>
							<tr><td colspan="4" class="simple-pos-empty"><?php esc_html_e( 'No shifts recorded.', 'simple-pos' ); ?></td></tr>
						<?php endif; ?>
						<?php foreach ( $shifts as $sh ) :
							$user = get_userdata( $sh->cashier_id );
							?>
							<tr>
								<td><?php echo esc_html( $user ? $user->display_name : 'User #' . $sh->cashier_id ); ?></td>
								<td><?php echo esc_html( mysql2date( 'M j, g:i a', $sh->clock_in ) ); ?></td>
								<td><?php echo $sh->clock_out ? esc_html( mysql2date( 'M j, g:i a', $sh->clock_out ) ) : '<span class="simple-pos-status simple-pos-status-completed">' . esc_html__( 'Active', 'simple-pos' ) . '</span>'; ?></td>
								<td class="num"><?php echo null !== $sh->duration_minutes ? esc_html( number_format( (float) $sh->duration_minutes / 60, 1 ) . ' h' ) : '—'; ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>