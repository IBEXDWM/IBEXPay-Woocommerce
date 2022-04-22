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
                    'default' => __('The easiest and fastest way for any business to receive instant Bitcoin payments via the Lightning Network.')
                ),
            );
        }
    }

    function add_ibexpay_woocommerce($methods) {
        $methods[] = 'WC_Ibexpay_Woocommerce';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_ibexpay_woocommerce');
}
