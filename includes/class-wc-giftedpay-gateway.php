<?php
if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

class WC_GiftedPay_Gateway extends WC_Payment_Gateway {

        /** @var string */
        public $api_key;

        /** @var string */
        public $receipt_link;

        public function __construct() {
                $this->id                 = 'giftedpay';
                $this->icon               = GIFTEDPAY_PLUGIN_URL . 'assets/images/mpesa-logo.png';
                $this->has_fields         = true;
                $this->method_title       = 'GiftedPay M-Pesa';
                $this->method_description = 'Accept M-Pesa STK Push payments via GiftedPay. Supports Till, Paybill, Bank, and B2C apps. <a href="https://pay.gifted.co.ke" target="_blank">Sign up for free →</a>';
                $this->supports           = [ 'products', 'refunds' ];

                $this->init_form_fields();
                $this->init_settings();

                $this->title        = $this->get_option( 'title' );
                $this->description  = $this->get_option( 'description' );
                $this->api_key      = $this->get_option( 'api_key' );
                $this->receipt_link = $this->get_option( 'receipt_link', 'yes' );

                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
                add_action( 'woocommerce_api_giftedpay_callback', [ $this, 'handle_callback' ] );
                add_action( 'woocommerce_thankyou_' . $this->id, [ $this, 'thankyou_page' ] );
                add_action( 'woocommerce_email_before_order_table', [ $this, 'email_receipt_link' ], 10, 3 );
        }

        // ─────────────────────────────────────────────
        // Admin Settings
        // ─────────────────────────────────────────────

        public function init_form_fields() {
                $this->form_fields = [
                        'enabled' => [
                                'title'   => 'Enable / Disable',
                                'type'    => 'checkbox',
                                'label'   => 'Enable GiftedPay M-Pesa payments',
                                'default' => 'yes',
                        ],
                        'api_key' => [
                                'title'       => 'GiftedPay API Key',
                                'type'        => 'password',
                                'description' => 'Your only required credential. Found in GiftedPay Dashboard → Apps → click your app. Starts with <code>gifted_mpesa_stk_</code>. <br><small style="color:#00a651">✓ The callback URL is set automatically — no need to configure it in your GiftedPay app settings.</small>',
                                'default'     => '',
                                'placeholder' => 'gifted_mpesa_stk_...',
                        ],
                        'title' => [
                                'title'       => 'Payment Method Title',
                                'type'        => 'text',
                                'description' => 'Name shown to customers at checkout.',
                                'default'     => 'M-Pesa (via GiftedPay)',
                                'desc_tip'    => true,
                        ],
                        'description' => [
                                'title'       => 'Description',
                                'type'        => 'textarea',
                                'description' => 'Brief message shown under the payment method at checkout.',
                                'default'     => 'Pay securely with M-Pesa. Enter your Safaricom number and confirm the STK push prompt on your phone.',
                                'desc_tip'    => true,
                        ],
                        'phone_label' => [
                                'title'   => 'Phone Field Label',
                                'type'    => 'text',
                                'default' => 'M-Pesa Phone Number',
                        ],
                        'phone_placeholder' => [
                                'title'   => 'Phone Field Placeholder',
                                'type'    => 'text',
                                'default' => 'e.g. 0712 345 678',
                        ],
                        'receipt_link' => [
                                'title'   => 'Show Receipt Link',
                                'type'    => 'checkbox',
                                'label'   => 'Show a GiftedPay receipt link on the thank-you page and in order emails.',
                                'default' => 'yes',
                        ],
                        'polling_interval' => [
                                'title'       => 'Polling Interval (seconds)',
                                'type'        => 'number',
                                'description' => 'How often the thank-you page checks payment status.',
                                'default'     => '4',
                                'custom_attributes' => [ 'min' => '3', 'max' => '15' ],
                                'desc_tip'    => true,
                        ],
                        'polling_timeout' => [
                                'title'       => 'Polling Timeout (seconds)',
                                'type'        => 'number',
                                'description' => 'Stop polling after this many seconds (max 3 minutes recommended).',
                                'default'     => '150',
                                'custom_attributes' => [ 'min' => '60', 'max' => '300' ],
                                'desc_tip'    => true,
                        ],
                        'test_connection' => [
                                'title'       => 'Connection Test',
                                'type'        => 'title',
                                'description' => $this->connection_test_html(),
                        ],
                ];
        }

        private function connection_test_html() {
                if ( ! $this->api_key ) {
                        return '<span style="color:#888">Enter your API key above and save to test the connection.</span>';
                }
                $response = wp_remote_post( GIFTEDPAY_API_BASE . '/payments/verify', [
                        'headers' => [
                                'Content-Type'  => 'application/json',
                                'Authorization' => 'Bearer ' . $this->api_key,
                        ],
                        'body'    => json_encode( [ 'checkoutRequestId' => 'test_connection_probe' ] ),
                        'timeout' => 8,
                ] );
                if ( is_wp_error( $response ) ) {
                        return '<span style="color:#cc0000">⚠ Could not reach GiftedPay API: ' . esc_html( $response->get_error_message() ) . '</span>';
                }
                $code = wp_remote_retrieve_response_code( $response );
                // 200 or 404 both mean the key is valid (404 = transaction not found, which is expected for a probe)
                if ( in_array( $code, [ 200, 404 ], true ) ) {
                        return '<span style="color:#0a7a0a">✓ API key is valid — connected to GiftedPay successfully.</span>';
                }
                if ( $code === 401 ) {
                        return '<span style="color:#cc0000">✗ Invalid API key — please copy it again from your GiftedPay dashboard.</span>';
                }
                if ( $code === 403 ) {
                        return '<span style="color:#cc0000">✗ Access denied — your app may be disabled. Check your GiftedPay dashboard.</span>';
                }
                return '<span style="color:#cc0000">✗ Unexpected response (HTTP ' . $code . '). Check that your site can reach mpesa.gifted.co.ke.</span>';
        }

        // ─────────────────────────────────────────────
        // Checkout Fields
        // ─────────────────────────────────────────────

        public function payment_fields() {
                $description = $this->get_description();
                if ( $description ) {
                        echo '<p class="giftedpay-desc">' . wp_kses_post( $description ) . '</p>';
                }

                $label       = esc_html( $this->get_option( 'phone_label', 'M-Pesa Phone Number' ) );
                $placeholder = esc_attr( $this->get_option( 'phone_placeholder', 'e.g. 0712 345 678' ) );

                echo '<div class="giftedpay-field-wrap">';
                echo '<label for="giftedpay_phone">' . $label . ' <abbr title="required">*</abbr></label>';
                echo '<input type="tel" id="giftedpay_phone" name="giftedpay_phone" placeholder="' . $placeholder . '" autocomplete="tel" class="giftedpay-phone-input" />';
                echo '<p class="giftedpay-hint">You will receive an M-Pesa prompt on your phone. Enter your PIN to complete payment.</p>';
                echo '</div>';

                echo '<div id="giftedpay-mpesa-logo-wrap"><span class="giftedpay-powered">Powered by <strong>GiftedPay</strong> &amp; M-Pesa</span></div>';
        }

        public function validate_fields() {
                $phone = sanitize_text_field( $_POST['giftedpay_phone'] ?? '' );
                if ( empty( $phone ) ) {
                        wc_add_notice( 'Please enter your M-Pesa phone number.', 'error' );
                        return false;
                }
                $normalized = $this->normalize_phone( $phone );
                if ( ! $normalized ) {
                        wc_add_notice( 'Please enter a valid Safaricom number (e.g. 0712 345 678).', 'error' );
                        return false;
                }
                return true;
        }

        // ─────────────────────────────────────────────
        // Process Payment
        // ─────────────────────────────────────────────

        public function process_payment( $order_id ) {
                $order = wc_get_order( $order_id );
                $phone = sanitize_text_field( $_POST['giftedpay_phone'] ?? '' );
                $norm  = $this->normalize_phone( $phone );

                if ( ! $norm ) {
                        wc_add_notice( 'Invalid phone number. Please try again.', 'error' );
                        return [ 'result' => 'fail' ];
                }

                $amount      = (int) round( $order->get_total() );
                $ref         = 'Order-' . $order_id;
                $desc        = get_bloginfo( 'name' ) . ' Order #' . $order_id;
                $callback    = home_url( '/?wc-api=giftedpay_callback' );

                $payload = [
                        'phone_number'     => $norm,
                        'amount'           => $amount,
                        'account_reference'=> $ref,
                        'transaction_desc' => $desc,
                        'callback_url'     => $callback,
                ];

                $response = wp_remote_post( GIFTEDPAY_API_BASE . '/payments/process', [
                        'headers' => [
                                'Content-Type'  => 'application/json',
                                'Authorization' => 'Bearer ' . $this->api_key,
                        ],
                        'body'    => json_encode( $payload ),
                        'timeout' => 30,
                ] );

                if ( is_wp_error( $response ) ) {
                        wc_add_notice( 'Payment initiation failed: ' . $response->get_error_message() . '. Please try again.', 'error' );
                        return [ 'result' => 'fail' ];
                }

                $http_code = wp_remote_retrieve_response_code( $response );
                $body      = json_decode( wp_remote_retrieve_body( $response ), true );

                if ( ! ( $body['success'] ?? false ) ) {
                        $msg = $body['message'] ?? $body['detail'] ?? 'Unknown error. Please try again.';
                        wc_add_notice( 'GiftedPay: ' . esc_html( $msg ), 'error' );
                        return [ 'result' => 'fail' ];
                }

                $checkout_id = $body['checkout_request_id'] ?? '';

                // Store on order for later verification
                $order->update_meta_data( '_giftedpay_checkout_id', $checkout_id );
                $order->update_meta_data( '_giftedpay_phone', $norm );
                $order->update_meta_data( '_giftedpay_amount', $amount );
                $order->update_status( 'pending', 'GiftedPay: Awaiting M-Pesa STK Push confirmation.' );
                $order->save();

                $redirect = add_query_arg(
                        [ 'giftedpay_cid' => urlencode( $checkout_id ) ],
                        $this->get_return_url( $order )
                );

                return [
                        'result'   => 'success',
                        'redirect' => $redirect,
                ];
        }

        // ─────────────────────────────────────────────
        // Thank-You Page
        // ─────────────────────────────────────────────

        public function thankyou_page( $order_id ) {
                $order       = wc_get_order( $order_id );
                $checkout_id = $order ? $order->get_meta( '_giftedpay_checkout_id' ) : '';

                if ( ! $checkout_id || ! $order->has_status( 'pending' ) ) {
                        return;
                }

                $interval = (int) $this->get_option( 'polling_interval', 4 );
                $timeout  = (int) $this->get_option( 'polling_timeout', 150 );
                $phone    = $order->get_meta( '_giftedpay_phone' );
                $display  = $phone ? '0' . substr( $phone, 3 ) : '';

                echo '<div id="giftedpay-polling-wrap"
                        data-checkout-id="' . esc_attr( $checkout_id ) . '"
                        data-order-id="' . esc_attr( $order_id ) . '"
                        data-interval="' . esc_attr( $interval * 1000 ) . '"
                        data-timeout="' . esc_attr( $timeout * 1000 ) . '"
                        data-ajax-url="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '"
                        data-nonce="' . esc_attr( wp_create_nonce( 'giftedpay_poll' ) ) . '">';

                echo '<div class="giftedpay-status-box giftedpay-pending">
                        <div class="giftedpay-spinner"></div>
                        <div class="giftedpay-status-text">
                                <strong>Waiting for M-Pesa confirmation…</strong><br>
                                <span>An STK Push has been sent to <strong>' . esc_html( $display ?: $phone ) . '</strong>. Enter your M-Pesa PIN to complete payment.</span>
                        </div>
                </div>';

                echo '<div class="giftedpay-status-box giftedpay-success" style="display:none">
                        <div class="giftedpay-icon">✓</div>
                        <div class="giftedpay-status-text">
                                <strong>Payment confirmed!</strong><br>
                                <span>Your M-Pesa payment was received. Your order is being processed.</span>
                                <div class="giftedpay-receipt-link"></div>
                        </div>
                </div>';

                echo '<div class="giftedpay-status-box giftedpay-failed" style="display:none">
                        <div class="giftedpay-icon giftedpay-icon-fail">✗</div>
                        <div class="giftedpay-status-text">
                                <strong>Payment not completed.</strong><br>
                                <span class="giftedpay-fail-reason"></span><br>
                                <a href="' . esc_url( wc_get_checkout_url() ) . '" class="button alt" style="margin-top:10px">Try Again</a>
                        </div>
                </div>';

                echo '<div class="giftedpay-status-box giftedpay-timeout" style="display:none">
                        <div class="giftedpay-icon" style="color:#f0a500">⏱</div>
                        <div class="giftedpay-status-text">
                                <strong>Taking longer than expected.</strong><br>
                                <span>If you completed the payment, your order will be updated automatically. Check your M-Pesa messages — if charged, contact us with your receipt.</span>
                        </div>
                </div>';

                echo '</div>';

                $receipt_base = GIFTEDPAY_API_BASE . '/payments/receipt/';
                echo '<script>var giftedpayReceiptBase=' . json_encode( $receipt_base ) . ';</script>';
        }

        // ─────────────────────────────────────────────
        // Webhook Callback from GiftedPay
        // ─────────────────────────────────────────────

        public function handle_callback() {
                $raw  = file_get_contents( 'php://input' );
                $body = json_decode( $raw, true );

                if ( ! $body ) {
                        status_header( 400 );
                        die( 'Invalid payload' );
                }

                $checkout_id = $body['checkout_request_id'] ?? $body['CheckoutRequestID'] ?? '';
                $status      = $body['status'] ?? '';
                $receipt     = $body['mpesa_receipt_number'] ?? $body['receipt_number'] ?? '';
                $result_desc = $body['result_desc'] ?? $body['ResultDesc'] ?? '';
                $amount      = $body['amount'] ?? 0;

                // Also handle raw M-Pesa STK callback format
                if ( isset( $body['Body']['stkCallback'] ) ) {
                        $stk         = $body['Body']['stkCallback'];
                        $checkout_id = $stk['CheckoutRequestID'] ?? '';
                        $result_code = $stk['ResultCode'] ?? -1;
                        $result_desc = $stk['ResultDesc'] ?? '';
                        $status      = $result_code === 0 ? 'completed' : 'failed';
                        if ( $result_code === 0 && isset( $stk['CallbackMetadata']['Item'] ) ) {
                                foreach ( $stk['CallbackMetadata']['Item'] as $item ) {
                                        if ( $item['Name'] === 'MpesaReceiptNumber' ) $receipt = $item['Value'];
                                        if ( $item['Name'] === 'Amount' ) $amount = $item['Value'];
                                }
                        }
                }

                if ( ! $checkout_id ) {
                        status_header( 400 );
                        die( 'Missing checkout_request_id' );
                }

                $orders = wc_get_orders( [
                        'meta_key'   => '_giftedpay_checkout_id',
                        'meta_value' => $checkout_id,
                        'limit'      => 1,
                ] );

                if ( empty( $orders ) ) {
                        status_header( 404 );
                        die( 'Order not found' );
                }

                $order = $orders[0];

                if ( $status === 'completed' && $order->has_status( [ 'pending', 'on-hold' ] ) ) {
                        $order->payment_complete( $receipt );
                        $order->add_order_note(
                                sprintf(
                                        'GiftedPay: Payment confirmed via callback. Receipt: %s | Amount: KES %s',
                                        $receipt,
                                        number_format( $amount, 2 )
                                )
                        );
                } elseif ( in_array( $status, [ 'failed', 'cancelled', 'timeout', 'failed_insufficient_funds', 'failed_invalid_input' ], true ) ) {
                        if ( $order->has_status( 'pending' ) ) {
                                $order->update_status(
                                        'failed',
                                        'GiftedPay: Payment ' . $status . '. ' . $result_desc
                                );
                        }
                }

                status_header( 200 );
                die( 'OK' );
        }

        // ─────────────────────────────────────────────
        // Order Email: receipt link
        // ─────────────────────────────────────────────

        public function email_receipt_link( $order, $sent_to_admin, $plain_text ) {
                if ( $this->receipt_link !== 'yes' || $plain_text ) {
                        return;
                }
                if ( $order->get_payment_method() !== $this->id ) {
                        return;
                }
                $checkout_id = $order->get_meta( '_giftedpay_checkout_id' );
                if ( ! $checkout_id ) {
                        return;
                }
                $url = GIFTEDPAY_API_BASE . '/payments/receipt/' . urlencode( $checkout_id );
                echo '<p style="margin-bottom:20px"><a href="' . esc_url( $url ) . '" style="color:#008000;font-weight:bold">View / Download M-Pesa Receipt →</a></p>';
        }

        // ─────────────────────────────────────────────
        // Helpers
        // ─────────────────────────────────────────────

        private function normalize_phone( $phone ) {
                $phone = preg_replace( '/[\s\-\+\(\)]/', '', $phone );
                if ( preg_match( '/^(07|01)\d{8}$/', $phone ) ) {
                        return '254' . substr( $phone, 1 );
                }
                if ( preg_match( '/^254(7|1)\d{8}$/', $phone ) ) {
                        return $phone;
                }
                return null;
        }

        public function get_icon() {
                $icon_url = GIFTEDPAY_PLUGIN_URL . 'assets/images/mpesa-badge.svg';
                return '<img src="' . esc_url( $icon_url ) . '" alt="M-Pesa" style="max-height:28px;vertical-align:middle;margin-left:6px" />';
        }
}
