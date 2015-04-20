<?php
/*
Plugin Name:  WooCommerce CCavenue gateway Iframe integration
Plugin URI: http://www.aheadzen.com
Description: Extends WooCommerce with ccavenue indian payment gateway. Collect card credentials and accept payments on your checkout page using our secure iFrame. Reduce payment hops and allow customers to make secure payments without leaving your webpage for a seamless brand experience.
Version: 1.0
Author: Krishna
Author URI: http://www.aheadzen.com/

    Copyright: © 2009-2014 mRova.
    License: GNU General Public License v3.0
    License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/
    if ( ! defined( 'ABSPATH' ) )
        exit;
    add_action('plugins_loaded', 'woocommerce_mrova_ccave_init', 0);

    function woocommerce_mrova_ccave_init() {

        if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

    /**
     * Gateway class
     */
    class WC_Mrova_Ccave extends WC_Payment_Gateway {
        public function __construct(){

            // Go wild in here
            $this -> id           = 'ccavenue';
            $this -> method_title = __('CCAvenue', 'mrova');
            $this -> icon         =  plugins_url( 'images/logo.gif' , __FILE__ );
            $this -> has_fields   = true;
            
            $this -> init_form_fields();
            $this -> init_settings();

            $this -> title            = $this -> settings['title'];
            $this -> description      = $this -> settings['description'];
            $this -> merchant_id      = $this -> settings['merchant_id'];
            $this -> working_key      = $this -> settings['working_key'];
            $this -> access_code      = $this -> settings['access_code'];
			$this -> sandbox      	  = $this -> settings['sandbox'];
			$this -> iframemode       = $this -> settings['iframemode'];
			$this -> hideccavenuelogo = $this -> settings['hideccavenuelogo'];
			
			if($this -> hideccavenuelogo=='yes')
			{
				$this -> icon = '';	
			}

			if($this -> sandbox=='yes')
			{
				 $this -> liveurlonly = "https://test.ccavenue.com/transaction/transaction.do";
			}else{
				 $this -> liveurlonly = "https://secure.ccavenue.com/transaction/transaction.do";
			}
			 $this -> liveurl  = $this -> liveurlonly.'?command=initiateTransaction';
			
            $this->notify_url = str_replace( 'https:', 'http:', home_url( '/wc-api/WC_Mrova_Ccave' )  );

            $this -> msg['message'] = "";
            $this -> msg['class']   = "";
			
			$this -> payment_option = $_POST['payment_option'];
			$this -> card_type		= $_POST['card_type'];
			$this -> card_name 		= $_POST['card_name'];
			$this -> data_accept 	= $_POST['data_accept'];
			$this -> card_number 	= $_POST['card_number'];
			$this -> expiry_month 	= $_POST['expiry_month'];
			$this -> expiry_year 	= $_POST['expiry_year'];
			$this -> cvv_number 	= $_POST['cvv_number'];
			$this -> issuing_bank 	= $_POST['issuing_bank'];
			
            //add_action('init', array(&$this, 'check_ccavenue_response'));
            //update for woocommerce >2.0
            add_action( 'woocommerce_api_wc_mrova_ccave', array( $this, 'check_ccavenue_response' ) );

            add_action('valid-ccavenue-request', array($this, 'successful_request'));
            if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            } else {
                add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
            }
            add_action('woocommerce_receipt_ccavenue', array($this, 'receipt_page'));
            add_action('woocommerce_thankyou_ccavenue',array($this, 'thankyou_page'));
        }

        function init_form_fields(){

            $this -> form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'mrova'),
                    'type' => 'checkbox',
                    'label' => __('Enable CCAvenue Payment Module.', 'mrova'),
                    'default' => 'no'),
				
				 'sandbox' => array(
                    'title' => __('Enable Sandbox?', 'mrova'),
                    'type' => 'checkbox',
                    'label' => __('Enable Sandbox CCAvenue Payment.', 'mrova'),
                    'default' => 'no'),
				 
				 'iframemode' => array(
                    'title' => __('Iframe/Redirect Payment', 'mrova'),
                    'type' => 'checkbox',
                    'label' => __('Enable Iframe method and do not want customer to redirect on CCAvenue site.', 'mrova'),
                    'default' => 'no'),
				 
				 'hideccavenuelogo' => array(
                    'title' => __('Show/Hide Logo', 'mrova'),
                    'type' => 'checkbox',
                    'label' => __('Hide CCAvenue logo on checkout page.', 'mrova'),
                    'default' => 'no'),
				 
                'title' => array(
                    'title' => __('Title:', 'mrova'),
                    'type'=> 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'mrova'),
                    'default' => __('CCAvenue', 'mrova')),
                'description' => array(
                    'title' => __('Description:', 'mrova'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'mrova'),
                    'default' => __('Pay securely by Credit or Debit card or internet banking through CCAvenue Secure Servers.', 'mrova')),
                'merchant_id' => array(
                    'title' => __('Merchant ID', 'mrova'),
                    'type' => 'text',
                    'description' => __('This id(USER ID) available at "Generate Working Key" of "Settings and Options at CCAvenue."')),
                'working_key' => array(
                    'title' => __('Working Key', 'mrova'),
                    'type' => 'text',
                    'description' =>  __('Given to Merchant by CCAvenue', 'mrova'),
                    ),
                'access_code' => array(
                    'title' => __('Access Code', 'mrova'),
                    'type' => 'text',
                    'description' =>  __('Given to Merchant by CCAvenue', 'mrova'),
                    )
                );


}
        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis
         **/
        public function admin_options(){
            echo '<h3>'.__('CCAvenue Payment Gateway', 'mrova').'</h3>';
            echo '<p>'.__('CCAvenue is most popular payment gateway for online shopping in India').'</p>';
            echo '<table class="form-table">';
            $this -> generate_settings_html();
            echo '</table>';

        }
        /**
         *  There are no payment fields for CCAvenue, but we want to show the description if set.
         **/
        function payment_fields(){
            if($this -> description) echo wpautop(wptexturize($this -> description));
			?>
            <style>
            ul.payoptions{list-style:none; width:100%;}
			ul.payoptions li{display:inline-block; margin-right:15px !important;}
			ul.payoptions li .payOption{ margin-right:0 !important;}
            </style>
            <label><?php _e('Payment Option','mrova')?> : </label>
            <ul class="payoptions">
            <li><input class="payOption" type="radio" name="payment_option" value="OPTCRDC">Credit Card</li>
            <li><input class="payOption" type="radio" name="payment_option" value="OPTDBCRD">Debit Card </li>
            <li><input class="payOption" type="radio" name="payment_option" value="OPTNBK">Net Banking</li>
            <li><input class="payOption" type="radio" name="payment_option" value="OPTCASHC">Cash Card</li>
            <li><input class="payOption" type="radio" name="payment_option" value="OPTMOBP">Mobile Payments</li>
            </ul>
            
            <label><?php _e('Card Type','mrova')?> : </label>
            <input type="text" id="card_type" name="card_type" value="" readonly="readonly"/>
            
            <label><?php _e('Card Name','mrova')?> : </label>
            <select name="card_name" id="card_name"> <option value="">Select Card Name</option> </select>
           <?php /*?> <label><?php _e('Data Accepted At','mrova')?> : </label>
            <input type="text" id="data_accept" name="data_accept" readonly="readonly" /><?php */?>
            <input type="hidden" id="data_accept" name="data_accept" />
            
            <label><?php _e('Card Number','mrova')?> : </label>
            <input type="text" name="card_number" value=""/>e.g. 4111111111111111
            
             <label><?php _e('Expiry Month-Year','mrova')?> : </label>
            <?php
            $montharr = array(
							  '01'=>'Jan',
							  '02'=>'Feb',
							  '03'=>'Mar',
							  '04'=>'Apr',
							  '05'=>'May',
							  '06'=>'Jun',
							  '07'=>'Jul',
							  '08'=>'Aug',
							  '09'=>'Sep',
							  '10'=>'Oct',
							  '11'=>'Nov',
							  '12'=>'Dec',
							  );
			?>
            <select name="expiry_month">
            <option value="">Select Month</option>
            <?php
			if($montharr)
			{
				foreach($montharr as $key =>$val)
				{
				?>
                <option value="<?php echo $key;?>"><?php echo $val;?></option>
                <?php	
				}
			}
			?>
            </select>
            &nbsp;&nbsp;
            <select name="expiry_year">
            <option value="">Select Year</option>
            <?php
			for($yr=date('Y');$yr<date('Y')+15;$yr++)
			{
			?>
				<option value="<?php echo $yr;?>"><?php echo $yr;?></option>
			<?php	
			}
			?>
            </select>
            
            <label><?php _e('CVV Number','mrova')?> : </label>
           <input type="text" name="cvv_number" value=""/>e.g. 328
           
            <label><?php _e('Issuing Bank','mrova')?> : </label>
           <input type="text" name="issuing_bank" value=""/>e.g. State Bank Of India

<script type="text/javascript">
  jQuery(function(){
  
	 /* json object contains
	 	1) payOptType - Will contain payment options allocated to the merchant. Options may include Credit Card, Net Banking, Debit Card, Cash Cards or Mobile Payments.
	 	2) cardType - Will contain card type allocated to the merchant. Options may include Credit Card, Net Banking, Debit Card, Cash Cards or Mobile Payments.
	 	3) cardName - Will contain name of card. E.g. Visa, MasterCard, American Express or and bank name in case of Net banking. 
	 	4) status - Will help in identifying the status of the payment mode. Options may include Active or Down.
	 	5) dataAcceptedAt - It tell data accept at CCAvenue or Service provider
	 	6)error -  This parameter will enable you to troubleshoot any configuration related issues. It will provide error description.
	 */	  
  	  var jsonData;
  	  var access_code="<?php echo $this -> access_code;?>" // shared by CCAVENUE 
  	  var amount="<?php echo WC()->cart->total;?>";
  	  var currency="<?php echo get_woocommerce_currency();?>";
	  jQuery.ajax({
           url:'<?php echo $this -> liveurlonly;?>?command=getJsonData&access_code='+access_code+'&currency='+currency+'&amount='+amount,
           dataType: 'jsonp',
           jsonp: false,
           jsonpCallback: 'processData',
           success: function (data) { 
                 jsonData = data;
                 // processData method for reference
                 processData(data);
				 
           },
           error: function(xhr, textStatus, errorThrown) {
               alert('An error occurred! ' + ( errorThrown ? errorThrown :xhr.status ));
               //console.log("Error occured");
           }
   		});
   
   		jQuery(".payOption").click(function(){
			
            jQuery("#card_name").children().remove(); // remove old card names from old one
            jQuery("#card_name").append("<option value=''>Select</option>");
           	
           	var paymentOption = jQuery(this).val();
           	jQuery("#card_type").val(paymentOption.replace("OPT",""));
           	
           	jQuery.each(jsonData, function(index,value) {
           		//console.log(value.error);
            	 console.log(value);
            	 if(value.payOpt==paymentOption){
            		//console.log(value[paymentOption]);
            		var payOptJSONArray = jQuery.parseJSON(value[paymentOption]);
                	jQuery.each(payOptJSONArray, function() {
    	            	jQuery("#card_name").find("option:last").after("<option class='"+this['dataAcceptedAt']+" "+this['status']+"'  value='"+this['cardName']+"'>"+this['cardName']+"</option>");
                	});
                  }
           	});
            	
         });
            
          jQuery("#card_name").click(function(){
          	if(jQuery(this).find(":selected").hasClass("DOWN")){
          		alert("Selected option is currently unavailable. Select another payment option or try again later.");
          	}
          	if(jQuery(this).find(":selected").hasClass("CCAvenue")){
          		jQuery("#data_accept").val("Y");
          	}else{
          		jQuery("#data_accept").val("N");
          	}
          });
   
   
   // below code for reference 
 
   function processData(data){
         var paymentOptions = [];
         var creditCards = [];
         var debitCards = [];
         var netBanks = [];
         var cashCards = [];
         var mobilePayments=[];
         jQuery.each(data, function() {
         	 // this.error shows if any error   	
             console.log(this.error);
              paymentOptions.push(this.payOpt);
			   switch(this.payOpt){
                case 'OPTCRDC':
                	var jsonData = this.OPTCRDC;
                 	var obj = jQuery.parseJSON(jsonData);
                 	jQuery.each(obj, function() {
                 		creditCards.push(this['cardName']);
                	});
                break;
                case 'OPTDBCRD':
                	var jsonData = this.OPTDBCRD;
                 	var obj = jQuery.parseJSON(jsonData);
                 	jQuery.each(obj, function() {
                 		debitCards.push(this['cardName']);
                	});
                break;
              	case 'OPTNBK':
	              	var jsonData = this.OPTNBK;
	                var obj = jQuery.parseJSON(jsonData);
	                jQuery.each(obj, function() {
	                 	netBanks.push(this['cardName']);
	                });
                break;
                
                case 'OPTCASHC':
                  var jsonData = this.OPTCASHC;
                  var obj =  jQuery.parseJSON(jsonData);
                  jQuery.each(obj, function() {
                  	cashCards.push(this['cardName']);
                  });
                 break;
                   
                  case 'OPTMOBP':
                  var jsonData = this.OPTMOBP;
                  var obj =  jQuery.parseJSON(jsonData);
                  jQuery.each(obj, function() {
                  	mobilePayments.push(this['cardName']);
                  });
              }
              
            });
           
           //console.log(creditCards);
          // console.log(debitCards);
          // console.log(netBanks);
          // console.log(cashCards);
         //  console.log(mobilePayments);
            
      }
      
     
   
  });
</script>
            <?php
        }
        /**
         * Receipt Page
         **/
        function receipt_page($order){

           // echo '<p>'.__('Thank you for your order, please click the button below to pay with CCAvenue.', 'mrova').'</p>';
            echo $this -> generate_ccavenue_form($order);
        }
        /**
         * Process the payment and return the result
         **/
        function process_payment($order_id){
            $order = new WC_Order($order_id);
			update_post_meta($order_id,'_post_data',$_POST);
			//print_r($_POST);exit; //vaj
			return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url( true ));
        }
        /**
         * Check for valid CCAvenue server callback
         **/
        function check_ccavenue_response(){
            global $woocommerce;

            $msg['class']   = 'error';
            $msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
            
            if(isset($_REQUEST['encResp'])){

                $encResponse = $_REQUEST["encResp"];         
                $rcvdString  = decrypt($encResponse,$this -> working_key);      
                
                $decryptValues = array();

                parse_str( $rcvdString, $decryptValues );
                $order_id_time = $decryptValues['order_id'];
                $order_id = explode('_', $decryptValues['order_id']);
                $order_id = (int)$order_id[0];

                if($order_id != ''){
                    try{
                        $order = new WC_Order($order_id);
                        $order_status = $decryptValues['order_status'];
                        $transauthorised = false;
                        if($order -> status !=='completed'){
                            if($order_status=="Success")
                            {
                                $transauthorised = true;
                                $msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.";
                                $msg['class'] = 'success';
                                if($order -> status != 'processing'){
                                    $order -> payment_complete();
                                    $order -> add_order_note('CCAvenue payment successful<br/>Bank Ref Number: '.$decryptValues['bank_ref_no']);
                                    $woocommerce -> cart -> empty_cart();

                                }

                            }
                            else if($order_status==="Aborted")
                            {
                                $msg['message'] = "Thank you for shopping with us. We will keep you posted regarding the status of your order through e-mail";
                                $msg['class'] = 'success';

                            }
                            else if($order_status==="Failure")
                            {
                             $msg['class'] = 'error';
                             $msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
                         }
                         else
                         {
                           $msg['class'] = 'error';
                           $msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";


                       }

                       if($transauthorised==false){
                        $order -> update_status('failed');
                        $order -> add_order_note('Failed');
                        $order -> add_order_note($this->msg['message']);
                    }

                }
            }catch(Exception $e){

                $msg['class'] = 'error';
                $msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";

            }

        }




    }

    if ( function_exists( 'wc_add_notice' ) )
    {
        wc_add_notice( $msg['message'], $msg['class'] );

    }
    else 
    {
        if($msg['class']=='success'){
            $woocommerce->add_message( $msg['message']);
        }else{
            $woocommerce->add_error( $msg['message'] );

        }
        $woocommerce->set_messages();
    }
    $redirect_url = get_permalink(woocommerce_get_page_id('myaccount'));
    wp_redirect( $redirect_url );
    exit;

}
       /*
        //Removed For WooCommerce 2.0
       function showMessage($content){
            return '<div class="box '.$this -> msg['class'].'-box">'.$this -> msg['message'].'</div>'.$content;
        }*/
        /**
         * Generate CCAvenue button link
         **/
        public function generate_ccavenue_form($order_id){
            global $woocommerce;
            $order = new WC_Order($order_id);
            $order_id = $order_id.'_'.date("ymds");
			
			$post_data = get_post_meta($order_id,'_post_data',true);
			update_post_meta($order_id,'_post_data',array());
			
			$ccavenue_args = array(
                'merchant_id'      => $this -> merchant_id,
                'amount'           => $order -> order_total,
                'order_id'         => $order_id,
                'redirect_url'     => $this->notify_url,
                'cancel_url'       => $this->notify_url,
                'billing_name'     => $order -> billing_first_name .' '. $order -> billing_last_name,
                'billing_address'  => trim($order -> billing_address_1, ','),
                'billing_country'  => wc()->countries -> countries [$order -> billing_country],
                'billing_state'    => $order -> billing_state,
                'billing_city'     => $order -> billing_city,
                'billing_zip'      => $order -> billing_postcode,
                'billing_tel'      => $order->billing_phone,
                'billing_email'    => $order -> billing_email,
                'delivery_name'    => $order -> shipping_first_name .' '. $order -> shipping_last_name,
                'delivery_address' => $order -> shipping_address_1,
                'delivery_country' => $order -> shipping_country,
                'delivery_state'   => $order -> shipping_state,
                'delivery_tel'     => '',
                'delivery_city'    => $order -> shipping_city,
                'delivery_zip'     => $order -> shipping_postcode,
                'language'         => 'EN',
                'currency'         => get_woocommerce_currency(),
				
				'payment_option'	=> $post_data['payment_option'],
				'card_type'		 	=> $post_data['card_type'],
				'card_name' 		=> $post_data['card_name'],
				'data_accept' 		=> $post_data['data_accept'],
				'card_number' 		=> $post_data['card_number'],
				'expiry_month' 		=> $post_data['expiry_month'],
				'expiry_year' 		=> $post_data['expiry_year'],
				'cvv_number' 		=> $post_data['cvv_number'],
				'issuing_bank' 		=> $post_data['issuing_bank'],
                );
			
			if($this -> iframemode=='yes') //Iframe mode
			{
				$ccavenue_args['integration_type'] = 'iframe_normal';
			}
foreach($ccavenue_args as $param => $value) {
 $paramsJoined[] = "$param=$value";
}
$merchant_data   = implode('&', $paramsJoined);

//echo $merchant_data;
$encrypted_data = encrypt($merchant_data, $this -> working_key);

$form = '';
if($this -> iframemode=='yes') //Iframe mode
{
	$production_url = $this -> liveurl.'&encRequest='.$encrypted_data.'&access_code='.$this->access_code;
	
	//echo 'DATA VALUE:'.$merchant_data;
	//echo '<br><br><br>';
	//echo 'URL TO CCAvenue : '.$production_url;
	
	$form .= '<iframe src="'.$production_url.'" id="paymentFrame" name="paymentFrame"  height="2000" width="600" frameborder="0" scrolling="No" ></iframe>
	
	<script type="text/javascript">
		jQuery(document).ready(function(){
			 window.addEventListener(\'message\', function(e) {
				 jQuery("#paymentFrame").css("height",e.data[\'newHeight\']+\'px\'); 	 
			 }, false);
			
		});
	</script>';
	/*if(!$_POST)
	{
		wc_enqueue_js( 'jQuery("#ccavenue_payment_form").submit();');
		
	}
	$targetto = 'target="paymentFrame"';*/

}else{ //redirect to CCAvenue site
		wc_enqueue_js( '
		$.blockUI({
			message: "' . esc_js( __( 'Thank you for your order. We are now redirecting you to CcAvenue to make payment.', 'woocommerce' ) ) . '",
			baseZ: 99999,
			overlayCSS:
			{
				background: "#fff",
				opacity: 0.6
			},
			css: {
				padding:        "20px",
				zindex:         "9999999",
				textAlign:      "center",
				color:          "#555",
				border:         "3px solid #aaa",
				backgroundColor:"#fff",
				cursor:         "wait",
				lineHeight:     "24px",
			}
		});
	jQuery("#submit_ccavenue_payment_form").click();
	' );
	$targetto = 'target="_top"';
	
	//===================================
	
	$ccavenue_args_array   = array();
	$ccavenue_args_array[] = "<input type='hidden' name='encRequest' value='$encrypted_data'/>";
	$ccavenue_args_array[] = "<input type='hidden' name='access_code' value='{$this->access_code}'/>";	
	
	$form .= '<form action="' . esc_url( $this -> liveurl ) . '" method="post" id="ccavenue_payment_form"  '.$targetto.'>
	' . implode( '', $ccavenue_args_array ) . '
	<!-- Button Fallback -->
	<div class="payment_buttons">
	<input type="submit" class="button alt" id="submit_ccavenue_payment_form" value="' . __( 'Pay via CCAvenue', 'woocommerce' ) . '" /> <a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __( 'Cancel order &amp; restore cart', 'woocommerce' ) . '</a>
	</div>
	<script type="text/javascript">
	jQuery(".payment_buttons").hide();
	</script>
	</form>';
}



return $form;



}


        // get all pages
function get_pages($title = false, $indent = true) {
    $wp_pages = get_pages('sort_column=menu_order');
    $page_list = array();
    if ($title) $page_list[] = $title;
    foreach ($wp_pages as $page) {
        $prefix = '';
                // show indented child pages?
        if ($indent) {
            $has_parent = $page->post_parent;
            while($has_parent) {
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

}

    /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_mrova_ccave_gateway($methods) {
        $methods[] = 'WC_Mrova_Ccave';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_mrova_ccave_gateway' );
}

/*
ccavenue functions
 */

function encrypt($plainText,$key)
{
    $secretKey = hextobin(md5($key));
    $initVector = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
    $openMode = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '','cbc', '');
    $blockSize = mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, 'cbc');
    $plainPad = pkcs5_pad($plainText, $blockSize);
    if (mcrypt_generic_init($openMode, $secretKey, $initVector) != -1) 
    {
      $encryptedText = mcrypt_generic($openMode, $plainPad);
      mcrypt_generic_deinit($openMode);

  } 
  return bin2hex($encryptedText);
}

function decrypt($encryptedText,$key)
{
    $secretKey = hextobin(md5($key));
    $initVector = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
    $encryptedText=hextobin($encryptedText);
    $openMode = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '','cbc', '');
    mcrypt_generic_init($openMode, $secretKey, $initVector);
    $decryptedText = mdecrypt_generic($openMode, $encryptedText);
    $decryptedText = rtrim($decryptedText, "\0");
    mcrypt_generic_deinit($openMode);
    return $decryptedText;

}
    //*********** Padding Function *********************

function pkcs5_pad ($plainText, $blockSize)
{
    $pad = $blockSize - (strlen($plainText) % $blockSize);
    return $plainText . str_repeat(chr($pad), $pad);
}

    //********** Hexadecimal to Binary function for php 4.0 version ********

function hextobin($hexString) 
{ 
    $length = strlen($hexString); 
    $binString="";   
    $count=0; 
    while($count<$length) 
    {       
        $subString =substr($hexString,$count,2);           
        $packedString = pack("H*",$subString); 
        if ($count==0)
        {
            $binString=$packedString;
        } 

        else 
        {
            $binString.=$packedString;
        } 

        $count+=2; 
    } 
    return $binString; 
} 
function mrova_debug($what){
    echo '<pre>';
    print_r($what);
    echo '</pre>';
}
?>