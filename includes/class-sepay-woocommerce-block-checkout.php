<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class SePay_WC_SePay_Blocks extends AbstractPaymentMethodType {

    private $gateway;
    protected $name = 'sepay';

    public function initialize()
    {
        $this->settings = get_option( 'woocommerce_sepay_settings', [] );
        $this->gateway = new Sepay_Gateway();
    }

    public function is_active()
    {
        return $this->gateway->is_available();
    }

    public function get_payment_method_script_handles()
    {
        $script_path = plugin_dir_path(__FILE__) . 'block/checkout.js';
        // Sử dụng filemtime để đặt phiên bản dựa trên thời gian chỉnh sửa cuối cùng của tệp
        if (file_exists($script_path)) {
            $script_version = filemtime($script_path);
        } else {
            $script_version = '';
        }
        
        wp_register_script(
            'wc-sepay-blocks-integration',
            plugin_dir_url(__FILE__) . 'block/checkout.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            $script_version,
            true
        );

        return [ 'wc-sepay-blocks-integration' ];
    }

    public function get_payment_method_data() {
        return [
            'title' => $this->gateway->title,
            'description' => $this->gateway->description,
        ];
    }

}
