<?php

function processInvoice ($invoice, $payment) {
		//$payment->getOrder()->sendNewOrderEmail();
		$order = $payment->getOrder();
		if (!$invoice->isCanceled()) {
			$invoice->sendEmail();
			$order->addStatusToHistory(
						$order->getStatus(),
						'Invoice email sent',
						'Invoice email sent'
					);
		}
	}	
	
	/**
	* Send authorize request to gateway
	*
	* @param   Varien_Object $payment
	* @param   decimal $amount
	* @return  Mage_Payeezy_Model_Payeezy
	*/
	public function authorize(Varien_Object $payment, $amount)
	{



		$error = false;
		
		if ($amount>0) {
			$payment->setAnetTransType(self::REQUEST_TYPE_AUTH_ONLY);
			$payment->setAmount($amount);

			
			$request = $this->_buildRequest($payment);



			$result  = $this->_postRequest($request);

		
			
			$payment->setCcApproval($result->getApprovalCode())
			->setLastTransId($result->getTransactionId())
			->setCcTransId($result->getTransactionTag())
			->setCcAvsStatus($result->getAvsResultCode())
			->setCcCidStatus($result->getCardCodeResponseCode());
			

			$code = $result->getBankRespCode();
			$text = $result->getGatewayMessage();
			
			
			switch ($result->getTransactionStatus()) {
				case self::RESPONSE_CODE_APPROVED:
					$payment->setStatus(self::STATUS_APPROVED);
					if ($result->getTransactionId() != $payment->getParentTransactionId()) {
						$payment->setTransactionId($result->getTransactionId());
						$payment->setCcTransId($result->getTransactionTag());
						$payment->setLastTransId($result->getTransactionId());
					}					
					$payment
						->setIsTransactionClosed(0)
						->setTransactionAdditionalInfo('real_transaction_id', $result->getTransactionId());					
					// added by Gayatri 10/Jun/2010
					if( !$order = $payment->getOrder() )
					{
						$order = $payment->getQuote();
					}



                   $message = urldecode(ucwords($result->getTransactionStatus())) . ' at Payeezy, Trans ID: ' . $result->getTransactionId().
						$result->getGatewayMessage() . ' from Payeezy, Trans ID: ' . $result->getTransactionId(). 
						' Transaction Tag : ' . $result->getTransactionTag().
						' Transaction Type : ' . $result->getTransactionType() ;

					$order->addStatusToHistory(
						$order->getStatus(), $message);
					// end added by Gayatri 10/Jun/2010




					break;
					
				case self::RESPONSE_CODE_DECLINED:
					$error = Mage::helper('paygate')->__('Payment authorization transaction has been declined. ' . "\n$text");
					break;
					
				default:
					$error = Mage::helper('paygate')->__('Payment authorization error. ' . "\n$text");
					break;
			}
		} else {
			$error = Mage::helper('paygate')->__('Invalid amount for authorization.');
		}

		if ($error !== false) {
			Mage::throwException($error);
		}

		/* for partial payment 24 April 2015 */

 	   $paymentparam = Mage::app()->getRequest()->getParams();
       if($paymentparam['payment']['partial_payment']=='on'){
			$this->authorizeForSplit($payment, $amount);
		}

	  /* End for partial payment 24 April 2015 */	
    
		return $this;
	}


   
   public function authorizeForSplit(Varien_Object $payment, $amount)
	{



		$error = false;
		
		if ($amount>0) {
			

			    /* for split */
			$payment->setAnetTransType(self::REQUEST_TYPE_AUTH_CAPTURE);
					
			$request = $this->_buildRequest($payment);
		    $result  = $this->_postRequest($request);
		    $payment->setAmount($result->getAmount());	


		    
		    /* End split */

		
			
			$payment->setCcApproval($result->getApprovalCode())
			->setLastTransId($result->getTransactionId())
			->setCcTransId($result->getTransactionTag())
			->setCcAvsStatus($result->getAvsResultCode())
			->setCcCidStatus($result->getCardCodeResponseCode());
			

			$code = $result->getBankRespCode();
			$text = $result->getGatewayMessage();
			
			
			switch ($result->getTransactionStatus()) {
				case self::RESPONSE_CODE_APPROVED:
					$payment->setStatus(self::STATUS_APPROVED);
					if ($result->getTransactionId() != $payment->getParentTransactionId()) {
						$payment->setTransactionId($result->getTransactionId());
						$payment->setCcTransId($result->getTransactionTag());
						$payment->setLastTransId($result->getTransactionId());
					}					
					$payment
						->setIsTransactionClosed(0)
						->setTransactionAdditionalInfo('real_transaction_id', $result->getTransactionId());					
					// added by Gayatri 10/Jun/2010
					if( !$order = $payment->getOrder() )
					{
						$order = $payment->getQuote();
					}

					$abc = urldecode(ucwords($result->getTransactionStatus())) . ' at Payeezy, Trans ID: ' . $result->getTransactionId().
						$result->getGatewayMessage() . ' from Payeezy, Trans ID: ' . $result->getTransactionId(). 
						' Transaction Tag : ' . $result->getTransactionTag(). 
						' Transaction Type : ' . $result->getTransactionType();
				

					$order->addStatusToHistory(
						$order->getStatus(),
						$abc 
					);
					// end added by Gayatri 10/Jun/2010




					break;
					
				case self::RESPONSE_CODE_DECLINED:
					$error = Mage::helper('paygate')->__('Payment authorization transaction has been declined. ' . "\n$text");
					break;
					
				default:
					$error = Mage::helper('paygate')->__('Payment authorization error. ' . "\n$text");
					break;
			}
		} else {
			$error = Mage::helper('paygate')->__('Invalid amount for authorization.');
		}

		if ($error !== false) {
			Mage::throwException($error);
		}
    
		return $this;
	}






	public function capture(Varien_Object $payment, $amount)
	{
	
			
		$error = false;
		
        if ($payment->getParentTransactionId()) {
            $payment->setAnetTransType(self::REQUEST_TYPE_PRIOR_AUTH_CAPTURE);
			
        } else {
            $payment->setAnetTransType(self::REQUEST_TYPE_AUTH_CAPTURE);
        }		
		
		$payment->setAmount($amount);
		
		$request = $this->_buildRequest($payment);
		
		if ($payment->getAnetTransType() == "PRIOR_AUTH_CAPTURE")
		{
			$transactionId = $payment->getParentTransactionId();
			$transaction_tag = $payment->getCcTransId();
		
			$request->setXTransactionId($transactionId);			//AJITH
			$request->setXTransactionTag($transaction_tag);			//AJITH
			$request->setXAmount($amount);							//AJITH
		}
		
		
		$result  = $this->_postRequest($request);
		
		if ($result->getTransactionStatus() == self::RESPONSE_CODE_APPROVED) {
			$payment->setStatus(self::STATUS_APPROVED);
			$payment->setCcTransId($result->getTransactionTag());
			$payment->setLastTransId($result->getTransactionId());
			
			
		
			
			if ($result->getTransactionId() != $payment->getParentTransactionId()) {
				$payment->setTransactionId($result->getTransactionId());
			}			
			$payment
				->setIsTransactionClosed(0)
				->setCcTransId( $result->getTransactionTag())
				->setTransactionAdditionalInfo('real_transaction_id', $result->getTransactionTag());
				
			// added by Gayatri 10/Jun/2010
			if( !$order = $payment->getOrder() )
			{
				$order = $payment->getQuote();
			}
			$order->addStatusToHistory(
				$order->getStatus(),
				urldecode($result->getResponseReasonText()) . ' at Payeezy, Trans ID: ' . $result->getTransactionId(),
				$result->getResponseReasonText() . ' from Payeezy, Trans ID: ' . $result->getTransactionId()
			);
			// end added by Gayatri 10/Jun/2010			
		} else {
			if ($result->getResponseReasonText()) {
				$error = $result->getResponseReasonText();
			} else {
				$error = Mage::helper('paygate')->__('Error in capturing the payment');
			}
			if( !$order = $payment->getOrder() )
			{
				$order = $payment->getQuote();
			}
			$order->addStatusToHistory(
				$order->getStatus(),
				urldecode($error) . ' at Payeezy',
				$error . ' from Payeezy'
			);			
		}

		if ($error !== false) {
			Mage::throwException($error);
		}

		return $this;
	}

    /**
     * Check void availability
     *
     * @return bool
     */
    public function canVoid(Varien_Object $payment)
    {
		return $this->_canVoid;
    }
	public function void(Varien_Object $payment)
	{		
		$error = false;
		$transactionId = $payment->getVoidTransactionId();
		if (empty($transactionId)) {
			$transactionId = $payment->getParentTransactionId();
		}
		
		$transaction_tag = $payment->getCcTransId(); //AJITH
		
		$amount = $payment->getAmount();
		if ($amount <= 0) {
			$amount = $payment->getAmountAuthorized();
			$payment->setAmount($payment->getAmountAuthorized());
		}
		
		if ($transactionId && $amount > 0) {
			$payment->setAnetTransType(self::REQUEST_TYPE_VOID);
			$request 	 = $this->_buildRequest($payment);
			
			$request->setXTransactionId($transactionId);			//AJITH
			$request->setXTransactionTag($transaction_tag);			//AJITH
			$request->setXAmount($amount);							//AJITH
			
			$result = $this->_postRequest($request);
			if ($result->getTransactionStatus()==self::RESPONSE_CODE_APPROVED) {
			
				$payment->setStatus(self::STATUS_VOID);
				if ($result->getTransactionId() != $payment->getParentTransactionId()) {
					$payment->setTransactionId($result->getTransactionId());
				}
								
				$payment
					->setIsTransactionClosed(1)
					->setShouldCloseParentTransaction(1)
					->setCcTransId($result->getTransactionTag())
					->setTransactionAdditionalInfo('real_transaction_id', $result->getTransactionId());
				
				
				
			} else {
				//$errorMsg = $result->getResponseReasonText();
				$errorMsg = "Void ".$result->getTransactionStatus(). ' - ' .$result->getErrorText().".";
				$error = true;
			}
		} else if (!$transactionId) {
			$errorMsg = Mage::helper('paygate')->__('Error in voiding the payment. Transaction ID not found');
			$error = true;
			
		} else if ($amount <= 0) {
			$errorMsg = Mage::helper('paygate')->__('Error in voiding the payment. Payment amount is 0');
			$error = true;
			
		} else {
			$errorMsg = Mage::helper('paygate')->__('Error in voiding the payment');
			$error = true;
			
		}
		
		if ($error !== false) {
			Mage::throwException($errorMsg);
		}
		return $this;	
	}

    /**
     * Check refund availability
     *
     * @return bool
     */
    public function canRefund()
    {
		return $this->_canRefund;
    }
	public function refund(Varien_Object $payment, $amount)
	{
		$error = false;
		$transactionId = $payment->getRefundTransactionId();
		if (empty($transactionId)) {
			$transactionId = $payment->getParentTransactionId();
		}		
																		 
		
		$transaction_tag = $payment->getCcTransId();
		
		
		
		if ((($this->getConfigData('test') && $transactionId == 0) || $transactionId) && $amount>0) {
			$payment->setAnetTransType(self::REQUEST_TYPE_CREDIT);
			$request = $this->_buildRequest($payment);
			
			$request->setXTransactionId($transactionId);
			$request->setXTransactionTag($transaction_tag);
			$request->setXAmount($amount);
			
			
			$result = $this->_postRequest($request);
			if ($result->getTransactionStatus()==self::RESPONSE_CODE_APPROVED) {
				$payment->setStatus(self::STATUS_SUCCESS);
				if ($result->getTransactionId() != $payment->getParentTransactionId()) {
					$payment->setTransactionId($result->getTransactionId());
				}
				$shouldCloseCaptureTransaction = $payment->getOrder()->canCreditmemo() ? 0 : 1;
				$payment
					 ->setIsTransactionClosed(1)
					 ->setShouldCloseParentTransaction($shouldCloseCaptureTransaction)
					 ->setTransactionAdditionalInfo('real_transaction_id', $result->getTransactionId());				
			} else {				
				$errorMsg = "Refund ".$result->getTransactionStatus(). ' - ' .$result->getErrorText().".";
				$error = true;
			}

		} else {
			$errorMsg = Mage::helper('paygate')->__('Error in refunding the payment');
			$error = true;
		}

		if ($error !== false) {
			Mage::throwException($errorMsg);
		}
		return $this;
	}

	/**
	* Prepare request to gateway
	*
	* @link   http://www.authorize.net/support/AIM_guide.pdf
	* @param  Mage_Sales_Model_Document $order
	* @return unknown
	*/
	protected function _buildRequest(Varien_Object $payment)
	{
		
		$order = $payment->getOrder();
		
		

		if (!$payment->getAnetTransMethod()) {
			$payment->setAnetTransMethod(self::REQUEST_METHOD_CC);
		}
		
		

		$request = Mage::getModel('payeezy/payeezy_request')
		->setXVersion(3.1)
		->setXDelimData('True')
		->setXDelimChar(self::RESPONSE_DELIM_CHAR)
		->setXRelayResponse('False');





		
		$request->setXTestRequest($this->getConfigData('test') ? 'TRUE' : 'FALSE');

			
		$request->setXApiKey($this->getConfigData('apikey'))
		->setXApiSecret($this->getConfigData('api_secret'))
		->setXMerchantToken($this->getConfigData('merchant_token'))
		->setXType($payment->getAnetTransType())
		->setXMethod($payment->getAnetTransMethod());

		if ($payment->getAmount()) {
			$request->setXAmount($payment->getAmount(),2);
			$request->setXCurrencyCode($order->getBaseCurrencyCode());
			
		}
		
		

		switch ($payment->getAnetTransType()) {
			case self::REQUEST_TYPE_CREDIT:
			case self::REQUEST_TYPE_VOID:
			case self::REQUEST_TYPE_PRIOR_AUTH_CAPTURE: //Capturing After Authorization.
				$request->setXTransId($payment->getCcTransId());				
				$request->setXTransactionTag($request->getTransactionTag());				
				$request->setXCardNum($payment->getCcNumber())
					->setXExpDate(sprintf('%02d-%04d', $payment->getCcExpMonth(), $payment->getCcExpYear()))
					->setXCardCode($payment->getCcCid())
					->setXCardName($payment->getCcOwner())    //SreeAdded
					->setXCreditCardType($payment->getCcType())    //AJITH					
					;				
				break;
			case self::REQUEST_TYPE_AUTH_CAPTURE:  //Auth and Capture Together
				//$request->setXTransId($payment->getCcTransId());
				$request->setXTransId($payment->getLastTransId());
				$request->setXTransactionTag($request->getTransactionTag());
				$request->setXCreditCardType($payment->getCcType());    //AJITH
				break;	
			case self::REQUEST_TYPE_CAPTURE_ONLY:
				$request->setXAuthCode($payment->getCcAuthCode());
				$request->setXCreditCardType($payment->getCcType());    //AJITH
				break;
		}

	

		if (!empty($order)) {
			
			
			$freight = $order->getShippingAmount();
			$tax = $order->getTaxAmount();
			$subtotal = $order->getSubtotal();
			
			$request->setXInvoiceNum($order->getIncrementId());

			$billing = $order->getBillingAddress();
			
			if (!empty($billing)) {

				$email = $billing->getEmail();
				if(!$email)$email = $order->getBillingAddress()->getEmail();
				if(!$email)$email = $order->getCustomerEmail();

				$request->setXFirstName($billing->getFirstname())
				->setXLastName($billing->getLastname())
				->setXCompany($billing->getCompany())
				->setXAddress($billing->getStreet(1))
				->setXCity($billing->getCity())
				->setXState($billing->getRegion())
				->setXZip($billing->getPostcode())
				->setXCountry($billing->getCountry())
				->setXPhone($billing->getTelephone())
				->setXFax($billing->getFax())
				->setXCustId($billing->getCustomerId())
				->setXCustomerIp($order->getRemoteIp())
				->setXCustomerTaxId($billing->getTaxId())
				->setXEmail($email)  //Sree 17Nov2008
				->setXEmailCustomer($this->getConfigData('email_customer'))
				->setXMerchantEmail($this->getConfigData('merchant_email'));
			}

			$shipping = $order->getShippingAddress();
		
			if (!$shipping) {
				$shipping = $billing;
			}
			if (!empty($shipping)) {
			
				$request->setXShipToFirstName($shipping->getFirstname())
				->setXShipToLastName($shipping->getLastname())
				->setXShipToCompany($shipping->getCompany())
				->setXShipToAddress($shipping->getStreet(1))
				->setXShipToCity($shipping->getCity())
				->setXShipToState($shipping->getRegion())
				->setXShipToZip($shipping->getPostcode())
				->setXShipToCountry($shipping->getCountry());

				if(!isset($freight) || $freight<=0) $freight = $shipping->getShippingAmount();
				if(!isset($tax) || $tax<=0) $tax = $shipping->getTaxAmount();
				if(!isset($subtotal) || $subtotal<=0) $subtotal = $shipping->getSubtotal();				
			}

			$request->setXPoNum($payment->getPoNumber())
			->setXTax($tax)
			->setXSubtotal($subtotal)
			->setXFreight($freight);
			
		}

		

		switch ($payment->getAnetTransMethod()) {
			case self::REQUEST_METHOD_CC:
				if($payment->getCcNumber()){				
					$request->setXCardNum($payment->getCcNumber())
					->setXExpDate(sprintf('%02d-%04d', $payment->getCcExpMonth(), $payment->getCcExpYear()))
					->setXCardCode($payment->getCcCid())
					->setXCardName($payment->getCcOwner())    //SreeAdded
					->setXCreditCardType($payment->getCcType())    //AJITH
					;
				}
				break;

			case self::REQUEST_METHOD_ECHECK:
				$request->setXBankAbaCode($payment->getEcheckRoutingNumber())
				->setXBankName($payment->getEcheckBankName())
				->setXBankAcctNum($payment->getEcheckAccountNumber())
				->setXBankAcctType($payment->getEcheckAccountType())
				->setXBankAcctName($payment->getEcheckAccountName())
				->setXEcheckType($payment->getEcheckType())
				->setXCreditCardType($payment->getCcType()); //AJITH
				break;
		}
		

		return $request;
	}

	protected function _postRequest(Varien_Object $request)
	{
		$result = Mage::getModel('payeezy/payeezy_result');
		
		/**
		* @TODO
		* Sree handle exception
		*/
		$m = $request->getData();

	

		// Pre-Build Returned results
		$r = array (
		0 => '1',
		1 => '1',
		2 => '1',
		3 => '(TESTMODE) This transaction has been approved.',
		4 => '000000',
		5 => 'P',
		6 => '0',
		7 => '100000018',
		8 => '',
		9 => '2704.99',
		10 => 'CC',
		11 => 'auth_only',
		12 => '',
		13 => 'Sreeprakash',
		14 => 'N.',
		15 => 'Schogini',
		16 => 'XYZ',
		17 => 'City',
		18 => 'Idaho',
		19 => '695038',
		20 => 'US',
		21 => '1234567890',
		22 => '',
		23 => '',
		24 => 'Sreeprakash',
		25 => 'N.',
		26 => 'Schogini',
		27 => 'XYZ',
		28 => 'City',
		29 => 'Idaho',
		30 => '695038',
		31 => 'US',
		32 => '',
		33 => '',
		34 => '',
		35 => '',
		36 => '',
		37 => '382065EC3B4C2F5CDC424A730393D2DF',
		38 => '',
		39 => '',
		40 => '',
		41 => '',
		42 => '',
		43 => '',
		44 => '',
		45 => '',
		46 => '',
		47 => '',
		48 => '',
		49 => '',
		50 => '',
		51 => '',
		52 => '',
		53 => '',
		54 => '',
		55 => '',
		56 => '',
		57 => '',
		58 => '',
		59 => '',
		60 => '',
		61 => '',
		62 => '',
		63 => '',
		64 => '',
		65 => '',
		66 => '',
		67 => '',
		);

		//Replace the values from Magento 
		$r[7]  = $m['x_invoice_num']; //InvoiceNumber
		$r[8]  = ''; //Description
		$r[9]  = $m['x_amount']; //Amount
		$r[10] = $m['x_method']; //Method = CC
		$r[11] = $m['x_type']; //TransactionType
		$r[12] = $m['x_cust_id']; //CustomerId
		$r[13] = $m['x_first_name']; 
		$r[14] = $m['x_last_name'];
		$r[15] = $m['x_company'];
		$r[16] = $m['x_address'];
		$r[17] = $m['x_city'];
		$r[18] = $m['x_state'];
		$r[19] = $m['x_zip'];
		$r[20] = $m['x_country'];
		$r[21] = $m['x_phone'];
		$r[22] = $m['x_fax'];
		$r[23] = '';
		
		//no shipping
		$m['x_ship_to_first_name'] 	= !isset($m['x_ship_to_first_name'])?$m['x_first_name']:$m['x_ship_to_first_name'];
		$m['x_ship_to_first_name'] 	= !isset($m['x_ship_to_first_name'])?$m['x_first_name']:$m['x_ship_to_first_name'];
		$m['x_ship_to_last_name'] 	= !isset($m['x_ship_to_last_name'])?$m['x_last_name']:$m['x_ship_to_last_name'];
		$m['x_ship_to_company'] 	= !isset($m['x_ship_to_company'])?$m['x_company']:$m['x_ship_to_company'];
		$m['x_ship_to_address'] 	= !isset($m['x_ship_to_address'])?$m['x_address']:$m['x_ship_to_address'];
		$m['x_ship_to_city'] 		= !isset($m['x_ship_to_city'])?$m['x_city']:$m['x_ship_to_city'];
		$m['x_ship_to_state'] 		= !isset($m['x_ship_to_state'])?$m['x_state']:$m['x_ship_to_state'];
		$m['x_ship_to_zip'] 		= !isset($m['x_ship_to_zip'])?$m['x_zip']:$m['x_ship_to_zip'];
		$m['x_ship_to_country'] 	= !isset($m['x_ship_to_country'])?$m['x_country']:$m['x_ship_to_country'];

		$r[24] = $m['x_ship_to_first_name'];
		$r[25] = $m['x_ship_to_last_name'];
		$r[26] = $m['x_ship_to_company'];
		$r[27] = $m['x_ship_to_address'];
		$r[28] = $m['x_ship_to_city'];
		$r[29] = $m['x_ship_to_state'];
		$r[30] = $m['x_ship_to_zip'];
		$r[31] = $m['x_ship_to_country'];

		//Dummy Replace the values from Payeezy 
		$r[0]  = '1';  // response_code
		$r[1]  = '1';  // ResponseSubcode
		$r[2]  = '1';  // ResponseReasonCode
		$r[3]  = '(TESTMODE2) This transaction has been approved.'; //ResponseReasonText
		$r[4]  = '000000'; //ApprovalCode
		$r[5]  = 'P'; //AvsResultCode
		$r[6]  = '0'; //TransactionId
		$r[37] = '382065EC3B4C2F5CDC424A730393D2DF'; //Md5Hash
		$r[39] = ''; //CardCodeResponse

		// Add Payeezy Here
		$rr = $this->_payeezyapi($m);
		
		
		//Replace the values from Payeezy 		
		$r[0]  							= $rr['transaction_status'];
		$r[1]  							= $rr['validation_status'];
		$r[2]  							= $rr['transaction_type'];
		$r[3]  							= $rr['transaction_id']; //'(TESTMODE2) This transaction has been approved.'; //ResponseReasonText
		$r[4]  							= $rr['transaction_tag']; //'000000'; //ApprovalCode
		$r[5]  							= $rr['bank_resp_code']; //'P'; //AvsResultCode
		$r[6]  							= $rr['bank_message']; //'0'; //TransactionId
		$r[37] 							= $rr['gateway_resp_code'];
		$r[39] 							= $rr['gateway_message'];
		$r[40] 							= $rr['correlation_id'];		
		$r[41] 							= $rr['method'];
		$r[42] 							= $rr['amount'];
		$r[43] 							= $rr['currency'];
		$r[45] 							= $rr['token_type'];
		$r[46] 							= $rr['token_value'];
		$r[47]							= $rr['error_text'];

		if ($r) {			
			$result->setTransactionStatus( $r[0] );			
			$result->setValidationStatus( $r[1] );			
			$result->setTransactionType( $r[2] );			
			$result->setTransactionId( $r[3] );			
			$result->setTransactionTag( $r[4] );			
			$result->setBankRespCode( $r[5] );			
			$result->setBankMessage( $r[6] );			
			$result->setGatewayRespCode( $r[37] );			
			$result->setGatewayMessage( $r[39] );			
			$result->setCorrelationId( $r[40] );	
			
			$result->setMethod($r[41]);			
			$result->setAmount($r[42]);			
			$result->setCurrency($r[43]);			
			$result->setTokenType($r[45]);			
			$result->setTokenValue($r[46]);
			$result->setErrorText($r[47]);
			
		} else {
			Mage::throwException(
			Mage::helper('paygate')->__('Error in payment gateway')
			);
		}
		
		return $result;
	}
	
	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	function xmlentities( $string ) { 
        $not_in_list = "A-Z0-9a-z\s_-"; 
        return preg_replace_callback( "/[^{$not_in_list}]/" , 'get_xml_entity_at_index_0' , $string ); 
    } 
    function get_xml_entity_at_index_0( $CHAR ) { 
        if( !is_string( $CHAR[0] ) || ( strlen( $CHAR[0] ) > 1 ) ) { 
            die( "function: 'get_xml_entity_at_index_0' requires data type: 'char' (single character). '{$CHAR[0]}' does not match this type." ); 
        } 
        switch( $CHAR[0] ) { 
            case "'":    case '"':    case '&':    case '<':    case '>': 
                return htmlspecialchars( $CHAR[0], ENT_QUOTES );    break; 
            default: 
                return numeric_entity_4_char($CHAR[0]);                break; 
        }        
    } 
    function numeric_entity_4_char( $char ) { 
        return "&#".str_pad(ord($char), 3, '0', STR_PAD_LEFT).";"; 
    }    
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	
	function _payeezyapi($m)
	{
	
		$apikey					= $this->getConfigData('apikey');
		$api_secret				= $this->getConfigData('api_secret');
		$merchant_token			= $this->getConfigData('merchant_token');
		$payeezy_url			= $this->getConfigData('payeezy_url');
		$merchant_reference			= $this->getConfigData('merchant_ref');
		
		$get_credit_card_type = array(		
		'MC' => 'Mastercard',
		'AE' => 'American Express',
		'VI' => 'Visa',
		'DI' => 'Discover');		
		$credit_card_type 		= $get_credit_card_type[$m['x_credit_card_type']];
				
		$isTest				= $this->getConfigData('test');
		
		if ( $m['x_type'] != 'REFUND')
		{
			$amount 			= str_replace(",", "", number_format($m['x_amount'], 2)); //Proper number format. No commas to avoid XML error
			$amount 			= str_replace(".", "", $amount); //Proper number format. No decimal XML error
		}
		else
		{
			$amount = $m['x_tax'] + $m['x_subtotal'] + $m['x_freight'];
			
			$amount 			= str_replace(",", "", number_format($amount, 2)); //Proper number format. No commas to avoid XML error
			$amount 			= str_replace(".", "", $amount); //Proper number format. No decimal XML error
		}
		$expDate			= substr($m['x_exp_date'],0,2) . substr($m['x_exp_date'],-2);
		
		// billing address
		$address 			= trim($m['x_address'] . ', ' . $m['x_city'] . ',' . $m['x_state'] . ','. $m['x_country']);
		$cardHoldersName	= htmlentities(trim($m['x_first_name'] . ' ' . $m['x_last_name']), ENT_QUOTES, 'UTF-8');
		$billingAddress		= htmlentities($address, ENT_QUOTES, 'UTF-8');
		$zipcode			= $m['x_zip'];
		
		$cardHoldersName2	= trim($m['x_first_name'] . ' ' . $m['x_last_name']);
		$verificationStr	= trim($m['x_address']) . '|' . trim($m['x_zip']) . '|' . trim($m['x_city']) . '|' . trim($m['x_state']) . '|' . trim($m['x_country']);
		

		
	
		/**Dont Delete this section. Will switch to this code later for automatic selection of TEST and LIVE urls **/
		// get the end point based on test or live mode.
		if ($isTest) {
			$wsdl = 'https://api-cert.payeezy.com/v1/transactions';
		} else {
			$wsdl = 'https://api.payeezy.com/v1/transactions';
		}
		
		// Sree 

		$errors = '';
		try {
			
			$payeezy = new Payeezy();
				
			$payeezy->setApiKey($apikey);
			$payeezy->setApiSecret($api_secret);
			$payeezy->setMerchantToken($merchant_token); //1ea82b7e604020b4
			$payeezy->setUrl($payeezy_url);

		
			$card_holder_name = $card_number = $card_type = $card_cvv = $card_expiry = $currency_code = $merchant_ref="";
			
			$card_holder_name = $this->processInput($cardHoldersName);
			//$card_holder_name = $this->processInput("John Appleseed");     //HARD CODED VALUE UNCOMMENT
			$card_number = $this->processInput($m['x_card_num']);
			$card_type = $this->processInput($credit_card_type);					
			$card_cvv = $this->processInput($m['x_card_code']);
			$card_expiry = $this->processInput($expDate);
			$amount = $this->processInput($amount);
			$currency_code = $this->processInput($m['x_currency_code']);			
			$merchant_ref = $this->processInput($merchant_reference);
			
			
			
			
			$primaryTxPayload = array(
			  "amount"=> $amount,
			  "card_number" => $card_number,
			  "card_type" => $card_type,
			  "card_holder_name" => $card_holder_name,
			  "card_cvv" => $card_cvv,
			  "card_expiry" => $card_expiry,
			  "merchant_ref" => $merchant_ref,
			  "currency_code" => $currency_code,
			);
			
			/****************************************************************************************************
			Mage::log("----------------------PrimaryTaxPayLoad------------------------", null, 'debug.log');
			Mage::log($primaryTxPayload, null, 'debug.log');
			Mage::log("------------------------END PrimaryTaxPayLoad----------------", null, 'debug.log');
			*****************************************************************************************************/
 
	
		
			switch ($m['x_type']) {
				case 'AUTH_CAPTURE':
					//$finalTxResponse_JSON = json_decode($payeezy->purchase($primaryTxPayload));
					/*******************************************************************************************************/
					//CAPTURE
					// first do an authorize					
					$primaryTxResponse_JSON = json_decode($payeezy->authorize($primaryTxPayload));

				


					// $primaryTxResponse_JSON->transaction_status == "approved"					
					//function setSecondaryTxPayload($transaction_id, $transaction_tag, $amount, $currency_code $split_shipment)
					/*$secondaryTxPayload = $this->setSecondaryTxPayload($primaryTxResponse_JSON->transaction_id
																		,$primaryTxResponse_JSON->transaction_tag
																		,$primaryTxResponse_JSON->amount
																		,$primaryTxResponse_JSON->currency
																		,1/2);
					// capture the previous txn using the transaction id and transaction tag
					$finalTxResponse_JSON = json_decode($payeezy->capture($secondaryTxPayload));
					/******************************************************************************************************/
					/******************************************************************************************************
					Mage::log("----------------------Authorize and Capture/PURCHASE------------------------", null, 'debug.log');
					Mage::log($finalTxResponse_JSON, null, 'debug.log');
					Mage::log("------------------------END Authorize and Capture/PURCHASE----------------", null, 'debug.log');
					//$secondaryTxResponse_JSON->transaction_status == "approved"	
					******************************************************************************************************/

                    /****************************************************************************************************/
                    ////////////////////////////////////////// oasisfleeting //////////////////////////////////////////
                    /****************************************************************************************************/

                    // Transaction type must be split to do split shipment. not payment or authorize
                    // first do an authorize
                    //$primaryTxResponse_JSON = json_decode($payeezy->authorize($this->setPrimaryTxPayload()));


                   
                    //$this->assertEquals($primaryTxResponse_JSON->transaction_status,"approved");


                    // in this example, the shipment is split into 2 txns
                    $split_amount = ($primaryTxResponse_JSON->amount)/2;

               


                    // the first shipment is sent out .. split shipmant value is 01/99 since the total no. of shipments is unknown
                    // We do know the number of payments there will be in total.

                    //This appears to only be available through a manual decision in the back end
                    //tab12 - https://developer.payeezy.com/faq-page#collapse_12
        


                    $secondaryTxPayload = $this->setSecondaryTxPayload($primaryTxResponse_JSON->transaction_id,
                                                                       $primaryTxResponse_JSON->transaction_tag,
                                                                       $split_amount,
                                                                       $m['x_currency_code'],
                                                                       '01/02');


                   

                    $secondaryTxResponse_JSON = json_decode($payeezy->split_shipment($secondaryTxPayload));

                   // $finalTxResponse_JSON = json_decode($payeezy->capture($secondaryTxPayload));

                $finalTxResponse_JSON =  $secondaryTxResponse_JSON ;


                   
                    //$this->assertEquals($secondaryTxResponse_JSON->transaction_status,"approved");

                    // the second shipment is sent out. It is also the final shipment .. therefore 02/02
                    //$secondaryTxPayload = $this->setSecondaryTxPayload($primaryTxResponse_JSON->transaction_id
                    //    ,$primaryTxResponse_JSON->transaction_tag
                    //    ,$split_amount
                    //    ,"02/02");
                    //$secondaryTxResponse_JSON = json_decode(self::$payeezy->split_shipment($secondaryTxPayload));
                    //$this->assertEquals($secondaryTxResponse_JSON->transaction_status,"approved");*/



					break;
				case 'CAPTURE_ONLY':
				case 'PRIOR_AUTH_CAPTURE':
					$secondaryTxPayload = $this->setSecondaryTxPayload($m['x_transaction_id']
			                                                    ,$m['x_transaction_tag']
			                                                    ,$amount
																,$m['x_currency_code']
			                                                    ,'02/02');
					// capture the previous txn using the transaction id and transaction tag
					$finalTxResponse_JSON = json_decode($payeezy->capture($secondaryTxPayload));
					
					break;
				case 'VOID':
					$secondaryTxPayload = $this->setSecondaryTxPayload($m['x_transaction_id']
			                                                    ,$m['x_transaction_tag']
			                                                    ,$amount
																,$m['x_currency_code']
			                                                    ,null);
					
					$finalTxResponse_JSON = json_decode($payeezy->void($secondaryTxPayload));
					
					/****************************************************************************************************
					Mage::log("----------------------VOID------------------------", null, 'debug.log');
					Mage::log($finalTxResponse_JSON, null, 'debug.log');
					Mage::log("------------------------END VOID----------------", null, 'debug.log');
					****************************************************************************************************/
					
					break;				
				case 'REFUND': // refund
					
					$secondaryTxPayload = $this->setSecondaryTxPayload($m['x_transaction_id']
			                                                    ,$m['x_transaction_tag']
			                                                    ,$amount
																,$m['x_currency_code']
			                                                    ,null);
					// refund the purchase using the transaction id and transaction tag
					$finalTxResponse_JSON = json_decode($payeezy->refund($secondaryTxPayload));
					/****************************************************************************************************
					Mage::log("----------------------REFUND------------------------", null, 'debug.log');
					Mage::log($finalTxResponse_JSON, null, 'debug.log');
					Mage::log("------------------------END REFUND----------------", null, 'debug.log');
					****************************************************************************************************/

					
					break;
				case 'AUTH_ONLY':
					$finalTxResponse_JSON = json_decode($payeezy->authorize($primaryTxPayload));
					break;
				default:
					break;
			}
			
	
		} catch (Exception $e) {
			$msg = $e->getMessage();
			if (empty($msg)) {
				$msg = 'Unknown error';
			}
			Mage::throwException('Payeezy Exception: ' . $msg);
		}		
		
		
		// Load Default Dummy Values
		$rr 							= array();		
		$rr['method']					= 'xxxxxxx_xxxx';
		$rr['amount']					= '0000';
		$rr['currency']					= 'CUR';
		$rr['cvv2']						= 'X';
		$rr['token_type']				= 'xxxxxxxxxx';
		$rr['token_value']				= '00000000000000000';		
		$rr['transaction_status']		= 'xxxxxxxx';	
		$rr['validation_status']		= 'xxxxxxx';
		$rr['transaction_type']			= 'xxxxxxxxx';
		$rr['transaction_id'] 			= 'ET152456';
		$rr['transaction_tag'] 			= '000000'; //ApprovalCode
		$rr['bank_resp_code']			= '000';
		$rr['bank_message']				= 'Xxxxxxxx';
		$rr['gateway_resp_code']		= '00';
		$rr['gateway_message']			= 'Xxxxxxxxxxx Yyyyyy';
		$rr['correlation_id']			= '000.1234567891234';
		$rr['error_text']				= 'n';
		
	
		// check the response
		if ($finalTxResponse_JSON->transaction_status == "approved") {
			// success					
			$rr['method']					= $finalTxResponse_JSON->method;
			$rr['amount']					= $finalTxResponse_JSON->amount;
			$rr['currency']					= $finalTxResponse_JSON->currency;
			$rr['cvv2']						= $finalTxResponse_JSON->cvv2;
			$rr['token_type']				= $finalTxResponse_JSON->token->token_type;
			$rr['token_value']				= $finalTxResponse_JSON->token->token_data->value;		
			$rr['transaction_status']		= $finalTxResponse_JSON->transaction_status;
			$rr['validation_status']		= $finalTxResponse_JSON->validation_status;
			$rr['transaction_type']			= $finalTxResponse_JSON->transaction_type;
			$rr['transaction_id'] 			= $finalTxResponse_JSON->transaction_id;
			$rr['transaction_tag'] 			= $finalTxResponse_JSON->transaction_tag;
			$rr['bank_resp_code']			= $finalTxResponse_JSON->bank_resp_code;
			$rr['bank_message']				= $finalTxResponse_JSON->bank_message;
			$rr['gateway_resp_code']		= $finalTxResponse_JSON->gateway_resp_code;
			$rr['gateway_message']			= $finalTxResponse_JSON->gateway_message;
			$rr['correlation_id']			= $finalTxResponse_JSON->correlation_id;
				
		} else {
				
			$rr['transaction_status']		= $finalTxResponse_JSON->transaction_status;	
			$rr['error_text']				= $finalTxResponse_JSON->Error->messages[0]->description;	
			
		}

      

		return $rr;
	}

	function getResponseReason($code) 
	{
		$code = trim($code);
		$responses = array(
		'00' => 'Transaction Normal',
		'08' => 'CVV2/CID/CVC2 Data not verified',
		'22' => 'Invalid Credit Card Number',
		'25' => 'Invalid Expiry Date',
		'26' => 'Invalid Amount',
		'27' => 'Invalid Card Holder',
		'28' => 'Invalid Authorization No',
		'31' => 'Invalid Verification String',
		'32' => 'Invalid Transaction Code',
		'57' => 'Invalid Reference No',
		'58' => 'Invalid AVS String, The length of the AVS String has exceeded the max. 40 characters',
		'60' => 'Invalid Customer Reference Number',
		'63' => 'Invalid Duplicate',
		'64' => 'Invalid Refund',
		'68' => 'Restricted Card Number',
		'72' => 'Data within the transaction is incorrect',
		'93' => 'Invalid authorization number entered on a pre-auth completion',
		'11' => 'Invalid Sequence No',
		'12' => 'Message Timed-out at Host',
		'21' => 'BCE Function Error',
		'23' => 'Invalid Response from First Data',
		'30' => 'Invalid Date From Host',
		'10' => 'Invalid Transaction Description',
		'14' => 'Invalid Gateway ID',
		'15' => 'Invalid Transaction Number',
		'16' => 'Connection Inactive',
		'17' => 'Unmatched Transaction',
		'18' => 'Invalid Reversal Response',
		'19' => 'Unable to Send Socket Transaction',
		'20' => 'Unable to Write Transaction to File',
		'24' => 'Unable to Void Transaction',
		'40' => 'Unable to Connect',
		'41' => 'Unable to Send Logon',
		'42' => 'Unable to Send Trans',
		'43' => 'Invalid Logon',
		'52' => 'Terminal not Activated',
		'53' => 'Terminal/Gateway Mismatch',
		'54' => 'Invalid Processing Center',
		'55' => 'No Processors Available',
		'56' => 'Database Unavailable',
		'61' => 'Socket Error',
		'62' => 'Host not Ready',
		'44' => 'Address not Verified',
		'70' => 'Transaction Placed in Queue',
		'73' => 'Transaction Received from Bank',
		'76' => 'Reversal Pending',
		'77' => 'Reversal Complete',
		'79' => 'Reversal Sent to Bank',
		'F1' => 'Address check failed - Fraud suspected',
		'F2' => 'Card/Check Number check failed - Fraud suspected',
		'F3' => 'Country Check Failed - Fraud Suspected',
		'F4' => 'Customer Reference Check Failed - Fraud Suspected',
		'F5' => 'Email Address check failed - Fraud suspected',
		'F6' => 'IP Address check failed - Fraud suspected');
		
		if (!isset($responses[$code]) || empty($responses[$code])) {
			$msg = 'Unknown reason';
		} else {
			$msg = $responses[$code];
		}
		
		//return "There has been an error while processing this payment. Kindly check and try again. \n\n" . $msg . ' (' . $code . ')';
		return "Unable to process order. Please check entered credit card information and try again or use a different payment method.\nProcessor response: (" . $code . ")";
		
	}	
	
	function getAvsResponseText($avs) 
	{
		$avs = trim($avs);
		$msg = 'Unrecognized response';
		switch ($avs) {
			case 'X': 
				$msg = 'exact match, 9 digit zip';
				break;
			case 'Y': 
				$msg = 'exact match, 5 digit zip';
				break;
			case 'A': 
				$msg = 'address match only';
				break;
			case 'W': 
				$msg = '9 digit zip match only';
				break;
			case 'Z': 
				$msg = '5 digit zip match only';
				break;
			case 'N': 
				$msg = 'no address or zip match';
				break;
			case 'U': 
				$msg = 'address unavailable';
				break;
			case 'G': 
				$msg = 'non-North American issuer, does not participate';
				break;
			case 'R': 
				$msg = 'issuer system unavailable';
				break;
			case 'E': 
				$msg = 'not a Mail/Phone order';
				break;
			case 'S': 
				$msg = 'service not supported';
				break;
			case 'Q': 
				$msg = 'Bill to address did not pass edit checks';
				break;
			case 'D': 
				$msg = 'International street address and postal code match';
				break;
			case 'B': 
				$msg = 'International street address match, postal code not verified due to incompatable formats';
				break;
			case 'C': 
				$msg = 'International street address and postal code not verified due to incompatable formats';
				break;
			case 'P': 
				$msg = 'International postal code match, street address not verified due to incompatable format';
				break;
			case '1': 
				$msg = 'Cardholder name matches';
				break;
			case '2': 
				$msg = 'Cardholder name, billing address, and postal code match';
				break;
			case '3': 
				$msg = 'Cardholder name and billing postal code match';
				break;
			case '4': 
				$msg = 'Cardholder name and billing address match';
				break;
			case '5': 
				$msg = 'Cardholder name incorrect, billing address and postal code match';
				break;
			case '6': 
				$msg = 'Cardholder name incorrect, billing postal code matches';
				break;
			case '7': 
				$msg = 'Cardholder name incorrect, billing address matches';
				break;
			case '8': 
				$msg = 'Cardholder name, billing address, and postal code are all incorrect';
				break;			
		}
		
		return $msg;
	}
	
	function getCvvResponseText($cvv)
	{
		$cvv = trim($cvv);
		$msg = 'Unrecognized response';
		switch ($cvv) {
			case 'M': 
				$msg = 'CVV2 / CVC2/CVD Match.'; 
				break;
			case 'N': 
				$msg = 'CVV2 / CVC2/CVD No Match.'; 
				break;
			case 'P': 
				$msg = 'Not Processed.'; 
				break;
			case 'S': 
				$msg = 'Merchant has indicated that CVV2 / CVC2/CVD is not present on the card.'; 
				break;
			case 'U': 
				$msg = 'Issuer is not certified and / or has not provided Visa encryption keys.'; 
				break;			
		}
		
		return $msg;
	}
	
	function logit($func, $arr=array()) 
	{
		// get the log file
		if(!isset($this->pth)||empty($this->pth)){
			$cfg = Mage::getConfig();
			$this->pth = $cfg->getBaseDir();
		}
		$f = $this->pth . '/magento_log.txt';
		
		// If, debug mode is off or module is live then, truncate & delete the file
		if (!$this->getConfigData('debug') || !$this->getConfigData('test')) {
			if (file_exists($f)) {
				$FH = @fopen($f, "w");
				fclose($FH);
				@unlink($f);
			}
			return;
		}
	
		// do not log in live mode
		if (!$this->getConfigData('test')) return;
		
		if (!is_writable($f)) return;
		
		$a = '';
		if(count($arr)>0) $a = var_export($arr,true);
		
		// card details should never be stored anywhere not even in the logs
		$cleanCard = "<creditcard>
						<cardnumber>xxxxxxxxxxxxxxxx</cardnumber>
						<cardexpmonth>xx</cardexpmonth>
						<cardexpyear>xx</cardexpyear>
						<cvmvalue>xxx</cvmvalue>
						<cvmindicator>provided</cvmindicator>
					</creditcard>";
		$a = preg_replace('/<creditcard>(.*)<\/creditcard>/smUi', $cleanCard, $a);
		@file_put_contents($f , '----- Inside ' . $func . ' =1= ' . date('d/M/Y H:i:s') . ' -----' . "\n" . $a, FILE_APPEND);
	}

	// Sree 
	function processInput($data)
	{
		$data = trim($data);
		$data = stripslashes($data);
		$data = htmlspecialchars($data);
		return strval($data);
	}
	
	 
	// Sree
	function setSecondaryTxPayload($transaction_id, $transaction_tag, $amount, $currency, $split_ship)
	{
	
        $transaction_type 	= $merchant_ref	= $currency_code = "";

        $transaction_id 	= $this->processInput($transaction_id);
        $transaction_tag 	= $this->processInput($transaction_tag);
        $amount 			= $this->processInput($amount);
        $currency_code 		= $this->processInput($currency);
        //why is this hardcoded value ? System config offers field for merchant reference code
        //astonishing sale is the merchant ref used in the testing sandbox here https://developer.payeezy.com/payeezy_new_docs/apis/post/transactions/%7Bid%7D-1
        $merchant_ref 		= $this->processInput("Astonishing-Sale");
        $split_shipment 	= $this->processInput($split_ship);

        if( is_null($split_shipment) )
        {
            $secondaryTxPayload = array(
                "amount"=> $amount,
                "transaction_tag" => $transaction_tag,
                "transaction_id" => $transaction_id,
                "merchant_ref" => $merchant_ref,
                "currency_code" => $currency_code,
            );
        }
        else{
            $secondaryTxPayload = array(
                "amount"=> $amount,
                "transaction_tag" => $transaction_tag,
                "transaction_id" => $transaction_id,
                "merchant_ref" => $merchant_ref,
                "currency_code" => $currency_code,
                "split_shipment" => $split_shipment,
            );
        }
			   
		return $secondaryTxPayload;
}

//if( !function_exists( 'xmlentities' ) ) {  AJITH COMMENTED
 /////////////////////////////////////////////////////////////////////////////////
 
 /////////////////////////////////////////////////////////////////////////////////
//} AJITH COMMENTED
}
