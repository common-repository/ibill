<?php
/**
 * Gateway API related functions and the functions are used to the WC_ibill_Gateway.
 * @package iBill
 * @since   1.0.0
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class ibill_payments{

  private $endpoint = 'https://api.ibill.com/payment/';
  /**
   * Set authentication details 
   * @param string $username
   * @param string $password
   * @param string $mode_type
   */
  function setAuth($account_id, $private_key, $mode_type) {
    $this->login['account_id'] = $this->escap_string(trim($account_id));
    $this->login['private_key'] = $this->escap_string(trim($private_key));
    $this->login['mode_type'] = $this->escap_string(trim($mode_type));
  }

  /**
   * Set payloads for payment & append additional params here if required
   * @param int $orderid
   * @param string $orderdescription
   * @param float $tax
   * @param int $shipping
   * @param int $ponumber
   * @param string $ipaddress
   */

  function setParams($orderid,
        $orderdescription,
        $tax,
        $shipping,
        $ponumber,
        $ipaddress) {
    $this->order['orderid']          = $this->escap_string($orderid);
    $this->order['orderdescription'] = $this->escap_string($orderdescription);
    $this->order['tax']              = $this->escap_string($tax);
    $this->order['shipping']         = $this->escap_string($shipping);
    $this->order['ponumber']         = $this->escap_string($ponumber);
    $this->order['ipaddress']        = $this->escap_string($ipaddress);
  }

  /**
   * Payloads for billing address
   * @param string $firstname
   * @param string $lastname
   * @param string $company
   * @param string $address1
   * @param string $address2
   * @param string $city
   * @param string $state
   * @param string $zip
   * @param string $country
   * @param string $phone
   * @param string $fax
   * @param string $email
   * @param string $website
   */
  function setBillingAddress($firstname,
        $lastname,
        $company,
        $address1,
        $address2,
        $city,
        $state,
        $zip,
        $country,
        $phone,
        $fax,
        $email,
        $website) {
    $this->billing['firstname'] = $this->escap_string($firstname);
    $this->billing['lastname']  = $this->escap_string($lastname);
    $this->billing['company']   = $this->escap_string($company);
    $this->billing['address1']  = $this->escap_string($address1);
    $this->billing['address2']  = $this->escap_string($address2);
    $this->billing['city']      = $this->escap_string($city);
    $this->billing['state']     = $this->escap_string($state);
    $this->billing['zip']       = $this->escap_string($zip);
    $this->billing['country']   = $this->escap_string($country);
    $this->billing['phone']     = $this->escap_string($phone);
    $this->billing['fax']       = $this->escap_string($fax);
    $this->billing['email']     = $this->escap_string(sanitize_email($email));
    $this->billing['website']   = $this->escap_string($website);
  }

  /**
   * Get payloads for billing details
   * @return array
   */
  function getBillingAddress(){
    return array(
      'firstname' => $this->billing['firstname'],
      'lastname' => $this->billing['lastname'],
      'company' => $this->billing['company'],
      'address' => (!empty($this->billing['address1'])?$this->billing['address1']:'').((!empty($this->billing['address1']) && !empty($this->billing['address2']))?', ':'').(!empty($this->billing['address2'])?$this->billing['address2']:''),
      'city' => $this->billing['city'],
      'state' => $this->billing['state'],
      'zip' => (string) $this->billing['zip'],
      'country' => $this->billing['country'],
      'phone' => $this->billing['phone'],
      'fax' => $this->billing['fax'],
      'email' => $this->billing['email'],
      'website' => $this->billing['website']
    );
  }

  /**
   * Payloads for shipping address
   * @param string $firstname
   * @param string $lastname
   * @param string $company
   * @param string $address1
   * @param string $address2
   * @param string $city
   * @param string $state
   * @param string $zip
   * @param string $country
   * @param string $email 
   */
  function setShippingAddress($firstname,
        $lastname,
        $company,
        $address1,
        $address2,
        $city,
        $state,
        $zip,
        $country,
        $phone,
        $email) {
    $this->shipping['firstname'] = $this->escap_string($firstname);
    $this->shipping['lastname']  = $this->escap_string($lastname);
    $this->shipping['company']   = $this->escap_string($company);
    $this->shipping['address1']  = $this->escap_string($address1);
    $this->shipping['address2']  = $this->escap_string($address2);
    $this->shipping['city']      = $this->escap_string($city);
    $this->shipping['state']     = $this->escap_string($state);
    $this->shipping['zip']       = $this->escap_string($zip);
    $this->shipping['country']   = $this->escap_string($country);
    $this->shipping['phone']      = $this->escap_string($phone);
    $this->shipping['email']     = $this->escap_string(sanitize_email($email));
  }

  /**
   * Get payloads for shipping address
   * @return array
   */
  function getShippingAddress() {

    return array(
      'shipping_firstname' => $this->shipping['firstname'],
      'shipping_lastname' => $this->shipping['lastname'],
      'shipping_company' => $this->shipping['company'],
      'shipping_address1' => $this->shipping['address1'],
      'shipping_address2' => $this->shipping['address2'],
      'shipping_city' => $this->shipping['city'],
      'shipping_state' => $this->shipping['state'],
      'shipping_zip' => $this->shipping['zip'],
      'shipping_country' => $this->shipping['country'],
      'shipping_email' => $this->shipping['email']
    );
  }

  /**
   * final payload for transaction & call the iBill API
   * @param float $amount
   * @param int $ccnumber
   * @param int $ccexp
   * @param int $cvv
   * @return array
   */
  function doSale($amount, $ccnumber, $card_expiry_month, $card_expiry_year, $cvv="",$vault_id=NULL) {
   
    $payload = array(
      'type' =>'charge',
      'card_number' => $ccnumber,
      "card_expiry_month"=>$card_expiry_month,
      "card_expiry_year"=>$card_expiry_year,
      'amount' => number_format($amount,2,".",""),
      'card_cvv' => $cvv,
      'ipaddress' =>$this->order['ipaddress'],
      'order_id' =>$this->order['orderid'],
      'orderdescription' =>$this->order['ipaddress'],
      'tax' =>number_format($this->order['tax'],2,".",""),
      'shipping' =>number_format($this->order['shipping'],2,".",""),
      'ponumber' =>$this->order['ponumber'],
    );
    
    // get billing details
    $payload =array_merge($payload,$this->getBillingAddress());
    // get shipping details
    $payload =array_merge($payload,$this->getShippingAddress());
    return $this->httpRequest(http_build_query($payload));
  }

  /**
   * Void transaction
   * @param int $transactionid
   * @return array
   */
  function doVoid($transactionid) {
    $payload = array(
      'type' =>'void',
      'payment_id' => $transactionid
    );
    return $this->httpRequest(http_build_query($payload),1);
  }

  /**
   * Method for refund payment
   * @param int $transactionid
   * @param float $amount
   * @return array
   */
  function doRefund($transactionid, $amount = 0) {
    $payload = array(
      'type' =>'refund',
      'payment_id' => $transactionid,
      'amount' => number_format($amount,2,".","")
    );

    return $this->httpRequest(http_build_query($payload),1);
  }

  /**
   * 
   * @param array $inputs
   * @param boolean $return_type
   * @return array
   */
  function httpRequest($inputs,$return_type=0) {

    parse_str($inputs, $input_data);
    $input_data['account_id']  = $this->login['account_id'];
    $input_data['private_key'] = $this->login['private_key'];
    $input_data['mode_type'] = $this->login['mode_type'];
  
    // Amount convert from dollar to cent.
    if(isset($input_data['amount']) && !empty($input_data['amount'])){
      $input_data['amount'] = ($input_data['amount']*100);
    }
    $headers =  $this->getHeader($input_data);
    
    $http = _wp_http_get_object();
    $response = $http->post($this->endpoint.$input_data['type'],  ['headers' =>$headers, 'body' => json_encode($input_data)] );
    $response = new WP_REST_Response($response);
    $data = $response->data['body'];
    
    if($return_type==1){
      return $data;
    }

   $data = json_decode($data );
   $this->responses['response'] = $data;
    return $this->responses['response'];
  }

  /**
   * API header
   * @param array $response
   * @return array
   */
  function getHeader($response){
    $get_headers = ['Accept' => 'application/json','Authorization' => 'Bearer '.$response['private_key'].'', 'Content-Type' => 'application/json', 'IBILL-ACCOUNT-ID' => $response['account_id'], 'IBILL-ENVIRONMENT' => $response['mode_type']];
    return $get_headers;
  }

  /**
   * Convert string to array
   * @param array $response
   * @return array
   */
  function getResponse($response){
    $get_array = $data = json_decode($response);
    return $get_array;
  }

  /**
   * Sanitizes a string from user input or from the database.
   */
  function escap_string($string=NULL){
    if(!empty($string)){
      return sanitize_text_field($string);
    }

  }
}
?>