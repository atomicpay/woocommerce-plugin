<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
/*
    Plugin Name: AtomicPay for WooCommerce
    Plugin URI:  https://github.com/atomicpay/woocommerce-plugin/releases/tag/v1.0.3
    Description: Enable your WooCommerce store to accept cryptocurrency payments with AtomicPay.
    Author:      AtomicPay
    Text Domain: AtomicPay.io
    Author URI:  https://github.com/atomicpay

    Version:           1.0.3
    License:           Copyright 2018 AtomicPay, MIT License
    License URI:       https://github.com/atomicpayserver/woocommerce-plugin/blob/master/LICENSE
    GitHub Plugin URI: https://github.com/atomicpayserver/woocommerce-plugin
 */


// Exit if accessed directly
if (false === defined('ABSPATH'))
{
    exit;
}

define("ATOMICPAY_VERSION", "1.0.3");

// Ensure that WooCommerce is loaded
add_action('plugins_loaded', 'woocommerce_atomicpay_init', 0);
register_activation_hook(__FILE__, 'woocommerce_atomicpay_activate');

function woocommerce_atomicpay_init()
{
    if (true === class_exists('WC_Gateway_AtomicPay'))
    {
        return;
    }

    if (false === class_exists('WC_Payment_Gateway'))
    {
        return;
    }

    class WC_Gateway_AtomicPay extends WC_Payment_Gateway
    {
    	 private $is_initialized = false;

        // Constructor for the WC Gateway.
        public function __construct()
        {
            // General settings
            $this->id                 = 'atomicpay';
            $this->icon               = plugin_dir_url(__FILE__).'templates/atomicpay.png';
            $this->has_fields         = false;
            $this->order_button_text  = __('Proceed to AtomicPay', 'atomicpay');
            $this->method_title       = 'AtomicPay';
            $this->method_description = 'AtomicPay allows you to accept cryptocurrency payments on your WooCommerce store.';

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title              = $this->get_option('title');
            $this->description        = $this->get_option('description');
            $this->order_statuses       = $this->get_option('order_statuses');
            $this->debug              = 'yes' === $this->get_option('debug', 'no');

            // Define API settings
            $this->api_accountID      = get_option('woocommerce_atomicpay_accountID');
            $this->api_privateKey     = get_option('woocommerce_atomicpay_privateKey');
            $this->api_publicKey      = get_option('woocommerce_atomicpay_publicKey');

            // Define debugging & informational settings
            $this->debug_php_version    = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
            $this->debug_plugin_version = constant("ATOMICPAY_VERSION");

            $this->log('AtomicPay for Woocommerce plugin called. Plugin is v' . $this->debug_plugin_version . ' and PHP is v' . $this->debug_php_version);
            $this->log('[Info] Account ID: ' . $this->api_accountID);
            $this->log('[Info] Account Private Key: ' . $this->api_privateKey);
            $this->log('[Info] Account Public Key: ' . $this->api_publicKey);

			// Set transaction speed
            $this->transaction_speed  = $this->get_option('transaction_speed');
            if($this->transaction_speed == "" || $this->transaction_speed == "0")
            {
				$this->log('[Error] Transaction speed cannot be blank or invalid.');
            }
            else
            {
            	$this->log('[Info] Transaction speed set to: ' . $this->transaction_speed);
			}

            // Action???
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // Check if plugin is valid for usage and add IPN Callback
            if (false === $this->is_valid_for_use())
            {
                $this->enabled = 'no';
                $this->log('[Info] The plugin is NOT valid for usage');
            }
            else
            {
                $this->enabled = 'yes';
                $this->log('[Info] The plugin is valid for usage.');
                add_action('woocommerce_api_wc_gateway_atomicpay', array($this, 'ipn_callback'));
            }

            $this->is_initialized = true;
        }

        public function is_atomicpay_payment_method($order)
        {
            $actualMethod = '';
            if(method_exists($order, 'get_payment_method'))
            {
                $actualMethod = $order->get_payment_method();
            }
            else
            {
                $actualMethod = get_post_meta( $order->id, '_payment_method', true );
            }
            return $actualMethod === 'atomicpay';
        }

        public function __destruct(){}

		// Check API Credentials and Currency Support
        public function is_valid_for_use()
        {
            // Check that API credentials are set
            if (true === is_null($this->api_accountID) || true === is_null($this->api_privateKey) || true === is_null($this->api_publicKey))
            {
                return false;
            }

            if($this->transaction_speed == "" || $this->transaction_speed == "0")
            {
            	return false;
            }

            // Check currency is supported by AtomicPay
            try
            {
                $currency_code = get_woocommerce_currency();
                $endpoint_url = 'https://merchant.atomicpay.io/api/v1/currencies';

                $data_to_post = [
                  'method' => 'FETCH',
                	'code' => $currency_code,
                  'AccountID' => $this->api_accountID,
                  'AccountPrivateKey' => $this->api_privateKey
                ];

                $options = [
                  CURLOPT_URL        => $endpoint_url,
                  CURLOPT_POST       => true,
                  CURLOPT_RETURNTRANSFER => true,
                  CURLOPT_POSTFIELDS => $data_to_post,
                ];

                $curl = curl_init();
                curl_setopt_array($curl, $options);
                $response = curl_exec($curl);
                curl_close($curl);

                $data = json_decode($response);
                $code = $data->code;

                if($code == "200")
                {
                	$this->log('[Info] Currency is supported by AtomicPay.');
                }
                else
                {
					$this->log('[Error] Currency is not supported by AtomicPay.');
					throw new \Exception('Currency not suppored');
                }
            }
            catch (\Exception $e)
            {
                $this->log('[Error] Plugin is invalid for usage: ' . $e->getMessage());
                return false;
            }

            return true;
        }

        //Initialise WC Gateway Settings Form Fields
        public function init_form_fields()
        {
            $this->log('[Info] Started init_form_fields()...');
            $log_file = 'atomicpay-' . sanitize_file_name( wp_hash( 'atomicpay' ) ) . '-log';
            $logs_href = get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-status&tab=logs&log_file=' . $log_file;

            $this->form_fields = array(
                'title' => array(
                    'title'       => __('Payment Method Name', 'atomicpay'),
                    'type'        => 'text',
                    'description' => __('Define the name of this payment method as displayed to the customer during checkout.', 'atomicpay'),
                    'default'     => __('AtomicPay', 'atomicpay'),
                    'desc_tip'    => true,
               ),
                'description' => array(
                    'title'       => __('Display Message', 'atomicpay'),
                    'type'        => 'textarea',
                    'description' => __('Displayed message to explain how the customer will be paying for the purchase.', 'atomicpay'),
                    'default'     => 'Pay with cryptocurrencies via AtomicPay. You will be redirected to AtomicPay to complete your purchase.',
                    'desc_tip'    => true,
               ),
                'api_auth' => array(
                    'type'        => 'api_auth',
               ),
                'transaction_speed' => array(
                    'title'       => __('Transaction Speed', 'atomicpay'),
                    'type'        => 'select',
                    'description' => 'The transaction speed determines how quickly an invoice payment is considered to be confirmed, at which you would fulfill and complete the order. Note: 1 confirmation may take up to 10 mins.',
                    'options'     => array(
                        '' => 'Please choose a Speed value',
                        'high'    => 'High Risk (1 Confirmation)',
                        'medium'  => 'Medium Risk (2 Confirmations)',
                        'low'  => 'Low Risk (6 Confirmations)'
                    ),
                    'default' => 'default',
                    'desc_tip'    => true,
               ),
                'order_statuses' => array(
                    'type' => 'order_statuses'
               ),
                'debug' => array(
                    'title'       => __('Debug Log', 'atomicpay'),
                    'type'        => 'checkbox',
                    'label'       => sprintf(__('Enable logging <a href="%s" class="button">View Logs</a>', 'atomicpay'), $logs_href),
                    'default'     => 'no',
                    'description' => __('Log AtomicPay plugin events for debugging and troubleshooting', 'atomicpay'),
                    'desc_tip'    => true,
               ),
                'notification_url' => array(
                    'title'       => __('Notification URL', 'atomicpay'),
                    'type'        => 'url',
                    'description' => __('AtomicPay will send IPNs to this notification URL, along with the invoice data', 'atomicpay'),
                    'default'     => '',
                    'placeholder' => WC()->api_request_url('WC_Gateway_AtomicPay'),
                    'desc_tip'    => true,
               ),
                'redirect_url' => array(
                    'title'       => __('Redirect URL', 'atomicpay'),
                    'type'        => 'url',
                    'description' => __('Customers will be redirected back to this URL after successful, expired or failed payment', 'atomicpay'),
                    'default'     => '',
                    'placeholder' => $this->get_return_url(),
                    'desc_tip'    => true,
               ),
                'support_details' => array(
		            'title'       => __( 'Plugin Information', 'atomicpay' ),
		            'type'        => 'title',
		            'description' => sprintf(__('This plugin version is %s and your PHP version is %s. Developed by AtomicPay - www.atomicpay.io.', 'atomicpay'), constant("ATOMICPAY_VERSION"), PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION),
	           ),
           );

            $this->log('[Info] Initialized form fields: ' . var_export($this->form_fields, true));
            $this->log('[Info] Exiting init_form_fields()...');
        }


        // HTML output for form type [api_auth]
        public function generate_api_auth_html()
        {
            $this->log('[Info] Started generate_api_auth_html()...');

            ob_start();

            wp_enqueue_script('atomicpay-authorization', plugins_url('templates/authorization.js', __FILE__), array('jquery'), '1.0.0', true);
            wp_localize_script( 'atomicpay-authorization', 'AtomicPayAjax', array(
                'ajaxurl'     => admin_url( 'admin-ajax.php' ),
                'authNonce'   => wp_create_nonce( 'atomicpay-authorize-nonce' ),
                'revokeNonce' => wp_create_nonce( 'atomicpay-revoke-nonce' )
                )
            );

            $auth_form = file_get_contents(plugin_dir_path(__FILE__).'templates/authorization.tpl');
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                <label for="woocommerce_atomicpay_api">API Integration <span class="woocommerce-help-tip" data-tip="These values can be obtained at your AtomicPay merchant account under API Integration"></span></label>
                </th>
                <td class="forminp" id="atomicpay_api_auth">
                    <div id="atomicpay_api_auth_form">
                        <?php
                        echo sprintf($auth_form, 'visible', $this->api_accountID, $this->api_privateKey, $this->api_publicKey);
                        ?>
                    </div>
                       <script type="text/javascript">
                        var ajax_loader_url = '<?php echo plugins_url('templates/ajax-loader.gif', __FILE__); ?>';
                    </script>
                </td>
            </tr>
            <?php

            $this->log('[Info] Exiting generate_api_auth_html()...');

            return ob_get_clean();
        }

        // HTML output for form type [order_status]
        public function generate_order_statuses_html()
        {
            $this->log('[Info] Started generate_order_status_html()...');

            ob_start();

            $atm_statuses = array(
			'new'=>'New Order',
			'paid'=>'Paid',
			'exception_underPaid'=>'Underpaid',
			'exception_overPaid'=>'Overpaid',
			'confirmed'=>'Confirmed',
			'complete'=>'Complete',
			'invalid'=>'Invalid',
			'expired'=>'Expired',
			'exception_paidAfterExpiry'=>'Paid After Expiry');

            $defined_statuses = array(
			'new'=>'wc-pending',
			'paid'=>'wc-on-hold',
			'exception_underPaid'=>'wc-failed',
			'exception_overPaid'=>'wc-on-hold',
			'confirmed'=>'wc-processing',
			'complete'=>'wc-processing',
			'invalid'=>'wc-failed',
			'expired'=>'wc-cancelled',
			'exception_paidAfterExpiry'=>'wc-failed');

            $wc_statuses = wc_get_order_statuses();
            $wc_statuses = array('ATOMICPAY_IGNORE' => '') + $wc_statuses;
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">
				<label for="woocommerce_atomicpay_status">Order Status <span class="woocommerce-help-tip" data-tip="You can predefine how different invoice status will reflect on your WooCommerce order status"></span></label>
                </th>
                <td class="forminp" id="atomicpay_order_statuses">
                    <table cellspacing="0" cellpadding="0">
                        <?php

                            foreach ($atm_statuses as $atm_state => $atm_name) {
                            ?>
                            <tr>
                            <td><?php echo $atm_name; ?></td>
                            <td>=></td>
                            <td>
                                <select name="woocommerce_atomicpay_order_statuses[<?php echo $atm_state; ?>]">
                                <?php

                                $order_statuses = get_option('woocommerce_atomicpay_settings');
                                $order_statuses = $order_statuses['order_statuses'];
                                foreach ($wc_statuses as $wc_state => $wc_name) {
                                    $current_option = $order_statuses[$atm_state];

                                    if (true === empty($current_option)) {
                                        $current_option = $defined_statuses[$atm_state];
                                    }

                                    if ($current_option === $wc_state) {
                                        echo "<option value=\"$wc_state\" selected>$wc_name</option>\n";
                                    } else {
                                        echo "<option value=\"$wc_state\">$wc_name</option>\n";
                                    }
                                }

                                ?>
                                </select>
                            </td>
                            </tr>
                            <?php
                        }

                        ?>
                    </table>
                </td>
            </tr>
            <?php

            $this->log('[Info] Exiting generate_order_status_html()...');

            return ob_get_clean();
        }

        // Validation of Order Status
        public function validate_order_statuses_field()
        {
            $order_statuses = $this->get_option('order_statuses');

            $order_statuses_key = $this->plugin_id . $this->id . '_order_statuses';
            if ( isset( $_POST[ $order_statuses_key ] ) )
            {
                $order_statuses = $_POST[ $order_statuses_key ];
            }

            return $order_statuses;
        }

        // Payment Status for the order received page.
        public function thankyou_page($order_id)
        {
            $this->log('[Info] Started thankyou_page with Order ID: ' . $order_id);
            $this->log('[Info] Exiting thankyou_page with Order ID: ' . $order_id);
        }

        // Payment Processing Function
        public function process_payment($order_id)
        {
            $this->log('[Info] Started process_payment() with Order ID: ' . $order_id . '...');

            if (true === empty($order_id))
            {
                $this->log('[Error] Order ID is missing. Validation failed. Unable to proceed.');
                throw new \Exception('Order ID is missing. Validation failed. Unable to proceed.');
            }

            $order = wc_get_order($order_id);

            if(false === $order)
            {
                $this->log('[Error] Unable to retrieve the order details for Order ID ' . $order_id . '. Unable to proceed.');
                throw new \Exception('Unable to retrieve the order details for Order ID ' . $order_id . '. Unable to proceed.');
            }

            $notification_url = $this->get_option('notification_url', WC()->api_request_url('WC_Gateway_AtomicPay'));
            $this->log('[Info] Generating payment invoice for Order ID ' . $order->get_order_number());

            // Get and update order status
            $new_order_statuses = $this->get_option('order_statuses');
            $new_order_status = $new_order_statuses['new'];
            $this->log('[Info] Setting order status to: '.$new_order_status);

            $order->update_status($new_order_status);
            $this->log('[Info] Order status updated');
            $thanks_link = $this->get_return_url($order);

            $this->log('[Info] Thank You URL: ' . $thanks_link);

            // Redirect URL & Notification URL
            $redirect_url = $this->get_option('redirect_url', $thanks_link);

            if($redirect_url !== $thanks_link)
            {
                $order_received_len = strlen('order-received');
                if(substr($redirect_url, -$order_received_len) === 'order-received')
                {
                    $this->log('substr($redirect_url, -$order_received_pos) === order-received');
                    $redirect_url = $redirect_url . '=' . $order->get_id();
                }
                else
                {
                    $redirect_url = add_query_arg( 'order-received', $order->get_id(), $redirect_url);
                }

                $redirect_url = add_query_arg( 'key', $order->get_order_key(), $redirect_url);
            }

            $this->log('[Info] Redirect URL: ' . $redirect_url);
            $this->log('[Info] Notification URL: ' . $notification_url);

            // Get the currency code
            $currency_code = get_woocommerce_currency();

            $this->log('[Info] Currency Code: ' . $currency_code);
            $this->log('[Info] Validating and checks passed...');

            // Setup the Invoice
            try
            {
                $this->log('[Info] Attempting to generate payment invoice for Order ID: ' . $order->get_order_number() . '...');

                $order_total = $order->calculate_totals();
                if (true === isset($order_total) && false === empty($order_total))
                {
                    $order_total = (float)$order_total;
                    if($order_total == 0 || $order_total === '0')
                    {
						$this->log('[Error] Price must be formatted as a float. Order amount: ". $order_total');
						throw new \Exception('Price must be formatted as a float. Order amount:  ". $order_total');
                    }
                }
                else
                {
                	$this->log('[Error] Order amount is invalid or empty. Validation failed. Unable to proceed.');
                	throw new \Exception('Order amount is invalid or empty. Validation failed. Unable to proceed.');
                }

                $orderID = $order->get_order_number();
                $notificationEmail = $order->get_billing_email();
                $transactionSpeed = $this->transaction_speed;
                $endpoint_url = 'https://merchant.atomicpay.io/api/v1/invoices';

                $data_to_post = [
                  'method' => 'GENERATE',
                  'AccountID' => $this->api_accountID,
                  'AccountPrivateKey' => $this->api_privateKey,
                  'orderID' => $orderID,
                  'amount' => $order_total,
                  'currency' => $currency_code,
                  'notificationEmail' => $notificationEmail,
                  'notificationUrl' => $notification_url,
                  'redirectUrl' => $redirect_url,
                  'transactionSpeed' => $transactionSpeed
                ];

                $options = [
                  CURLOPT_URL        => $endpoint_url,
                  CURLOPT_POST       => true,
                  CURLOPT_RETURNTRANSFER => true,
                  CURLOPT_POSTFIELDS => $data_to_post,
                ];

                $curl = curl_init();
                curl_setopt_array($curl, $options);
                $response = curl_exec($curl);
                curl_close($curl);

                $data = json_decode($response);
                $code = $data->code;

                if($code == "200")
                {
					$invoice_id = $data->invoice_id;
					$invoice_url = $data->invoice_url;

					$this->log('[Info] Generation of payment invoice is successful. Invoice ID: ' . $invoice_id);
                }
                else
                {
                	$errormessage = $data->message;
					$this->log('[Error] Generation of payment invoice failed. ' . $errormessage);
					throw new \Exception('Generation of payment invoice failed. ' . $errormessage);
                }

            }
            catch (\Exception $e)
            {
                $this->log('[Error] Error generating invoice.');
                error_log($e->getMessage());

                return array(
                    'result'    => 'success',
                    'messages'  => 'Apologies. Checkout with AtomicPay does not appear to be working at the moment.'
                );
            }

            update_post_meta($order_id, 'AtomicPay_InvoiceURL', $invoice_url);
            update_post_meta($order_id, 'AtomicPay_InvoiceID', $invoice_id);

			// Empty WC cart
			WC()->cart->empty_cart();

            $this->log('[Info] Payment invoice assigned to Order ID: ' . $order_id);
            $this->log('[Info] Shopping cart is emptied');
            $this->log('[Info] Exiting process_payment()...');

            // Redirect customer to AtomicPay checkout page
            return array(
                'result'   => 'success',
                'redirect' => $invoice_url,
            );
        }

        public function ipn_callback()
        {
            $this->log('[Info] Started ipn_callback()...');
            $post = file_get_contents("php://input");

            if (true === empty($post))
            {
                $this->log('[Error] No POST data sent to IPN handler');
                error_log('[Error] Received empty POST data from IPN message');
                wp_die('No post data');
            }
            else
            {
                $this->log('[Info] The POST data sent to IPN handler is valid');
            }

            $json = json_decode($post);
            $this->log('[Info] Decoding IPN json data....');

            if (true === empty($json))
            {
                $this->log('[Error] Invalid JSON payload sent to IPN handler: ' . $post);
                error_log('[Error] Received an invalid JSON payload: ' . $post);
                wp_die('Invalid JSON');
            }
            else
            {
                $this->log('[Info] IPN json data decoded');
            }

            if (false === array_key_exists('invoice_id', $json))
            {
                $this->log('[Error] Invoice ID is not found in JSON payload: ' . var_export($json, true));
                error_log('[Error] Invoice ID is not found in JSON payload: ' . var_export($json, true));
                wp_die('No Invoice ID');
            }
            else
            {
                $this->log('[Info] Invoice ID is found in JSON payload');
            }

            // Connect to AtomicPay to validate Invoice ID
			try
			{
				$invoice_id = $json->invoice_id;
                $endpoint_url = 'https://merchant.atomicpay.io/api/v1/invoices';

                $data_to_post = [
                	'method' => 'FETCH',
                	'InvoiceID' => $invoice_id,
                  	'AccountID' => $this->api_accountID,
                  	'AccountPrivateKey' => $this->api_privateKey,
                ];

                $options = [
                	CURLOPT_URL        => $endpoint_url,
                	CURLOPT_POST       => true,
                	CURLOPT_RETURNTRANSFER => true,
                	CURLOPT_POSTFIELDS => $data_to_post,
                ];

                $curl = curl_init();
                curl_setopt_array($curl, $options);
                $response = curl_exec($curl);
                curl_close($curl);

                $data = json_decode($response);
                $code = $data->code;

                if($code == "200")
                {
                    $result = $data->result;
					$result_array = $result["0"];

                  	$atm_invoice_timestamp = $result_array->invoice_timestamp;
                  	$atm_invoice_id = $result_array->invoice_id;
                	$atm_order_id = $result_array->order_id;
                	$atm_order_description = $result_array->order_description;
                	$atm_fiat_price = $result_array->fiat_price;
                	$atm_fiat_currency = $result_array->fiat_currency;
                	$atm_transaction_speed = $result_array->transaction_speed;
                	$atm_payment_currency = $result_array->payment_currency;
                	$atm_payment_rate = $result_array->payment_rate;
                	$atm_payment_address = $result_array->payment_address;
                	$atm_payment_paid = $result_array->payment_paid;
                	$atm_payment_due = $result_array->payment_due;
                	$atm_payment_total = $result_array->payment_total;
                	$atm_payment_txid = $result_array->payment_txid;
                	$atm_payment_confirmation = $result_array->payment_confirmation;
                	$atm_notification_email = $result_array->notification_email;
                	$atm_notification_url = $result_array->notification_url;
                	$atm_redirect_url = $result_array->redirect_url;
                	$atm_status = $result_array->status;
                	$atm_statusException = $result_array->statusException;
                	$atm_payment_rate = "$atm_payment_rate $atm_fiat_currency";

                    $this->log('[Info] The Invoice ID is valid.');

                    if(false === isset($atm_order_id) && true === empty($atm_order_id))
                    {
                        $this->log('[Error] Could not fetch the order ID from the invoice API. Validation failed. Unable to proceed.');
                        throw new \Exception('Could not fetch the order ID from the invoice API. Validation failed. Unable to proceed.');
                    }
                    else
                    {
                        $this->log('[Info] Found Order ID: ' . $atm_order_id);
                    }

					//Check if Order ID matches WC Order ID
					//Meant for basic and advanced woocommerce order numbering plugins. To apply other filters, add coding here

                    $order_id = apply_filters('woocommerce_order_id_from_number', $atm_order_id);
                    $order = wc_get_order($order_id);

					if(false === $order || 'WC_Order' !== get_class($order))
					{
						$this->log('[Error] Could not match Order ID: "' . $order_id . '". If you use an alternative order numbering system, please apply a search filter at class-wc-gateway-atomicpay.php');
						throw new \Exception('Could not match Order ID: "' . $order_id . '". If you use an alternative order numbering system, please apply a search filter at class-wc-gateway-atomicpay.php');
					}
					else
					{
						$this->log('[Info] Matched Order ID:' . $order_id);
					}

					//Check if WC payment method is via AtomicPay
					if(!$this->is_atomicpay_payment_method($order))
					{
						$this->log('[Info] Not using AtomicPay payment method...');
						$this->log('[Info] Exiting ipn_callback()...');
						return;
					}

					//Check if IPN Invoice ID matches WC Invoice ID
					$expected_invoiceId = get_post_meta($order_id, 'AtomicPay_InvoiceID', true);
					if($expected_invoiceId !== $atm_invoice_id)
					{
						$this->log('[Error] Received IPN for Order ID: '. $order_id . ' with Invoice ID: ' . $atm_invoice_id . ' while expected Invoice ID is ' . $expected_invoiceId);
						throw new \Exception('Received IPN for Order ID: '. $order_id . ' with Invoice ID: ' . $atm_invoice_id . ' while expected Invoice ID is ' . $expected_invoiceId);
					}

					//Fetch current status of WC order
					$current_status = $order->get_status();
					if (false === isset($current_status) && true === empty($current_status))
					{
						$this->log('[Error] Unable to get the current status from the order');
						throw new \Exception('Unable to get the current status from the order');
					}
					else
					{
						$this->log('[Info] The current order status for this order is ' . $current_status);
					}

					//Get predefined order states of AtomicPay
					$order_statuses = $this->get_option('order_statuses');
					$new_order_status = $order_statuses['new'];
					$paid_status      = $order_statuses['paid'];
					$confirmed_status = $order_statuses['confirmed'];
					$complete_status  = $order_statuses['complete'];
					$invalid_status   = $order_statuses['invalid'];
					$expired_status   = $order_statuses['expired'];
					$exception_underPaid    = $order_statuses['exception_underPaid'];
					$exception_overPaid    = $order_statuses['exception_overPaid'];
					$exception_paidAfterExpiry    = $order_statuses['exception_paidAfterExpiry'];

					//Check if invoice status is available
					$checkStatus = $atm_status;
					if (false === isset($checkStatus) && true === empty($checkStatus))
					{
						$this->log('[Error] Unable to get the current status from the invoice');
						throw new \Exception('Unable to get the current status from the invoice');
					}
					else
					{
						$this->log('[Info] The current order status for this invoice is ' . $checkStatus);
					}

					switch ($checkStatus)
					{
						case 'paid':
							$this->log('[Info] The invoice status is paid. Order status has been set as paid');

							// Reduce stock levels
							if (function_exists('wc_reduce_stock_levels'))
							{
								wc_reduce_stock_levels($order_id);
							}
							else
							{
								$order->reduce_order_stock();
							}

							if($atm_statusException != "")
							{
								if($atm_statusException == "Overpaid")
								{
									$this->log('[Info] The invoice is overpaid...');
									if($exception_overPaid !== 'ATOMICPAY_IGNORE')
									$order->update_status($exception_overPaid , __('The invoice is overpaid.', 'atomicpay'));
									$order->add_order_note(__('The invoice is overpaid. Awaiting network confirmation status. Please contact customer on refund matters.', 'atomicpay'));
								}
							}
							else
							{
								if($paid_status !== 'ATOMICPAY_IGNORE')
								$order->update_status($paid_status);
								$order->add_order_note(__('AtomicPay invoice has been paid. Awaiting network confirmation status.', 'atomicpay'));
							}

							break;

						case 'confirmed':
							$this->log('[Info] The invoice status is confirmed. Order status has been set as confirmed');
							if($confirmed_status !== 'ATOMICPAY_IGNORE')
							$order->update_status($confirmed_status);
							$order->add_order_note(__('AtomicPay invoice has been confirmed. Awaiting payment completion status.', 'atomicpay'));
							break;

						case 'complete':
							$this->log('[Info] The invoice status is completed. Order status has been set as completed');
							$order->payment_complete();
							if($complete_status !== 'ATOMICPAY_IGNORE')
							$order->update_status($complete_status);
							$order->add_order_note(__('AtomicPay invoice payment has completed', 'atomicpay'));
							break;

						case 'invalid':
							$this->log('[Info] The invoice status is invalid. Order status has been set as invalid');
							if($invalid_status !== 'ATOMICPAY_IGNORE')
							$order->update_status($invalid_status, __('Payment invoice is invalid for this order. The payment was not confirmed by the network. Do not process this order', 'atomicpay'));
							break;

						case 'expired':
							$this->log('[Info] The invoice status is expired. Order status has been set as expired');
							if($atm_statusException != "")
							{
								if($atm_statusException == "Paid After Expiry")
								{
									$this->log('[Info] The invoice has paid after expiry...');
									if($exception_paidAfterExpiry !== 'ATOMICPAY_IGNORE')
									$order->update_status($exception_paidAfterExpiry , __('The invoice was paid after expiry', 'atomicpay'));
									$order->add_order_note(__('A payment has been received after the invoice has expired', 'atomicpay'));
								}

								if($atm_statusException == "Underpaid")
								{
									$this->log('[Info] The invoice is underpaid...');
									if($exception_underPaid !== 'ATOMICPAY_IGNORE')
									$order->update_status($exception_underPaid , __('The invoice is underpaid.', 'atomicpay'));
									$order->add_order_note(__('The invoice is underpaid and has expired. Please kindly contact your customer', 'atomicpay'));
								}
							}
							else
							{
								if($expired_status !== 'ATOMICPAY_IGNORE')
								$order->update_status($expired_status, __('Payment invoice has expired for this order. Do not process this order', 'atomicpay'));
							}

							break;

						default:
							$this->log('[Info] IPN response is an unknown message type. See error message below:');
							$error_string = 'Unhandled invoice status: ' . $atm_status;
							$this->log("[Warning] $error_string");
					}

					update_post_meta($order_id, 'AtomicPay_PaymentCurrency', $atm_payment_currency);
					update_post_meta($order_id, 'AtomicPay_PaymentRate', $atm_payment_rate);
					update_post_meta($order_id, 'AtomicPay_PaymentTotal', $atm_payment_total);
					update_post_meta($order_id, 'AtomicPay_PaymentPaid', $atm_payment_paid);
					update_post_meta($order_id, 'AtomicPay_PaymentDue', $atm_payment_due);
					update_post_meta($order_id, 'AtomicPay_PaymentAddress', $atm_payment_address);
					update_post_meta($order_id, 'AtomicPay_PaymentConfirmation', $atm_payment_confirmation);
					update_post_meta($order_id, 'AtomicPay_PaymentTxID', $atm_payment_txid);
					update_post_meta($order_id, 'AtomicPay_Status', $atm_status);
					update_post_meta($order_id, 'AtomicPay_StatusException', $atm_statusException);

					$this->log('[Info] Exiting ipn_callback()...');
                }
                else
                {
					$this->log('[Error] The Invoice ID is invalid');
					$this->log('[Info] Exiting ipn_callback()...');
					wp_die('Invalid IPN');
                }
            }
            catch (\Exception $e)
            {
                $error_string = 'IPN Check: Can\'t validate Invoice ID: ' . $atm_invoice_id;
                $this->log("    [Error] $error_string");
                $this->log("    [Error] " . $e->getMessage());

                wp_die($e->getMessage());
            }
        }

        public function log($message)
        {
            if (true === isset($this->debug) && 'yes' == $this->debug)
            {
                if (false === isset($this->logger) || true === empty($this->logger))
                {
                    $this->logger = new WC_Logger();
                }

                $this->logger->add('atomicpay', $message);
            }
        }

	}

    // Add Payment Gateway to WooCommerce
    function wc_add_atomicpay($methods)
    {
        $methods[] = 'WC_Gateway_AtomicPay';

        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'wc_add_atomicpay');

	// Add WC Logger
	if (!function_exists('atomicpay_log'))
	{
		function atomicpay_log($message)
		{
			$logger = new WC_Logger();
			$logger->add('atomicpay', $message);
		}
	}

    // Add link to the plugin entry in the plugins menu under Settings
    add_filter('plugin_action_links', 'atomicpay_plugin_action_links', 10, 2);

    function atomicpay_plugin_action_links($links, $file)
    {
        static $this_plugin;

        if (false === isset($this_plugin) || true === empty($this_plugin)) {
            $this_plugin = plugin_basename(__FILE__);
        }

        if ($file == $this_plugin) {
            $log_file = 'atomicpay-' . sanitize_file_name( wp_hash( 'atomicpay' ) ) . '-log';
            $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=wc_gateway_atomicpay">Settings</a>';
            $logs_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-status&tab=logs&log_file=' . $log_file . '">Logs</a>';
            array_unshift($links, $settings_link, $logs_link);
        }

        return $links;
    }

	// Request or Revoke API Authorization
    add_action('wp_ajax_atomicpay_authorize_nonce', 'ajax_atomicpay_authorize_nonce');
    add_action('wp_ajax_atomicpay_revoke_nonce', 'ajax_atomicpay_revoke_nonce');

	// Request API Authorization
    function ajax_atomicpay_authorize_nonce()
    {
        $nonce = $_POST['authNonce'];
        if ( ! wp_verify_nonce( $nonce, 'atomicpay-authorize-nonce' ) )
        {
            die ( 'Unauthorized');
        }

        if ( current_user_can( 'manage_options' ) )
        {
            // Validate API Values
            if (true === isset($_POST['accountID']) && trim($_POST['accountID']) !== '')
            {
                $accountID = trim($_POST['accountID']);
            }
            else
            {
                wp_send_json_error("Account ID is required");
                return;
            }

            if (true === isset($_POST['privateKey']) && trim($_POST['privateKey']) !== '')
            {
                $privateKey = trim($_POST['privateKey']);
            }
            else
            {
                wp_send_json_error("Private Key is required");
                return;
            }

            if (true === isset($_POST['publicKey']) && trim($_POST['publicKey']) !== '')
            {
                $publicKey = trim($_POST['publicKey']);
            }
            else
            {
                wp_send_json_error("Public Key is required");
                return;
            }

            // Validate API Connection
            $endpoint_url = 'https://merchant.atomicpay.io/api/v1/authorization';

            $data_to_post = [
              'method' => 'VALIDATE',
              'AccountID' => $accountID,
              'AccountPrivateKey' => $privateKey,
              'AccountPublicKey' => $publicKey
            ];

            $options = [
              CURLOPT_URL        => $endpoint_url,
              CURLOPT_POST       => true,
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_POSTFIELDS => $data_to_post,
            ];

            $curl = curl_init();
            curl_setopt_array($curl, $options);
            $response = curl_exec($curl);
            curl_close($curl);

            $data = json_decode($response);
            $code = $data->code;

            if($code == "200")
            {
				$message = $data->message;
				update_option('woocommerce_atomicpay_accountID', (string)$accountID);
				update_option('woocommerce_atomicpay_privateKey', (string)$privateKey);
				update_option('woocommerce_atomicpay_publicKey', (string)$publicKey);

				wp_send_json(array('message' => $message, 'accountID' => (string) $accountID, 'privateKey' => (string) $privateKey, 'publicKey' => (string) $publicKey));
            }
            else
            {
				$message = $data->message;
				wp_send_json_error($message);
				return;
            }
        }
        exit;
    }

	// Revoke API Authorization
    function ajax_atomicpay_revoke_nonce()
    {
        $nonce = $_POST['revokeNonce'];
        if ( ! wp_verify_nonce( $nonce, 'atomicpay-revoke-nonce' ) )
        {
            die ( 'Unauthorized');
        }

        if ( current_user_can( 'manage_options' ) )
        {
            update_option('woocommerce_atomicpay_accountID', null);
            update_option('woocommerce_atomicpay_privateKey', null);
            update_option('woocommerce_atomicpay_publicKey', null);
            wp_send_json(array('message' => 'API Authorization Revoked'));
        }
        exit;
    }

    function action_woocommerce_thankyou_atomicpay($order_id)
    {
        $wc_order = wc_get_order($order_id);

        if($wc_order === false)
        {
            return;
        }

        $order_data = $wc_order->get_data();
        $status = $order_data['status'];

        $payment_status = file_get_contents(plugin_dir_path(__FILE__) . 'templates/paymentStatus.tpl');
        $payment_status = str_replace('{$statusTitle}', _x('Payment Status', 'woocommerce_atomicpay'), $payment_status);

        switch ($status)
        {
            case 'on-hold':
                $status_description = _x('Waiting for payment', 'woocommerce_atomicpay');
                break;

            case 'processing':
                $status_description = _x('Payment processing', 'woocommerce_atomicpay');
                break;

            case 'completed':
                $status_description = _x('Payment completed', 'woocommerce_atomicpay');
                break;

            case 'failed':
                $status_description = _x('Payment failed', 'woocommerce_atomicpay');
                break;

            default:
                $status_description = _x(ucfirst($status), 'woocommerce_atomicpay');
                break;
        }

        echo str_replace('{$paymentStatus}', $status_description, $payment_status);
    }

    add_action("woocommerce_thankyou_atomicpay", 'action_woocommerce_thankyou_atomicpay', 10, 1);
}

function woocommerce_atomicpay_failed_requirements()
{
    global $wp_version;
    global $woocommerce;

    $errors = array();
    if (extension_loaded('openssl')  === false){
        $errors[] = 'The AtomicPay payment plugin requires the OpenSSL extension for PHP in order to function. Please contact your web server administrator for assistance.';
    }
    // PHP 5.4+ required
    if (true === version_compare(PHP_VERSION, '5.4.0', '<')) {
        $errors[] = 'Your PHP version is too old. The AtomicPay payment plugin requires PHP 5.4 or higher to function. Please contact your web server administrator for assistance.';
    }

    // Wordpress 3.9+ required
    if (true === version_compare($wp_version, '3.9', '<')) {
        $errors[] = 'Your WordPress version is too old. The AtomicPay payment plugin requires Wordpress 3.9 or higher to function. Please contact your web server administrator for assistance.';
    }

    // WooCommerce required
    if (true === empty($woocommerce)) {
        $errors[] = 'The WooCommerce plugin for WordPress needs to be installed and activated. Please contact your web server administrator for assistance.';
    }elseif (true === version_compare($woocommerce->version, '2.2', '<')) {
        $errors[] = 'Your WooCommerce version is too old. The AtomicPay payment plugin requires WooCommerce 2.2 or higher to function. Your version is '.$woocommerce->version.'. Please contact your web server administrator for assistance.';
    }

    // GMP or BCMath required
    if (false === extension_loaded('gmp') && false === extension_loaded('bcmath')) {
        $errors[] = 'The AtomicPay payment plugin requires the GMP or BC Math extension for PHP in order to function. Please contact your web server administrator for assistance.';
    }

    // Curl required
    if (false === extension_loaded('curl')) {
        $errors[] = 'The AtomicPay payment plugin requires the Curl extension for PHP in order to function. Please contact your web server administrator for assistance.';
    }

    if (false === empty($errors)) {
        return implode("<br>\n", $errors);
    } else {
        return false;
    }

}

// Activating the plugin
function woocommerce_atomicpay_activate()
{
    // Check for Requirements
    $failed = woocommerce_atomicpay_failed_requirements();

    $plugins_url = admin_url('plugins.php');

    // Check Requirements. Activate the plugin
    if ($failed === false)
    {
        // Deactivate any older versions that might still be present
        $plugins = get_plugins();

        foreach ($plugins as $file => $plugin)
        {
			if ('AtomicPay for WooCommerce' === $plugin['Name'] && true === is_plugin_active($file) && (0 > version_compare( $plugin['Version'], '1.0.3' )))
			{
                deactivate_plugins(plugin_basename(__FILE__));
                wp_die('AtomicPay for WooCommerce requires the older version of this plugin to be deactivated. <br><a href="'.$plugins_url.'">Return to plugins screen</a>');
            }
        }

        update_option('woocommerce_atomicpay_version', constant("ATOMICPAY_VERSION"));

    }
    else
    {
        // Requirements failed. Return an error message
        wp_die($failed . '<br><a href="'.$plugins_url.'">Return to plugins screen</a>');
    }
}
