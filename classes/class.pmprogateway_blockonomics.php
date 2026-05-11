<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PMProGateway_blockonomics extends PMProGateway {

	const API_BASE     = 'https://www.blockonomics.co';
	const SATOSHIS_PER_BTC = 100000000;

	public function __construct( $gateway = null ) {
		$this->gateway             = 'blockonomics';
		$this->gateway_display_name = __( 'Bitcoin (Blockonomics)', 'pmpro-blockonomics' );
		$this->gateway_nickname     = __( 'Bitcoin', 'pmpro-blockonomics' );
		$this->supports             = [ 'subscription_cancellation' => false ];

		parent::__construct( $gateway );

		if ( is_admin() ) {
			add_filter( 'pmpro_payment_options', [ $this, 'pmpro_payment_options' ] );
			add_filter( 'pmpro_payment_option_fields', [ $this, 'pmpro_payment_option_fields' ], 10, 2 );
			add_action( 'pmpro_save_payment_settings', [ $this, 'pmpro_save_payment_option_fields' ] );
		}
	}

	// -------------------------------------------------------------------------
	// Admin settings
	// -------------------------------------------------------------------------

	public static function getGatewayOptions() {
		return [
			'sslseal',
			'nuclear_HTTPS',
			'gateway_environment',
			'blockonomics_api_key',
			'blockonomics_confirmations',
		];
	}

	public function pmpro_payment_options( $options ) {
		$gateway_options = self::getGatewayOptions();
		return array_merge( $options, $gateway_options );
	}

	public function pmpro_payment_option_fields( $values, $gateway ) {
		if ( $gateway !== 'blockonomics' ) {
			return;
		}
		?>
		<tr class="pmpro_settings_divider gateway gateway_blockonomics" <?php if ( $gateway !== 'blockonomics' ) { ?>style="display: none;"<?php } ?>>
			<td colspan="2">
				<hr />
				<h2 class="title"><?php esc_html_e( 'Blockonomics Bitcoin Settings', 'pmpro-blockonomics' ); ?></h2>
			</td>
		</tr>

		<tr class="gateway gateway_blockonomics" <?php if ( $gateway !== 'blockonomics' ) { ?>style="display: none;"<?php } ?>>
			<th scope="row" valign="top">
				<label for="blockonomics_api_key"><?php esc_html_e( 'API Key', 'pmpro-blockonomics' ); ?></label>
			</th>
			<td>
				<input id="blockonomics_api_key" name="blockonomics_api_key" type="text" class="regular-text"
					value="<?php echo esc_attr( pmpro_getOption( 'blockonomics_api_key' ) ); ?>" />
				<p class="description">
					<?php
					printf(
						wp_kses(
							/* translators: %s: Blockonomics URL */
							__( 'Get your API key from <a href="%s" target="_blank">Blockonomics dashboard</a>. Set the callback URL to: %s', 'pmpro-blockonomics' ),
							[ 'a' => [ 'href' => [], 'target' => [] ] ]
						),
						'https://www.blockonomics.co/#/merchants',
						'<code>' . esc_html( self::get_callback_url() ) . '</code>'
					);
					?>
				</p>
			</td>
		</tr>

		<tr class="gateway gateway_blockonomics" <?php if ( $gateway !== 'blockonomics' ) { ?>style="display: none;"<?php } ?>>
			<th scope="row" valign="top">
				<label for="blockonomics_confirmations"><?php esc_html_e( 'Confirmations Required', 'pmpro-blockonomics' ); ?></label>
			</th>
			<td>
				<select id="blockonomics_confirmations" name="blockonomics_confirmations">
					<option value="0" <?php selected( pmpro_getOption( 'blockonomics_confirmations' ), '0' ); ?>><?php esc_html_e( '0 — Instant (unconfirmed)', 'pmpro-blockonomics' ); ?></option>
					<option value="1" <?php selected( pmpro_getOption( 'blockonomics_confirmations' ), '1' ); ?>><?php esc_html_e( '1 — Low risk', 'pmpro-blockonomics' ); ?></option>
					<option value="2" <?php selected( pmpro_getOption( 'blockonomics_confirmations' ), '2' ); ?>><?php esc_html_e( '2 — Standard (recommended)', 'pmpro-blockonomics' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'Number of blockchain confirmations before the membership is activated. 0 = activate on first seen (less secure).', 'pmpro-blockonomics' ); ?></p>
			</td>
		</tr>
		<?php
	}

	public function pmpro_save_payment_option_fields() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$api_key       = isset( $_POST['blockonomics_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['blockonomics_api_key'] ) ) : '';
		$confirmations = isset( $_POST['blockonomics_confirmations'] ) ? absint( $_POST['blockonomics_confirmations'] ) : 2;

		pmpro_setOption( 'blockonomics_api_key', $api_key );
		pmpro_setOption( 'blockonomics_confirmations', (string) $confirmations );
	}

	// -------------------------------------------------------------------------
	// Checkout process
	// -------------------------------------------------------------------------

	public function process( &$order ) {
		$api_key = pmpro_getOption( 'blockonomics_api_key' );

		if ( empty( $api_key ) ) {
			$order->error = __( 'Blockonomics API key is not configured.', 'pmpro-blockonomics' );
			return false;
		}

		$btc_price = $this->get_btc_price( $order->currency ?: get_option( 'pmpro_currency', 'USD' ) );
		if ( ! $btc_price ) {
			$order->error = __( 'Could not retrieve the current Bitcoin price. Please try again.', 'pmpro-blockonomics' );
			return false;
		}

		$btc_amount = round( $order->subtotal / $btc_price, 8 );
		$address    = $this->get_new_address( $api_key );

		if ( ! $address ) {
			$order->error = __( 'Could not generate a Bitcoin address. Please check your Blockonomics API key.', 'pmpro-blockonomics' );
			return false;
		}

		// Store payment details in order meta.
		update_option( 'pmpro_blockonomics_order_' . $address, [
			'order_id'   => $order->id,
			'btc_amount' => $btc_amount,
			'currency'   => $order->currency ?: get_option( 'pmpro_currency', 'USD' ),
			'fiat_amount'=> $order->subtotal,
			'created'    => time(),
			'status'     => 'pending',
		] );

		// Save to order so the confirmation page can display it.
		$order->payment_transaction_id = $address;

		$order->saveOrder();

		// Redirect to the Bitcoin payment page.
		$redirect_url = add_query_arg( [
			'pmpro-blockonomics' => 'pay',
			'address'            => rawurlencode( $address ),
			'amount'             => $btc_amount,
			'order_id'           => $order->id,
		], pmpro_url( 'checkout' ) );

		wp_safe_redirect( $redirect_url );
		exit;
	}

	public function charge( &$order ) {
		// Bitcoin payments are always initiated via process(); charge is N/A.
		return false;
	}

	public function cancel( &$order ) {
		$order->updateStatus( 'cancelled' );
		return true;
	}

	// -------------------------------------------------------------------------
	// Payment waiting page
	// -------------------------------------------------------------------------

	public static function render_payment_page() {
		if ( empty( $_GET['pmpro-blockonomics'] ) || $_GET['pmpro-blockonomics'] !== 'pay' ) {
			return;
		}

		$address  = isset( $_GET['address'] )  ? sanitize_text_field( wp_unslash( $_GET['address'] ) )  : '';
		$amount   = isset( $_GET['amount'] )   ? (float) $_GET['amount']                                  : 0;
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] )                              : 0;

		if ( ! $address || ! $amount || ! $order_id ) {
			wp_die( esc_html__( 'Invalid payment parameters.', 'pmpro-blockonomics' ) );
		}

		$btc_uri    = 'bitcoin:' . $address . '?amount=' . number_format( $amount, 8, '.', '' );
		$qr_url     = 'https://chart.googleapis.com/chart?chs=250x250&cht=qr&chl=' . rawurlencode( $btc_uri );
		$block_link = 'https://www.blockonomics.co/#/search?q=' . rawurlencode( $address );

		// Determine confirmation requirement label.
		$confirmations = (int) pmpro_getOption( 'blockonomics_confirmations' );
		$conf_label    = $confirmations === 0
			? __( 'Payment will be detected immediately on the blockchain.', 'pmpro-blockonomics' )
			: sprintf(
				_n(
					'Membership activates after %d confirmation.',
					'Membership activates after %d confirmations.',
					$confirmations,
					'pmpro-blockonomics'
				),
				$confirmations
			);

		// Flush any existing output and render standalone payment page.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		get_header();
		?>
		<div id="pmpro-blockonomics-payment" class="pmpro_content_wrap">
			<h2><?php esc_html_e( 'Pay with Bitcoin', 'pmpro-blockonomics' ); ?></h2>

			<p class="pmpro-blockonomics-instructions">
				<?php esc_html_e( 'Send the exact amount below to the Bitcoin address to complete your membership.', 'pmpro-blockonomics' ); ?>
			</p>

			<div class="pmpro-blockonomics-amount">
				<strong><?php echo esc_html( number_format( $amount, 8 ) ); ?> BTC</strong>
			</div>

			<div class="pmpro-blockonomics-qr">
				<img src="<?php echo esc_url( $qr_url ); ?>" alt="<?php esc_attr_e( 'Bitcoin payment QR code', 'pmpro-blockonomics' ); ?>" width="250" height="250" />
			</div>

			<div class="pmpro-blockonomics-address">
				<label><?php esc_html_e( 'Bitcoin Address', 'pmpro-blockonomics' ); ?></label>
				<input type="text" value="<?php echo esc_attr( $address ); ?>" readonly id="pmpro-blockonomics-addr" />
				<button type="button" onclick="navigator.clipboard.writeText(document.getElementById('pmpro-blockonomics-addr').value)">
					<?php esc_html_e( 'Copy', 'pmpro-blockonomics' ); ?>
				</button>
			</div>

			<p class="pmpro-blockonomics-conf-note"><?php echo esc_html( $conf_label ); ?></p>

			<p class="pmpro-blockonomics-waiting" id="pmpro-blockonomics-status">
				<?php esc_html_e( 'Waiting for payment…', 'pmpro-blockonomics' ); ?>
			</p>

			<p>
				<a href="<?php echo esc_url( $block_link ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Track this address on Blockonomics', 'pmpro-blockonomics' ); ?>
				</a>
			</p>
		</div>

		<script>
		(function() {
			var orderId  = <?php echo absint( $order_id ); ?>;
			var address  = <?php echo wp_json_encode( $address ); ?>;
			var statusEl = document.getElementById( 'pmpro-blockonomics-status' );
			var pollUrl  = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;

			function pollStatus() {
				var xhr = new XMLHttpRequest();
				xhr.open( 'POST', pollUrl, true );
				xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
				xhr.onload = function() {
					if ( xhr.status === 200 ) {
						try {
							var data = JSON.parse( xhr.responseText );
							if ( data.status === 'confirmed' ) {
								statusEl.textContent = <?php echo wp_json_encode( __( 'Payment confirmed! Activating membership…', 'pmpro-blockonomics' ) ); ?>;
								window.location.href = data.redirect;
								return;
							}
							if ( data.status === 'seen' ) {
								statusEl.textContent = <?php echo wp_json_encode( __( 'Payment detected, waiting for confirmations…', 'pmpro-blockonomics' ) ); ?>;
							}
						} catch (e) {}
					}
					setTimeout( pollStatus, 15000 );
				};
				xhr.onerror = function() { setTimeout( pollStatus, 30000 ); };
				xhr.send( 'action=pmpro_blockonomics_check_status&order_id=' + orderId + '&address=' + encodeURIComponent( address ) + '&_ajax_nonce=' + <?php echo wp_json_encode( wp_create_nonce( 'pmpro_blockonomics_check' ) ); ?> );
			}

			setTimeout( pollStatus, 10000 );
		})();
		</script>
		<?php
		get_footer();
		exit;
	}

	// -------------------------------------------------------------------------
	// Blockonomics callback handler (called directly from parse_request)
	// -------------------------------------------------------------------------

	public static function handle_callback() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$addr   = isset( $_GET['addr'] )   ? sanitize_text_field( wp_unslash( $_GET['addr'] ) )   : '';
		$status = isset( $_GET['status'] ) ? (int) $_GET['status']                                  : -1;
		$value  = isset( $_GET['value'] )  ? absint( $_GET['value'] )                               : 0; // satoshis
		$txid   = isset( $_GET['txid'] )   ? sanitize_text_field( wp_unslash( $_GET['txid'] ) )    : '';
		$rbf    = isset( $_GET['rbf'] )    ? (int) $_GET['rbf']                                     : 0;
		// phpcs:enable

		if ( ! $addr ) {
			status_header( 400 );
			echo 'Missing addr parameter.';
			return;
		}

		$data = get_option( 'pmpro_blockonomics_order_' . $addr );
		if ( ! $data ) {
			status_header( 404 );
			echo 'Order not found for address.';
			return;
		}

		$required_conf = (int) pmpro_getOption( 'blockonomics_confirmations' );

		if ( $status === 0 ) {
			// Unconfirmed — update status.
			$data['status'] = 'seen';
			$data['txid']   = $txid;
			update_option( 'pmpro_blockonomics_order_' . $addr, $data );

			if ( $required_conf === 0 ) {
				self::complete_order( $data, $addr, $value, $txid );
			}
		} elseif ( $status >= 1 ) {
			// Confirmed (status 1 = 1+ conf, status 2 = fully confirmed).
			// Only complete once; ignore replayed callbacks.
			if ( $data['status'] === 'confirmed' ) {
				status_header( 200 );
				echo 'Already processed.';
				return;
			}

			// Verify amount paid (allow 1% underpayment tolerance for exchange fluctuation).
			$expected_satoshis = (int) round( $data['btc_amount'] * self::SATOSHIS_PER_BTC );
			if ( $value < $expected_satoshis * 0.99 ) {
				$data['status'] = 'underpaid';
				$data['paid']   = $value;
				update_option( 'pmpro_blockonomics_order_' . $addr, $data );
				status_header( 200 );
				echo 'Underpaid.';
				return;
			}

			if ( $status >= $required_conf || ( $status >= 1 && $required_conf <= 1 ) ) {
				self::complete_order( $data, $addr, $value, $txid );
			}
		}

		status_header( 200 );
		echo 'OK';
	}

	private static function complete_order( array $data, string $addr, int $value, string $txid ) {
		require_once PMPRO_DIR . '/classes/class.memberorder.php';

		$order = new MemberOrder( $data['order_id'] );
		if ( ! $order || ! $order->id ) {
			return;
		}

		if ( $order->status === 'success' ) {
			return; // Idempotency guard.
		}

		$order->payment_transaction_id = $txid ?: $addr;
		$order->saveOrder();

		$data['status'] = 'confirmed';
		$data['txid']   = $txid;
		$data['paid']   = $value;
		update_option( 'pmpro_blockonomics_order_' . $addr, $data );

		// Activate the membership.
		pmpro_complete_async_checkout( $order );
	}

	// -------------------------------------------------------------------------
	// AJAX: poll order status from the payment waiting page
	// -------------------------------------------------------------------------

	public static function ajax_check_status() {
		check_ajax_referer( 'pmpro_blockonomics_check' );

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$address  = sanitize_text_field( wp_unslash( $_POST['address'] ?? '' ) );

		if ( ! $order_id || ! $address ) {
			wp_send_json_error( 'Bad params' );
		}

		$data = get_option( 'pmpro_blockonomics_order_' . $address );

		if ( ! $data || (int) $data['order_id'] !== $order_id ) {
			wp_send_json_error( 'Not found' );
		}

		$order = new MemberOrder( $order_id );

		if ( $data['status'] === 'confirmed' || ( $order && $order->status === 'success' ) ) {
			wp_send_json( [
				'status'   => 'confirmed',
				'redirect' => pmpro_url( 'confirmation', '?level=' . absint( $order->membership_id ) ),
			] );
		}

		wp_send_json( [ 'status' => $data['status'] ?? 'pending' ] );
	}

	// -------------------------------------------------------------------------
	// Blockonomics API helpers
	// -------------------------------------------------------------------------

	private function get_new_address( string $api_key ): ?string {
		$callback_url = self::get_callback_url();

		$response = wp_remote_post(
			self::API_BASE . '/api/new_address',
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $api_key,
				],
				'body'    => [ 'match_callback' => $callback_url ],
				'timeout' => 15,
			]
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return $body['address'] ?? null;
	}

	private function get_btc_price( string $currency = 'USD' ): ?float {
		$cached = get_transient( 'pmpro_blockonomics_btc_price_' . $currency );
		if ( $cached ) {
			return (float) $cached;
		}

		$response = wp_remote_get(
			self::API_BASE . '/api/price?currency=' . rawurlencode( $currency ),
			[ 'timeout' => 10 ]
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$body  = json_decode( wp_remote_retrieve_body( $response ), true );
		$price = $body['price'] ?? null;

		if ( $price ) {
			set_transient( 'pmpro_blockonomics_btc_price_' . $currency, $price, 5 * MINUTE_IN_SECONDS );
		}

		return $price ? (float) $price : null;
	}

	public static function get_callback_url(): string {
		return add_query_arg( 'pmpro-blockonomics', 'callback', home_url( '/' ) );
	}
}

// Register AJAX hooks here — inside class file so they load once.
add_action( 'wp_ajax_pmpro_blockonomics_check_status',        [ 'PMProGateway_blockonomics', 'ajax_check_status' ] );
add_action( 'wp_ajax_nopriv_pmpro_blockonomics_check_status', [ 'PMProGateway_blockonomics', 'ajax_check_status' ] );

// Hook into the checkout page parse to render the payment waiting view.
add_action( 'pmpro_checkout_before_change_membership_level', [ 'PMProGateway_blockonomics', 'render_payment_page' ] );
