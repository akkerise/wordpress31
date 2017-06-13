<?php

/**
 * Plugin Name: Ngan Luong payment gateway for WooCommerce
 * Plugin URI: https://www.nganluong.vn/
 * Description: Plugin tích hợp NgânLượng.vn được build trên WooCommerce 3.x
 * Version: 3.1
 * Author: Đức LM(0948389111) - Thanh NA (0968381829)
 * Author URI: http://www.webckk.com/
 */
ini_set('display_errors', true);
add_action('plugins_loaded', 'woocommerce_payment_nganluong_init', 0);
add_action('parse_request', array('WC_Gateway_NganLuong', 'nganluong_return_handler'));
function woocommerce_payment_nganluong_init()
{
    if (!class_exists('WC_Payment_Gateway'))
        return;

    class WC_Gateway_NganLuong extends WC_Payment_Gateway
    {
        // Debug parameters
        private $debug_params;
        private $debug_md5;
        private $merchant_id;
        private $status_order;
        private $receiver_email;
        private $url_api;
        private $merchant_pass;
        function __construct()
        {
            $this->icon = @$this->settings['icon']; // Icon URL
            $this->id = 'nganluong';
            $this->method_title = 'Ngân Lượng';
            $this->has_fields = false;

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->settings['title'];
            $this->description = $this->settings['description'];

            $this->receiver_email = $this->settings['receiver_email'];
            $this->merchant_id = $this->settings['merchant_id'];
            $this->url_api = $this->settings['url_api'];
            $this->merchant_pass = $this->settings['merchant_pass'];

            $this->redirect_page_id = $this->settings['redirect_page_id'];
            $this->status_order = $this->settings['status_order'];
            $this->debug = @$this->settings['debug'];
            $this->order_button_text = __('Proceed to Ngân Lượng', 'woocommerce');
            $this->msg['message'] = "";
            $this->msg['class'] = "";
            // Add the page after checkout to redirect to Ngan Luong
            add_action('woocommerce_receipt_NganLuong', array($this, 'receipt_page'));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            // add_action('woocommerce_thankyou_NganLuongVN', array($this, 'thankyou_page'));
        }

        /**
         * Logging method.
         * @param string $message
         */
        public static function log($message)
        {
            $log = new WC_Logger();
            $log->add('nganluong', $message);
        }

        public function init_form_fields()
        {
            // Admin fields
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Activate', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Activate the payment gateway for Ngan Luong', 'woocommerce'),
                    'default' => 'yes'),
                'title' => array(
                    'title' => __('Name', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Tên phương thức thanh toán ( khi khách hàng chọn phương thức thanh toán )', 'woocommerce'),
                    'default' => __('NganLuongVN', 'woocommerce')),
                'icon' => array(
                    'title' => __('Icon', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Icon phương thức thanh toán', 'woocommerce'),
                    'default' => __('https://www.nganluong.vn/css/checkout/version20/images/logoNL.png', 'woocommerce')),
                'description' => array(
                    'title' => __('Mô tả', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('Mô tả phương thức thanh toán.', 'woocommerce'),
                    'default' => __('Click place order and you will be directed to the Ngan Luong website in order to make payment', 'woocommerce')),
                'redirect_page_id' => array(
                    'title' => __('Return URL'),
                    'type' => 'select',
                    'options' => $this->get_pages('Hãy chọn...'),
                    'description' => __('Hãy chọn trang/url để chuyển đến sau khi khách hàng đã thanh toán tại NganLuong.vn thành công', 'woocommerce')
                ),
                'status_order' => array(
                    'title' => __('Trạng thái Order'),
                    'type' => 'select',
                    'options' => wc_get_order_statuses(),
                    'description' => __('Chọn trạng thái orders cập nhật', 'woocommerce')
                ),
                'nlcurrency' => array(
                    'title' => __('Currency', 'woocommerce'),
                    'type' => 'text',
                    'default' => 'vnd',
                    'description' => __('"vnd" or "usd"', 'woocommerce')
                ),
                'receiver_email' => array(
                    'title' => __('Merchant Emai Nhận Tiền', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Đây là tài khoản NganLuong.vn (Email) để nhận tiền')),
                'url_api' => array(
                    'title' => __('URL API Ngân Lượng ', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('"https://www.nganluong.vn/checkout.api.nganluong.post.php"', 'woocommerce')
                ),
                'merchant_id' => array(
                    'title' => __('Merchant ID', 'woocommerce'),
                    'type' => 'text'
                ),
                'merchant_pass' => array(
                    'title' => __('Merchant Pass', 'woocommerce'),
                    'type' => 'password'
                )
            );
        }

        /**
         *  There are no payment fields for NganLuongVN, but we want to show the description if set.
         * */
        function payment_fields()
        {
            if ($this->description)
                echo wpautop(wptexturize(__($this->description, 'woocommerce')));
            echo '<br>';
            require_once 'template.php';
        }

        /**
         * Process the payment and return the result.
         * @param  int $order_id
         * @return array
         */
        public function process_payment($order_id)
        {

            $order = wc_get_order($order_id);
            $checkouturl = $this->generate_NganLuongVN_url($order_id);
            $this->log($checkouturl);
            return array(
                'result' => 'success',
                'redirect' => $checkouturl
            );
        }

        function generate_NganLuongVN_url($order_id)
        {
            // This is from the class provided by Ngan Luong. Not advisable to mess.
            global $woocommerce;
            $order = new WC_Order($order_id);
            $settings = get_option('woocommerce_nganluong_settings', null);
            $order_items = $order->get_items();
            $return_url = wc_get_checkout_url();
            $cancel_url = wc_get_checkout_url();
            $total_amount = (int)$order->get_total();
            $fee_shipping = $order->get_total_shipping_refunded();
            $product_names = [];
            foreach ($order_items as $order_item) {
                $product_names[] = $order_item['name'];
            }
            $order_description = implode(', ', $product_names); // this goes into transaction info, which shows up on Ngan Luong as the description of goods
            $array_items = [
                'item_name' => $order->get_order_item_totals(),
                'item_quantity' => $order->get_order_item_totals(),
                'item_amount' => $total_amount,
                'totalItems' => $order->get_item_count()
            ];
            $payment_method = $_POST['option_payment'];
            $bank_code = @$_POST['bankcode'];
            $order_code = $order_id;
            $payment_type = '';
            $discount_amount = 0;
            $tax_amount = 0;
            $buyer_fullname = $order->get_formatted_billing_full_name();
            $buyer_email = $order->get_billing_email();
            $buyer_mobile = $order->get_billing_phone();
            $buyer_address = $order->get_formatted_billing_address();
            $nlcheckout= new NL_CheckOutV3($settings['merchant_id'],$settings['merchant_pass'],$settings['receiver_email'],$settings['url_api']);
            if ($payment_method != '' && $buyer_email != "" && $buyer_mobile != "" && $buyer_fullname != "" && filter_var($buyer_email, FILTER_VALIDATE_EMAIL)) {
                $nl_result = '';
                if ($payment_method == "VISA") {
                    $nl_result = $nlcheckout->VisaCheckout($order_code, $total_amount, $payment_type,
                        $order_description, $tax_amount, $fee_shipping, $discount_amount,
                        $return_url, $cancel_url, $buyer_fullname, $buyer_email, $buyer_mobile,
                        $buyer_address, $array_items, $bank_code);
                } elseif ($payment_method == "NL") {
                    $nl_result = $nlcheckout->NLCheckout($order_code, $total_amount, $payment_type,
                        $order_description, $tax_amount, $fee_shipping, $discount_amount, $return_url,
                        $cancel_url, $buyer_fullname, $buyer_email, $buyer_mobile, $buyer_address, $array_items);
                } elseif ($payment_method == "ATM_ONLINE" && $bank_code != '') {
                    $nl_result = $nlcheckout->BankCheckout($order_code, $total_amount, $bank_code,
                        $payment_type, $order_description, $tax_amount, $fee_shipping,
                        $discount_amount, $return_url, $cancel_url, $buyer_fullname, $buyer_email,
                        $buyer_mobile, $buyer_address, $array_items);
                } elseif ($payment_method == "NH_OFFLINE") {
                    $nl_result = $nlcheckout->officeBankCheckout($order_code, $total_amount, $bank_code, $payment_type,
                        $order_description, $tax_amount, $fee_shipping, $discount_amount, $return_url, $cancel_url,
                        $buyer_fullname, $buyer_email, $buyer_mobile, $buyer_address, $array_items);
                } elseif ($payment_method == "ATM_OFFLINE") {
                    $nl_result = $nlcheckout->BankOfflineCheckout($order_code, $total_amount, $bank_code, $payment_type,
                        $order_description, $tax_amount, $fee_shipping, $discount_amount, $return_url, $cancel_url,
                        $buyer_fullname, $buyer_email, $buyer_mobile, $buyer_address, $array_items);
                } elseif ($payment_method == "IB_ONLINE") {
                    $nl_result = $nlcheckout->IBCheckout($order_code, $total_amount, $bank_code, $payment_type,
                        $order_description, $tax_amount, $fee_shipping, $discount_amount, $return_url, $cancel_url,
                        $buyer_fullname, $buyer_email, $buyer_mobile, $buyer_address, $array_items);
                } elseif ($payment_method == "CREDIT_CARD_PREPAID") {
                    $nl_result = $nlcheckout->PrepaidVisaCheckout($order_code, $total_amount, $payment_type, $order_description,
                        $tax_amount, $fee_shipping, $discount_amount, $return_url, $cancel_url, $buyer_fullname,
                        $buyer_email, $buyer_mobile, $buyer_address, $array_items, $bank_code);
                }
                if (!empty($nl_result) && (string)$nl_result->error_code == '00') {
                    //Cập nhât order với token  $nl_result->token để sử dụng check hoàn thành sau này
                    $settings = get_option('woocommerce_nganluong_settings', null);
                    $old_status = 'wc-' . $order->get_status();
                    $note = ': Thanh toán trực tuyến qua Ngân Lượng.';

                    if (!empty($payment_method)) {
                        $note .= ' Phương thức thanh toán : ' . $nlcheckout->GetStringPaymentMethod((string)$payment_method);
                    } else {
                        $note .= '';
                    }
                    if (!empty($bank_code)) {
                        $note .= ' . Thanh toán qua ngân hàng : ' . $nlcheckout->GetStringBankCode((string)$bank_code);
                    } else {
                        $note .= '';
                    }
                    // $order->update_status($new_order_status);
//                    $order->add_order_note(sprintf(__('Cập nhật trạng thái từ %1$s thành %2$s.' . $note, 'woocommerce'), wc_get_order_status_name($old_status), wc_get_order_status_name($new_order_status)), 0, false);
                    $order->add_order_note(sprintf(__('Phương thức thanh toán ' . $note, 'woocommerce')), 0, false);
                    $new_status = $nlcheckout->GetErrorMessage((string)$nl_result->transaction_status);
                    self::log('Cập nhật đơn hàng ID: ' . $order_id . ' trạng thái ' . $new_status);
                    return (string)$nl_result->checkout_url;
                } else {
                    echo $nl_result->error_message;
                }
            } else {
                echo "<h3> Bạn chưa nhập đủ thông tin khách hàng </h3>";
            }
        }

        function showMessage($content)
        {
            return '<div class="box ' . $this->msg['class'] . '-box">' . $this->msg['message'] . '</div>' . $content;
        }

        // get all pages
        function get_pages($title = false, $indent = true)
        {
            $wp_pages = get_pages('sort_column=menu_order');
            $page_list = array();
            if ($title)
                $page_list[] = $title;
            foreach ($wp_pages as $page) {
                $prefix = '';
                // show indented child pages?
                if ($indent) {
                    $has_parent = $page->post_parent;
                    while ($has_parent) {
                        $prefix .= ' - ';
                        $next_page = get_page($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }
                // add to page list array array
                $page_list[$page->ID] = $prefix . $page->post_title;
            }
            return $page_list;
        }

        /* Hàm thực hiện xác minh tính đúng đắn của các tham số trả về từ nganluong.vn */

        public static function nganluong_return_handler($order_id)
        {
            global $woocommerce;
            // This probably could be written better
            if ($_REQUEST['error_code'] == '00' && isset($_REQUEST['token'])) {
                self::log($_SERVER['REMOTE_ADDR'] . json_encode(@$_REQUEST));
                $settings = get_option('woocommerce_nganluong_settings', null);
                $nlcheckout = new NL_CheckOutV3($settings['merchant_site_code'], $settings['secure_pass'], $settings['merchant_id'], $settings['nganluong_url']);
                $nl_result = $nlcheckout->GetTransactionDetail($_GET['token']);
                if ((string)$nl_result->transaction_status == '00') {
                    $order = new WC_Order((int)$nl_result->order_code);
                    // phương thức
                    // số dư ví
                    // Xác thực mã của chủ web với mã trả về từ nganluong.vn
                    // status tạm giữ 2 ngày nên để chế độ pending
//                    $new_order_status = $settings['status_order'];
                    // tuy nhiên ta sẽ fix cứng status này là completed
                    $new_order_status = 'wc-completed';
                    $old_status = 'wc-' . $order->get_status();
//                    Kiểu giao dịch: 1 - Ngay; 2 - Tạm giữ; Nếu không truyền hoặc bằng rỗng thì lấy theo chính sách của NganLuong.vn
                    if ($new_order_status !== $old_status) {
                        $note = ' Thanh toán trực tuyến qua Ngân Lượng ';
                        if ((string)$nl_result->payment_type[0] === '2' || $nl_result->payment_type[0] === 2) {
                            $note .= ' với hình thức thanh toán tạm giữ';
                        } else if ((string)$nl_result->payment_type[0] === '1' || $nl_result->payment_type[0] === 1) {
                            $note .= ' với hình thức thanh toán ngay';
                        }else{
                            $note .= ' tự động lấy theo chính sách của Ngân Lượng.';
                        }
                        $note .= ' . Mã thanh toán: ' . (string)$nl_result->transaction_id;
                        $order->update_status($new_order_status);
                        $order->add_order_note(sprintf(__('Cập nhật trạng thái từ %1$s thành %2$s.' . $note, 'woocommerce'), wc_get_order_status_name($old_status), wc_get_order_status_name($new_order_status)), 0, false);
                        $new_status = $nlcheckout->GetErrorMessage((string)$nl_result->transaction_status);
                        self::log('Cập nhật đơn hàng ID: ' . (string)$nl_result->order_code . ' trạng thái ' . $new_status);
                    }
                    // Remove cart
                    $woocommerce->cart->empty_cart();
                    // Empty awaiting payment session
                    unset($_SESSION['order_awaiting_payment']);
                    wp_redirect(get_permalink($settings['redirect_page_id']));
                    exit;
                }
            }
        }

    }

    class NL_CheckOutV3
    {
        public $url_api = 'https://sandbox.nganluong.vn:8088/nl30/checkout.api.nganluong.post.php';
        public $merchant_id = '';
        public $merchant_password = '';
        public $receiver_email = '';
        public $cur_code = 'vnd';

        function __construct($merchant_id, $merchant_password, $receiver_email, $url_api)
        {
            $this->version = '3.1';
            $this->url_api = $url_api;
            $this->merchant_id = $merchant_id;
            $this->merchant_password = $merchant_password;
            $this->receiver_email = $receiver_email;
        }

        function GetTransactionDetail($token)
        {
            ###################### BEGIN #####################
            $settings = get_option('woocommerce_nganluong_settings', null);
            $params = array(
                'merchant_id' => $settings['merchant_id'],
                'merchant_password' => MD5($settings['merchant_pass']),
                'version' => $this->version,
                'function' => 'GetTransactionDetail',
                'token' => $token
            );

            $post_field = '';
            foreach ($params as $key => $value) {
                if ($post_field != '') $post_field .= '&';
                $post_field .= $key . "=" . $value;
            }
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->url_api);
            curl_setopt($ch, CURLOPT_ENCODING, 'UTF-8');
            curl_setopt($ch, CURLOPT_VERBOSE, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_field);
            $result = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);

            if ($result != '' && $status == 200) {
                $nl_result = simplexml_load_string($result);
                return $nl_result;
            }

            return false;
            ###################### END #####################

        }


        /*

        Hàm lấy link thanh toán bằng thẻ visa
        ===============================
        Tham số truyền vào bắt buộc phải có
                    order_code
                    total_amount
                    payment_method

                    buyer_fullname
                    buyer_email
                    buyer_mobile
        ===============================
            $array_items mảng danh sách các item name theo quy tắc
            item_name1
            item_quantity1
            item_amount1
            item_url1
            .....
            payment_type Kiểu giao dịch: 1 - Ngay; 2 - Tạm giữ; Nếu không truyền hoặc bằng rỗng thì lấy theo chính sách của NganLuong.vn
         */
        function VisaCheckout($order_code, $total_amount, $payment_type, $order_description, $tax_amount,
                              $fee_shipping, $discount_amount, $return_url, $cancel_url, $buyer_fullname, $buyer_email, $buyer_mobile,
                              $buyer_address, $array_items, $bank_code)
        {
            $params = array(
                'cur_code' => $this->cur_code,
                'function' => 'SetExpressCheckout',
                'version' => $this->version,
                'merchant_id' => $this->merchant_id, //Mã merchant khai báo tại NganLuong.vn
                'receiver_email' => $this->receiver_email,
                'merchant_password' => MD5($this->merchant_password), //MD5(Mật khẩu kết nối giữa merchant và NganLuong.vn)
                'order_code' => $order_code, //Mã hóa đơn do website bán hàng sinh ra
                'total_amount' => $total_amount, //Tổng số tiền của hóa đơn
                'payment_method' => 'VISA', //Phương thức thanh toán, nhận một trong các giá trị 'VISA','ATM_ONLINE', 'ATM_OFFLINE' hoặc 'NH_OFFLINE'
                'bank_code' => $bank_code, //Phương thức thanh toán, nhận một trong các giá trị 'VISA','ATM_ONLINE', 'ATM_OFFLINE' hoặc 'NH_OFFLINE'
                'payment_type' => $payment_type, //Kiểu giao dịch: 1 - Ngay; 2 - Tạm giữ; Nếu không truyền hoặc bằng rỗng thì lấy theo chính sách của NganLuong.vn
                'order_description' => $order_description, //Mô tả đơn hàng
                'tax_amount' => $tax_amount, //Tổng số tiền thuế
                'fee_shipping' => $fee_shipping, //Phí vận chuyển
                'discount_amount' => $discount_amount, //Số tiền giảm giá
                'return_url' => $return_url, //Địa chỉ website nhận thông báo giao dịch thành công
                'cancel_url' => $cancel_url, //Địa chỉ website nhận "Hủy giao dịch"
                'buyer_fullname' => $buyer_fullname, //Tên người mua hàng
                'buyer_email' => $buyer_email, //Địa chỉ Email người mua
                'buyer_mobile' => $buyer_mobile, //Điện thoại người mua
                'buyer_address' => $buyer_address, //Địa chỉ người mua hàng
                'total_item' => count($array_items)
            );
            $post_field = '';
            foreach ($params as $key => $value) {
                if ($post_field != '') $post_field .= '&';
                $post_field .= $key . "=" . $value;
            }
            if (count($array_items) > 0) {
                foreach ($array_items as $array_item) {
                    foreach ($array_item as $key => $value) {
                        if ($post_field != '') $post_field .= '&';
                        $post_field .= $key . "=" . $value;
                    }
                }
            }
            //die($post_field);

            $nl_result = $this->CheckoutCall($post_field);
            return $nl_result;
        }

        function PrepaidVisaCheckout($order_code, $total_amount, $payment_type, $order_description, $tax_amount, $fee_shipping, $discount_amount, $return_url, $cancel_url, $buyer_fullname, $buyer_email, $buyer_mobile, $buyer_address, $array_items, $bank_code)
        {
            $params = array(
                'cur_code' => $this->cur_code,
                'function' => Config::$_FUNCTION,
                'version' => Config::$_VERSION,
                'merchant_id' => $this->merchant_id, //Mã merchant khai báo tại NganLuong.vn
                'receiver_email' => $this->receiver_email,
                'merchant_password' => MD5($this->merchant_password), //MD5(Mật khẩu kết nối giữa merchant và NganLuong.vn)
                'order_code' => $order_code, //Mã hóa đơn do website bán hàng sinh ra
                'total_amount' => $total_amount, //Tổng số tiền của hóa đơn
                'payment_method' => 'CREDIT_CARD_PREPAID', //Phương thức thanh toán, nhận một trong các giá trị 'VISA','ATM_ONLINE', 'ATM_OFFLINE' hoặc 'NH_OFFLINE'
                'bank_code' => $bank_code, //Phương thức thanh toán, nhận một trong các giá trị 'VISA','ATM_ONLINE', 'ATM_OFFLINE' hoặc 'NH_OFFLINE'
                'payment_type' => $payment_type, //Kiểu giao dịch: 1 - Ngay; 2 - Tạm giữ; Nếu không truyền hoặc bằng rỗng thì lấy theo chính sách của NganLuong.vn
                'order_description' => $order_description, //Mô tả đơn hàng
                'tax_amount' => $tax_amount, //Tổng số tiền thuế
                'fee_shipping' => $fee_shipping, //Phí vận chuyển
                'discount_amount' => $discount_amount, //Số tiền giảm giá
                'return_url' => $return_url, //Địa chỉ website nhận thông báo giao dịch thành công
                'cancel_url' => $cancel_url, //Địa chỉ website nhận "Hủy giao dịch"
                'buyer_fullname' => $buyer_fullname, //Tên người mua hàng
                'buyer_email' => $buyer_email, //Địa chỉ Email người mua
                'buyer_mobile' => $buyer_mobile, //Điện thoại người mua
                'buyer_address' => $buyer_address, //Địa chỉ người mua hàng
                'total_item' => count($array_items)
            );
            //var_dump($params); exit;
            $post_field = '';
            foreach ($params as $key => $value) {
                if ($post_field != '')
                    $post_field .= '&';
                $post_field .= $key . "=" . $value;
            }
            if (count($array_items) > 0) {
                foreach ($array_items as $array_item) {
                    foreach ($array_item as $key => $value) {
                        if ($post_field != '') {
                            $post_field .= '&';
                        } else {
                            $post_field .= $key . "=" . $value;
                        }
                    }
                }
            }
            //die($post_field);

            $nl_result = $this->CheckoutCall($post_field);
            return $nl_result;
        }

        /*
        Hàm lấy link thanh toán qua ngân hàng
        ===============================
        Tham số truyền vào bắt buộc phải có
                    order_code
                    total_amount
                    bank_code // Theo bảng mã ngân hàng

                    buyer_fullname
                    buyer_email
                    buyer_mobile
        ===============================

            $array_items mảng danh sách các item name theo quy tắc
            item_name1
            item_quantity1
            item_amount1
            item_url1
            .....
            payment_type Kiểu giao dịch: 1 - Ngay; 2 - Tạm giữ; Nếu không truyền hoặc bằng rỗng thì lấy theo chính sách của NganLuong.vn

        */
        function BankCheckout($order_code, $total_amount, $bank_code, $payment_type, $order_description, $tax_amount,
                              $fee_shipping, $discount_amount, $return_url, $cancel_url, $buyer_fullname, $buyer_email, $buyer_mobile,
                              $buyer_address, $array_items)
        {
            $params = array(
                'cur_code' => $this->cur_code,
                'function' => 'SetExpressCheckout',
                'version' => $this->version,
                'merchant_id' => $this->merchant_id, //Mã merchant khai báo tại NganLuong.vn
                'receiver_email' => $this->receiver_email,
                'merchant_password' => MD5($this->merchant_password), //MD5(Mật khẩu kết nối giữa merchant và NganLuong.vn)
                'order_code' => $order_code, //Mã hóa đơn do website bán hàng sinh ra
                'total_amount' => $total_amount, //Tổng số tiền của hóa đơn
                'payment_method' => 'ATM_ONLINE', //Phương thức thanh toán, nhận một trong các giá trị 'ATM_ONLINE', 'ATM_OFFLINE' hoặc 'NH_OFFLINE'
                'bank_code' => $bank_code, //Mã Ngân hàng
                'payment_type' => $payment_type, //Kiểu giao dịch: 1 - Ngay; 2 - Tạm giữ; Nếu không truyền hoặc bằng rỗng thì lấy theo chính sách của NganLuong.vn
                'order_description' => $order_description, //Mô tả đơn hàng
                'tax_amount' => $tax_amount, //Tổng số tiền thuế
                'fee_shipping' => $fee_shipping, //Phí vận chuyển
                'discount_amount' => $discount_amount, //Số tiền giảm giá
                'return_url' => $return_url, //Địa chỉ website nhận thông báo giao dịch thành công
                'cancel_url' => $cancel_url, //Địa chỉ website nhận "Hủy giao dịch"
                'buyer_fullname' => $buyer_fullname, //Tên người mua hàng
                'buyer_email' => $buyer_email, //Địa chỉ Email người mua
                'buyer_mobile' => $buyer_mobile, //Điện thoại người mua
                'buyer_address' => $buyer_address, //Địa chỉ người mua hàng
                'total_item' => count($array_items)
            );

            $post_field = '';
            foreach ($params as $key => $value) {
                if ($post_field != '') $post_field .= '&';
                $post_field .= $key . "=" . $value;
            }
            if (count($array_items) > 0) {
                foreach ($array_items as $array_item) {
                    foreach ($array_item as $key => $value) {
                        if ($post_field != '') {
                            $post_field .= '&';
                        } else {
                            $post_field .= $key . "=" . $value;
                        }
                    }
                }
            }
            //$post_field="function=SetExpressCheckout&version=3.1&merchant_id=24338&receiver_email=payment@hellochao.com&merchant_password=5b39df2b8f3275d1c8d1ea982b51b775&order_code=macode_oerder123&total_amount=2000&payment_method=ATM_ONLINE&bank_code=ICB&payment_type=&order_description=&tax_amount=0&fee_shipping=0&discount_amount=0&return_url=http://localhost/testcode/nganluong.vn/checkoutv3/payment_success.php&cancel_url=http://nganluong.vn&buyer_fullname=Test&buyer_email=saritvn@gmail.com&buyer_mobile=0909224002&buyer_address=&total_item=1&item_name1=Product name&item_quantity1=1&item_amount1=2000&item_url1=http://nganluong.vn/"	;
            //echo $post_field;
            //die;
            $nl_result = $this->CheckoutCall($post_field);

            return $nl_result;
        }

        function BankOfflineCheckout($order_code, $total_amount, $bank_code, $payment_type, $order_description, $tax_amount,
                                     $fee_shipping, $discount_amount, $return_url, $cancel_url, $buyer_fullname, $buyer_email, $buyer_mobile,
                                     $buyer_address, $array_items)
        {
            $params = array(
                'cur_code' => $this->cur_code,
                'function' => 'SetExpressCheckout',
                'version' => $this->version,
                'merchant_id' => $this->merchant_id, //Mã merchant khai báo tại NganLuong.vn
                'receiver_email' => $this->receiver_email,
                'merchant_password' => MD5($this->merchant_password), //MD5(Mật khẩu kết nối giữa merchant và NganLuong.vn)
                'order_code' => $order_code, //Mã hóa đơn do website bán hàng sinh ra
                'total_amount' => $total_amount, //Tổng số tiền của hóa đơn
                'payment_method' => 'ATM_OFFLINE', //Phương thức thanh toán, nhận một trong các giá trị 'ATM_ONLINE', 'ATM_OFFLINE' hoặc 'NH_OFFLINE'
                'bank_code' => $bank_code, //Mã Ngân hàng
                'payment_type' => $payment_type, //Kiểu giao dịch: 1 - Ngay; 2 - Tạm giữ; Nếu không truyền hoặc bằng rỗng thì lấy theo chính sách của NganLuong.vn
                'order_description' => $order_description, //Mô tả đơn hàng
                'tax_amount' => $tax_amount, //Tổng số tiền thuế
                'fee_shipping' => $fee_shipping, //Phí vận chuyển
                'discount_amount' => $discount_amount, //Số tiền giảm giá
                'return_url' => $return_url, //Địa chỉ website nhận thông báo giao dịch thành công
                'cancel_url' => $cancel_url, //Địa chỉ website nhận "Hủy giao dịch"
                'buyer_fullname' => $buyer_fullname, //Tên người mua hàng
                'buyer_email' => $buyer_email, //Địa chỉ Email người mua
                'buyer_mobile' => $buyer_mobile, //Điện thoại người mua
                'buyer_address' => $buyer_address, //Địa chỉ người mua hàng
                'total_item' => count($array_items)
            );

            $post_field = '';
            foreach ($params as $key => $value) {
                if ($post_field != '') $post_field .= '&';
                $post_field .= $key . "=" . $value;
            }
            if (count($array_items) > 0) {
                foreach ($array_items as $array_item) {
                    foreach ($array_item as $key => $value) {
                        if ($post_field != '') {
                            $post_field .= '&';
                        } else {
                            $post_field .= $key . "=" . $value;
                        }
                    }
                }
            }
            //$post_field="function=SetExpressCheckout&version=3.1&merchant_id=24338&receiver_email=payment@hellochao.com&merchant_password=5b39df2b8f3275d1c8d1ea982b51b775&order_code=macode_oerder123&total_amount=2000&payment_method=ATM_ONLINE&bank_code=ICB&payment_type=&order_description=&tax_amount=0&fee_shipping=0&discount_amount=0&return_url=http://localhost/testcode/nganluong.vn/checkoutv3/payment_success.php&cancel_url=http://nganluong.vn&buyer_fullname=Test&buyer_email=saritvn@gmail.com&buyer_mobile=0909224002&buyer_address=&total_item=1&item_name1=Product name&item_quantity1=1&item_amount1=2000&item_url1=http://nganluong.vn/"	;
            //echo $post_field;
            //die;
            $nl_result = $this->CheckoutCall($post_field);

            return $nl_result;
        }


        function officeBankCheckout($order_code, $total_amount, $bank_code, $payment_type, $order_description, $tax_amount,
                                    $fee_shipping, $discount_amount, $return_url, $cancel_url, $buyer_fullname, $buyer_email, $buyer_mobile,
                                    $buyer_address, $array_items)
        {
            $params = array(
                'cur_code' => $this->cur_code,
                'function' => 'SetExpressCheckout',
                'version' => $this->version,
                'merchant_id' => $this->merchant_id, //Mã merchant khai báo tại NganLuong.vn
                'receiver_email' => $this->receiver_email,
                'merchant_password' => MD5($this->merchant_password), //MD5(Mật khẩu kết nối giữa merchant và NganLuong.vn)
                'order_code' => $order_code, //Mã hóa đơn do website bán hàng sinh ra
                'total_amount' => $total_amount, //Tổng số tiền của hóa đơn
                'payment_method' => 'NH_OFFLINE', //Phương thức thanh toán, nhận một trong các giá trị 'ATM_ONLINE', 'ATM_OFFLINE' hoặc 'NH_OFFLINE'
                'bank_code' => $bank_code, //Mã Ngân hàng
                'payment_type' => $payment_type, //Kiểu giao dịch: 1 - Ngay; 2 - Tạm giữ; Nếu không truyền hoặc bằng rỗng thì lấy theo chính sách của NganLuong.vn
                'order_description' => $order_description, //Mô tả đơn hàng
                'tax_amount' => $tax_amount, //Tổng số tiền thuế
                'fee_shipping' => $fee_shipping, //Phí vận chuyển
                'discount_amount' => $discount_amount, //Số tiền giảm giá
                'return_url' => $return_url, //Địa chỉ website nhận thông báo giao dịch thành công
                'cancel_url' => $cancel_url, //Địa chỉ website nhận "Hủy giao dịch"
                'buyer_fullname' => $buyer_fullname, //Tên người mua hàng
                'buyer_email' => $buyer_email, //Địa chỉ Email người mua
                'buyer_mobile' => $buyer_mobile, //Điện thoại người mua
                'buyer_address' => $buyer_address, //Địa chỉ người mua hàng
                'total_item' => count($array_items)
            );

            $post_field = '';
            foreach ($params as $key => $value) {
                if ($post_field != '') $post_field .= '&';
                $post_field .= $key . "=" . $value;
            }
            if (count($array_items) > 0) {
                foreach ($array_items as $array_item) {
                    foreach ($array_item as $key => $value) {
                        if ($post_field != '') {
                            $post_field .= '&';
                        } else {
                            $post_field .= $key . "=" . $value;
                        }
                    }
                }
            }
            //$post_field="function=SetExpressCheckout&version=3.1&merchant_id=24338&receiver_email=payment@hellochao.com&merchant_password=5b39df2b8f3275d1c8d1ea982b51b775&order_code=macode_oerder123&total_amount=2000&payment_method=ATM_ONLINE&bank_code=ICB&payment_type=&order_description=&tax_amount=0&fee_shipping=0&discount_amount=0&return_url=http://localhost/testcode/nganluong.vn/checkoutv3/payment_success.php&cancel_url=http://nganluong.vn&buyer_fullname=Test&buyer_email=saritvn@gmail.com&buyer_mobile=0909224002&buyer_address=&total_item=1&item_name1=Product name&item_quantity1=1&item_amount1=2000&item_url1=http://nganluong.vn/"	;
            //echo $post_field;
            //die;
            $nl_result = $this->CheckoutCall($post_field);

            return $nl_result;
        }

        /*

        Hàm lấy link thanh toán tại văn phòng ngân lượng

        ===============================
        Tham số truyền vào bắt buộc phải có
                    order_code
                    total_amount
                    bank_code // HN hoặc HCM

                    buyer_fullname
                    buyer_email
                    buyer_mobile
        ===============================

            $array_items mảng danh sách các item name theo quy tắc
            item_name1
            item_quantity1
            item_amount1
            item_url1
            .....
            payment_type Kiểu giao dịch: 1 - Ngay; 2 - Tạm giữ; Nếu không truyền hoặc bằng rỗng thì lấy theo chính sách của NganLuong.vn

        */
        function TTVPCheckout($order_code, $total_amount, $bank_code, $payment_type, $order_description, $tax_amount,
                              $fee_shipping, $discount_amount, $return_url, $cancel_url, $buyer_fullname, $buyer_email, $buyer_mobile,
                              $buyer_address, $array_items)
        {
            $params = array(
                'cur_code' => $this->cur_code,
                'function' => 'SetExpressCheckout',
                'version' => $this->version,
                'merchant_id' => $this->merchant_id, //Mã merchant khai báo tại NganLuong.vn
                'receiver_email' => $this->receiver_email,
                'merchant_password' => MD5($this->merchant_password), //MD5(Mật khẩu kết nối giữa merchant và NganLuong.vn)
                'order_code' => $order_code, //Mã hóa đơn do website bán hàng sinh ra
                'total_amount' => $total_amount, //Tổng số tiền của hóa đơn
                'payment_method' => 'ATM_ONLINE', //Phương thức thanh toán, nhận một trong các giá trị 'ATM_ONLINE', 'ATM_OFFLINE' hoặc 'NH_OFFLINE'
                'bank_code' => $bank_code, //Mã Ngân hàng
                'payment_type' => $payment_type, //Kiểu giao dịch: 1 - Ngay; 2 - Tạm giữ; Nếu không truyền hoặc bằng rỗng thì lấy theo chính sách của NganLuong.vn
                'order_description' => $order_description, //Mô tả đơn hàng
                'tax_amount' => $tax_amount, //Tổng số tiền thuế
                'fee_shipping' => $fee_shipping, //Phí vận chuyển
                'discount_amount' => $discount_amount, //Số tiền giảm giá
                'return_url' => $return_url, //Địa chỉ website nhận thông báo giao dịch thành công
                'cancel_url' => $cancel_url, //Địa chỉ website nhận "Hủy giao dịch"
                'buyer_fullname' => $buyer_fullname, //Tên người mua hàng
                'buyer_email' => $buyer_email, //Địa chỉ Email người mua
                'buyer_mobile' => $buyer_mobile, //Điện thoại người mua
                'buyer_address' => $buyer_address, //Địa chỉ người mua hàng
                'total_item' => count($array_items)
            );

            $post_field = '';
            foreach ($params as $key => $value) {
                if ($post_field != '') $post_field .= '&';
                $post_field .= $key . "=" . $value;
            }
            if (count($array_items) > 0) {
                foreach ($array_items as $array_item) {
                    foreach ($array_item as $key => $value) {
                        if ($post_field != '') {
                            $post_field .= '&';
                        } else {
                            $post_field .= $key . "=" . $value;
                        }
                    }
                }
            }

            $nl_result = $this->CheckoutCall($post_field);
            return $nl_result;
        }

        /*

        Hàm lấy link thanh toán dùng số dư ví ngân lượng
        ===============================
        Tham số truyền vào bắt buộc phải có
                    order_code
                    total_amount
                    payment_method

                    buyer_fullname
                    buyer_email
                    buyer_mobile
        ===============================
            $array_items mảng danh sách các item name theo quy tắc
            item_name1
            item_quantity1
            item_amount1
            item_url1
            .....

            payment_type Kiểu giao dịch: 1 - Ngay; 2 - Tạm giữ; Nếu không truyền hoặc bằng rỗng thì lấy theo chính sách của NganLuong.vn
         */
        function NLCheckout($order_code, $total_amount, $payment_type, $order_description, $tax_amount,
                            $fee_shipping, $discount_amount, $return_url, $cancel_url, $buyer_fullname,
                            $buyer_email, $buyer_mobile, $buyer_address, $array_items)
        {
            $params = array(
                'cur_code' => $this->cur_code,
                'function' => 'SetExpressCheckout',
                'version' => $this->version,
                'merchant_id' => $this->merchant_id, //Mã merchant khai báo tại NganLuong.vn
                'receiver_email' => $this->receiver_email,
                'merchant_password' => MD5($this->merchant_password), //MD5(Mật khẩu kết nối giữa merchant và NganLuong.vn)
                'order_code' => $order_code, //Mã hóa đơn do website bán hàng sinh ra
                'total_amount' => $total_amount, //Tổng số tiền của hóa đơn
                'payment_method' => 'NL', //Phương thức thanh toán
                'payment_type' => $payment_type, //Kiểu giao dịch: 1 - Ngay; 2 - Tạm giữ; Nếu không truyền hoặc bằng rỗng thì lấy theo chính sách của NganLuong.vn
                'order_description' => $order_description, //Mô tả đơn hàng
                'tax_amount' => $tax_amount, //Tổng số tiền thuế
                'fee_shipping' => $fee_shipping, //Phí vận chuyển
                'discount_amount' => $discount_amount, //Số tiền giảm giá
                'return_url' => $return_url, //Địa chỉ website nhận thông báo giao dịch thành công
                'cancel_url' => $cancel_url, //Địa chỉ website nhận "Hủy giao dịch"
                'buyer_fullname' => $buyer_fullname, //Tên người mua hàng
                'buyer_email' => $buyer_email, //Địa chỉ Email người mua
                'buyer_mobile' => $buyer_mobile, //Điện thoại người mua
                'buyer_address' => $buyer_address, //Địa chỉ người mua hàng
                'total_item' => $array_items['totalItems'] //Tổng số sản phẩm trong đơn hàng
            );
            $post_field = '';
            foreach ($params as $key => $value) {
                if ($post_field != '') $post_field .= '&';
                $post_field .= $key . "=" . $value;
            }
            if (count($array_items) > 0) {
                foreach ($array_items as $array_item) {
                    foreach ($array_item as $key => $value) {
                        if ($post_field != '') {
                            $post_field .= '&';
                        } else {
                            $post_field .= $key . "=" . $value;
                        }
                    }
                }
            }

            //die($post_field);
            $nl_result = $this->CheckoutCall($post_field);
            return $nl_result;
        }

        function IBCheckout($order_code, $total_amount, $bank_code, $payment_type, $order_description, $tax_amount, $fee_shipping, $discount_amount, $return_url, $cancel_url, $buyer_fullname, $buyer_email, $buyer_mobile, $buyer_address, $array_items)
        {
            $params = array(
                'cur_code' => $this->cur_code,
                'function' => 'SetExpressCheckout',
                'version' => $this->version,
                'merchant_id' => $this->merchant_id, //Mã merchant khai báo tại NganLuong.vn
                'receiver_email' => $this->receiver_email,
                'merchant_password' => MD5($this->merchant_password), //MD5(Mật khẩu kết nối giữa merchant và NganLuong.vn)
                'order_code' => $order_code, //Mã hóa đơn do website bán hàng sinh ra
                'total_amount' => $total_amount, //Tổng số tiền của hóa đơn
                'payment_method' => 'IB_ONLINE', //Phương thức thanh toán
                'bank_code' => $bank_code,
                'payment_type' => $payment_type, //Kiểu giao dịch: 1 - Ngay; 2 - Tạm giữ; Nếu không truyền hoặc bằng rỗng thì lấy theo chính sách của NganLuong.vn
                'order_description' => $order_description, //Mô tả đơn hàng
                'tax_amount' => $tax_amount, //Tổng số tiền thuế
                'fee_shipping' => $fee_shipping, //Phí vận chuyển
                'discount_amount' => $discount_amount, //Số tiền giảm giá
                'return_url' => $return_url, //Địa chỉ website nhận thông báo giao dịch thành công
                'cancel_url' => $cancel_url, //Địa chỉ website nhận "Hủy giao dịch"
                'buyer_fullname' => $buyer_fullname, //Tên người mua hàng
                'buyer_email' => $buyer_email, //Địa chỉ Email người mua
                'buyer_mobile' => $buyer_mobile, //Điện thoại người mua
                'buyer_address' => $buyer_address, //Địa chỉ người mua hàng
                'total_item' => count($array_items) //Tổng số sản phẩm trong đơn hàng
            );
            $post_field = '';
            foreach ($params as $key => $value) {
                if ($post_field != '')
                    $post_field .= '&';
                $post_field .= $key . "=" . $value;
            }
            if (count($array_items) > 0) {
                foreach ($array_items as $array_item) {
                    foreach ($array_item as $key => $value) {
                        if ($post_field != '') {
                            $post_field .= '&';
                        } else {
                            $post_field .= $key . "=" . $value;
                        }
                    }
                }
            }

            //die($post_field);
            $nl_result = $this->CheckoutCall($post_field);
            return $nl_result;
        }

        function CheckoutCall($post_field)
        {

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->url_api);
            curl_setopt($ch, CURLOPT_ENCODING, 'UTF-8');
            curl_setopt($ch, CURLOPT_VERBOSE, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_field);
            $result = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);

            if ($result != '' && $status == 200) {
                $xml_result = str_replace('&', '&amp;', (string)$result);
                $nl_result = simplexml_load_string($xml_result);
                $nl_result->error_message = $this->GetErrorMessage($nl_result->error_code);
            } else {
                $nl_result->error_message = $error;
            }
            return $nl_result;

        }

        function GetErrorMessage($error_code)
        {
            $arrCode = array(
                '00' => 'Thành công',
                '99' => 'Lỗi chưa xác minh',
                '06' => 'Mã merchant không tồn tại hoặc bị khóa',
                '02' => 'Địa chỉ IP truy cập bị từ chối',
                '03' => 'Mã checksum không chính xác, truy cập bị từ chối',
                '04' => 'Tên hàm API do merchant gọi tới không hợp lệ (không tồn tại)',
                '05' => 'Sai version của API',
                '07' => 'Sai mật khẩu của merchant',
                '08' => 'Địa chỉ email tài khoản nhận tiền không tồn tại',
                '09' => 'Tài khoản nhận tiền đang bị phong tỏa giao dịch',
                '10' => 'Mã đơn hàng không hợp lệ',
                '11' => 'Số tiền giao dịch lớn hơn hoặc nhỏ hơn quy định',
                '12' => 'Loại tiền tệ không hợp lệ',
                '29' => 'Token không tồn tại',
                '80' => 'Không thêm được đơn hàng',
                '81' => 'Đơn hàng chưa được thanh toán',
                '110' => 'Địa chỉ email tài khoản nhận tiền không phải email chính',
                '111' => 'Tài khoản nhận tiền đang bị khóa',
                '113' => 'Tài khoản nhận tiền chưa cấu hình là người bán nội dung số',
                '114' => 'Giao dịch đang thực hiện, chưa kết thúc',
                '115' => 'Giao dịch bị hủy',
                '118' => 'tax_amount không hợp lệ',
                '119' => 'discount_amount không hợp lệ',
                '120' => 'fee_shipping không hợp lệ',
                '121' => 'return_url không hợp lệ',
                '122' => 'cancel_url không hợp lệ',
                '123' => 'items không hợp lệ',
                '124' => 'transaction_info không hợp lệ',
                '125' => 'quantity không hợp lệ',
                '126' => 'order_description không hợp lệ',
                '127' => 'affiliate_code không hợp lệ',
                '128' => 'time_limit không hợp lệ',
                '129' => 'buyer_fullname không hợp lệ',
                '130' => 'buyer_email không hợp lệ',
                '131' => 'buyer_mobile không hợp lệ',
                '132' => 'buyer_address không hợp lệ',
                '133' => 'total_item không hợp lệ',
                '134' => 'payment_method, bank_code không hợp lệ',
                '135' => 'Lỗi kết nối tới hệ thống ngân hàng',
                '140' => 'Đơn hàng không hỗ trợ thanh toán trả góp',);

            return $arrCode[(string)$error_code];
        }

        function GetStringBankCode($bank_code)
        {
            $arrCode = array(
                'NL' => 'Hệ thống thanh toán số dư ví Ngân Lượng',
                'BIDV' => 'Ngân hàng TMCP Đầu tư &amp; Phát triển Việt Nam',
                'VCB' => 'Ngân hàng TMCP Ngoại Thương Việt Nam',
                'DAB' => 'Ngân hàng Đông Á',
                'TCB' => 'Ngân hàng Kỹ Thương',
                'MB' => 'Ngân hàng Quân Đội',
                'VIB' => 'Ngân hàng Quốc tế',
                'ICB' => 'Ngân hàng Công Thương Việt Nam',
                'EXB' => 'Ngân hàng Xuất Nhập Khẩu',
                'ACB' => 'Ngân hàng Á Châu',
                'HDB' => 'Ngân hàng Phát triển Nhà TPHCM',
                'MSB' => 'Ngân hàng Hàng Hải',
                'NVB' => 'Ngân hàng Nam Việt',
                'VAB' => 'Ngân hàng Việt Á',
                'VPB' => 'Ngân Hàng Việt Nam Thịnh Vượng',
                'SCB' => 'Ngân hàng Sài Gòn Thương tín',
                'PGB' => 'Ngân hàng Xăng dầu Petrolimex',
                'GPB' => 'Ngân hàng TMCP Dầu khí Toàn Cầu',
                'AGB' => 'Ngân hàng Nông nghiệp và Phát triển nông thôn',
                'SGB' => 'Ngân hàng Sài Gòn Công Thương',
                'BAB' => 'Ngân hàng Bắc Á',
                'TPB' => 'Tiền phong bank',
                'NAB' => 'Ngân hàng Nam Á',
                'SHB' => 'Ngân hàng TMCP Sài Gòn - Hà Nội (SHB)',
                'OJB' => 'Ngân hàng TMCP Đại Dương (OceanBank)',
                'VISA' => 'Thẻ VISA',
                'MASTER' => 'Thẻ MASTER'
            );

            return $arrCode[(string)$bank_code];
        }

        function GetStringPaymentMethod($payment_method)
        {
            $arrCode = array(
                'NL' => 'Thanh toán qua số dư ví',
                'VISA' => 'Thanh toán bằng thẻ Visa, Master Card',
                'ATM_ONLINE' => 'Thanh toán online dùng thẻ ATM/Tài khoản ngân hàng trong nước',
                'ATM_OFFLINE' => 'Thanh toán chuyển khoản tại cây ATM',
                'NH_OFFLINE' => 'Thanh toán chuyển khoản hoặc nộp tiền tại quầy giao dịch NH',
                'TTVP' => 'Tiền mặt tại văn phòng NganLuong.vn',
                'CREDIT_CARD_PREPAID' => 'Thanh toán bằng thẻ visa, master trả trước',
                'IB_ONLINE' => 'Thanh toán bằng internet banking'
            );
            return $arrCode[(string)$payment_method];
        }


    }


    function woocommerce_add_NganLuong_gateway($methods)
    {
        $methods[] = 'WC_Gateway_NganLuong';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_NganLuong_gateway');
}


