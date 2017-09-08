<?php
/*
Plugin Name: TheCartPress - PayBox Payment Gateway
Plugin URI: http://www.paybox.kz
Description: Integrate your PayBox payment gateway with TheCartPress.
Version: 1.1
Author:  Platron
Author URI: http://www.platron.ru
*/

if ( !defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'TCPSkeletonLoader' ) ) {

	require_once('classes/form-builder.php');
	require_once('classes/PG_Signature.php');

	class TheCartPress_Platron_Loader {
			public static $plugin_title = 'TheCartPress - PayBox payment gateway';
			public static $plugin_description = 'Integrates PayBox payment gateway with yout TheCartPress shop.';
	        /**
	         * Checks if TheCartPress is activated
	         *
	         * @since 1.0
	         */
	        static function init() {
	            if ( ! function_exists( 'is_plugin_active' ) ) {
	                    require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
	            }
	            if ( ! is_plugin_active( 'thecartpress/TheCartPress.class.php' ) ) {
	                    add_action( 'admin_notices', array( __CLASS__,        'admin_notices' ) );
	            }
	        }

	        /**
	         * Displays a message if TheCartPress is not activated 
	         *
	         * @since 1.0
	         */
	        static function admin_notices() {
	                echo '<div class="error"><p>', __( '<strong>' . self::$plugin .'</strong> requires TheCartPress plugin activated.', 'tcp-skeleton' ), '</p></div>';
	        }

	        /**
	         * Loads the plugin itself
	         *
	         * @since 1.0
	         */
	        static function tcp_init() {
				tcp_load_platron_plugin();
	        }
	}
	//WordPress hooks
	add_action( 'init'		, array( 'TheCartPress_Platron_Loader', 'init' ) );
	//TheCartPress hooks
	add_action( 'tcp_init'		, array( 'TheCartPress_Platron_Loader', 'tcp_init' ) );

	/**
	 * Loads the skeleton payment/shipping plugin
	 *
	 * @since 1.0
	 */
	function tcp_load_platron_plugin() {
	        
		class PlatronForTheCartPress extends TCP_Plugin {
			const protocol = 7;

			function getFields() {
				$fields = array(
					'platron_merchant_id' => array(
						'title' => __('PayBox Merchant ID', 'tcp-platron'),
						'type' => 'text',
						'description' => __('Type in your merchant ID from <a href="https://paybox.kz/admin/merchants.php">PayBox</a>.', 'tcp-platron')
					),	
					'platron_secret_key' => array(
						'title' => __('Secret key', 'tcp-platron'),
						'type' => 'text',
						'description' => __('Secret key from <a href="https://paybox.kz/admin/merchants.php">PayBox</a>.', 'tcp-platron')
					),
					'platron_lifetime' => array(
						'title' => __('Lifetime', 'tcp-platron'),
						'type' => 'text',
						'description' => 'Time live transaction. Set in minutes. 0 - don`t use. Max 7 days',
						'default' => '0'
					),
					'platron_demo_mode' => array(
						'title' => __( 'Demo mode', 'tcp-platron' ), 
						'type' => 'checkbox', 
						'label' => __( 'Enable/Disable', 'tcp-platron' ), 
						'description' => __( 'To test integration and connection.' ), 
						'default' => 'yes'
					),
					'platron_payment_system' => array(
						'title' => __('Payment system', 'tcp-platron'),
						'type' => 'text',
						'description' => 'If you wan`t customer to choose payment systen on shop side - set this parameter',
					),
					'platron_logo' => array(
						'title' => __('Payment system logo', 'tcp-platron'),
						'type' => 'text',
						'description' => 'If you wan`t customer to see payment system logo',
						'default' => 'https://paybox.kz/assets/frontend/img/logo.png',
					),
				);

				return $fields;			
			}
			function getTitle() {
				return TheCartPress_Platron_Loader::$plugin_title;
			}

			function getDescription() {
				return TheCartPress_Platron_Loader::$plugin_description;
			}

			function showEditFields( $data ) {
				$fields = $this->getFields();

				foreach($fields as $id => $field) {
					if(method_exists('Platron_Form_Builder', $field['type'])) {
						call_user_func_array(array('Platron_Form_Builder', $field['type']), array($id, $field, $data));
					}
				}
			}

			function saveEditFields( $data ) {
				$fields = $this->getFields();
				foreach($fields as $id => $field) {	
					$data[$id] = isset($_REQUEST[$id]) ? $_REQUEST[$id] : '';
				}
				return $data;
			} 

			function getCheckoutMethodLabel( $instance, $shippingCountry, $shoppingCart = false) {
				$data = tcp_get_payment_plugin_data( 'PlatronForTheCartPress', $instance );
				$title = isset( $data['title'] ) ? $data['title'] : $this->getTitle();
				$image = '';
				if(!empty($data['platron_logo']))
					$image = "<img src='$data[platron_logo]' alt='$title'> ";
				return tcp_string( 'TheCartPress', 'pay_PlatronForTheCartPress-title', $image . $title ); //multilanguage
			}

			function getCost( $instance, $shippingCountry, $shoppingCart = false ) {			
				return 0;
			}

			function getNotice(  $instance, $shippingCountry, $shoppingCart, $order_id = 0 ) {
				$data = tcp_get_payment_plugin_data( get_class( $this ), $instance );
				return isset( $data['notice'] ) ? $data['notice'] : '';
			}

			function showPayForm( $instance, $shippingCountry, $shoppingCart, $order_id ) {
				$data = tcp_get_payment_plugin_data( get_class( $this ), $instance );
				$order = Orders::get( $order_id );
				$order_details = OrdersDetails::getDetails( $order_id );
				$strDescription = '';
				foreach($order_details as $objItem){
					$strDescription .= strip_tags($objItem->name);
					if($objItem->qty_ordered > 1)
						$strDescription .= "*".$objItem->qty_ordered;
					$strDescription .= "; ";
				}
				
				$strCurrency = tcp_get_the_currency_iso();
				$amount = $this->format_price( Orders::getTotal( $order_id ) );
				if($strCurrency == 'RUR')
					$strCurrency = 'RUB';
				
				$strNotifyUrl = add_query_arg( 
					array(
						'action' => 'tcp_platron_ipn',
						'instance' => $instance
					), admin_url( 'admin-ajax.php' ) );
				$continue_url = add_query_arg( 'tcp_checkout', 'ok', tcp_get_the_checkout_url() );
				$cancel_url = add_query_arg( 'tcp_checkout', 'ko', tcp_get_the_checkout_url() );
				
				$arrFields = array(
					'pg_merchant_id'		=> $data['platron_merchant_id'],
					'pg_order_id'			=> $order_id,
					'pg_currency'			=> $strCurrency,
					'pg_amount'				=> sprintf('%0.2f', $amount),
					'pg_lifetime'			=> isset($this->payment_params->lifetime)?$this->payment_params->lifetime*60:0,
					'pg_testing_mode'		=> ($data['platron_demo_mode'] == 'yes')?1:0,
					'pg_description'		=> $strDescription,
					'pg_user_ip'			=> $_SERVER['REMOTE_ADDR'],
					'pg_language'			=> (WPLANG == 'ru_RU')?'ru':'en',
					'pg_check_url'			=> $strNotifyUrl,
					'pg_result_url'			=> $strNotifyUrl,
					'pg_success_url'		=> $continue_url,
					'pg_failure_url'		=> $cancel_url,
					'pg_request_method'		=> 'GET',
					'pg_salt'				=> rand(21,43433), // Параметры безопасности сообщения. Необходима генерация pg_salt и подписи сообщения.
				);
				
				if(!empty($order->shipping_telephone_1)){
					preg_match_all("/\d/", $order->shipping_telephone_1, $array);
					$strPhone = implode('',@$array[0]);
					$arrFields['pg_user_phone'] = $strPhone;
				}

				if(!empty($order->shipping_telephone_2)){
					preg_match_all("/\d/", $order->shipping_telephone_2, $array);
					$strPhone = implode('',@$array[0]);
					$arrFields['pg_user_phone'] = $strPhone;
				}

				if(!empty($order->shipping_email)){
					$arrFields['pg_user_email'] = $order->shipping_email;
					$arrFields['pg_user_contact_email'] = $order->shipping_email;
				}
				
				if(!empty($order->billing_email)){
					$arrFields['pg_user_email'] = $order->billing_email;
					$arrFields['pg_user_contact_email'] = $order->billing_email;
				}

				if(!empty($data['platron_payment_system']))
					$arrFields['pg_payment_system'] = $data['platron_payment_system'];

				$arrFields['pg_sig'] = PG_Signature::make('payment.php', $arrFields, $data['platron_secret_key']);
				
				echo '<form id="platron_payment_form" action="https://www.paybox.kz/payment.php" method="post">';
				foreach($arrFields as $strName => $strValue){
					echo '<input type="hidden" name="'.$strName.'" value="'.$strValue.'" />';
				}
				echo '<input type="submit" value="'.__('Pay', 'tcp-platron').'" /></form>';
				
				Orders::editStatus( $order_id, $data['new_status'] );
				require_once( TCP_CHECKOUT_FOLDER . 'ActiveCheckout.class.php' );
			}

			function tcp_platron_ipn() {
				if(!empty($_POST))
					$arrRequest = $_POST;
				else
					$arrRequest = $_GET;
				
				$order = Orders::get( $arrRequest['pg_order_id'] );
				$data = (object) tcp_get_payment_plugin_data( get_class( $this ), $arrRequest['instance'] );
				$amount = $this->format_price( Orders::getTotal( $arrRequest['pg_order_id'] ) );
								
				$thisScriptName = PG_Signature::getOurScriptName();
				if (empty($arrRequest['pg_sig']) || !PG_Signature::check($arrRequest['pg_sig'], $thisScriptName, $arrRequest, $data->platron_secret_key))
					die("Wrong signature");

				if(!isset($arrRequest['pg_result'])){
					$bCheckResult = 0;
					if(empty($order) || $order->status != $data->new_status)
						$error_desc = "Товар не доступен. Либо заказа нет, либо его статус " . $order->status;	
					elseif(sprintf('%0.2f',$arrRequest['pg_amount']) != sprintf('%0.2f',$amount))
						$error_desc = "Неверная сумма";
					else
						$bCheckResult = 1;

					$arrResponse['pg_salt']              = $arrRequest['pg_salt']; // в ответе необходимо указывать тот же pg_salt, что и в запросе
					$arrResponse['pg_status']            = $bCheckResult ? 'ok' : 'error';
					$arrResponse['pg_error_description'] = $bCheckResult ?  ""  : $error_desc;
					$arrResponse['pg_sig']				 = PG_Signature::make($thisScriptName, $arrResponse, $data->platron_secret_key);

					$objResponse = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><response/>');
					$objResponse->addChild('pg_salt', $arrResponse['pg_salt']);
					$objResponse->addChild('pg_status', $arrResponse['pg_status']);
					$objResponse->addChild('pg_error_description', $arrResponse['pg_error_description']);
					$objResponse->addChild('pg_sig', $arrResponse['pg_sig']);

				}
				else{
					$bResult = 0;
					if(empty($order) || 
							($order->status != $data->new_status &&
							!($order->status != Orders::$ORDER_PROCESSING && $arrRequest['pg_result'] == 1) && 
							( $order->status != Orders::$ORDER_CANCELLED && $arrRequest['pg_result'] == 0)))
						$strResponseDescription = "Товар не доступен. Либо заказа нет, либо его статус " . $order->status;		
					elseif(sprintf('%0.2f',$arrRequest['pg_amount']) != sprintf('%0.2f',$amount))
						$strResponseDescription = "Неверная сумма";
					else {
						$bResult = 1;
						$strResponseStatus = 'ok';
						$strResponseDescription = "Оплата принята";
						if ($arrRequest['pg_result'] == 1) {
							// Установим статус оплачен
							$order_status = Orders::$ORDER_PROCESSING;
						}
						else{
							// Не удачная оплата
							$order_status = Orders::$ORDER_CANCELLED;
						}
					}
					// Transaction status
					$additional = 'PayBox transaction # ' . $arrRequest['pg_payment_id'];
					// Update the order with new state, transaction id and transaction status message
					Orders::editStatus( $arrRequest['pg_order_id'], $order_status, $arrRequest['pg_payment_id'], $additional );
						
					if(!$bResult)
						if($arrRequest['pg_can_reject'] == 1)
							$strResponseStatus = 'rejected';
						else
							$strResponseStatus = 'error';

					$objResponse = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><response/>');
					$objResponse->addChild('pg_salt', $arrRequest['pg_salt']); // в ответе необходимо указывать тот же pg_salt, что и в запросе
					$objResponse->addChild('pg_status', $strResponseStatus);
					$objResponse->addChild('pg_description', $strResponseDescription);
					$objResponse->addChild('pg_sig', PG_Signature::makeXML($thisScriptName, $objResponse, $data->platron_secret_key));
				}

				header("Content-type: text/xml");
				echo $objResponse->asXML();
				die();
			}

			function format_price( $price ) {
				return number_format($price * 100, 0, '', '');
			}

		}

		if ( function_exists( 'tcp_register_payment_plugin' ) ) tcp_register_payment_plugin( 'PlatronForTheCartPress' );

		$Platron_Instance = new PlatronForTheCartPress();
		add_action( 'wp_ajax_tcp_platron_ipn'		, array( $Platron_Instance, 'tcp_platron_ipn' ) );
		add_action( 'wp_ajax_nopriv_tcp_platron_ipn'	, array( $Platron_Instance, 'tcp_platron_ipn' ) );
	}
}// class_exists check
?>