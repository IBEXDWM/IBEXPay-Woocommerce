<?php

/* @wordpress-plugin
 * Plugin Name: IBEXPay Woocommerce Payment Gateway
 * Plugin URI: https://www.ibexmercado.com/ibex-pay
 * Description: The easiest and fastest way for any business to receive Bitcoin payments.
 * Version: 1.1.2
 * Author: IBEX
 * Author URI: https://www.ibexmercado.com/
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) return;

add_action('plugins_loaded', 'init_ibexpay_woocommerce');

function init_ibexpay_woocommerce() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class WC_Gateway_Ibexpay extends WC_Payment_Gateway {
        public function __construct() {
            $this->id = 'ibexpay';
            $this->has_fields = false;
            $this->method_title = 'IBEXPay WooCommerce';
            $this->method_description = 'The easiest and fastest way for any business to receive Bitcoin payments.';

            $this->init_form_fields();
            $this->init_settings();

            $this->enabled = $this->get_option('enabled');
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->button_id = $this->get_option('button_id');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_wc_gateway_ibexpay', array($this, 'payment_callback'));
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable IBEXPay WooCommerce', 'woocommerce'),
                    'label' => __('Enable IBEXPay WooCommerce', 'woocommerce'),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ),

                'title' => array(
                    'title' => __('Title', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('The payment method title which a customer sees at the checkout of your store.', 'woocommerce'),
                    'default' => __('Pay with Bitcoin', 'woocommerce')
                ),

                'description' => array(
                    'title' => __('Description', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('The payment method description which a customer sees at the checkout of your store.', 'woocommerce'),
                    'default' => __('Pay with Bitcoin On-chain or over the Lightning Network. Powered by IBEX')
                ),

                'button_id' => array(
                    'title' => __('vBPT ID', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('vBPT ID from IBEXPay', 'woocommerce'),
                    'default' => __('')
                ),
            );
        }

        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            $order_items = $order->get_items();
            $webhook = trailingslashit(get_bloginfo('wpurl')) . '?wc-api=wc_gateway_ibexpay';
            $goback = trailingslashit(get_bloginfo('wpurl'));
            $success = add_query_arg('order', $order->get_id(), add_query_arg('key', $order->get_order_key(), $this->get_return_url($order)));
            $secret = wp_generate_uuid4();
            $description = '';

            foreach($order_items as $item_id => $item) {
                $item_data = $item->get_data();
                $description .= $item_data['name'] . ' (X' . $item_data['quantity'] . '), ';
            }
            $description = substr($description, 0, -2);

            $ibexpay_order_id = get_post_meta($order->get_id(), 'ibexpay_order_id', true);

            if (empty($ibexpay_order_id)) {
                $url = 'https://ibexpay-api.ibexmercado.com/ecommerce/' . $this->button_id . '/checkout';

                $headers = array('Content-Type' => 'application/json');

                $payload = json_encode(
                    array(
                        'description' => $description,
                        'amount' => floatval($order->get_total()),
                        'orderId' => (string) $order->get_id(),
                        'webhookUrl' => $webhook,
                        'webhookSecret' => $secret,
                        'successUrl' => $success,
                        'gobackUrl' => $goback,
                        'notifyAll' => true
                    ),
                    true
                );

                $args = array(
                    'method' => 'POST',
                    'headers'  => $headers,
                    'body' => $payload,
                    'data_format' => 'body',
                    'timeout' => 45,
                    'blocking' => true
                );

                $response = wp_remote_post($url, $args);
                $http_code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                $json = json_decode($body, true);

                if ($http_code != 200) return;

                $ibexpay_order_id = $json['id'];
                update_post_meta($order_id, 'ibexpay_order_id', $ibexpay_order_id);
                update_post_meta($order_id, 'ibexpay_secret', $secret);

                return array(
                    'result' => 'success',
                    'redirect' => 'https://ibexpay.ibexmercado.com/ecommerce/checkout/' . $ibexpay_order_id,
                );
            }
            else {
                return array(
                    'result' => 'success',
                    'redirect' => 'https://ibexpay.ibexmercado.com/ecommerce/checkout/' . $ibexpay_order_id,
                );
            }
        }

        public function payment_callback() {
            try {
                $body = file_get_contents('php://input');
                $request = json_decode($body, true);

                $order = wc_get_order($request['orderId']);
                if (empty($order)) {
                    throw new Exception('Order #' . $request['orderId'] . ' does not exists');
                }

                $ibexpay_order_id = get_post_meta($order->get_id(), 'ibexpay_order_id', true);
                if (empty($ibexpay_order_id)) {
                    throw new Exception('Order is not from IBEXPay');
                }

                $ibexpay_secret = get_post_meta($order->get_id(), 'ibexpay_secret', true);
                if (strcmp($ibexpay_secret, $request['secret']) != 0) {
                    throw new Exception('Request is not signed with the same key');
                }

                update_post_meta($order->get_id(), 'ibexpay_transaction_id', $request['transactionId']);

                if (strcmp('SETTLED', $request['status']) == 0) {
                    $previous_status = "wc-" . $order->get_status();
                    $order->add_order_note(__('Successful payment via ' . $request['paymentMethod'] . ' credited to your IBEXPay account at ' . $request['transaction']['settledAtUtc'], 'ibexpay'));
                    $order->payment_complete();

                    if ($order->get_status() === 'processing' && ($previous_status === 'wc-expired' || $previous_status === 'wc-canceled' || $previous_status === 'wc-on-hold')) {
                        WC()->mailer()->emails['WC_Email_Customer_Processing_Order']->trigger($order->get_id());
                    }

                    if (($order->get_status() === 'processing' || $order->get_status() == 'completed') && ($previous_status === 'wc-expired' || $previous_status === 'wc-canceled' || $previous_status === 'wc-on-hold')) {
                        WC()->mailer()->emails['WC_Email_New_Order']->trigger($order->get_id());
                    }
                } else if (strcmp('UPDATE', $request['status']) == 0) {
                    $previous_status = "wc-" . $order->get_status();
                    if ($previous_status != 'wc-on-hold') {
                        $order->update_status('on-hold');
                        $order->add_order_note(__('There is an underpayment. The order will be on hold until payment is fulfilled'));
                    }

                    $order->add_order_note(__('A new payment confirmation arrived via ' . $request['paymentMethod'] . ' The transaction is not fulfilled yet, there are ' . $request['transactionOnchainAmount']['confirmedSats'] . ' confirmed SATS and ' . $request['transactionOnchainAmount']['unconfirmedSats'] . ' unconfirmed SATS the transaction requires ' . $request['transactionOnchainAmount']['requiredSats'] . ' SATS to be confirmed'));
                }

            } catch (Exception $e) {
                die($e->getMessage());
            }
        }
    }

    function add_ibexpay_woocommerce($methods) {
        $methods[] = 'WC_Gateway_Ibexpay';
        return $methods;
    }

    function ibexpay_custom_button($button) {
        $targeted_payments_methods = array('ibexpay');
        $chosen_payment_method = WC()->session->get('chosen_payment_method');

        if(in_array($chosen_payment_method, $targeted_payments_methods) && ! is_wc_endpoint_url()) {
            $button = '
                <style>.ibex-button{ display: flex; align-items: center; justify-content: center; width: 100%; font-size: 1.41575em; background-color: #ffffff; color: #f4911a; border-radius: 10px; box-shadow: rgba(0, 0, 0, 0.35) 0px 5px 15px; font-weight: 300; } .ibex-button:hover{ background-color: #fefefe; color: #f4911a; } .ibex-image{ width: 2.0em; margin-right: 10px; } .ibex-bold{ font-weight: 600; font-style: italic; }</style>
                <button type="submit" class="ibex-button" name="woocommerce_checkout_place_order" id="place_order"><img src="' . plugins_url('/assets/ibex-logo.png', __FILE__) . '" class="ibex-image" /><span>Pay with <span class="ibex-bold">bitcoin</span></span></button>
            ';
        }

        return $button;
    }

    function custom_checkout_jquery_script() {
        if (is_checkout() && ! is_wc_endpoint_url()) {
            ?>
                <script type="text/javascript">
                jQuery( function($){
                    $('form.checkout').on('change', 'input[name="payment_method"]', function(){
                        $(document.body).trigger('update_checkout');
                    });
                });
                </script>
            <?php
        }
    }

    add_filter('woocommerce_payment_gateways', 'add_ibexpay_woocommerce');
    add_filter('woocommerce_order_button_html', 'ibexpay_custom_button');
    add_action('wp_footer', 'custom_checkout_jquery_script');
}
