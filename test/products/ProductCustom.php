<?php
/**
 * Product Class.
 *
 * The Product Custom Class.
 *
 * @class    ProductCustom
 * @category Class
 * @author   Codingkart
 */  
class ProductCustom extends BaseProduct
{
  	 /**
     * Constructor for the Product class
     *
     * Sets up all the appropriate hooks and actions
     * 
     */
    public function __construct() {
        
        // After add to cart button
        add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'codingkart_woocommerce_product_page'), 10);

        // ajax get json of single product
        add_action( 'wp_ajax_codingkart_woocommerce_get_product_json_data', array($this,'codingkart_woocommerce_get_product_json_data') );
        add_action( 'wp_ajax_nopriv_codingkart_woocommerce_get_product_json_data', array($this,'codingkart_woocommerce_get_product_json_data' ));

        // Remove product Review tab
        add_filter( 'woocommerce_product_tabs', array($this,'codingkart_remove_product_review'), 99);

        // Wrong API URL popup
        add_action('wp_footer', array($this,'codingkart_wrong_api_url_popup'));

        // BaseRest Class Object
        $this->BaseRest_obj = new BaseRest;

        // BaseCustomer Class Object
        $this->BaseCustomer_obj = new BaseCustomer;
    }
	
	/**
     *  add custom button on product details 
     */
    public function codingkart_woocommerce_product_page() {
	  	global $product;
		
        if ( is_user_logged_in() ) {
            $user_id = get_current_user_id();
            $user = get_userdata( $user_id );
          
            // calling function codingkart_customer_check_user_type with help of BaseCustomer class object
            $check_type=$this->BaseCustomer_obj->codingkart_customer_check_user_type('subscriber');
          
            if($check_type)
            {
                echo '<a href="javascript:void(0)" rel="nofollow" style="margin-left: 5px;" id="ajax_api_call_'.$product->get_id().'" data-product_id="'.$product->get_id().'" class="ajax_api_call button" title="Upload to AutoDS">Upload to AutoDS<i class="fa fa-upload"></i></a>';
            }
            
        } else {
            echo '<a href="'.site_url().'/my-account/" rel="nofollow" style="margin-left: 5px;" class="upload-button-non-loggedin button" title="Upload to AutoDS">Upload to AutoDS<i class="fa fa-upload"></i></a>'; 
        }
	}


    /**
     *  get product json data by product id  
     */
    public function codingkart_woocommerce_get_product_json_data() {
        global $woocommerce;
        $id = $_POST['data_product_id'];
        $data = $this->BaseRest_obj->codingkart_woocommerce_get_api_response(site_url().'/wp-json/wc/v2/products/', $id);

        //wp_send_json( trim($data) );

        if ( is_user_logged_in() ) {
            // check user is subscriber
            $check_type=$this->BaseCustomer_obj->codingkart_customer_check_user_type('subscriber');

            if($check_type){
               echo $this->codingkart_send_product_data_to_url($data);die;
            }
        }

    }
	
    /**
     *  Send data to url 
     */
    public function codingkart_send_product_data_to_url($data){
        $user_id = get_current_user_id();
        $token = $this->BaseCustomer_obj->codingkart_get_set_autods_token($user_id);
        
        $api_url        = api_url;
        $url            = $api_url.'api/ebay_api/user/'.$token.'/upload_item';
        
        $make_call = $this->BaseRest_obj->codingkart_rest_callAPI('POST',$url,$data);

        $make_call = str_replace(array('\n', '<p></p>'), '', $make_call);
        //$response = json_decode($make_call, true);

        $haystack = $make_call;
        $needle   = "Not Found";

        // URL should be right
        if( strpos( $haystack, $needle ) !== false) {
            wp_send_json_error( 'Error: Invalid URL!' );
        }else{
            $result = array($url, $make_call);
            $result = json_encode($result);
            return $result;
        }
    }

    /**
     *  Additional Vendor Information  
     */
    public function codingkart_additional_vendor_information(){ ?>
        <li class="dokan-store-cancellation-rate">
            <i class="fa fa-percent"></i>
            Cancelation rate: 0%
        </li>
        <li class="dokan-store-cancellation-rate">
            <i class="fa fa-percent"></i>
            Returns rate: 2%
        </li>
        <li class="dokan-store-cancellation-rate">
            <i class="fa fa-clock-o"></i>
            Ship on time: 100%
        </li>
        <li class="dokan-store-cancellation-rate">
            <i class="fa fa-clock-o"></i>
            Customer support average response time: Less than a day
        </li>
    <?php }

    /**
     *  Remove product Review tab
     */
    public function codingkart_remove_product_review($tabs) {
        unset($tabs['reviews']);
        return $tabs;
    }

    /**
     *  Wrong API URL popup
     */
    public function codingkart_wrong_api_url_popup(){ ?>
        <!-- Modal -->
        <div id="wrong_api_url_popup" class="modal fade" role="dialog">
          <div class="modal-dialog">
            <!-- Modal content-->
            <div class="modal-content">
              <div class="modal-body">
                <p>Invalid API URL!</p>
              </div>
            </div>

          </div>
        </div>
    <?php }
}
new ProductCustom();

?>