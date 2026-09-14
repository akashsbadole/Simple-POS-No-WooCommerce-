<?php
/**
 * Kitchen display screen: polls the REST API and shows pending orders.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$msg = isset( $_GET['skd_msg'] ) ? sanitize_key( $_GET['skd_msg'] ) : '';
?>
<div class="wrap simple-pos-wrap">
	<div class="simple-pos-page-header">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Kitchen Display', 'wp-pos-plugin' ); ?></h1>
	</div>
	<?php if ( 'bumped' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Order bumped.', 'wp-pos-plugin' ); ?></p></div>
	<?php endif; ?>

	<p class="simple-pos-muted" style="margin:0 0 16px"><?php esc_html_e( 'New sale items appear here automatically. Tap a card to bump the whole order to Ready.', 'wp-pos-plugin' ); ?></p>

	<div id="simple-pos-kitchen" class="simple-pos-kitchen"
		data-rest="<?php echo esc_url_raw( rest_url( 'simple-pos/v1' ) ); ?>"
		data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
		<div class="simple-pos-kitchen-grid">
			<div id="simple-pos-kitchen-pending" class="simple-pos-kitchen-panel">
				<h2><?php esc_html_e( 'To prepare', 'wp-pos-plugin' ); ?></h2>
			</div>
			<div id="simple-pos-kitchen-ready" class="simple-pos-kitchen-panel">
				<h2><?php esc_html_e( 'Ready', 'wp-pos-plugin' ); ?></h2>
			</div>
		</div>
	</div>

	<script>
	(function(){
		'use strict';
		var wrap = document.getElementById('simple-pos-kitchen');
		if(!wrap) return;
		var rest = wrap.getAttribute('data-rest');
		var nonce = wrap.getAttribute('data-nonce');

		function card(order, ready){
			var box = document.createElement('div');
			box.className = 'simple-pos-kitchen-card' + (ready ? ' is-ready' : '');
			var html = '<div class="simple-pos-kitchen-number">'+escapeHtml(order.sale_number)+'</div>';
			html += '<div class="simple-pos-kitchen-items">';
			order.items.forEach(function(it){
				html += '<div class="simple-pos-kitchen-item"><span>'+escapeHtml(it.product_name)+'</span><strong>×'+it.qty+'</strong></div>';
			});
			html += '</div>';
			if(!ready){
				html += '<button type="button" class="simple-pos-kitchen-bump">'+'<?php echo esc_js( __( 'Bump to Ready', 'wp-pos-plugin' ) ); ?>'+'</button>';
			}
			box.innerHTML = html;
			var bump = box.querySelector('.simple-pos-kitchen-bump');
			if(bump){
				bump.addEventListener('click', function(){
					fetch(rest + '/kitchen/orders/' + order.sale_id + '/bump', {
						method: 'POST',
						headers: { 'X-WP-Nonce': nonce }
					}).then(function(){ refresh(); });
				});
			}
			return box;
		}

		function fill(id, orders, ready){
			var el = document.getElementById(id);
			var msg = el.querySelector('.simple-pos-kitchen-empty');
			var wrap = document.createElement('div');
			if(!orders.length){
				wrap.innerHTML = '<p class="simple-pos-kitchen-empty">'+'<?php echo esc_js( __( 'Nothing here.', 'wp-pos-plugin' ) ); ?>'+'</p>';
			} else {
				orders.forEach(function(o){ wrap.appendChild(card(o, ready)); });
			}
			el.replaceChild(wrap, el.lastChild);
		}

		function refresh(){
			fetch(rest + '/kitchen/orders?status=pending', { headers: { 'X-WP-Nonce': nonce } })
				.then(function(r){ return r.json(); })
				.then(function(list){ fill('simple-pos-kitchen-pending', list, false); });
			fetch(rest + '/kitchen/orders?status=ready', { headers: { 'X-WP-Nonce': nonce } })
				.then(function(r){ return r.json(); })
				.then(function(list){ fill('simple-pos-kitchen-ready', list, true); });
		}

		function escapeHtml(s){
			return String(s == null ? '' : s).replace(/[&<>"']/g, function(c){
				return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c];
			});
		}

		// Prime the two panels before polling.
		['simple-pos-kitchen-pending','simple-pos-kitchen-ready'].forEach(function(id){
			var el = document.getElementById(id);
			var box = document.createElement('div');
			box.className = 'simple-pos-kitchen-card is-loading';
			box.innerHTML = '<p class="simple-pos-kitchen-empty">'+'<?php echo esc_js( __( 'Loading…', 'wp-pos-plugin' ) ); ?>'+'</p>';
			el.appendChild(box);
		});

		refresh();
		setInterval(refresh, 8000);
	})();
	</script>
</div>