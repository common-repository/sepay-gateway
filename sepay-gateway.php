<?php
/*
 * Plugin Name: SePay Gateway
 * Plugin URI: https://docs.sepay.vn/woocommerce.html
 * Description: SePay - Giải pháp tự động xác nhận thanh toán chuyển khoản ngân hàng
 * Author: SePay Team
 * Author URI: https://sepay.vn/
 * Version: 1.0.10
 * Text Domain: sepay-gateway
 * License: GNU General Public License v3.0
 */


if (!defined('ABSPATH')) {
	die("No cheating!");
}
// Define text domain constant
define('SEPAY_GATEWAY_TEXTDOMAIN', 'sepay-gateway');

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))))
    return;

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'sepay_add_gateway_class' );
function sepay_add_gateway_class( $gateways ) {
	$gateways[] = 'Sepay_Gateway'; // your class name is here
	return $gateways;
}


/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'sepay_init_gateway_class', 0);

add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'sepay_add_action_links' );
function sepay_add_action_links ( $actions ) {
    $mylinks = array(
       '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=sepay' ) . '">Settings</a>',
    );
    $actions = array_merge( $actions, $mylinks );
    return $actions;
 }

function sepay_declare_cart_checkout_blocks_compatibility() {

    // Check if the required class exists
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
}

add_action('before_woocommerce_init', 'sepay_declare_cart_checkout_blocks_compatibility');

add_action('woocommerce_blocks_loaded', 'sepay_register_order_approval_payment_method_type');

function sepay_register_order_approval_payment_method_type() {
    if ( ! class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        return;
    }

    require_once plugin_dir_path(__FILE__) . 'includes/class-sepay-woocommerce-block-checkout.php';
    
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function(Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
            $payment_method_registry->register(new SePay_WC_SePay_Blocks);
        }
    );
 }

add_action('admin_enqueue_scripts', 'sepay_add_scripts');

function sepay_add_scripts($hook) {
    $script_path = plugin_dir_path(__FILE__) . 'js/main.js';

    // Kiểm tra tệp có tồn tại không
    if (file_exists($script_path)) {
        $script_version = filemtime($script_path);
    } else {
        $script_version = '';
    }

    // Sử dụng filemtime để đặt phiên bản dựa trên thời gian chỉnh sửa cuối cùng của tệp

    wp_register_script('sepay-option-js', plugin_dir_url(__FILE__) . 'js/main.js', array('jquery'),$script_version,true);
    wp_enqueue_script('sepay-option-js');
}

function sepay_init_gateway_class() {

    class Sepay_Gateway extends WC_Payment_Gateway
    {
        public static $loaded = false;
        /**
         * Class constructor
         */
        public $title;
        public $description;
        public $enabled;
        public $icon;
        public $bank_brand_name;
        public $bank_account_number;
        public $bank_account_holder;
        public $bank_bin;
        public $bank_logo_url;
        public $pay_code_prefix;            
        public $api_key;            
        public $success_message; 
        public $order_when_completed;   
        public $download_mode;   
        public $show_bank_name;
        public $display_bank_name;
        public function __construct()
        {
            

            $this->id = 'sepay';
            $this->icon = '';
            $this->has_fields = false; 
            $this->method_title = 'SePay Gateway';
            $this->method_description = 'Thanh toán chuyển khoản ngân hàng với QR Code (VietQR). Tự động xác nhận thanh toán bởi <a href="https://sepay.vn">SePay</a>. <br>Xem hướng dẫn tại <a href="https://docs.sepay.vn/woocommerce.html">https://docs.sepay.vn/woocommerce.html</a><br><div id="content-render">URL API của bạn là: <span id="site_url">Đang tải url ...</span></div>';

            $this->supports = array(
                'products'
            );

            $bank_data = array(
                'vietcombank' => array('bin' => '970436', 'code' => 'VCB', 'short_name' => 'Vietcombank', 'full_name' => 'Ngân hàng TMCP Ngoại Thương Việt Nam'),
                'vpbank' => array('bin' => '970432', 'code' => 'VPB', 'short_name' => 'VPBank', 'full_name' => 'Ngân hàng TMCP Việt Nam Thịnh Vượng'),
                'acb' => array('bin' => '970416', 'code' => 'ACB', 'short_name' => 'ACB', 'full_name' => 'Ngân hàng TMCP Á Châu'),
                'sacombank' => array('bin' => '970403', 'code' => 'STB', 'short_name' => 'Sacombank', 'full_name' => 'Ngân hàng TMCP Sài Gòn Thương Tín'),
                'hdbank' => array('bin' => '970437', 'code' => 'HDB', 'short_name' => 'HDBank', 'full_name' => 'Ngân hàng TMCP Phát triển Thành phố Hồ Chí Minh'),
                'vietinbank' => array('bin' => '970415', 'code' => 'ICB', 'short_name' => 'VietinBank', 'full_name' => 'Ngân hàng TMCP Công thương Việt Nam'),
                'techcombank' => array('bin' => '970407', 'code' => 'TCB', 'short_name' => 'Techcombank', 'full_name' => 'Ngân hàng TMCP Kỹ thương Việt Nam'),
                'mbbank' => array('bin' => '970422', 'code' => 'MB', 'short_name' => 'MBBank', 'full_name' => 'Ngân hàng TMCP Quân đội'),
                'bidv' => array('bin' => '970418', 'code' => 'BIDV', 'short_name' => 'BIDV', 'full_name' => 'Ngân hàng TMCP Đầu tư và Phát triển Việt Nam'),
                'msb' => array('bin' => '970426', 'code' => 'MSB', 'short_name' => 'MSB', 'full_name' => 'Ngân hàng TMCP Hàng Hải Việt Nam'),
                'shinhanbank' => array('bin' => '970424', 'code' => 'SHBVN', 'short_name' => 'ShinhanBank', 'full_name' => 'Ngân hàng TNHH MTV Shinhan Việt Nam'),
                'tpbank' => array('bin' => '970423', 'code' => 'TPB', 'short_name' => 'TPBank', 'full_name' => 'Ngân hàng TMCP Tiên Phong'),
                'eximbank' => array('bin' => '970431', 'code' => 'EIB', 'short_name' => 'Eximbank', 'full_name' => 'Ngân hàng TMCP Xuất Nhập khẩu Việt Nam'),
                'vib' => array('bin' => '970441', 'code' => 'VIB', 'short_name' => 'VIB', 'full_name' => 'Ngân hàng TMCP Quốc tế Việt Nam'),
                'agribank' => array('bin' => '970405', 'code' => 'VBA', 'short_name' => 'Agribank', 'full_name' => 'Ngân hàng Nông nghiệp và Phát triển Nông thôn Việt Nam'),
                'publicbank' => array('bin' => '970439', 'code' => 'PBVN', 'short_name' => 'PublicBank', 'full_name' => 'Ngân hàng TNHH MTV Public Việt Nam'),
                'kienlongbank' =>  array('bin' => '970452', 'code' => 'KLB', 'short_name' => 'KienLongBank', 'full_name' => 'Ngân hàng TMCP Kiên Long'),
                'ocb' => array('bin' => '970448', 'code' => 'OCB', 'short_name' => 'OCB', 'full_name' => 'Ngân hàng TMCP Phương Đông'),
            );

            // Method with all the options fields
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->icon = esc_url(plugin_dir_url( __FILE__ )) . "imgs/qrcode-icon.png";

            $this->bank_brand_name = $bank_data[$this->get_option('bank_select')]['short_name'];
            $this->bank_account_number = $this->get_option('bank_account_number');
            $this->bank_account_holder = $this->get_option('bank_account_holder');

            $this->bank_bin = $bank_data[$this->get_option('bank_select')]['bin'];
            $this->bank_logo_url = esc_url(plugin_dir_url(__FILE__)) . "imgs/".$bank_data[$this->get_option('bank_select')]['code'].".png";
            $this->pay_code_prefix = $this->get_option('pay_code_prefix');            
            $this->api_key = $this->get_option('api_key');            
            $this->success_message = $this->get_option('success_message'); 
            $this->order_when_completed = $this->get_option('order_when_completed');   
            $this->download_mode = $this->get_option('download_mode');   
            $this->show_bank_name = $this->get_option('show_bank_name'); 

            if($this->show_bank_name && $this->show_bank_name == "brand_name")
                $this->display_bank_name = $this->bank_brand_name;
            else if($this->show_bank_name && $this->show_bank_name == "full_name")
                $this->display_bank_name = $bank_data[$this->get_option('bank_select')]['full_name'];
            else if($this->show_bank_name && $this->show_bank_name == "full_include_brand")
                $this->display_bank_name = $bank_data[$this->get_option('bank_select')]['full_name'] . " (" . $bank_data[$this->get_option('bank_select')]['short_name'] . ")";
            else 
                $this->display_bank_name = $this->bank_brand_name;
            
            // This action hook saves the settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ));

            if (!static::$loaded) {
                add_action('woocommerce_thankyou_sepay', array( $this, 'thankyou_qr' ) );
                static::$loaded = true;
            }
        }

        public function get_config() {
            return array(
                'bank_brand_name' => $this->bank_brand_name,
                'bank_account_holder' => $this->bank_account_holder,
                'bank_account_number' =>  $this->bank_account_number,
                'pay_code_prefix' => $this->pay_code_prefix,
                'api_key' => $this->api_key,
                'order_when_completed' => $this->order_when_completed,
            );
        }

        /**
         * Plugin options, will show in admin config
         */
        public function init_form_fields()
        {
            $this->form_fields = array(
                'url_root' => array(
                    'title'       => '',
                    'label'       => '',
                    'type'        => 'hidden',
                    'description' => '',
                    'default'     => get_site_url(),
                ),
                'enabled' => array(
                    'title'       => 'Bật/Tắt',
                    'label'       => 'Bật/Tắt SePay Gateway',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Tên hiển thị',
                    'type'        => 'text',
                    'description' => 'Tên phương thức thanh toán. Tên này sẽ hiển thị ở trang thanh toán.',
                    'desc_tip'    => true,
                    'default'     => 'Chuyển khoản ngân hàng (Quét mã QR)'
                ),
                'description' => array(
                    'title'       => 'Mô tả',
                    'type'        => 'textarea',
                    'desc_tip'    => true,
                    'description' => 'Mô tả này sẽ hiển thị ở trang thanh toán phía khách hàng.',
                    'default'     => 'Chuyển khoản vào tài khoản của chúng tôi (Có thể quét mã QR). Đơn hàng sẽ được xác nhận ngay sau khi chuyển khoản thành công.',
                ),
                'bank_select' => array(
                    'title'       => 'Ngân hàng',
                    'type'        => 'select',
                    'class'    => 'wc-enhanced-select',
                    'css'      => 'min-width: 350px;',
                    'desc_tip'    => true,
                    'description' => 'Chọn đúng ngân hàng nhận thanh toán của bạn.',
                    'options'  => array(
                        'vietcombank' => 'Vietcombank',
                        'vpbank' => 'VPBank',
                        'acb' => 'ACB',
                        'sacombank' => 'Sacombank',
                        'hdbank' => 'HDBank',
                        'vietinbank' => 'VietinBank',
                        'techcombank' => 'Techcombank',
                        'mbbank' => 'MBBank',
                        'bidv' => 'BIDV',
                        'msb' => 'MSB',
                        'shinhanbank' => 'ShinhanBank',
                        'tpbank' => 'TPBank',
                        'eximbank' => 'Eximbank',
                        'vib' => 'VIB',
                        'agribank' => 'Agribank',
                        'publicbank' => 'PublicBank',
                        'kienlongbank' => 'KienLongBank',
                        'ocb' => 'OCB',   
                    ),
                ),
                'bank_account_number' => array(
                    'title'       => 'Số tài khoản',
                    'type'        => 'text',
                    'desc_tip'    => true,
                    'description' => 'Điền đúng số tài khoản ngân hàng.',
                ),
                'bank_account_holder' => array(
                    'title'       => 'Chủ tài khoản',
                    'type'        => 'text',
                    'desc_tip'    => true,
                    'description' => 'Điền đúng tên chủ tài khoản.',
                ),
                'pay_code_prefix' => array(
                    'title'       => 'Tiền tố mã thanh toán',
                    'type'        => 'text',
                    'default'     => 'DH',
                    'desc_tip'    => true,
                    'description' => 'Hãy chắn chắn Tiền tố mã thanh toán tại đây trùng khớp với Tiền tố tại my.sepay.vn -> Cấu hình công ty -> Cấu trúc mã thanh toán',
                ),
                'api_key' => array(
                    'title'       => 'API Key',
                    'type'        => 'text',
                    'desc_tip'    => true,
                    'description' => 'Điền API Key này vào SePay khi bạn tạo webhook tại my.sepay.vn. API Key phải dài hơn 10 ký tự, chỉ bao gồm chữ và số.',
                    'default'     =>  bin2hex(random_bytes(24)),
                ),
                'success_message' => array(
                    'title'       => 'Thông điệp thanh toán thành công',
                    'type'        => 'textarea',
                    'desc_tip'    => true,
                    'description' => 'Nội dung thể hiện sau khi khách hàng thanh toán thành công. Hỗ trợ chữ thuần, HTML và Javascript',
                    'default'     => '<h2 class="text-success">Thanh toán thành công</h2>',
                ),
                'order_when_completed' => array(
                    'title'       => 'Trạng thái đơn hàng sau thanh toán',
                    'type'        => 'select',
                    'desc_tip'    => true,
                    'description' => 'Trạng thái đơn hàng sau khi thanh toán thành công. Nếu bạn không chỉ định, trạng thái đơn hàng sẽ được xử lý theo luồng của WooCommerce.',
                    'options'  => array(
                        'not_set' => 'Không chỉ định',
                        'processing' => 'Đang xử lý (Processing)',
                        'completed' => 'Đã hoàn tất (Completed)',
                    ),
                ),
                'download_mode' => array(
                    'title'       => 'Chế độ tải xuống sau khi thanh toán',
                    'type'        => 'select',
                    'desc_tip'    => true,
                    'description' => 'Dành cho các sản phẩm có thể tải xuống',
                    'options' => [
                        'auto' => 'Tự động',
                        'manual' => 'Thủ công'
                    ],
                    'default' => 'manual'
                ),
                'show_bank_name' => array(
                    'title'       => 'Hiển thị tên ngân hàng',
                    'type'        => 'select',
                    'desc_tip'    => true,
                    'description' => 'Thông tin hiển thị tên ngân hàng tại ô thanh toán.Ví dụ: Tên viết tắt: MSB. Tên đầy đủ: Ngân hàng TMCP Hàng Hải Việt Nam. Tên đầy đủ kèm tên viết tắt: Ngân hàng TMCP Hàng Hải Việt Nam (MSB)',
                    'options'  => array(
                        'brand_name' => 'Tên viết tắt',
                        'full_name' => 'Tên đầy đủ',
                        'full_include_brand' => 'Tên đầy đủ kèm tên viết tắt',
                    ),
                ),
            );
        }

       
       
        public function enqueue_sepay_scripts($order_id, $order) {

            // Đường dẫn đến tệp CSS
            $style_path = plugin_dir_path(__FILE__) . 'css/sepay_style.css';
            // Đường dẫn đến tệp JavaScript
            $script_path = plugin_dir_path(__FILE__) . 'js/sepay_script.js';
            // Đăng ký và thêm JS
            $style_version = filemtime($style_path);
            $script_version = filemtime($script_path);
            wp_enqueue_script('sepay_script', plugins_url('/js/sepay_script.js', __FILE__), array('jquery'), $script_version, true);
            wp_enqueue_style('sepay_style', plugin_dir_url(__FILE__) . '/css/sepay_style.css',array(), $style_version);

            $remark = $this->pay_code_prefix . $order_id;

            // Vietinbank prefix remark
            if ($this->bank_bin == '970415') {
                $remark = 'SEVQR ' . $remark;   
            }

            // Truyền các biến PHP sang JavaScript
            wp_localize_script('sepay_script', 'sepay_vars', array(
                'ajax_url' => esc_url(admin_url('admin-ajax.php')),
                'account_number' => $this->bank_account_number,
                'order_code' => $this->pay_code_prefix . $order_id,
                'remark' => $remark,
                'amount' => $order->get_total(),
                'order_nonce' => wp_create_nonce('submit_order'),
                'order_id' => $order_id,
                'download_mode' => $this->download_mode,
                'success_message' => $this->success_message ? wp_kses_post($this->success_message) : "<p>Thanh toán thành công!</p>",
            ));
        }
        public function thankyou_qr( $order_id ) {
            $order = wc_get_order( $order_id );

            $remark = $this->pay_code_prefix . $order_id;

            // Vietinbank prefix remark
            if ($this->bank_bin == '970415') {
                $remark = 'SEVQR ' . $remark;   
            }

        // Gọi hàm enqueue_sepay_scripts để truyền biến khi cần thiết
        $this->enqueue_sepay_scripts($order_id, $order);
        ?>
            <?php if (!$order->has_status('processing') && !$order->has_status('completed')): ?>
            <section class="woocommerce-sepay-bank-details">
                
                <div class="sepay-box">
                    <div class="box-title">
                        Thanh toán qua chuyển khoản ngân hàng
                    </div>
                    <div class="sepay-message">
                    </div>
                    <div class="sepay-pay-info">
                        <!-- QR method -->
                        <div class="qr-box">
                            <div class="qr-title">
                                Cách 1: Mở app ngân hàng/ Ví và <b>quét mã QR</b>
                            </div>
                            <div class="qr-zone">
                                <div class="qr-element">
                                    <div class="qr-top-border"></div>
                                    <div class="qr-bottom-border"></div>
                                    <div class="qr-content">
                                    <img decoding="async" class="qr-image"
                                    src="https://qr.sepay.vn/img?acc=<?php echo esc_html($this->bank_account_number); ?>&bank=<?php echo esc_html($this->bank_bin); ?>&amount=<?php echo esc_html($order->get_total()); ?>&des=<?php echo esc_html($remark); ?>&template=compact" />
                                    </div>
                                </div>
                                <div class="download-qr">
                                    <a class="button-qr"
                                        href="https://qr.sepay.vn/img?acc=<?php  echo esc_html($this->bank_account_number);?>&bank=<?php echo esc_html($this->bank_brand_name);?>&amount=<?php echo esc_html($order->get_total());?>&des=<?php echo esc_html($remark);?>&download=yes"
                                        download="">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round" class="lucide lucide-download">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                            <polyline points="7 10 12 15 17 10" />
                                            <line x1="12" x2="12" y1="15" y2="3" />
                                        </svg>
                                        <span>Tải ảnh QR</span>
                                    </a>
                                </div>
                            </div>
                            <div style="margin-top: -1rem;"></div>
                        </div>
                        <!-- /QR method -->

                        <!-- Manual method -->
                        <div class="manual-box">
                            <div class="manual-title">
                                Cách 2: Chuyển khoản <b>thủ công</b> theo thông tin
                            </div>

                            <div class="bank-info">
                                <div class="banner">
                                    <img decoding="async" class="bank-logo"
                                        src="<?php  echo esc_html($this->bank_logo_url);?>" />
                                </div>
                                <div class="bank-info-table">
                                    <div class="bank-info-row-group">
                                        <div class="bank-info-row">
                                            <div class="bank-info-cell">Ngân hàng</div>
                                            <div class="bank-info-cell font-bold">
 
                                                <?php  echo esc_html($this->display_bank_name);?>
                                            </div>
                                        </div>
                                        <div class="bank-info-row">
                                            <div class="bank-info-cell">Thụ hưởng</div>
                                            <div class="bank-info-cell">
                                                <span class="font-bold" id="copy_accholder">
                                                    <?php  echo esc_html($this->bank_account_holder);?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="bank-info-row">
                                            <div class="bank-info-cell">Số tài khoản</div>
                                            <div class="bank-info-cell">
                                                <div class="bank-info-value">
                                                    <span class="font-bold" id="copy_accno">
                                                        <?php  echo esc_html($this->bank_account_number);?>
                                                    </span>
                                                    <span id="sepay_copy_account_number">
                                                        <a id="sepay_copy_account_number_btn" href="javascript:;">
                                                            <svg width="15" height="15" viewBox="0 0 20 20" fill="none"
                                                                xmlns="http://www.w3.org/2000/svg">
                                                                <path fill-rule="evenodd" clip-rule="evenodd"
                                                                    d="M6.625 3.125C6.34886 3.125 6.125 3.34886 6.125 3.625V4.875H13.375C14.3415 4.875 15.125 5.6585 15.125 6.625V13.875H16.375C16.6511 13.875 16.875 13.6511 16.875 13.375V3.625C16.875 3.34886 16.6511 3.125 16.375 3.125H6.625ZM15.125 15.125H16.375C17.3415 15.125 18.125 14.3415 18.125 13.375V3.625C18.125 2.6585 17.3415 1.875 16.375 1.875H6.625C5.6585 1.875 4.875 2.6585 4.875 3.625V4.875H3.625C2.6585 4.875 1.875 5.6585 1.875 6.625V16.375C1.875 17.3415 2.6585 18.125 3.625 18.125H13.375C14.3415 18.125 15.125 17.3415 15.125 16.375V15.125ZM13.875 6.625C13.875 6.34886 13.6511 6.125 13.375 6.125H3.625C3.34886 6.125 3.125 6.34886 3.125 6.625V16.375C3.125 16.6511 3.34886 16.875 3.625 16.875H13.375C13.6511 16.875 13.875 16.6511 13.875 16.375V6.625Z"
                                                                    fill="rgba(51, 102, 255, 1)"></path>
                                                            </svg>
                                                        </a>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="bank-info-row">
                                            <div class="bank-info-cell">Số tiền</div>
                                            <div class="bank-info-cell">
                                                <div class="bank-info-value">
                                                    <span class="font-bold" id="copy_amount">
                                                        <?php echo wp_kses_post(wc_price($order->get_total()));?>
                                                    </span>
                                                    <span id="sepay_copy_amount">
                                                        <a id="sepay_copy_amount_btn" href="javascript:;">
                                                            <svg width="15" height="15" viewBox="0 0 20 20" fill="none"
                                                                xmlns="http://www.w3.org/2000/svg">
                                                                <path fill-rule="evenodd" clip-rule="evenodd"
                                                                    d="M6.625 3.125C6.34886 3.125 6.125 3.34886 6.125 3.625V4.875H13.375C14.3415 4.875 15.125 5.6585 15.125 6.625V13.875H16.375C16.6511 13.875 16.875 13.6511 16.875 13.375V3.625C16.875 3.34886 16.6511 3.125 16.375 3.125H6.625ZM15.125 15.125H16.375C17.3415 15.125 18.125 14.3415 18.125 13.375V3.625C18.125 2.6585 17.3415 1.875 16.375 1.875H6.625C5.6585 1.875 4.875 2.6585 4.875 3.625V4.875H3.625C2.6585 4.875 1.875 5.6585 1.875 6.625V16.375C1.875 17.3415 2.6585 18.125 3.625 18.125H13.375C14.3415 18.125 15.125 17.3415 15.125 16.375V15.125ZM13.875 6.625C13.875 6.34886 13.6511 6.125 13.375 6.125H3.625C3.34886 6.125 3.125 6.34886 3.125 6.625V16.375C3.125 16.6511 3.34886 16.875 3.625 16.875H13.375C13.6511 16.875 13.875 16.6511 13.875 16.375V6.625Z"
                                                                    fill="rgba(51, 102, 255, 1)"></path>
                                                            </svg>
                                                        </a>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="bank-info-row">
                                            <div class="bank-info-cell">Nội dung CK</div>
                                            <div class="bank-info-cell">
                                                <div class="bank-info-value">
                                                    <span id="copy_memo" class="font-bold">
                                                        <?php  echo esc_html($remark);?>
                                                    </span>
                                                    <span id="sepay_copy_transfer_content">
                                                        <a id="sepay_copy_transfer_content_btn" href="javascript:;">
                                                            <svg width="15" height="15" viewBox="0 0 20 20" fill="none"
                                                                xmlns="http://www.w3.org/2000/svg">
                                                                <path fill-rule="evenodd" clip-rule="evenodd"
                                                                    d="M6.625 3.125C6.34886 3.125 6.125 3.34886 6.125 3.625V4.875H13.375C14.3415 4.875 15.125 5.6585 15.125 6.625V13.875H16.375C16.6511 13.875 16.875 13.6511 16.875 13.375V3.625C16.875 3.34886 16.6511 3.125 16.375 3.125H6.625ZM15.125 15.125H16.375C17.3415 15.125 18.125 14.3415 18.125 13.375V3.625C18.125 2.6585 17.3415 1.875 16.375 1.875H6.625C5.6585 1.875 4.875 2.6585 4.875 3.625V4.875H3.625C2.6585 4.875 1.875 5.6585 1.875 6.625V16.375C1.875 17.3415 2.6585 18.125 3.625 18.125H13.375C14.3415 18.125 15.125 17.3415 15.125 16.375V15.125ZM13.875 6.625C13.875 6.34886 13.6511 6.125 13.375 6.125H3.625C3.34886 6.125 3.125 6.34886 3.125 6.625V16.375C3.125 16.6511 3.34886 16.875 3.625 16.875H13.375C13.6511 16.875 13.875 16.6511 13.875 16.375V6.625Z"
                                                                    fill="rgba(51, 102, 255, 1)"></path>
                                                            </svg>
                                                        </a>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="note">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003zM12 8.25a.75.75 0 01.75.75v3.75a.75.75 0 01-1.5 0V9a.75.75 0 01.75-.75zm0 8.25a.75.75 0 100-1.5.75.75 0 000 1.5z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    <span>Lưu ý: Vui lòng giữ nguyên nội dung chuyển khoản <b><?php  echo esc_html($remark)?></b> để xác nhận thanh toán tự
                                        động.</span>
                                </div>
                            </div>

                            <div></div>
                        </div>
                        <!-- /Manual method -->
                    </div>
                    <div class="sepay-pay-footer">
                            Trạng thái: Chờ thanh toán <img decoding="async" src="<?php echo esc_url(plugin_dir_url(__FILE__)) . 'imgs/loading.gif'; ?>" />
                        </div>
                    <div class="sepay-download" style="display: none;">
                        <?php if ($this->download_mode == 'auto'): ?>
                        <div class="autodownload">
                            <p class="countdown">Hệ thống sẽ tự động tải xuống sau vài giây nữa...</p>
                            <p class="subtle">Nếu tiến trình vẫn chưa tải xuống, vui lòng nhấp <span class="force-download">vào đây</span>.</p>
                        </div>
                        <?php endif ?>
                        <?php if ($this->download_mode == 'manual'): ?>
                        <div class="download-list">
                        </div>
                        <?php endif ?>
                    </div>
                </div>
            </section>

            
                
            <?php endif ?>
       <?php
    }

        /*
         * We're processing the payments here, everything about it is in Step 5
        */
        public function process_payment($order_id)
        {
            global $woocommerce;
            $order = new WC_Order($order_id);

            // Mark as on-hold (we're awaiting the cheque)
            $order->update_status('on-hold', __('Awaiting cheque payment', 'woocommerce'));

            // Remove cart
            $woocommerce->cart->empty_cart();

            // Return thankyou redirect
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );
        }
    }
    
    add_action( 'wp_ajax_nopriv_sepay_check_order_status', 'sepay_check_order_status' );
    add_action( 'wp_ajax_sepay_check_order_status', 'sepay_check_order_status' );

    function sepay_check_order_status() {
        global $wpdb; // this is how you get access to the database

        if ( ! isset( $_POST['order_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['order_nonce'] ) ), 'submit_order' ) ) {
            wp_die(); 
            return;
        }
            // Nonce is invalid, do not process the form data
        $order_id = intval( $_POST['orderID'] );
        
        $order = wc_get_order( $order_id );

        $downloads = [];

        if( $order->has_downloadable_item() && $order->is_download_permitted()){
            foreach( $order->get_items() as $item ){
                $product_id = $item->get_product_id(); // product ID
                $product = wc_get_product($product_id);

                $itemDownloads = array_map(function($download) use ($product, $product_id) {
                    return [
                        'id' => $download['id'],
                        'product_id' => $product_id,
                        'product_name' => $product->get_data()['name'],
                        'name' => $download['name'],
                        'downloads_remaining' => $download['downloads_remaining'],
                        'download_url' => $download['download_url'],
                        'access_expires' => $download['access_expires'],
                    ];
                }, array_values($item->get_item_downloads()));

                $downloads = array_merge($downloads, $itemDownloads);
            }
        }

        $order_status  = $order->get_status(); // Get the order status (see the conditional method has_status() below)

        $array_response = array(
            'status' => true,
            'order_status' => $order_status,
            'downloads' => $downloads
        );
        echo wp_json_encode($array_response);

        wp_die(); // this is required to terminate immediately and return a proper response
    }

    add_action( 'rest_api_init', function () {
        register_rest_route( 'sepay-gateway/v1', '/add-payment', array(
          'methods' => 'POST',
          'callback' => 'sepay_api',
          'permission_callback' => '__return_true',
        ) );
    } );

    function sepay_api( WP_REST_Request $request ) {
        $parameters = $request->get_json_params();

        $headers = getallheaders();

        $api_key = "";

        if(isset($headers['Authorization'])) {
            $arr1 = explode(" ", $headers['Authorization']);
            if(count($arr1) == 2 && $arr1[0] == "Apikey" && strlen($arr1[1]) >= 10) {
                $api_key = $arr1[1];
            }
        }
     
        //var_dump($this->get_option( 'pay_code_prefix' ));

        if(!ctype_alnum($api_key) || strlen($api_key) < 10) {
            return array('success' => 'false', 'message' => 'Invalid API Key format');
        }

        // get plugin config
        $sepay_plugin = new Sepay_Gateway();
        $plugin_config = $sepay_plugin->get_config();
        
        //if($api_key != "MPAELCQRY4LF5XOESIJSU3SFBQJJCVR3BAIGFDFASV6TKMGTN6OZ1QKT7Z7PY9WR")
        if($api_key != $plugin_config['api_key'])
            return array('success' => 'false', 'message' => 'Invalid API Key');
       
        if(!is_array($parameters))
            return array('success' => 'false', 'message' => 'Invalid JSON request');

          //  var_dump($parameters);
        if(!isset($parameters['accountNumber']) || !isset($parameters['gateway']) || !isset($parameters['code'])|| !isset($parameters['transferType'])|| !isset($parameters['transferAmount']))
            return array('success' => 'false', 'message' => 'Not enough required parameters');

        if($parameters['transferType'] != "in")
            return array('success' => 'false', 'message' => 'transferType must be in');
       
        $s_order_id = str_replace($plugin_config['pay_code_prefix'],"", $parameters['code']);
        if(!is_numeric($s_order_id))
            return array('success' => 'false', 'message' => "Order ID not found from pay code " . $parameters['code']);

        $s_order_id = intval($s_order_id);

        // get order details
        global $woocommerce;
        $order = wc_get_order( $s_order_id );

        if(!$order)
            return array('success' => 'false', 'message' => "Order ID ". $s_order_id . " not found ");

        $order_status  = $order->get_status();

        if($order_status == "completed" || $order_status == "processing")
            return array('success' => 'false', 'message' => "This order has already been completed before!");

        $order_total = $order->get_total();

        if(!is_numeric($order_total) || $order_total <= 0)
            return array('success' => 'false', 'message' => "order_total is <= 0");

        //if(strtolower($plugin_config['bank_brand_name']) != strtolower($parameters['gateway']) || $plugin_config['bank_account_number'] != $parameters['accountNumber'])
         //   return array('success' => 'false', 'message' => "The bank account information configured in the plugin is different from the webhook information sent");

        $order_note = "SePay: Đã nhận thanh toán <b>" .wc_price($parameters['transferAmount']) . "</b> vào tài khoản <b>" .$parameters['accountNumber'] . "</b> tại ngân hàng <b>" . $parameters['gateway'] . "</b> vào lúc <b>" . $parameters['transactionDate']  . "</b>";

        if($order_total == $parameters['transferAmount']) {
            $order->payment_complete();
			
            if(in_array($plugin_config['order_when_completed'], array("processing","completed"))) {
                $order->update_status($plugin_config['order_when_completed']);
            }
            
            wc_reduce_stock_levels($s_order_id);

            $order_note = $order_note . ". Khách hàng đã thanh toán đủ. Trạng thái đơn hàng được chuyển từ " . $order_status . " sang Completed"; 
        } else if($order_total > $parameters['transferAmount']) {
            $under_payment = wc_price($order_total - $parameters['transferAmount']);
            $order_note = $order_note . ". Khách hàng thanh toán THIẾU: <b>" . $under_payment . "</b>"; 

        } else if($order_total < $parameters['transferAmount']) {
            $over_payment = wc_price($parameters['transferAmount'] - $order_total);
            $order_note = $order_note . ". Khách hàng thanh toán THỪA: <b>" . $over_payment . "</b>"; 
        }

        $order->add_order_note( $order_note, false );

        return array('success' => 'true', 'message' => $order_note);
    }
}

