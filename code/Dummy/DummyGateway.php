<?php

/**
 * Test class for merchant-hosted payment
 */
class DummyGateway_MerchantHosted extends PaymentGateway_MerchantHosted {
  
  public function getSupportedCreditCardType() {
    return array('visa', 'master');
  }
  
  protected function creditCardTypeIDMapping() {
    return array();
  }
  
  /**
   * Override to cancel data validation
   * 
   * @see PaymentGateway::validate()
   * 
   * @param Array $data
   * @return ValidationResult
   */
  public function validate($data) {

    //Use this->validationResult so that all errors are added and can be accessible from Payment Test
    //TODO this should do actual validation of the data 

    $result = $this->getValidationResult();
    $amount = $data['Amount'];
    $cents = round($amount - intval($amount), 2);
    
    switch ($cents) {
      case 0.11:
        $result->error('Cents value is .11');
        $result->error('This is another error message for cents = .11');
        break;
    }
    return $result;
  }

  public function process($data) {
    //Validate first
    $result = $this->validate($data);
    if (!$result->valid()) {
      return new PaymentGateway_Failure($result->message());
    }

    //Mimic failures, like a gateway response such as 404, 500 etc.
    $amount = $data['Amount'];
    $cents = round($amount - intval($amount), 2);

    switch ($cents) {
      case 0.01:
        return new PaymentGateway_Failure(null, new SS_HTTPResponse('Internal Server Error', 500));
      case 0.02:
        return new PaymentGateay_Failure("Payment cannot be completed");
      case 0.03:
        return new PaymentGateay_Incomplete("Awaiting payment confirmation");
      default:
        return new PaymentGateway_Success();
    }
  }
}

/**
 * Test class for gateway-hosted payment
 */
class DummyGateway_GatewayHosted extends PaymentGateway_GatewayHosted {
  
  public function __construct() {
    parent::__construct();
    $this->gatewayURL = Director::baseURL() . 'dummy/external/pay';
  }
  
  /**
   * Override to cancel validation
   * 
   * @see PaymentGateway::validate()
   * 
   * @param Array $data
   * @return ValidationResult
   */
  public function validate($data) {

    $result = $this->getValidationResult();

    $amount = $data['Amount'];
    $cents = round($amount - intval($amount), 2);
    
    switch ($cents) {
      case 0.11:
        $result->error('Cents value is .11');
        $result->error('This is another error message for cents = .11');
        break;
    }
    return $result;
  }

  public function process($data) {

    //Validate first
    $result = $this->validate($data);
    if (!$result->valid()) {
      return new PaymentGateway_Failure($result->message());
    }

    $postData = array(
      'Amount' => $data['Amount'],
      'Currency' => $data['Currency'],
      'ReturnURL' => $this->returnURL    
    ); 

    //Mimic HTTP failure
    $amount = $data['Amount'];
    $cents = round($amount - intval($amount), 2);

    if ($cents == 0.01) {
      return new PaymentGateway_Failure(null, new SS_HTTPResponse('Internal Server Error', 500));
    }

    $queryString = http_build_query($postData);
    Controller::curr()->redirect($this->gatewayURL . '?' . $queryString);
  }
  
  public function parseResponse($response) {
    
  }
}

/**
 * Mock external gateway with payment form fields
 */
class DummyGateway_Controller extends ContentController {

  function pay($request) {
    return array(
      'Content' => "<h1>Fill out this form to make payment</h1>",
      'Form' => $this->PayForm()
    );
  }

  function PayForm() {

    $request = $this->getRequest();

    $fields = new FieldList(
      new TextField('Amount', 'Amount', $request->getVar('Amount')),
      new TextField('Currency', 'Currency', $request->getVar('Currency')),
      new TextField('CardHolderName', 'Card Holder Name', 'Test Testoferson'),
      new CreditCardField('CardNumber', 'Card Number', '1234567812345678'),
      new TextField('DateExpiry', 'Expiration date', '12/15'),
      new TextField('ReturnURL', 'ReturnURL', $request->getVar('ReturnURL'))
    );

    $actions = new FieldList(
      new FormAction("dopay", 'Process Payment')
    );

    //TODO validate this form

    return new Form($this, "PayForm", $fields, $actions);
  }

  function dopay($data, $form) {

    $returnURL = $data['ReturnURL'];
    $amount = $data['Amount'];
    $cents = round($amount - intval($amount), 2);

    if ($returnURL) {
      Controller::redirect($returnURL);
    } 
    else {
      // TODO: Return a error for processor to handle rather than user_error
      user_error("Return URL is not set for this transaction", E_USER_ERROR);
    }
  }
}