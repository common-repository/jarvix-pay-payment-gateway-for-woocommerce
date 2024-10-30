<?php
/*
 Plugin Name: Jarvix Pay Payment Gateway for WooCommerce
 Plugin URI: https://jarvixpay.com/
 Description: Grow your business easier than ever.
 Version: 1.3.0
 Author: Jarvix Pay
 Author URI: https://www.aigniter.com/
 */
add_action('plugins_loaded', 'woocommerce_jarvixpay_init', 0);

function woocommerce_jarvixpay_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }
    
    class WC_jarvixpay extends WC_Payment_Gateway
    {
        public function __construct()
        {
            $this -> id = 'jarvixpay';
            $this -> method_title = 'Jarvix Pay';
            $this -> method_description = '接受 Visa / MasterCard / American Express 信用卡付款 (Jarvix Pay｜安全付款通道)';
            $this -> has_fields = false;

            $this -> init_form_fields();
            $this -> init_settings();

            $this -> title = 					$this -> settings['title'];
            $this -> description = 				$this -> settings['description'];
            $this -> testmode = 	 			'yes' === $this->get_option('testmode');
            $this -> force_redirect = 	 		'yes' === $this -> get_option('force_redirect');
            $this -> currency = 				$this -> getCurrCode(get_woocommerce_currency());
            
            // testmode
            $this -> payment_url = 				'https://api.customer.pay.jarvix.ai/online_payment/order';
            $this -> account_id = 				$this -> settings['account_id'];
            $this -> secure_hash_secret = 		$this -> settings['secure_hash_secret'];

            $this -> prefix = 					$this -> settings['prefix'];

            $this -> msg['message'] = "";
            $this -> msg['class'] = "";

            if (version_compare(WOOCOMMERCE_VERSION, '3.9.0', '>=')) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ));
            } else {
                add_action('woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ));
            }

            add_action('woocommerce_receipt_jarvixpay', array(&$this, 'receipt_page'));

            /* for callback/datafeed */
            add_action('woocommerce_api_jarvixpay', array( $this, 'gateway_response' ));
            add_action('the_title', 'woo_personalize_order_received_title', 10, 2);

            global $newaid;
            global $newpurl;
            global $newhash;
            global $newprefix;
            global $jarvixpay;
            global $newTestMode;
            global $g_force_redirect;
            
            $jarvixpay = $this -> id;
            $newaid = $this -> account_id;
            $newpurl = $this -> payment_url;
            $newhash = $this -> secure_hash_secret;
            $newprefix = $this -> prefix;
            $newTestMode = $this -> testmode;
            $g_force_redirect = $this -> force_redirect;
            
            // For testing sandbox envirnoment
            if ($newTestMode) {
                $newpurl = 'https://api.staging.pay.jarvix.ai/api/v2/online_payment/order';
                $newaid = '146b80f5-a220-4377-82ee-7dcc4cf72e59';
                $newhash = 'luuqP5K7CIMJvsPlUuqnzk2qWieKXGXR';
            }
        }

        public function generatePaymentSecureHash($orderRef, $price, $currency, $account_id, $secureHashSecret)
        {
            $buffer = $orderRef .''. $price .''. $currency .''. $account_id .''. $secureHashSecret;
            return hash('sha256', $buffer);
        }
        
        public function getCurrCode($woocommerce_currency)
        {
            $cur = '';
            
            switch ($woocommerce_currency) {
                case 'HKD':
                    $cur = 'hkd';
                    break;
                case 'USD':
                    $cur = 'usd';
                    break;
                case 'SGD':
                    $cur = 'sgd';
                    break;
                case 'CNY':
                    $cur = 'cny';
                    break;
                case 'JPY':
                    $cur = 'jpy';
                    break;
                case 'TWD':
                    $cur = 'twd';
                    break;
                case 'AUD':
                    $cur = 'aud';
                    break;
                case 'EUR':
                    $cur = 'eur';
                    break;
                case 'GBP':
                    $cur = 'gbp';
                    break;
                case 'CAD':
                    $cur = 'cad';
                    break;
                case 'MOP':
                    $cur = 'mop';
                    break;
                case 'PHP':
                    $cur = 'php';
                    break;
                case 'THB':
                    $cur = 'thb';
                    break;
                case 'MYR':
                    $cur = 'myr';
                    break;
                case 'IDR':
                    $cur = 'idr';
                    break;
                case 'KRW':
                    $cur = 'krw';
                    break;
                case 'SAR':
                    $cur = 'sar';
                    break;
                case 'NZD':
                    $cur = 'nzd';
                    break;
                case 'AED':
                    $cur = 'aed';
                    break;
                case 'BND':
                    $cur = 'bnd';
                    break;
                case 'VND':
                    $cur = 'vnd';
                    break;
                case 'INR':
                    $cur = 'inr';
                    break;
                default:
                    $cur = 'hkd';
            }

            return $cur;
        }
        
        public function init_form_fields()
        {
            $this -> form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable'),
                    'type' => 'checkbox',
                    'label' => __('Enable Jarvix Pay Payment Module.'),
                    'default' => 'yes'),
                'title' => array(
                    'title' => __('Title:'),
                    'type'=> 'text',
                    'description' => __('This controls the title which the user sees during checkout.'),
                    'default' => __('信用卡')),
                'description' => array(
                    'title' => __('Description:'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.'),
                    'default' => __('接受 Visa / MasterCard / American Express 信用卡付款 (Jarvix Pay｜安全付款通道)')),
                'testmode' => array(
                    'title'       => __('Test Mode'),
                    'type'        => 'checkbox',
                    'label' 	  => __('Enable Jarvix Pay Test Mode'),
                    'description' => __('This is the payment URL of Jarvix Pay testing envirnoment (via client post through browser).'),
                    'default'     => 'no',
                    ),
                'force_redirect' => array(
                    'title'       => __('Force redirection'),
                    'type'        => 'checkbox',
                    'label' 	  => __('Enable force redirection to merchant page'),
                    'description' => __('If force redirection is enabled, it can confirm that the order status can be updated normally. However, the customer will not receive Jarvix Pay email receipt.'),
                    'default'     => 'no',
                    ),
                'account_id' => array(
                    'title' => __('Account ID'),
                    'type' => 'text',
                    'description' => __('This is your Account ID in Jarvix Pay.</br>*** If you enable Jarvix Pay Test Mode, you do not need to fill this Account ID. ***'),
                    'default' => __('')),
                'secure_hash_secret' => array(
                    'title' => __('Secure Hash Secret'),
                    'type' => 'text',
                    'description' => __('The secret key from Jarvix Pay for the "Secure Hash".</br>*** If you enable Jarvix Pay Test Mode, you do not need to fill this Secure Hash Secret. ***'),
                    'default' => __('')),
                'prefix' => array(
                    'title' => __('Prefix'),
                    'type' => 'text',
                    'description' => __('Prefix for Order Reference No. Default: "jarvixpay" (Warning: Do not use hash "#" or dash "-")'),
                    'default' => __('jarvixpay'))
            );
        }

        public function admin_options()
        {
            echo '<h3>'.__('Jarvix Pay Payment Gateway for WooCommerce v1.3.0').'</h3>';
            echo '<p>'.__('
                <hr/>
				<br/>
				<h2>APPLY NOW</h2>
				<strong>You are required to register Jarvix Pay in order to get the credentials (Account ID & Secure Hash Secret).</strong>
				<br/>
                <br/>
				<h3>Method 1: Contact Us Directly - <a href="https://jarvixpay.com/" target="_blank">https://jarvixpay.com/</a><h3/> 
				<br/>
				<span>1. Click Help (幫助)<span/> 
				<br/>
				<span>2. Fill in the information into Contact Us (聯繫我們) Form<span/> 
				<br/>
				<span>3. Copy the following into How can we help you?（需要什麼協助？)</span>
				<br/>
				<span>Request more about how to register Jarvix Pay for WooCommerce.</span>
				</br>
				<span>想了解如何申請Jarvix Pay WooCommerce的帳號</span>
				</br>
				<h3>Method 2: Fill in Application Form - <a href="https://jarvixpay.com/en#signup" target="_blank">https://jarvixpay.com/en#signup</a><h3/> 
				</br>
				<span>Our team will contact you and provide credentials within 24 hours.<span/> 
				</br>
			').'</p>';
            echo '<hr/>';
            echo '<table class="form-table">';
            // Generate the HTML For the settings form.
            $this -> generate_settings_html();
            echo '</table>';
        }
        
        public function payment_fields()
        {
            if ($this -> description) {
                echo wpautop(wptexturize($this -> description));
            }
        }
        
        /**
         * Receipt Page
         **/
        public function receipt_page($order)
        {
            echo '<p>'.__('謝謝您的訂單，即將轉到 Jarvix Pay｜安全付款通道 以繼續付款。').'</p>';
            echo $this -> generate_jarvixpay_form($order);
        }

        /**
         * Generate jarvixpay button link
         **/
        public function generate_jarvixpay_form($order_id)
        {
            global $woocommerce;
            global $newpurl;
            global $newaid;
            global $newhash;
            global $g_force_redirect;

            $order = new WC_Order($order_id);

            //prefix handler
            if ($this -> prefix == '') {
                $orderRef = $order_id;
            } else {
                $orderRef = $this -> prefix . '-' . $order_id;
            }

            $success_url = esc_url($this->get_return_url($order));//get_permalink( get_option('woocommerce_thanks_page_id') );
            $notify_url = esc_url($order->get_cancel_order_url());//get_permalink( get_option('woocommerce_thanks_page_id') );
            $failed_url = esc_url($this->get_return_url($order));//get_permalink( get_option('woocommerce_checkout_page_id') );
            $cancel_url = esc_url($order->get_cancel_order_url());//get_permalink( get_option('woocommerce_checkout_page_id') );
            
            //TODO: for checksum
            $secureHash = '';
            if ($newhash != '') {
                $secureHash = $this -> generatePaymentSecureHash($orderRef, $order -> get_total(), $this -> currency, $newaid, $newhash);
            }
            $remarks = '';
            $cmd = 'redirect';
            $force_redirect = 'false';
            if ($g_force_redirect) {
                $force_redirect = 'true';
            }

            $jarvixpay_args = array(
                'reference_id' => 		$orderRef,
                'account_id' => 		$newaid,
                'price' => 				$order -> get_total(),
                'currency' => 			$this -> currency,
                'order_description' => 	$orderRef,
                'remark' => 			$remarks,
                'force_redirect' => 	$force_redirect,
                'success_url' => 		$success_url,
                'notify_url' => 		$notify_url,
                'failed_url' => 		$failed_url,
                'cancel_url' => 		$cancel_url,
                'cmd' => 				$cmd,
                'checksum' => 			$secureHash
              );

            $jarvixpay_args_array = array();
            foreach ($jarvixpay_args as $key => $value) {
                $jarvixpay_args_array[] = "<input type='hidden' name='$key' value='$value' size='30'/>";
            }

            return '<form action="' . $newpurl . '" accept-charset="UTF-8" class="" id="contestForm" method="POST">
            	' . implode('', $jarvixpay_args_array) . '

            		</form>
		            <script type="text/javascript">
						jQuery(function(){						
							setTimeout("contestForm();", 2500);
	    				});
						function contestForm(){
							jQuery("#contestForm").submit();
						}
	    			</script>
            ';
        }
        
        /**
         * Process the payment and return the result
        **/
        public function process_payment($order_id)
        {
            $order = new WC_Order($order_id);

            return array(
                'result' 	=> 'success',

                'redirect'	=> $order->get_checkout_payment_url(true)
            );
        }


        /**
         * Check for valid jarvixpay server datafeed
         **/
        public function gateway_response()
        {
            global $woocommerce;
            global $newpurl;
            global $newaid;
            global $newhash;

            $url = $newpurl.'/status/'. $orderRef .'?account_id='. $newaid .'&secret_key='. $newhash;
            echo $this -> generate_jarvixpay_form($get);
             
            $params = array(
                'headers' => array(
                'Content-Type => application/json',
                  'Accept => */*',
                  'Accept-Encoding => gzip, deflate',
                'Cache-Control => no-cache',
                  'Connection => keep-alive'
                )
            );
            $response = wp_remote_get($url, $params);
            $json = wp_remote_retrieve_body($response);

            $json_Class = json_decode($json);

            $json_Array = json_decode($json, true);

            $someObject = $json_Class ->{ 'data' };
        
            foreach ($someObject as $key => $value) {
                $jid = $value->id;
                $jreference_id = $value->reference_id;
                $jprice = $value->price;
                $jcurrency = $value->currency;
                $jcurrent_status = $value->current_status; // if succeeded, ...
                $jorder_description = $value->order_description;
                $jremarks = $value->remarks;
                $jsuccess_url = $value->success_url;
                $jcancel_url = $value->cancel_url;
                $jfailed_url = $value->failed_url;
                $jnotify_url = $value->notify_url;

                $jcreated_at = $value->created_at;
                $transaction = $value->{'transaction_history'};

                foreach ($transaction as $key => $value) {
                    $jtransaction_id = $value->transaction_id;
                    $jstatus = $value->status;
                    $jamount = $value->amount;
                }
            }

            exit();
        }
    }

    /**
    * Check Payment Method & Get Respond from Jarvix Pay server
    **/

    function woo_personalize_order_received_title($title, $id)
    {
        global $woocommerce;

        if (is_order_received_page() && get_the_ID() === $id) {
            global $wp;
            global $jarvixpay;
            $order_id  = apply_filters('woocommerce_thankyou_order_id', absint($wp->query_vars['order-received']));
            $order_key = apply_filters('woocommerce_thankyou_order_key', empty($_GET['key']) ? '' : wc_clean($_GET['key']));
        
            if ($order_id > 0) {
                $order = wc_get_order($order_id);
                if ($order->get_order_key() != $order_key) {
                    $order = false;
                }
            }

            if (isset($order)) {

            // Jarvixpay
                if ($order->get_payment_method() == $jarvixpay) {
                    global $newhash;
                    global $newprefix;
                    global $newaid;
                    global $newpurl;

                    $orderRef = $order->get_order_number() ;
                    $url = ''.$newpurl.'/status/'. $newprefix .'-'. $order_id .'?account_id='.$newaid.'&secret_key='.$newhash.'';
                
                    $params = array(
                    'headers' => array(
                        'Content-Type => application/json',
                        'Accept => */*',
                        'Accept-Encoding => gzip, deflate',
                        'Cache-Control => no-cache',
                        'Connection => keep-alive'
                    )
                );
                    $response = wp_remote_get($url, $params);
                    $json = wp_remote_retrieve_body($response);

                    $json_Class = json_decode($json);

                    if ($json_Class -> {'success'} == true) {
                        $data = $json_Class ->{ 'data' };
                        $id = $data ->id;
                        $reference_id = $data -> reference_id;
                        $price = $data -> price;
                        $current_status = $data -> current_status;
                    }
    
                    if ($current_status == 'succeeded') {
                        if (is_wc_endpoint_url('order-received')) {
                        }
                    
                        $order -> payment_complete(); // Order received & Completed
                    } elseif ($current_status != 'succeeded') {
                        if (is_wc_endpoint_url('order-received')) {
                        }
                    }
                }
            }
        }
        return $title;
    }


    /**
     * Remove Text from Received Page and customize the text
     **/
    add_filter('woocommerce_thankyou_order_received_text', 'remove_thankyou_text');

    function remove_thankyou_text()
    {
        // $remove_text = '已付款成功 | </h1><span style="color:#666">多謝光臨，我們會盡快處理你的訂單，如有任何問題歡迎聯絡我們。</span>';
        $remove_text = '';
        return $remove_text ;
    }

    /**
     * Reload Status from View Order page
     **/
    add_action('woocommerce_view_order', 'action_woocommerce_view_order', 5, 2);

    function action_woocommerce_view_order($id)
    {
        global $wp;
        $order = wc_get_order($wp->query_vars['view-order']);

        $json_Class =json_decode($order);
        
        if ($json_Class -> {'id'} != null) {
            $order_id = $json_Class -> id;
            $order_key = $json_Class -> order_key;
        }

        if ($order->get_status() == 'pending') {
        }
    }
    
    /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_jarvixpay_gateway($methods)
    {
        $methods[] = 'WC_jarvixpay';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_jarvixpay_gateway');
}

/**
 * Display Payment Method in View-Order
 **/
add_action('woocommerce_order_details_after_order_table', 'view_order_custom_payment_instruction', 5, 1); // Email notifications
function view_order_custom_payment_instruction($order)
{

    // Only for "on-hold" and "processing" order statuses and on 'view-order' page
    if (in_array($order->get_status(), array( 'on-hold', 'processing' )) && is_wc_endpoint_url('view-order')) {

        // The "Payment instructions" will be displayed with that:
        do_action('woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id());
    }
}
