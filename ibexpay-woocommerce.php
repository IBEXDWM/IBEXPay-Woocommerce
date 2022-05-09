<?php

/* @wordpress-plugin
 * Plugin Name: IBEXPay WooCommerce
 * Plugin URI: https://www.ibexmercado.com/ibex-pay
 * Description: The easiest and fastest way for any business to receive instant Bitcoin payments via the Lightning Network.
 * Version: 1.0.0
 * Author: IBEXMercado
 * Author URI: https://www.ibexmercado.com/
 */

add_action('plugins_loaded', 'init_ibexpay_woocommerce');

function init_ibexpay_woocommerce() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    };

    class WC_Ibexpay_Woocommerce extends WC_Payment_Gateway {
        public function __construct() {
            $this->id = 'ibexpay_woocommerce';
            $this->has_fields = false;
            $this->method_title = 'IBEXPay WooCommerce';
            $this->method_description = 'The easiest and fastest way for any business to receive instant Bitcoin payments via the Lightning Network.';

            $this->init_form_fields();
            $this->init_settings();

            $this->enabled = $this->get_option('enabled');
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->button_id = $this->get_option('button_id');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
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
                    'default' => __('Pay with Bitcoin over the Lightning Network. Powered by IBEX')
                ),

                'button_id' => array(
                    'title' => __('Button ID', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Button ID from IBEXPay', 'woocommerce'),
                    'default' => __('')
                ),
            );
        }

        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            $order_items = $order->get_items();
            $callback = trailingslashit(get_bloginfo('wpurl')) . '?wc-api=wc_ibexpay_woocommerce';
            $success = add_query_arg('order', $order->get_id(), add_query_arg('key', $order->get_order_key(), $this->get_return_url($order)));
            $description = '';

            foreach($order_items as $item_id => $item) {
                $item_data = $item->get_data();
                $description .= $item_data['name'] . ' (X' . $item_data['quantity'] . '), ';
            }

            $description = substr($description, 0, -2);

            $ibexpay_order = 'order123';
            $redirect = 'https://ibexpay.ibexmercado.com/checkout/' . $ibexpay_order;

            WC()->cart->empty_cart();

            return array(
                'result'   => 'success',
                'redirect' => $redirect,
            );
        }
    }

    function add_ibexpay_woocommerce($methods) {
        $methods[] = 'WC_Ibexpay_Woocommerce';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_ibexpay_woocommerce');
}
