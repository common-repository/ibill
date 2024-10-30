<?php
/**
 * Initiate plugin settings & functions
 * 
 * Custom class(WC_ibill_Gateway) extended to  base WooCommerce class(WC_Payment_Gateway) 
 * and added the functions to capture payments and void/refund.
 * @package iBill
 * @since   1.0.0
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class WC_ibill_Gateway extends WC_Payment_Gateway {

  private $wc_payment_token  = NULL;
  private $inputs  = array();
  private $cardtype = "";

  public function __construct() {

      $this->id = 'wpg_ibill';
      $this->icon = plugins_url('assets/img/ibill.png', __FILE__);
      $this->has_fields = true;
      $this->method_title = 'iBill';
      $this->method_description = esc_html('iBill offers the best payments platform for running internet commerce. We build flexible and easy to use tools for ecommerce to help our merchants.');
      $this->init_form_fields();
      $this->init_settings();
      $this->supports = array(
            'default_credit_card_form', 
            'capture_charge',
            'refunds',
            'voids',
            'pre-orders',
            'products',
            'tokenization'
        );
      $this->title = $this->get_option('ibill_title');
      $this->ibill_account_id = $this->get_option('ibill_account_id');
      $this->ibill_private_key = $this->get_option('ibill_private_key');
      $this->transaction_type = $this->get_option('transaction_type');
      $this->ibill_cardtypes = $this->get_option('ibill_cardtypes');
      $this->ibill_mode_type = $this->get_option('ibill_mode_type');

      if (!defined("MTX_TRANSACTION_MODE")) {
          define("MTX_TRANSACTION_MODE", ($this->transaction_type == 'capture' ? true : false));
      }

      if (is_admin()) {
          add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
      }
      
     add_action( 'woocommerce_receipt_'.$this->id, array($this, 'receipt_page'));
     add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ));
  }

/**
 * Process refund & void from order details
 * @param int $order_id
 * @param float $amount
 * @param string $reason
 * @return boolean
 */ 
  public function process_refund($order_id, $amount = NULL, $reason = '') {
      $order = new WC_Order( $order_id );
      $pay = new ibill_payments;
      $pay->setAuth($this->ibill_account_id, $this->ibill_private_key, $this->ibill_mode_type);
      
      //Void transaction
        $response = $pay->doVoid($order->get_transaction_id());
        $response = $pay->getResponse($response);
        if(isset($response->success) && !empty($response->success) && isset($response->id) && !empty($response->id) ){
            $order->add_order_note( __('Order has been voided successfully.', 'woocommerce' ) );
            return true;
        }
        else{
            //Refund amount
            $response = $pay->doRefund($order->get_transaction_id(),$amount);
            $response = $pay->getResponse($response);
            if(isset($response->success) && !empty($response->success) && $response->success == true){
                $order->add_order_note( __('Order has been refunded successfully.', 'woocommerce' ) );
                return true;   
            }
            else
            {
                if(isset($response->error) && !empty($response->error)){
                    $order->add_order_note( __($response->error, 'woocommerce' ) );
                    throw new Exception( __($response->error, 'woocommerce' ) );
                    return false;        
                }
            }
        }
      return false;

    }

  /**
   * Settings title & short description of payment tab.
   */
  public function admin_options() {
      ?>
      <h3><?php _e('iBill for WooCommerce', 'woocommerce'); ?></h3>
      <p><?php _e('iBill offers the best payments platform for running internet commerce. We build flexible and easy to use tools for ecommerce to help our merchants.', 'woocommerce'); ?></p>
      <table class="form-table">
      <?php $this->generate_settings_html(); ?>
      </table>
      <?php
  }

  /**
   * Initiate form fields to take inputs for the gateway information related details.
   */
  public function init_form_fields() {
      $this->form_fields = array
          (
          'enabled' => array(
              'title' => __('Enable/Disable', 'woocommerce'),
              'type' => 'checkbox',
              'label' => __('Enable iBill Payments', 'woocommerce'),
              'default' => 'yes'
          ),
          'ibill_title' => array(
              'title' => __('Title', 'woocommerce'),
              'type' => 'text',
              'description' => __('This controls the title which the buyer sees during checkout.', 'woocommerce'),
              'default' => __('iBill', 'woocommerce'),
              'desc_tip' => true,
          ),
          'ibill_account_id' => array(
              'title' => __('Account ID', 'woocommerce'),
              'type' => 'text',
              'description' => __('Please provide iBill Account ID.', 'woocommerce'),
              'default' => '',
              'desc_tip' => true,
              'placeholder' => 'Account ID'
          ),
          'ibill_private_key' => array(
              'title' => __('Private Key', 'woocommerce'),
              'type' => 'password',
              'description' => __('Please provide iBill Private Key.', 'woocommerce'),
              'default' => '',
              'desc_tip' => true,
              'placeholder' => 'Private Key'
          ),
          'transaction_type' => array(
              'title' => __('Transaction Type', 'woocommerce'),
              'type' => 'select',
              'class' => 'chosen_select',
              'css' => 'width: 350px;',
              'desc_tip' => __('Select how transactions should be processed. Charge submits all transactions for settlement.', 'woocommerce'),
              'options' => array(
                  'capture' => 'Capture Only'
              )
          ),
          'ibill_cardtypes' => array(
              'title' => __('Accepted Cards', 'woocommerce'),
              'type' => 'multiselect',
              'class' => 'chosen_select',
              'css' => 'width: 350px;',
              'desc_tip' => __('Type of cards that are displayed to customers as accepted during checkout.', 'woocommerce'),
              'options' => array(
                  'mastercard' => 'MasterCard',
                  'visa' => 'Visa',
                  'discover' => 'Discover',
                  'amex' => 'American Express',
                  'jcb' => 'JCB',
                  'dinersclub' => 'Dinners Club',
              ),
              'default' => array('mastercard', 'visa', 'discover', 'amex'),
            ),
            'ibill_mode_type' => array(
                'title' => __('Mode', 'woocommerce'),
                'type' => 'select',
                'class' => 'chosen_select',
                'css' => 'width: 350px;',
                'desc_tip' => __('Select how transactions should be processed. Charge submits all transactions for settlement.', 'woocommerce'),
                'options' => array(
                    'production' => 'Production',
                    'sandbox' => 'Sandbox',
                )
            )
      );
  }

  /**
   * get the icon image based on the credit card and display on the checkout page.
   * @return image path
   */
  public function get_icon() {
      $icon = '';
      if (is_array($this->ibill_cardtypes)) {
          foreach ($this->ibill_cardtypes as $card_type) {

              if ($url = $this->get_payment_method_image_url($card_type)) {

                  $icon .= '<img src="' . esc_url($url) . '" alt="' . esc_attr(strtolower($card_type)) . '" />';
              }
          }
      } else {
          $icon .= '<img src="' . esc_url(plugins_url('assets/img/ibill.png', __FILE__)) . '" alt="iBill Gateway" />';
      }

      return apply_filters('woocommerce_ibill_icon', $icon, $this->id);
  }

  /**
   * Registers and enqueues the styles.
   */
	public function enqueue_scripts() {
        wp_enqueue_style('ibill-style-css', plugins_url('../style/ibill.css', __FILE__), array());
    }

  /**
   * pull card image based on the card type for image folder
   * @param string $type
   * @return string
   */
  public function get_payment_method_image_url($type) {

      $image_type = strtolower($type);

      return WC_HTTPS::force_https_url(plugins_url('../assets/img/' . $image_type . '.png', __FILE__));
  }

  /**
   * check type of card with regex and we can apply here new changes based on the new BIN(s)
   * @param int $number
   * @return string
   */
  function get_card_type($number) {
      $number = preg_replace('/[^\d]/', '', $number);
      if (preg_match('/^3[47][0-9]{13}$/', $number)) {
          return 'amex';
      } elseif (preg_match('/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/', $number)) {
          return 'dinersclub';
      } elseif (preg_match('/^6(?:011|5[0-9][0-9])[0-9]{12}$/', $number)) {
          return 'discover';
      } elseif (preg_match('/^(?:2131|1800|35\d{3})\d{11}$/', $number)) {
          return 'jcb';
      } elseif (preg_match('/^5[1-5][0-9]{14}$/', $number)) {
          return 'mastercard';
      } elseif (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', $number)) {
          return 'visa';
      } else {
          return 'Invalid Card No.';
      }
  }

  /**
   * Function to check IP & apply in the order API payload
   * @return string
   */
  function get_client_ip() {
      $ipaddress = '';
      if (getenv('HTTP_CLIENT_IP'))
          $ipaddress = getenv('HTTP_CLIENT_IP');
      else if (getenv('HTTP_X_FORWARDED_FOR'))
          $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
      else if (getenv('HTTP_X_FORWARDED'))
          $ipaddress = getenv('HTTP_X_FORWARDED');
      else if (getenv('HTTP_FORWARDED_FOR'))
          $ipaddress = getenv('HTTP_FORWARDED_FOR');
      else if (getenv('HTTP_FORWARDED'))
          $ipaddress = getenv('HTTP_FORWARDED');
      else if (getenv('REMOTE_ADDR'))
          $ipaddress = getenv('REMOTE_ADDR');
      else
          $ipaddress = '0.0.0.0';
      return $ipaddress;
  }
  
  /**
   * Initiate credit card form here
   * also you can modify as per your requirement and its presently using default form of WooCommerce
   */
  public function payment_fields() {
        $this->form();  
  }

  /**
   * check if the tokenization is support
   * @param string $name
   * @return string
   */
  public function field_name($name) {
      return $this->supports('tokenization') ? '' : ' name="' . esc_attr($this->id . '-' . $name) . '" ';
  }

  /**
   * Payment page card information form
   */
  public function form() {
  
   wp_enqueue_script( 'wc-credit-card-form' );

    $fields = array();

	$cvc_field = '<p class="form-row form-row-last">
		<label for="' . esc_attr( $this->id ) . '-card-cvc">' . esc_html__( 'Card code', 'woocommerce' ) . '&nbsp;<span class="required">*</span></label>
		<input id="' . esc_attr( $this->id ) . '-card-cvc" name="' . esc_attr($this->id) . '-card-cvc"  class="input-text wc-credit-card-form-card-cvc" inputmode="numeric" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" maxlength="4" placeholder="' . esc_attr__( 'CVC', 'woocommerce' ) . '" ' . $this->field_name( 'card-cvc' ) . ' style="width:100px" />
	</p>';

	$default_fields = array(
		'card-number-field' => '<p class="form-row form-row-wide">
			<label for="' . esc_attr( $this->id ) . '-card-number">' . esc_html__( 'Card number', 'woocommerce' ) . '&nbsp;<span class="required">*</span></label>
			<input id="' . esc_attr( $this->id ) . '-card-number" name="' . esc_attr($this->id) . '-card-number"  class="input-text wc-credit-card-form-card-number" inputmode="numeric" autocomplete="cc-number" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" ' . $this->field_name( 'card-number' ) . ' />
		</p>',
		'card-expiry-field' => '<p class="form-row form-row-first">
			<label for="' . esc_attr( $this->id ) . '-card-expiry">' . esc_html__( 'Expiry (MM/YY)', 'woocommerce' ) . '&nbsp;<span class="required">*</span></label>
			<input id="' . esc_attr( $this->id ) . '-card-expiry" name="' . esc_attr($this->id) . '-card-expiry"  class="input-text wc-credit-card-form-card-expiry" inputmode="numeric" autocomplete="cc-exp" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="' . esc_attr__( 'MM / YY', 'woocommerce' ) . '" ' . $this->field_name( 'card-expiry' ) . ' />
		</p>',
	);

	if ( ! $this->supports( 'credit_card_form_cvc_on_saved_method' ) ) {
		$default_fields['card-cvc-field'] = $cvc_field;
	}

    $allowed_html = array(
        'p'     => array( 'class' => array()),
        'label'     => array( 'class' => array()),
        'input'     => array('class' => array(),'name' => array(),'id' => array(),'inputmode' => array(),'autocomplete' => array(),'autocorrect' => array(),'type' => array(),'placeholder' => array(),'spellcheck' => array(),'maxlength' => array()),
        'strong' => array( 'class' => array()),
        'div' => array( 'class' => array(),'id' => array()),
        'span' => array( 'class' => array()) 
    );

	$fields = wp_parse_args( $fields, apply_filters( 'woocommerce_credit_card_form_fields', $default_fields, $this->id ) );
	?>

	<fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-cc-form" class='wc-credit-card-form wc-payment-form'>
		<?php do_action( 'woocommerce_credit_card_form_start', $this->id ); ?>
		<?php
		foreach ( $fields as $field ) {
			echo wp_kses($field,$allowed_html); // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
		}
		?>
		<?php do_action( 'woocommerce_credit_card_form_end', $this->id ); ?>
		<div class="clear"></div>
	</fieldset>
	<?php

	if ( $this->supports( 'credit_card_form_cvc_on_saved_method' ) ) {
		esc_html('<fieldset>' . $cvc_field . '</fieldset>'); // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
    }
   
  }

  /**
   * Process payment via iBill gateway and handle notice in the order history
   * @global array $woocommerce
   * @param int $order_id
   * @return array
   */
  public function process_payment($order_id) {
      global $woocommerce;
      $wc_order = new WC_Order($order_id);
      $this->inputs = $this->escap_array($_POST);
      $this->cardtype = $cardtype = $this->get_card_type(sanitize_text_field(str_replace(" ", "", $this->inputs[$this->id . '-card-number'])));
      $this->wc_payment_token = isset($this->inputs['wc-'. $this->id.'-payment-token'] ) ? $this->inputs['wc-'. $this->id.'-payment-token'] : '';
      if((empty($this->wc_payment_token) || $this->wc_payment_token =='new')){
        if($this->ibill_validate_error()){
            return false;
            wp_die();
        }
      }
      $exp_date = explode("/", sanitize_text_field($this->inputs[$this->id . '-card-expiry']));
      $exp_month = str_replace(' ', '', $exp_date[0]);
      $exp_year = str_replace(' ', '', $exp_date[1]);

      if (strlen($exp_year) == 2) {
          $exp_year += 2000;
      }

      $gw = new ibill_payments;
      $gw->setAuth($this->ibill_account_id, $this->ibill_private_key, $this->ibill_mode_type);
      $gw->setBillingAddress(
              $wc_order->billing_first_name, $wc_order->billing_last_name, $wc_order->billing_company, $wc_order->billing_address_1, $wc_order->billing_address_2, $wc_order->billing_city, $wc_order->billing_state, $wc_order->billing_postcode, $wc_order->billing_country, $wc_order->billing_phone, $wc_order->billing_phone, $wc_order->billing_email, get_bloginfo('url')
      );

      $gw->setShippingAddress(
              $wc_order->shipping_first_name, $wc_order->shipping_last_name, $wc_order->shipping_company, $wc_order->shipping_address_1, $wc_order->shipping_address_2, $wc_order->shipping_city, $wc_order->shipping_state, $wc_order->shipping_postcode, $wc_order->shipping_country, $wc_order->shipping_phone, $wc_order->shipping_email);

      $gw->setParams(
              $wc_order->get_order_number(), get_bloginfo('blogname') . ' Order #' . $wc_order->get_order_number(), number_format($wc_order->get_total_tax(), 2, ".", ""), number_format($wc_order->get_total_shipping(), 2, ".", ""), $wc_order->get_order_number(), $this->get_client_ip()
      );

        $r = $gw->doSale(
            number_format($wc_order->order_total, 2, ".", ""), sanitize_text_field(str_replace(" ", "", $this->inputs[$this->id . '-card-number'])), $exp_month, $exp_year, sanitize_text_field($this->inputs[$this->id . '-card-cvc'],$order_id)
        );

      if (count((array)$gw->responses['response']) > 0) {
          if (true == $gw->responses['response']->success) {
                $wc_order->add_order_note(__('Payment has been approved successfully. Transaction ID:  '.$gw->responses['response']->id.' & Authorization Code: '.$gw->responses['response']->avs_code, 'woocommerce'));
                $wc_order->payment_complete($gw->responses['response']->id);
                WC()->cart->empty_cart();
                $token = $this->ibill_get_payment_token(get_current_user_id()); 

                if(!empty($token)){
                    $wc_order->update_meta_data('wc_'.$this->id.'_token',$token);
                    $wc_order->save();
                }
              
              return array(
                  'result' => 'success',
                  'redirect' => $this->get_return_url($wc_order),
              );
          } else {
              $wc_order->add_order_note(__($gw->responses['response']->error, 'woocommerce'));
              wc_add_notice($gw->responses['response']->error, $notice_type = 'error');
          }
      } else {
          $wc_order->add_order_note(__($gw->responses['responsetext'], 'woocommerce'));
          wc_add_notice($gw->responses['responsetext'], $notice_type = 'error');
      }
  }

  /**
   * Function to validate card info
   */
  private function ibill_validate_error(){

        if(empty($this->inputs[$this->id . '-card-number']) || empty($this->inputs[$this->id . '-card-expiry']) || empty($this->inputs[$this->id . '-card-cvc'])){
            wc_add_notice('Please enter card information.', $notice_type = 'error');
            return true;
        }
        else if (!in_array($this->cardtype, $this->ibill_cardtypes)) {
            wc_add_notice('Merchant does not accept ' . $this->cardtype . ' card', $notice_type = 'error');
            return true;
        }
  }
    
    /**
     * get payment token
     * @param int $user_id
     * @param int $order_id
     * @return string
     */
    private function ibill_get_payment_token($user_id=NULL,$order_id=NULL){
        $token = WC_Payment_Tokens::get_customer_default_token($user_id);
        if(empty($token)){
            return false;
        }
        //Get the actual token string (used to communicate with payment processors).
        $token = WC_Payment_Tokens::get($token->get_id());
        return $token->get_token();
    }
  
    /**
     * Sanitizes a array from user input or from the database.
     * @param: $input_array array
     * @return array
     */
    private function escap_array($input_array=array()){
        if(!empty($input_array) && is_array($input_array)){
            foreach($input_array as $key=>$value){
                $input_array[$key] = sanitize_text_field($value);
            }
            return $input_array;
        }

    }
     
}
?>