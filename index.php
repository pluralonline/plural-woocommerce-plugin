<?php
/*
Plugin Name: Edge by Pine Labs for WooCommerce
Plugin URI: https://www.pinelabs.com/
Description: Edge Payment Gateway by Pine Labs for WooCommerce.
Version: 1.4.0
Author: Pine Labs
Author URI: https://www.pinelabs.com/
Copyright: © 2021 Pine Labs. All rights reserved.
*/

add_action('plugins_loaded', 'woocommerce_edgepg_init', 0);

function woocommerce_edgepg_init()
{
	if (!class_exists('WC_Payment_Gateway'))
	{
		return;
	}  

	/**
	* Localisation
	*/
	load_plugin_textdomain('wc-edgepg', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');
  
	
	/**
	* Gateway class
	*/
	class WC_EdgePg extends WC_Payment_Gateway
	{		 
		public function __construct()
		{
			$this->id = 'edgepg';
			$this->method_title = __('Edge by Pine Labs', 'edgepg');
			$this->method_description = __('Pay securely via Edge - Payment Gateway by Pine Labs.', 'edgepg');
			$this->icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/images/edgepg.png';
			$this->has_fields = false;
			$this->init_form_fields();
			$this->init_settings();
			$this->title = 'Edge by Pine Labs';
			$this->description = $this->settings['description'];
			$this->gateway_module = $this->settings['gateway_module'];
			$this->redirect_page_id = $this->settings['redirect_page_id'];
			$this->cart_system = $this->settings['cart_system'];
			$this->secret_key = $this->settings['secret_key'];
			$this->msg['message'] = "";
			$this->msg['class'] = ""; 
 
			add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'check_edgepg_response' ) );

			add_action('valid-edgepg-request', array(&$this, 'SUCCESS'));

			if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) 
			{
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
			} 
			else 
			{
				add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
			}

			add_action('woocommerce_receipt_edgepg', array(&$this, 'receipt_page'));
		}
    
	    function init_form_fields()
	    {
	    	$this->form_fields = array(
					'enabled' => array(
						'title' => __('Enable/Disable', 'edgepg'),
						'type' => 'checkbox',
						'label' => __('Enable Edge', 'edgepg'),
						'default' => 'no'
					),
					'cart_system' => array(
						'title' => __('Cart System'),
						'type' => 'select',
						'options' => ['Single Cart','Multi Cart'],
						'description' => "Single or Multi Cart System"
					),
					'description' => array(
						'title' => __('Description:', 'edgepg'),
						'type' => 'textarea',
						'description' => __('This controls the description which the user sees during checkout.', 'edgepg'),
						'default' => __('Pay securely via Edge - Payment Gateway by Pine Labs.', 'edgepg')
					),
					'gateway_module' => array(
						'title' => __('Gateway Mode', 'edgepg'),
						'type' => 'select',
						'options' => array("0"=>"Select","sandbox"=>"Sandbox","production"=>"Production"),
						'description' => __('Mode of gateway subscription.','edgepg')
					),
					'ppc_MerchantID' => array(
						'title' => __('Merchant ID', 'edgepg'),
						'type' => 'text',
						'description' =>  __('Merchant ID', 'edgepg')
					),
					'secret_key' => array(
						'title' => __('Secret Key', 'edgepg'),
						'type' => 'text',
						'description' =>  __('Secret Key', 'edgepg')
					),
					'ppc_PayModeOnLandingPage' => array(
						'title' => __('Payment Modes', 'edgepg'),
						'type' => 'text',
						'description' =>  __('Payment Modes as Comma Separated Values', 'edgepg')
					),
					'ppc_MerchantAccessCode' => array(
						'title' => __('Merchant Access Code', 'edgepg'),
						'type' => 'text',
						'description' =>  __('Merchant Access Code', 'edgepg')
					),
					'redirect_page_id' => array(
						'title' => __('Return Page'),
						'type' => 'select',
						'options' => $this->get_pages('Default Pinelab Page'),
						'description' => "Page to redirect to after transaction from Payment Gateway"
					)
					
	          	);
	    }
    
		/**
		* Admin Panel Options
		**/
	    public function admin_options()
	    {
			echo '<h3>'.__('Edge - Payment Gateway by Pine Labs', 'edgepg').'</h3>';
			echo '<p>'.__('Pine Labs is one of the leading payments solutions company in India.').'</p>';
			echo '<table class="form-table">';
		
			$this->generate_settings_html();
		
			echo '</table>';
	    }
		
		/**
		*  If There are no payment fields show the description if set.
		**/
	    function payment_fields()
	    {
			if($this->description) echo wpautop(wptexturize($this->description));
	    }
		
		/**
		* Receipt Page
		**/
	    function receipt_page($order)
	    {
			echo '<p>'.__('Do not click Refresh or Back Button & Please wait while we redirect you to the Payment page', 'edgepg').'</p>';
			echo $this->generate_edgepg_form($order);
	    }
    
		/**
		* Process the payment and return the result
		**/   
	    function process_payment($order_id)
	    {
            $order = new WC_Order($order_id);

            if ( version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                return array(
                    'result' => 'success',
                    'redirect' => add_query_arg('order', $order->get_id(),
                        add_query_arg('key', $order->get_order_key(), $order->get_checkout_payment_url(true)))
                );
            }
            else 
            {
                return array(
                    'result' => 'success',
                    'redirect' => add_query_arg('order', $order->get_id(),
                        add_query_arg('key', $order->get_order_key(), get_permalink(get_option('woocommerce_pay_page_id'))))
                );
            }
        }

        /**
		* Handle response from pg.
		**/
	    function check_edgepg_response()
	    {
			global $woocommerce;

			$order_id = '';

			plugin_log(__LINE__.  'Pinelab response post data : '. json_encode($_POST));
			
			if (isset($_POST['ppc_UniqueMerchantTxnID']))
			{ 
				$merchantTxnID = $_POST['ppc_UniqueMerchantTxnID'];

				$order_id = explode('_', $merchantTxnID);
				$order_id = (int)$order_id[0];    //get rid of time part
				
				$redirect_url = get_site_url()."/checkout/order-received/".$order_id."/?key=".$_COOKIE[$merchantTxnID];
			}

			if ($order_id != '')
			{
				$order = new WC_Order($order_id);

				$paymentMode = array(
					'1' => 'CREDIT/DEBIT CARD',
					'3' => 'NET BANKING',
					'4' => 'EMI',
					'10' => 'UPI',
					'11' => 'WALLET',
					'14' => 'DEBIT EMI'
				);
				$order->set_payment_method_title($paymentMode[$_POST['ppc_PaymentMode']]?? 'Pinelab' );
				 
				$enquiry_params['ppc_MerchantAccessCode'] = $_POST['ppc_MerchantAccessCode'];
                $enquiry_params['ppc_MerchantID'] = $_POST['ppc_MerchantID'];
                $enquiry_params['ppc_PinePGTransactionID'] = $_POST['ppc_PinePGTransactionID'];
                $enquiry_params['ppc_TransactionType'] = $_POST['ppc_UdfField2'] = 3;
                $enquiry_params['ppc_UniqueMerchantTxnID'] = $_POST['ppc_UniqueMerchantTxnID'];

                ksort($enquiry_params);
                $strString = "";

                foreach ($enquiry_params as $key => $val) {
                    $strString .= $key . "=" . $val . "&";
                }

				$strString = substr($strString, 0, -1);
 
				$responseHash = strtoupper(hash_hmac("sha256", $strString, Hex2String($this->settings['secret_key'])));

				$_POST['ppc_DIA_SECRET_TYPE'] = 'SHA256';
                $_POST['ppc_DIA_SECRET'] = $responseHash; 

				if (verify($_POST,$this->gateway_module) && $responseHash == $_POST['ppc_DIA_SECRET'])
				{  
					if (isset($_POST['ppc_PinePGTxnStatus']) && $_POST['ppc_PinePGTxnStatus'] == '4'
						&& isset($_POST['ppc_TxnResponseCode']) && $_POST['ppc_TxnResponseCode'] == '1')
					{	
						plugin_log(__LINE__.  'Pinelab ppc_PinePGTxnStatus : '.$_POST['ppc_PinePGTxnStatus'].' And Pinelab ppc_TxnResponseCode : '.$_POST['ppc_TxnResponseCode']);				
						$amount = floatval($_POST['ppc_Amount']) / 100.0;
						
						$this->msg['class'] = 'success';
						$this->msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful with the following order details: 
								<br> 
									Order Id: $order_id <br/>
									Amount: ‎₹ $amount 
								<br />		
							We will process and ship your order soon.";

						if ($order->status == 'processing' || $order->status == 'completed')
						{
							//do nothing
							plugin_log(__LINE__. ': order processing - txn id: '.$merchantTxnID);
						}
						else
						{
							//complete the order
							$order->payment_complete();
							$order->add_order_note('Payment via Edge Successful. Transaction Id: '. $merchantTxnID);
							$order->add_order_note($this->msg['message']);
							$woocommerce->cart->empty_cart();
							plugin_log(__LINE__. ': order completed - txn id: '. $merchantTxnID);
						}
					}
					else
					{
						plugin_log(__LINE__. ': in step 1 : order not completed - txn id : '. $merchantTxnID);
			    		$this->msg['class'] = 'error';
						$this->msg['message'] = "Thank you for shopping with us. However, the payment failed.";
						$order->update_status('failed');
						$order->add_order_note('Failed');
						$order->add_order_note($this->msg['message']);
						//Here you need to put in the routines for a failed
						//transaction such as sending an email to customer
						//setting database status etc etc			
					}
				}
				else{
					plugin_log(__LINE__. ': in step 2 : order not completed - txn id: '. $merchantTxnID);
					$this->msg['class'] = 'error';
					$this->msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
					$order->update_status('failed');
					$order->add_order_note('Data tampered.');
					$order->add_order_note($this->msg['message']);
				}
				
			}
			else
			{
				plugin_log(__LINE__. ': in step 3 : order not completed - txn id: '. $merchantTxnID);

				$this->msg['class'] = 'error';
				
				if ($this->msg['message'] == "")
				{
					plugin_log(__LINE__. ': in step 4 : order not completed - txn id : '. $merchantTxnID);
					$this->msg['message'] = "Error processing payment.";
				}
			}

			//manage messages
			if (function_exists('wc_add_notice'))
			{
				plugin_log(__LINE__. ': step -1 response messages - txn id : '. $merchantTxnID);
				wc_add_notice($this->msg['message'], $this->msg['class']);
			}
			else
			{
				if ($this->msg['class'] == 'success')
				{
					plugin_log(__LINE__. ': step -2 response messages - txn id : '. $merchantTxnID);
					$woocommerce->add_message($this->msg['message']);
				}
				else
				{
					plugin_log(__LINE__. ': step -3 response messages - txn id : '. $merchantTxnID);
					$woocommerce->add_error($this->msg['message']);
				}

				$woocommerce->set_messages();
			}
			
			if($this->redirect_page_id!=0){
				plugin_log(__LINE__. ': redirection - txn id : '. $merchantTxnID . ' redirection page id : '.$this->redirect_page_id);
				$redirect_url = ($this->redirect_page_id == "" || $this->redirect_page_id == 0) ? get_site_url() . "/" : get_permalink($this->redirect_page_id).'/?key='.$_COOKIE[$merchantTxnID];
			}
			//change due to maintain chookie at end  point
			unset ($_COOKIE[$merchantTxnID]);
			plugin_log(__LINE__. ': transaction id test : '. $this->redirect_page_id);
			plugin_log(__LINE__. ': redirection - txn id : '. $merchantTxnID. ' redirect_url : '.$redirect_url);
			wp_redirect($redirect_url);
			
			exit;			
	    }
        
	    public function generate_edgepg_form($order_id)
	    {	
			$return_url = get_site_url();

			$return_url .= '/wc-api/' . get_class( $this );	      	//for callback
			
			$order = new WC_Order($order_id);
			$productTotalAmt_beforeDiscount = $order->get_total() + $order->get_total_discount();
			
			$order_id = $order_id . '_' . date("ymdHis");
	      
			$cart_method = $this->cart_system;
			
			$ppc_Product_Code = '';
			$ppc_MerchantProductInfo = '';

	      	$items = $order->get_items();

			foreach ($items as $item) 
			{
				$ppc_MerchantProductInfo .= $item->get_name() . '|';
			}

			$ppc_MerchantProductInfo = substr($ppc_MerchantProductInfo, 0, -1);

			//set ppc_Product_Code only if there is a single product in order
			$i= 0;
			$product_info_data = [];
			if (count($items) == 1)
			{
				foreach ($items as $item) 
				{
			    	$product_id = $item->get_product_id();
			    	$product_variation_id = $item->get_variation_id();

					// Check if product has variation.
					if ($product_variation_id)
					{ 
						$product = wc_get_product($product_variation_id);
					}
					else
					{
						$product = wc_get_product($product_id);
					}

					// Set Product Code as SKU 
					$ppc_Product_Code = $product->get_sku();
				}
			} else {
				foreach ($items as $item) 
				{
					$product_id = $item->get_product_id();
					$product_variation_id = $item->get_variation_id();

					// Check if product has variation.
					if ($product_variation_id)
					{ 
						$product = wc_get_product($product_variation_id);
					}
					else
					{
						$product = wc_get_product($product_id);
					}

					$product_details = new \stdClass();
					// Set Product Code as SKU 
					$quantity = $item->get_quantity();
					$product_details->product_code = $product->get_sku();
					$product_details->product_amount = intval(floatval($product->get_price()) * 100)*$quantity;

					$product_info_data[$i] = $product_details;

					$i++;
				}
			}
			if($cart_method == "1") {
				
				if($product_info_data){
				 
					$product_info_data = balanceCartPrice($product_info_data,$productTotalAmt_beforeDiscount);	
					
					$ppc_MultiCartProductDetails = base64_encode(json_encode($product_info_data));
				}else{
					$ppc_MultiCartProductDetails = '';
				}
			}
			$action = 'https://pinepg.in/PinePGRedirect/index';
			
			if ($this->gateway_module == 'sandbox')
			{
				$action = 'https://uat.pinepg.in/PinePGRedirect/index';
			}

			$ppc_Amount = intval(floatval($order->order_total) * 100);
			
			$cookie_name = $order_id;
			$cookie_value = $_GET['key'];
			setcookie($cookie_name, $cookie_value, time() + (86400 * 30), "/"); // 86400 = 1 day
			
			$ppc_CustomerFirstName 	= '';
			$ppc_CustomerState 		= '';
			$ppc_CustomerCountry 	= '';
			$ppc_CustomerCity 		= '';
			$ppc_CustomerLastName 	= '';
			$ppc_CustomerAddress1 	= '';
			$ppc_CustomerAddress2 	= '';
			$ppc_CustomerAddressPIN = '';
			$ppc_CustomerEmail 		= '';
			$ppc_CustomerMobile 	= '';

			if ($order->has_billing_address())
			{
				$ppc_CustomerEmail 		= $order->get_billing_email();
				$ppc_CustomerFirstName 	= $order->get_billing_first_name();
				$ppc_CustomerLastName 	= $order->get_billing_last_name();
				$ppc_CustomerMobile 	= $order->get_billing_phone();
				$ppc_CustomerAddress1 	= $order->get_billing_address_1();
				$ppc_CustomerAddress2 	= $order->get_billing_address_2();
				$ppc_CustomerCity 		= $order->get_billing_city();
	        	$ppc_CustomerState 		= WC()->countries->states[$order->get_billing_country()][$order->get_billing_state()];
	        	$ppc_CustomerCountry 	= WC()->countries->countries[$order->get_billing_country()];
				$ppc_CustomerAddressPIN = $order->get_billing_postcode();	        	
			}

			$ppc_ShippingFirstName 	 = '';
			$ppc_ShippingLastName 	 = '';
			$ppc_ShippingAddress1 	 = '';
			$ppc_ShippingAddress2 	 = '';
			$ppc_ShippingCity 		 = '';
			$ppc_ShippingState 		 = '';
			$ppc_ShippingCountry 	 = '';
			$ppc_ShippingZipCode 	 = '';

			if ($order->has_shipping_address())
			{
				$ppc_ShippingFirstName 	 = $order->get_shipping_first_name();
				$ppc_ShippingLastName 	 = $order->get_shipping_last_name();
				$ppc_ShippingAddress1 	 = $order->get_shipping_address_1();
				$ppc_ShippingAddress2 	 = $order->get_shipping_address_2();			
	        	$ppc_ShippingCity 		 = $order->get_shipping_city();
	        	$ppc_ShippingState 		 = WC()->countries->states[$order->get_shipping_country()][$order->get_shipping_state()];
	        	$ppc_ShippingCountry 	 = WC()->countries->countries[$order->get_shipping_country()];
	        	$ppc_ShippingZipCode 	 = $order->get_shipping_postcode();
			}			

			$ppc_UniqueMerchantTxnID	= $order_id;
			$ppc_MerchantID 			= $this->settings['ppc_MerchantID'];
			$ppc_MerchantAccessCode 	= $this->settings['ppc_MerchantAccessCode'];
			$ppc_NavigationMode 		= '2';
			$ppc_TransactionType 		= '1';
			$ppc_MerchantReturnURL 		= $return_url;
			$ppc_PayModeOnLandingPage	= $this->settings['ppc_PayModeOnLandingPage'];
			$ppc_LPC_SEQ 				= '1';
			$ppc_UdfField1 				= "1.3.0";
			
			if($cart_method == "1") {
				$formdata = array('ppc_UniqueMerchantTxnID' => $ppc_UniqueMerchantTxnID,
								'ppc_Amount' => $ppc_Amount,
								'ppc_MerchantID' => $ppc_MerchantID, 
								'ppc_MerchantAccessCode' => $ppc_MerchantAccessCode, 
								'ppc_NavigationMode' => $ppc_NavigationMode, 
								'ppc_TransactionType' => $ppc_TransactionType,
								'ppc_Product_Code' => $ppc_Product_Code,
								'ppc_MerchantProductInfo' => $ppc_MerchantProductInfo,
								'ppc_MerchantReturnURL' => $ppc_MerchantReturnURL,
								'ppc_PayModeOnLandingPage' => $ppc_PayModeOnLandingPage, 
								'ppc_LPC_SEQ' => $ppc_LPC_SEQ,
								'ppc_CustomerFirstName' => $ppc_CustomerFirstName,
								'ppc_CustomerState' => $ppc_CustomerState,
								'ppc_CustomerCountry' => $ppc_CustomerCountry,
								'ppc_CustomerCity' => $ppc_CustomerCity,
								'ppc_CustomerLastName' => $ppc_CustomerLastName,
								'ppc_CustomerAddress1' => $ppc_CustomerAddress1,
								'ppc_CustomerAddress2' => $ppc_CustomerAddress2,
								'ppc_CustomerAddressPIN' => $ppc_CustomerAddressPIN,
								'ppc_CustomerEmail' => $ppc_CustomerEmail,
								'ppc_CustomerMobile' => $ppc_CustomerMobile,
								'ppc_ShippingFirstName' => $ppc_ShippingFirstName,
								'ppc_ShippingLastName' => $ppc_ShippingLastName,
								'ppc_ShippingAddress1' => $ppc_ShippingAddress1,
								'ppc_ShippingAddress2' => $ppc_ShippingAddress2,
								'ppc_ShippingCity' => $ppc_ShippingCity,
								'ppc_ShippingState' => $ppc_ShippingState,
								'ppc_ShippingCountry' => $ppc_ShippingCountry,
								'ppc_ShippingZipCode' => $ppc_ShippingZipCode,
								'ppc_UdfField1' => $ppc_UdfField1,
								'ppc_UdfField2' => $ppc_TransactionType,
								'ppc_MultiCartProductDetails' => $ppc_MultiCartProductDetails							
							);

				ksort($formdata); 
				$dataString = "";
				plugin_log(__LINE__. ': form Data : '. json_encode($formdata));
				foreach ($formdata as $key => $value)
				{
					$dataString .= $key . "=" . $value . "&";
				}

				$dataString = substr($dataString, 0, -1);

				$hash = strtoupper(hash_hmac("sha256", $dataString, Hex2String($this->settings['secret_key'])));

				$html = "<html>
							<body>
								<form action=\"" . $action . "\" method=\"post\" id=\"edgepg_form\" name=\"edgepg_form\">
									<input type=\"hidden\" name=\"ppc_UniqueMerchantTxnID\" value=\"" . $ppc_UniqueMerchantTxnID . "\" />
									<input type=\"hidden\" name=\"ppc_Amount\" value=\"" . $ppc_Amount . "\" />
									<input type=\"hidden\" name=\"ppc_MerchantID\" value=\"" . $ppc_MerchantID . "\" />
									<input type=\"hidden\" name=\"ppc_MerchantAccessCode\" value=\"" . $ppc_MerchantAccessCode . "\" />
									<input type=\"hidden\" name=\"ppc_MultiCartProductDetails\" value=\"" . $ppc_MultiCartProductDetails . "\" />
									<input type=\"hidden\" name=\"ppc_NavigationMode\" value=\"" . $ppc_NavigationMode . "\" />
									<input type=\"hidden\" name=\"ppc_TransactionType\" value=\"" . $ppc_TransactionType . "\" />
									<input type=\"hidden\" name=\"ppc_Product_Code\" value=\"" . $ppc_Product_Code . "\" />
									<input type=\"hidden\" name=\"ppc_MerchantReturnURL\" value=\"" . $ppc_MerchantReturnURL . "\" />
									<input type=\"hidden\" name=\"ppc_PayModeOnLandingPage\" value=\"" . $ppc_PayModeOnLandingPage . "\" />
									<input type=\"hidden\" name=\"ppc_DIA_SECRET\" value=\"" . $hash . "\" />
									<input type=\"hidden\" name=\"ppc_DIA_SECRET_TYPE\" value=\"sha256\"/>
									<input type=\"hidden\" name=\"ppc_LPC_SEQ\" value=\"" . $ppc_LPC_SEQ . "\"/>
									<input type=\"hidden\" name=\"ppc_MerchantProductInfo\" value=\"" . htmlspecialchars($ppc_MerchantProductInfo) . "\"/>
									<input type=\"hidden\" name=\"ppc_CustomerFirstName\" value=\"" . $ppc_CustomerFirstName . "\"/>
									<input type=\"hidden\" name=\"ppc_CustomerState\" value=\"" . $ppc_CustomerState . "\"/>
									<input type=\"hidden\" name=\"ppc_CustomerCountry\" value=\"" . $ppc_CustomerCountry . "\"/>
									<input type=\"hidden\" name=\"ppc_CustomerCity\" value=\"" . $ppc_CustomerCity . "\"/>
									<input type=\"hidden\" name=\"ppc_CustomerLastName\" value=\"" . $ppc_CustomerLastName . "\"/>
									<input type=\"hidden\" name=\"ppc_CustomerAddress1\" value=\"" . $ppc_CustomerAddress1 . "\"/>
									<input type=\"hidden\" name=\"ppc_CustomerAddress2\" value=\"" . $ppc_CustomerAddress2 . "\"/>
									<input type=\"hidden\" name=\"ppc_CustomerAddressPIN\" value=\"" . $ppc_CustomerAddressPIN . "\"/>
									<input type=\"hidden\" name=\"ppc_CustomerEmail\" value=\"" . $ppc_CustomerEmail . "\"/>
									<input type=\"hidden\" name=\"ppc_CustomerMobile\" value=\"" . $ppc_CustomerMobile . "\"/>
									<input type=\"hidden\" name=\"ppc_ShippingFirstName\" value=\"" . $ppc_ShippingFirstName . "\"/>
									<input type=\"hidden\" name=\"ppc_ShippingLastName\" value=\"" . $ppc_ShippingLastName . "\"/>
									<input type=\"hidden\" name=\"ppc_ShippingAddress1\" value=\"" . $ppc_ShippingAddress1 . "\"/>
									<input type=\"hidden\" name=\"ppc_ShippingAddress2\" value=\"" . $ppc_ShippingAddress2 . "\"/>
									<input type=\"hidden\" name=\"ppc_ShippingCity\" value=\"" . $ppc_ShippingCity . "\"/>
									<input type=\"hidden\" name=\"ppc_ShippingState\" value=\"" . $ppc_ShippingState . "\"/>
									<input type=\"hidden\" name=\"ppc_ShippingCountry\" value=\"" . $ppc_ShippingCountry . "\"/>
									<input type=\"hidden\" name=\"ppc_ShippingZipCode\" value=\"" . $ppc_ShippingZipCode . "\"/>
									<input type=\"hidden\" name=\"ppc_UdfField1\" value=\"" . $ppc_UdfField1 . "\"/>
									<input type=\"hidden\" name=\"ppc_UdfField2\" value=\"" . $ppc_TransactionType . "\"/>
									<input type='submit' value='Pay Now' style='display:none' />
								</form> 
								<script type=\"text/javascript\">document.getElementById(\"edgepg_form\").submit();</script>
							</body>
						</html>";
			} else {
				$formdata = array('ppc_UniqueMerchantTxnID' => $ppc_UniqueMerchantTxnID,
							'ppc_Amount' => $ppc_Amount,
							'ppc_MerchantID' => $ppc_MerchantID, 
							'ppc_MerchantAccessCode' => $ppc_MerchantAccessCode, 
							'ppc_NavigationMode' => $ppc_NavigationMode, 
							'ppc_TransactionType' => $ppc_TransactionType,
							'ppc_Product_Code' => $ppc_Product_Code,
							'ppc_MerchantProductInfo' => $ppc_MerchantProductInfo,
							'ppc_MerchantReturnURL' => $ppc_MerchantReturnURL,
							'ppc_PayModeOnLandingPage' => $ppc_PayModeOnLandingPage, 
							'ppc_LPC_SEQ' => $ppc_LPC_SEQ,
							'ppc_CustomerFirstName' => $ppc_CustomerFirstName,
							'ppc_CustomerState' => $ppc_CustomerState,
							'ppc_CustomerCountry' => $ppc_CustomerCountry,
							'ppc_CustomerCity' => $ppc_CustomerCity,
							'ppc_CustomerLastName' => $ppc_CustomerLastName,
							'ppc_CustomerAddress1' => $ppc_CustomerAddress1,
							'ppc_CustomerAddress2' => $ppc_CustomerAddress2,
							'ppc_CustomerAddressPIN' => $ppc_CustomerAddressPIN,
							'ppc_CustomerEmail' => $ppc_CustomerEmail,
							'ppc_CustomerMobile' => $ppc_CustomerMobile,
							'ppc_ShippingFirstName' => $ppc_ShippingFirstName,
							'ppc_ShippingLastName' => $ppc_ShippingLastName,
							'ppc_ShippingAddress1' => $ppc_ShippingAddress1,
							'ppc_ShippingAddress2' => $ppc_ShippingAddress2,
							'ppc_ShippingCity' => $ppc_ShippingCity,
							'ppc_ShippingState' => $ppc_ShippingState,
							'ppc_ShippingCountry' => $ppc_ShippingCountry,
							'ppc_ShippingZipCode' => $ppc_ShippingZipCode,
							'ppc_UdfField1' => $ppc_UdfField1,
							'ppc_UdfField2' => $ppc_TransactionType
														
						);

			ksort($formdata);
			plugin_log(__LINE__. ': form Data : '. json_encode($formdata));
			$dataString = "";

			foreach ($formdata as $key => $value)
			{
				$dataString .= $key . "=" . $value . "&";
			}

			$dataString = substr($dataString, 0, -1);
			
			$hash = strtoupper(hash_hmac("sha256", $dataString, Hex2String($this->settings['secret_key'])));

			$html = "<html>
						<body>
							<form action=\"" . $action . "\" method=\"post\" id=\"edgepg_form\" name=\"edgepg_form\">
								<input type=\"hidden\" name=\"ppc_UniqueMerchantTxnID\" value=\"" . $ppc_UniqueMerchantTxnID . "\" />
								<input type=\"hidden\" name=\"ppc_Amount\" value=\"" . $ppc_Amount . "\" />
								<input type=\"hidden\" name=\"ppc_MerchantID\" value=\"" . $ppc_MerchantID . "\" />
								<input type=\"hidden\" name=\"ppc_MerchantAccessCode\" value=\"" . $ppc_MerchantAccessCode . "\" />
								<input type=\"hidden\" name=\"ppc_NavigationMode\" value=\"" . $ppc_NavigationMode . "\" />
								<input type=\"hidden\" name=\"ppc_TransactionType\" value=\"" . $ppc_TransactionType . "\" />
								<input type=\"hidden\" name=\"ppc_Product_Code\" value=\"" . $ppc_Product_Code . "\" />
								<input type=\"hidden\" name=\"ppc_MerchantReturnURL\" value=\"" . $ppc_MerchantReturnURL . "\" />
								<input type=\"hidden\" name=\"ppc_PayModeOnLandingPage\" value=\"" . $ppc_PayModeOnLandingPage . "\" />
								<input type=\"hidden\" name=\"ppc_DIA_SECRET\" value=\"" . $hash . "\" />
								<input type=\"hidden\" name=\"ppc_DIA_SECRET_TYPE\" value=\"sha256\"/>
								<input type=\"hidden\" name=\"ppc_LPC_SEQ\" value=\"" . $ppc_LPC_SEQ . "\"/>
								<input type=\"hidden\" name=\"ppc_MerchantProductInfo\" value=\"" . htmlspecialchars($ppc_MerchantProductInfo) . "\"/>
								<input type=\"hidden\" name=\"ppc_CustomerFirstName\" value=\"" . $ppc_CustomerFirstName . "\"/>
								<input type=\"hidden\" name=\"ppc_CustomerState\" value=\"" . $ppc_CustomerState . "\"/>
								<input type=\"hidden\" name=\"ppc_CustomerCountry\" value=\"" . $ppc_CustomerCountry . "\"/>
								<input type=\"hidden\" name=\"ppc_CustomerCity\" value=\"" . $ppc_CustomerCity . "\"/>
								<input type=\"hidden\" name=\"ppc_CustomerLastName\" value=\"" . $ppc_CustomerLastName . "\"/>
								<input type=\"hidden\" name=\"ppc_CustomerAddress1\" value=\"" . $ppc_CustomerAddress1 . "\"/>
								<input type=\"hidden\" name=\"ppc_CustomerAddress2\" value=\"" . $ppc_CustomerAddress2 . "\"/>
								<input type=\"hidden\" name=\"ppc_CustomerAddressPIN\" value=\"" . $ppc_CustomerAddressPIN . "\"/>
								<input type=\"hidden\" name=\"ppc_CustomerEmail\" value=\"" . $ppc_CustomerEmail . "\"/>
								<input type=\"hidden\" name=\"ppc_CustomerMobile\" value=\"" . $ppc_CustomerMobile . "\"/>
								<input type=\"hidden\" name=\"ppc_ShippingFirstName\" value=\"" . $ppc_ShippingFirstName . "\"/>
								<input type=\"hidden\" name=\"ppc_ShippingLastName\" value=\"" . $ppc_ShippingLastName . "\"/>
								<input type=\"hidden\" name=\"ppc_ShippingAddress1\" value=\"" . $ppc_ShippingAddress1 . "\"/>
								<input type=\"hidden\" name=\"ppc_ShippingAddress2\" value=\"" . $ppc_ShippingAddress2 . "\"/>
								<input type=\"hidden\" name=\"ppc_ShippingCity\" value=\"" . $ppc_ShippingCity . "\"/>
								<input type=\"hidden\" name=\"ppc_ShippingState\" value=\"" . $ppc_ShippingState . "\"/>
								<input type=\"hidden\" name=\"ppc_ShippingCountry\" value=\"" . $ppc_ShippingCountry . "\"/>
								<input type=\"hidden\" name=\"ppc_ShippingZipCode\" value=\"" . $ppc_ShippingZipCode . "\"/>
								<input type=\"hidden\" name=\"ppc_UdfField1\" value=\"" . $ppc_UdfField1 . "\"/>
								<input type=\"hidden\" name=\"ppc_UdfField2\" value=\"" . $ppc_TransactionType . "\"/>
								<input type='submit' value='Pay Now' style='display:none' />
				 			</form> 
							<script type=\"text/javascript\">document.getElementById(\"edgepg_form\").submit();</script>
		 				</body>
		 			</html>";
			}
			
			return $html;
	    }
    
	    function get_pages($title = false, $indent = true) 
	    {
			$wp_pages = get_pages('sort_column=menu_order');
			$page_list = array();
			
			if ($title) 
			{
				$page_list[] = $title;
			}

			foreach ($wp_pages as $page)
			{
				$prefix = '';
				// show indented child pages?
				if ($indent) 
				{
					$has_parent = $page->post_parent;

					while ($has_parent) 
					{
						$prefix .=  ' - ';
						$next_page = get_page($has_parent);
						$has_parent = $next_page->post_parent;
					}
				}
				// add to page list array array
				$page_list[$page->ID] = $prefix . $page->post_title;
			}
			
			return $page_list;
	    }

		function get_cart(){

		}
	}
	
	function Hex2String($hex)
	{
	    $string = '';

	    for ($i = 0; $i < strlen($hex) - 1; $i += 2) 
	    {
	        $string .= chr(hexdec($hex[$i] . $hex[$i + 1]));
	    }
	    
	    return $string;
	}

	/**
	* Add the Gateway to WooCommerce
	**/
	function woocommerce_add_edgepg_gateway($methods)
	{
		$methods[] = 'WC_EdgePg';

		return $methods;
	}

	add_filter('woocommerce_payment_gateways', 'woocommerce_add_edgepg_gateway' );
	
	function balanceCartPrice($items,$total_amt) {
		
		$sumOfAllItemPrice = sumOfAllItemPrice($items)/100; 
		$diff = calcPriceDiff($total_amt,$sumOfAllItemPrice); 
		$discount = abs($diff) * 100; 
		$total_amt = $sumOfAllItemPrice * 100; 
		
		if($diff > 0){ 

			foreach($items as $key => $value){
					$single_item_percentage = ($items[$key]->product_amount/$total_amt) * $discount;
					$get_amt = $items[$key]->product_amount + $single_item_percentage;
					$items[$key]->product_amount = $get_amt;
			}
			
		}

		if($diff < 0){ 

			foreach($items as $key => $value){
					 $single_item_percentage = ($items[$key]->product_amount/$total_amt) * $discount; 
					 $get_amt = $items[$key]->product_amount - $single_item_percentage; 
					 $items[$key]->product_amount = $get_amt;
			}

		} 
		return $items;

	}

	function calcPriceDiff($totalCartPrice, $totalSumOfProductPrice) {
		plugin_log(__LINE__. ':  Pinelab amount difference for adjustment : ' .$totalCartPrice - $totalSumOfProductPrice);
		return $totalCartPrice - $totalSumOfProductPrice;
	}

	function sumOfAllItemPrice(Array $items) {
		$amount = 0;
		foreach ($items as $product) {
			$amount += $product->product_amount;
		}
		plugin_log(__LINE__. ': Pinelab sum of total item : ' .$amount);
		return $amount;
	}
	
}

function verify($params,$PayEnvironment)
    {  
        $curl = curl_init();

        $verifyApiUrl = 'https://pinepg.in/api/PG/V2';

        if ($PayEnvironment == 'sandbox')
        {
            $verifyApiUrl = 'https://uat.pinepg.in/api/PG/V2';
        }

        curl_setopt_array($curl, array(
            CURLOPT_URL => $verifyApiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => "ppc_DIA_SECRET=" . $params['ppc_DIA_SECRET'] . "&ppc_DIA_SECRET_TYPE=" . $params['ppc_DIA_SECRET_TYPE'] . "&ppc_MerchantAccessCode=" . $params['ppc_MerchantAccessCode'] . "&ppc_MerchantID=" . $params['ppc_MerchantID'] . "&ppc_PinePGTransactionID=" . $params['ppc_PinePGTransactionID'] . "&ppc_TransactionType=" . $params['ppc_UdfField2'] . "&ppc_UniqueMerchantTxnID=" . $params['ppc_UniqueMerchantTxnID'],
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded'
            ),
        ));

        $response = curl_exec($curl);
        plugin_log(__LINE__. ': Enquiry Api response:' . $response);

        $response = json_decode($response); 
        if (isset($response->ppc_TxnResponseMessage) && $response->ppc_TxnResponseMessage == "SUCCESS" && isset($response->ppc_TxnResponseCode) && $response->ppc_TxnResponseCode == 1 && $params['ppc_Amount'] == $response->ppc_Amount) {
            return true;
        }

        return false;
    } 

	function plugin_log( $entry, $mode = 'a', $file = 'PinePG' ) { 
        // Get WordPress uploads directory.
		$log_filename = getcwd()."/wp-content/plugins/edgelogs";
		if (!file_exists($log_filename)) 
		{ 
			mkdir($log_filename, 0777, true);
		}

		$log_file_data = $log_filename.'/log_' . date('d-M-Y') . '.log';
       
		file_put_contents($log_file_data, date('Y-m-d h:i:s').': '. $entry . "\n", FILE_APPEND);
    }
?>
