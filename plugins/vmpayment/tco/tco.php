<?php

defined('_JEXEC') or die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');

/**
 *
 * @author Craig Christenson
 * @version $Id: tco.php$
 * @package VirtueMart
 * @subpackage payment
 * @copyright Copyright (C) 2004-2008 soeren - All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * http://virtuemart.org
 */
if (!class_exists('vmPSPlugin'))
require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');

class plgVmPaymentTco extends vmPSPlugin {

	public static $_this = false;

	function __construct(& $subject, $config) {

		parent::__construct($subject, $config);

		$this->_loggable = true;
		$this->tableFields = array_keys($this->getTableSQLFields());

		$varsToPush = array('tco_seller_id' => array('', 'char'),
	    'tco_secret_word' => array('', 'char'),
	    'payment_currency' => array('', 'int'),
	    'payment_logos' => array('', 'char'),
	    'sandbox' => array(0, 'char'),
        'direct_checkout' => array(0, 'char'),
	    'debug' => array(0, 'int'),
	    'status_pending' => array('', 'char'),
	    'status_success' => array('', 'char'),
	    'status_canceled' => array('', 'char'),
	    'countries' => array(0, 'char'),
	    'min_amount' => array(0, 'int'),
	    'max_amount' => array(0, 'int'),
	    'cost_per_transaction' => array(0, 'int'),
	    'cost_percent_total' => array(0, 'int'),
	    'tax_id' => array(0, 'int')
		);

		$this->setConfigParameterable($this->_configTableFieldName, $varsToPush);

	}

	function _getTcoDetails($method)
    {
    	$tcoDetails = array(
                'seller_id' => $method->tco_seller_id,
                'secret_word' => $method->tco_secret_word,
                'url' => 'https://www.2checkout.com/checkout/spurchase'
            );

    	return $tcoDetails;
    }

	public function getVmPluginCreateTableSQL() {

		return $this->createTableSQL('Payment 2Checkout Table');
	}

	function getTableSQLFields() {

		$SQLfields = array(
	    'id' => ' INT(11) unsigned NOT NULL AUTO_INCREMENT ',
	    'virtuemart_order_id' => ' int(11) UNSIGNED DEFAULT NULL',
	    'order_number' => ' char(32) DEFAULT NULL',
	    'virtuemart_paymentmethod_id' => ' mediumint(1) UNSIGNED DEFAULT NULL',
	    'payment_name' => ' char(255) NOT NULL DEFAULT \'\' ',
	    'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\' ',
	    'payment_currency' => 'char(3) ',
	    'cost_per_transaction' => ' decimal(10,2) DEFAULT NULL ',
	    'cost_percent_total' => ' decimal(10,2) DEFAULT NULL ',
	    'tax_id' => ' smallint(1) DEFAULT NULL',
	    'tco_response' => ' varchar(255)  ',
	    'tco_response_payment_date' => ' char(28) DEFAULT NULL'
		);
		return $SQLfields;
	}

	function plgVmConfirmedOrder($cart, $order) {

		if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
			return null; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return false;
		}
		$session = JFactory::getSession();
		$return_context = $session->getId();
		$this->_debug = $method->debug;

		if (!class_exists('VirtueMartModelOrders'))
		require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
		if (!class_exists('VirtueMartModelCurrency'))
		require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');

		//$usr = & JFactory::getUser();
		$new_status = '';

		$usrBT = $order['details']['BT'];
                $address = ((isset($order['details']['BT'])) ? $order['details']['BT'] : $order['details']['ST']);

		if (!class_exists('TableVendors'))
		require(JPATH_VM_ADMINISTRATOR . DS . 'table' . DS . 'vendors.php');
		$vendorModel = VmModel::getModel('Vendor');
		$vendorModel->setId(1);
		$vendor = $vendorModel->getVendor();
		$vendorModel->addImages($vendor, 1);
		$this->getPaymentCurrency($method);
		$q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $method->payment_currency . '" ';
		$db = &JFactory::getDBO();
		$db->setQuery($q);
		$currency_code_3 = $db->loadResult();

		$paymentCurrency = CurrencyDisplay::getInstance($method->payment_currency);
		$totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total, false), 2);
		$cd = CurrencyDisplay::getInstance($cart->pricesCurrency);

		$tcoDetails = $this->_getTcoDetails($method);

		if (empty($tcoDetails['seller_id'])) {
			vmInfo(JText::_('VMPAYMENT_TCO_SELLER_ID_NOT_SET'));
			return false;
		}
	    if ($method->sandbox == 1) {
	    	$demo = "Y";
	    } else {
	    	$demo = "N";
	    }
		$testReq = $method->debug == 1 ? 'YES' : 'NO';
		$post_variables = Array(
                    "sid" => $tcoDetails['seller_id'],
                    "x_receipt_link_url" => JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id."&o_id={$order['details']['BT']->order_number}"),
                    "merchant_order_id" => $order['details']['BT']->order_number,
                    "custom" => $return_context,
                    "cart_order_id" => JText::_('VMPAYMENT_TCO_ORDER_NUMBER') . ': ' . $order['details']['BT']->order_number,
                    "total" => $totalInPaymentCurrency,
                    "currency_code" => $currency_code_3,
                    "first_name" => $address->first_name,
                    "last_name" => $address->last_name,
                    "street_address" => $address->address_1,
                    "street_address2" => isset($address->address_2) ? $address->address_2 : '',
                    "zip" => $address->zip,
                    "city" => $address->city,
                    "state" => isset($address->virtuemart_state_id) ? ShopFunctions::getStateByID($address->virtuemart_state_id) : '',
                    "country" => ShopFunctions::getCountryByID($address->virtuemart_country_id, 'country_3_code'),
                    "email" => $order['details']['BT']->email,
                    "phone" => $address->phone_1,
                    "demo" => $demo,
                    "purchase_step" => 'payment-method',
                    "id_type" => "1"
		);

	  $i = 0;
		foreach ($cart->products as $key => $product) {
		$post_variables["c_prod_" . $i] = $i.",".$product->quantity;
		$post_variables["c_name_" . $i] = substr(strip_tags($product->product_name), 0, 127);
		$post_variables["c_description_" . $i] = substr(strip_tags($product->product_name), 0, 127);
		$post_variables["c_price_" . $i] = $cart->pricesUnformatted[$key]['salesPrice'];
		$i++;
		}



		// Prepare data that should be stored in the database
		$dbValues['order_number'] = $order['details']['BT']->order_number;
		$dbValues['payment_name'] = $this->renderPluginName($method, $order);
		$dbValues['virtuemart_paymentmethod_id'] = $cart->virtuemart_paymentmethod_id;
		$dbValues['tco_custom'] = $return_context;
		$dbValues['cost_per_transaction'] = $method->cost_per_transaction;
		$dbValues['cost_percent_total'] = $method->cost_percent_total;
		$dbValues['payment_currency'] = $method->payment_currency;
		$dbValues['payment_order_total'] = $totalInPaymentCurrency;
		$dbValues['tax_id'] = $method->tax_id;
		$this->storePSPluginInternalData($dbValues);

		// add spin image
		$html = '<html><head><title>Redirection</title></head><body><div style="margin: auto; text-align: center;">';
		$html .= '<form action="' . $tcoDetails['url'] . '" method="post" name="vm_tco_form" >';
		foreach ($post_variables as $name => $value) {
			$html.= '<input type="hidden" name="' . $name . '" value="' . htmlspecialchars($value) . '" />';
		}

        if ($method->direct_checkout == 1) {
            $html.= '<input type="submit"  value="' . JText::_('VMPAYMENT_TCO_BUTTON_MESSAGE') . '" />';
            $html.= '</form></div>';
            $html.= '<script src="https://www.2checkout.com/static/checkout/javascript/direct.min.js"></script>';
        } else {
            $html.= '<input type="submit"  value="' . JText::_('VMPAYMENT_TCO_REDIRECT_MESSAGE') . '" />';
            $html.= '</form></div>';
            $html.= ' <script type="text/javascript">';
            $html.= ' document.vm_tco_form.submit();';
            $html.= '</script>';
        }
        $html.= '</body></html>';

		return $this->processConfirmedOrderPaymentResponse(2, $cart, $order, $html, $dbValues['payment_name'], $new_status);
	}

	function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId) {

		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return null; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return false;
		}
		$this->getPaymentCurrency($method);
		$paymentCurrencyId = $method->payment_currency;
	}

	function plgVmOnPaymentResponseReceived(&$html) {

		// the payment itself should send the parameter needed.
		$virtuemart_paymentmethod_id = JRequest::getInt('pm', 0);
		// $order_number = JRequest::getVar('on', 0);
		$order_number = JRequest::getVar('on', 0);
		$vendorId = 0;
		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return null; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return false;
		}
		if (!class_exists('VirtueMartCart'))
		require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
		if (!class_exists('shopFunctionsF'))
		require(JPATH_VM_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
		if (!class_exists('VirtueMartModelOrders'))
		require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );

		$tco_data = JRequest::get('request');
		$payment_name = $this->renderPluginName($method);

		if (!empty($tco_data)) {
			vmdebug('plgVmOnPaymentResponseReceived', $tco_data);
			$order_number = $tco_data['merchant_order_id'];
			$return_context = $tco_data['custom'];
			$virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number);
			$payment_name = $this->renderPluginName($method);
			$html = $this->_getPaymentResponseHtml($tco_data, $payment_name);
			if ($virtuemart_order_id) {
				$order['customer_notified']=1;
				$order['order_status'] = $this->_getPaymentStatus($method, $tco_data['key'], $tco_data['demo'], $tco_data['order_number'], $tco_data['total']);
				$order['comments'] = JText::sprintf('VMPAYMENT_TCO_PAYMENT_STATUS_CONFIRMED', $order_number);
				// send the email ONLY if payment has been accepted
				$modelOrder = VmModel::getModel('orders');
				$orderitems = $modelOrder->getOrder($virtuemart_order_id);
				$nb_history = count($orderitems['history']);
				if ($orderitems['history'][$nb_history - 1]->order_status_code != $order['order_status']) {
					$this->_storeTcoInternalData($method, $tco_data, $virtuemart_order_id);
					$this->logInfo('plgVmOnPaymentResponseReceived, sentOrderConfirmedEmail ' . $order_number, 'message');
					$order['virtuemart_order_id'] = $virtuemart_order_id;
					$order['comments'] = JText::sprintf('VMPAYMENT_TCO_EMAIL_SENT');
					$modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);
				}
			} else {
				vmError('2Checkout data received, but no order number');
				return;
			}
		} else {
			$virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number);
		}

		$cart = VirtueMartCart::getCart();
		$cart->emptyCart();
		return true;
	}

	function plgVmOnUserPaymentCancel() {

		if (!class_exists('VirtueMartModelOrders'))
		require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );

		$order_number = JRequest::getVar('on');
		if (!$order_number)
		return false;
		$db = JFactory::getDBO();
		$query = 'SELECT ' . $this->_tablename . '.`virtuemart_order_id` FROM ' . $this->_tablename . " WHERE  `order_number`= '" . $order_number . "'";

		$db->setQuery($query);
		$virtuemart_order_id = $db->loadResult();

		if (!$virtuemart_order_id) {
			return null;
		}
		$this->handlePaymentUserCancel($virtuemart_order_id);

		//JRequest::setVar('paymentResponse', $returnValue);
		return true;
	}

	function _storeTcoInternalData($method, $tco_data, $virtuemart_order_id) {

		// get all know columns of the table
		$db = JFactory::getDBO();
		$query = 'SHOW COLUMNS FROM `' . $this->_tablename . '` ';
		$db->setQuery($query);
		$columns = $db->loadResultArray(0);
		$post_msg = '';
		foreach ($tco_data as $key => $value) {
			$post_msg .= $key . "=" . $value . "<br />";
			$table_key = 'tco_response_' . $key;
			if (in_array($table_key, $columns)) {
				$response_fields[$table_key] = $value;
			}
		}

		$response_fields['payment_name'] = $this->renderPluginName($method);
		$response_fields['tcoresponse_raw'] = $post_msg;
		$return_context = $tco_data['custom'];
		$response_fields['order_number'] = $tco_data['merchant_order_id'];
		$response_fields['virtuemart_order_id'] = $virtuemart_order_id;
		$this->storePSPluginInternalData($response_fields, 'virtuemart_order_id', true);
	}

	function _getTablepkeyValue($virtuemart_order_id) {
		$db = JFactory::getDBO();
		$q = 'SELECT ' . $this->_tablepkey . ' FROM `' . $this->_tablename . '` '
		. 'WHERE `virtuemart_order_id` = ' . $virtuemart_order_id;
		$db->setQuery($q);

		if (!($pkey = $db->loadResult())) {
			JError::raiseWarning(500, $db->getErrorMsg());
			return '';
		}
		return $pkey;
	}

	function _getPaymentStatus($method, $key, $demo, $order_number, $total) {
		$tcoDetails = $this->_getTcoDetails($method);
		if ($demo == 'Y') {
			$order_number = 1;
		}
		$compare_string = $tcoDetails['secret_word'] . $tcoDetails['seller_id'] . $order_number . $total;
  		$compare_hash1 = strtoupper(md5($compare_string));
  		$compare_hash2 = $key;
 		if ($compare_hash1 != $compare_hash2) {
			$new_status = $method->status_pending;
		} else {
			$new_status = $method->status_success;
		}
		return $new_status;
	}

	/**
	 * Display stored payment data for an order
	 * @see components/com_virtuemart/helpers/vmPSPlugin::plgVmOnShowOrderBEPayment()
	 */
	function plgVmOnShowOrderBEPayment($virtuemart_order_id, $payment_method_id) {

		if (!$this->selectedThisByMethodId($payment_method_id)) {
			return null; // Another method was selected, do nothing
		}

		$this->getPaymentCurrency($paymentTable);
		$q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $paymentTable->payment_currency . '" ';
		$db = &JFactory::getDBO();
		$db->setQuery($q);
		$currency_code_3 = $db->loadResult();
		$html = '<table class="adminlist">' . "\n";
		$html .=$this->getHtmlHeaderBE();
		$code = "tco_response_";
		foreach ($paymentTable as $key => $value) {
			if (substr($key, 0, strlen($code)) == $code) {
				$html .= $this->getHtmlRowBE($key, $value);
			}
		}
		$html .= '</table>' . "\n";
		return $html;
	}

	function _getPaymentResponseHtml($tcoTable, $payment_name) {

		$html = '<table>' . "\n";
		$html .= $this->getHtmlRow('TCO_PAYMENT_NAME', $payment_name);
		if (!empty($tcoTable)) {
			$html .= $this->getHtmlRow('TCO_ORDER_NUMBER', $tcoTable['order_number']);
			$html .= $this->getHtmlRow('TCO_AMOUNT', $tcoTable['total'] . " " . $tcoTable['currency_code']);
		}
		$html .= '</table>' . "\n";

		return $html;
	}

	function getCosts(VirtueMartCart $cart, $method, $cart_prices) {
		if (preg_match('/%$/', $method->cost_percent_total)) {
			$cost_percent_total = substr($method->cost_percent_total, 0, -1);
		} else {
			$cost_percent_total = $method->cost_percent_total;
		}
		return ($method->cost_per_transaction + ($cart_prices['salesPrice'] * $cost_percent_total * 0.01));
	}


	protected function checkConditions($cart, $method, $cart_prices) {


		$address = (($cart->ST == 0) ? $cart->BT : $cart->ST);

		$amount = $cart_prices['salesPrice'];
		$amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount
		OR
		($method->min_amount <= $amount AND ($method->max_amount == 0) ));

		$countries = array();
		if (!empty($method->countries)) {
			if (!is_array($method->countries)) {
				$countries[0] = $method->countries;
			} else {
				$countries = $method->countries;
			}
		}
		// probably did not gave his BT:ST address
		if (!is_array($address)) {
			$address = array();
			$address['virtuemart_country_id'] = 0;
		}

		if (!isset($address['virtuemart_country_id']))
		$address['virtuemart_country_id'] = 0;
		if (in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0) {
			if ($amount_cond) {
				return true;
			}
		}

		return false;
	}

	/**
	 * We must reimplement this triggers for joomla 1.7
	 */

	/**
	 * Create the table for this plugin if it does not yet exist.
	 * This functions checks if the called plugin is active one.
	 * When yes it is calling the standard method to create the tables
	 * @author Valérie Isaksen
	 *
	 */
	function plgVmOnStoreInstallPaymentPluginTable($jplugin_id) {

		return $this->onStoreInstallPluginTable($jplugin_id);
	}

	/**
	 * This event is fired after the payment method has been selected. It can be used to store
	 * additional payment info in the cart.
	 *
	 * @author Max Milbers
	 * @author Valérie isaksen
	 *
	 * @param VirtueMartCart $cart: the actual cart
	 * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
	 *
	 */
	public function plgVmOnSelectCheckPayment(VirtueMartCart $cart) {
		return $this->OnSelectCheck($cart);
	}

	/**
	 * plgVmDisplayListFEPayment
	 * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
	 *
	 * @param object $cart Cart object
	 * @param integer $selected ID of the method selected
	 * @return boolean True on succes, false on failures, null when this plugin was not selected.
	 * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
	 *
	 * @author Valerie Isaksen
	 * @author Max Milbers
	 */
	public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn) {
		return $this->displayListFE($cart, $selected, $htmlIn);
	}

	/*
	 * plgVmonSelectedCalculatePricePayment
	* Calculate the price (value, tax_id) of the selected method
	* It is called by the calculator
	* This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
	* @author Valerie Isaksen
	* @cart: VirtueMartCart the current cart
	* @cart_prices: array the new cart prices
	* @return null if the method was not selected, false if the shiiping rate is not valid any more, true otherwise
	*
	*
	*/

	public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {
		return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
	}

	/**
	 * plgVmOnCheckAutomaticSelectedPayment
	 * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
	 * The plugin must check first if it is the correct type
	 * @author Valerie Isaksen
	 * @param VirtueMartCart cart: the cart object
	 * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
	 *
	 */
	function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array()) {
		return $this->onCheckAutomaticSelected($cart, $cart_prices);
	}

	/**
	 * This method is fired when showing the order details in the frontend.
	 * It displays the method-specific data.
	 *
	 * @param integer $order_id The order ID
	 * @return mixed Null for methods that aren't active, text (HTML) otherwise
	 * @author Max Milbers
	 * @author Valerie Isaksen
	 */
	public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {
		$this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
	}

	/**
	 * This event is fired during the checkout process. It can be used to validate the
	 * method data as entered by the user.
	 *
	 * @return boolean True when the data was valid, false otherwise. If the plugin is not activated, it should return null.
	 * @author Max Milbers

	 public function plgVmOnCheckoutCheckDataPayment($psType, VirtueMartCart $cart) {
	 return null;
	 }
	 */

	/**
	 * This method is fired when showing when priting an Order
	 * It displays the the payment method-specific data.
	 *
	 * @param integer $_virtuemart_order_id The order ID
	 * @param integer $method_id  method used for this order
	 * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
	 * @author Valerie Isaksen
	 */
	function plgVmonShowOrderPrintPayment($order_number, $method_id) {
		return $this->onShowOrderPrint($order_number, $method_id);
	}

	/**
	 * Save updated order data to the method specific table
	 *
	 * @param array $_formData Form data
	 * @return mixed, True on success, false on failures (the rest of the save-process will be
	 * skipped!), or null when this method is not actived.
	 * @author Oscar van Eijk

	 public function plgVmOnUpdateOrderPayment(  $_formData) {
	 return null;
	 }
	 */
	/**
	 * Save updated orderline data to the method specific table
	 *
	 * @param array $_formData Form data
	 * @return mixed, True on success, false on failures (the rest of the save-process will be
	 * skipped!), or null when this method is not actived.
	 * @author Oscar van Eijk

	 public function plgVmOnUpdateOrderLine(  $_formData) {
	 return null;
	 }
	 */
	/**
	 * plgVmOnEditOrderLineBE
	 * This method is fired when editing the order line details in the backend.
	 * It can be used to add line specific package codes
	 *
	 * @param integer $_orderId The order ID
	 * @param integer $_lineId
	 * @return mixed Null for method that aren't active, text (HTML) otherwise
	 * @author Oscar van Eijk

	 public function plgVmOnEditOrderLineBE(  $_orderId, $_lineId) {
	 return null;
	 }
	 */

	/**
	 * This method is fired when showing the order details in the frontend, for every orderline.
	 * It can be used to display line specific package codes, e.g. with a link to external tracking and
	 * tracing systems
	 *
	 * @param integer $_orderId The order ID
	 * @param integer $_lineId
	 * @return mixed Null for method that aren't active, text (HTML) otherwise
	 * @author Oscar van Eijk

	 public function plgVmOnShowOrderLineFE(  $_orderId, $_lineId) {
	 return null;
	 }
	 */
	function plgVmDeclarePluginParamsPayment($name, $id, &$data) {
		return $this->declarePluginParams('payment', $name, $id, $data);
	}

	function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) {
		return $this->setOnTablePluginParams($name, $id, $table);
	}

}

// No closing tag
