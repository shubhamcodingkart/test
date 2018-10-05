<?php
/**
 * @class    BaseNewOrderApi
 * @category Class
 * @author   Codingkart
 */  
class BaseNewOrderApi extends WC_REST_Orders_Controller 
{

    /**
     * Constructor for the BaseNewOrderApi class
     *
     * Sets up all the appropriate hooks and actions
     * 
     */
    public function __construct() {

        // Assign vendor to new_order api
    	add_action( 'woocommerce_rest_insert_shop_order_object', array( $this, 'codingkart_assign_vendor_to_new_order_api'));
        
		// Assign vendor to new_order api
        add_action( 'woocommerce_api_create_order', array( $this, 'codingkart_assign_vendor'), 10, 3 );
         
		// Requested order API data for validations
		add_filter( 'woocommerce_api_create_order_data',  array( $this, 'filter_woocommerce_api_create_order_data'), 10, 2 ); 

        // Requested API url error validations
        add_action( 'rest_pre_echo_response', array( $this, 'change_api_url_error_response'), 10, 3 );

    }
    
	/**
     *  Assign vendor to new_order api
    */
    public function codingkart_assign_vendor( $order_id, $data, $this_ ){
        // save token
        update_post_meta($order_id, 'autods_token', $data['token']);
		$order = wc_get_order( $order_id );
    	$this->codingkart_assign_vendor_to_new_order_api($order);

        $data = file_get_contents(site_url().'/wp-json/wc/v2/orders/'.$order_id);

        $json_data = json_decode($data);
        $array_data = array('success'=>True, 'order'=>$json_data, 'code'=>'200');
        $response = json_encode($array_data);
        //echo stripslashes( trim($response) );
        echo trim( stripslashes($response) );
        //print_r(stripslashes($response));
        die;
    } 
	 
    /**
     *  Assign vendor to new_order api
    */
    public function codingkart_assign_vendor_to_new_order_api( $order ) {
		global $wpdb;
        $order_id = $order->get_id();
        $get_order = wc_get_order( $order_id );
        $items = $get_order->get_items();

        foreach ( $items as $item ) {
            $product_id = $item->get_product_id();
            $post_author_id = get_post_field( 'post_author', $product_id );
            $arg = array(
             'ID' => $order_id,
             'post_author' => $post_author_id,
            );
            wp_update_post( $arg );
            $order_post_author_id = get_post_field( 'post_author', $order_id );

            // update order status to pending
            $the_order = new WC_Order( $order_id );
            $the_order->update_status('wc-pending');

            // insert order data in wp_dokan_orders table
            $table_name = $wpdb->prefix . "dokan_orders";
            $order_total = $order->get_total();
            $wpdb->insert($table_name, array('order_id' => $order_id, 'seller_id' => $post_author_id, 'order_total'=> $order_total, 'order_status' => 'wc-pending') );

        }

        // Deduct amount from customer wallet
        $customer_id = $get_order->get_user_id();
        $customer_wallet_balance = get_user_meta($customer_id, 'wallet-amount', true);
        $updated_vendor_wallet_balance = $customer_wallet_balance - $order_total;
        update_user_meta($customer_id, 'wallet-amount', $updated_vendor_wallet_balance);
		
    }

    /**
     *  validate create order api line items
    */
    public function codingkart_validate_line_items($line_items){
        $products_sum = 0;
        foreach ($line_items as $key => $products) {
            $product_id        = $products['product_id'];
            $product_quantity =  $products['quantity'];
            
            $product = wc_get_product($product_id);

            // -------- Must be a valid WC_Product
            if ( ! is_object( $product ) ) {
                // Error Code 1003 is used for line Items
                $this->get_order_api_error_by_code('1003'); 
            }
            elseif ( is_object( $product) ) {
                $manage_stock = get_post_meta( $product_id, '_manage_stock', true );
                $stock = $product->get_stock_quantity();
                if ($manage_stock == 'yes') {
                    if ($stock < $product_quantity ) {
                        // Error Code 1006 is used for line items stock validation
                        $this->get_order_api_error_by_code('1006');
                    }
                }

                if( $product->is_on_sale() ) {
                    $product_price = $product->get_sale_price();
                }else{
                    $product_price = $product->get_regular_price();
                }
                
                $price_with_quantity = $product_price * $product_quantity;
                $products_sum+= $price_with_quantity; //product price total with quantity
            }
        }

        return $products_sum;
    }

    /**
     *  API order response validation function
    */
    public function api_crete_new_order_validations($data){

        // -------- Customer exist validation
        if ( false === get_user_by( 'id', $data['customer_id'] ) ) {
            // Error Code 1004 is used for User validation
            $this->get_order_api_error_by_code('1004'); 
        }

        // ------ Wallet balance validation

        //validate create order api line items
        $shippings_sum = 0;
        $line_items = $data['line_items'];
        $products_sum = $this->codingkart_validate_line_items($line_items);

        // get all shipping methods
        foreach ($data['shipping_lines'] as $key => $shippings) {
            $shipping_total  =  $shippings['total'];
            $shippings_sum+= $shipping_total; // shipping total
        }

        $total = $products_sum + $shippings_sum; // order total

        $customer_id = $data['customer_id']; // customer id
        $customer_wallet_balance = get_user_meta($customer_id, 'wallet-amount', true); // customer wallet balance

        // if wallet balance is less than order total
        if ($customer_wallet_balance < $total) {
           // Error Code 1002 is used for validate wallet balance
            $this->get_order_api_error_by_code('1002'); 
        }

        // ----------------------------------

        // -------- Shipping Address validation
        //$vendor_id = get_post_field( 'post_author', $product_id );
        // Get vendor id from line items
        $vendor_id = $this->get_vendorid_from_order_api_line_items($line_items);

        // get all vendor shipping zones
        $vendor_country_rates = get_user_meta($vendor_id, '_dps_country_rates', true);
        $vendor_state_rates = get_user_meta($vendor_id, '_dps_state_rates', true);
        
        $shipping_country = $data['shipping']['country'];
        $shipping_state = $data['shipping']['state'];

        //check country code exist
        if (array_key_exists($shipping_country, $vendor_country_rates)){
            //check state code exist
            if (!empty($vendor_state_rates[$shipping_country])) {
                if ( !array_key_exists($shipping_state, $vendor_state_rates[$shipping_country]) ) {
                    // Error Code 1005 is used for Customer Address validation
                    $this->get_order_api_error_by_code('1005'); 
                }
            }
        }else{
            // Error Code 1005 is used for Customer Address validation
            $this->get_order_api_error_by_code('1005');
        }

    }
	
	/**
     *  Requested order API data
    */
    public function filter_woocommerce_api_create_order_data( $data, $instance) { 
        
        $autods_token = $data['token'];
        
        $BaseCustomer_obj = new BaseCustomer;
        $get_customerid_by_token = $BaseCustomer_obj->codingkart_get_userid_by_autods_token($autods_token);
        
        if (empty($get_customerid_by_token)) {
             $customer_id = 0;
        }else{
            $customer_id = $get_customerid_by_token; //get customer id from autods Token
        }

        $data['customer_id'] = $customer_id;
        
        // api order response validation function
        $this->api_crete_new_order_validations($data);

        // ------------------------------------------------------------------------------------------

        $line_items = $data['line_items'];
        $vendor_id = $this->get_vendorid_from_order_api_line_items($line_items);

        // get all vendor shipping zones
        $vendor_country_rates = get_user_meta($vendor_id, '_dps_country_rates', true);
        $vendor_state_rates = get_user_meta($vendor_id, '_dps_state_rates', true);

        $shipping_country = $data['shipping']['country'];
        $shipping_state = $data['shipping']['state'];

        if (array_key_exists($shipping_country, $vendor_country_rates)){

            if ( empty($shipping_state) ) {
                $data['shipping_lines'][0]['method_id']    = 'regular_shipping';
                $data['shipping_lines'][0]['method_title'] = 'Regular Shipping';
                $data['shipping_lines'][0]['total']        = $vendor_country_rates[$shipping_country];
            }

            elseif ( !empty($shipping_state) ) {
                //check state code exist
                if (!empty($vendor_state_rates[$shipping_country])) {
                    if ( array_key_exists($shipping_state, $vendor_state_rates[$shipping_country]) ) {
                        $data['shipping_lines'][0]['method_id']    = 'regular_shipping';
                        $data['shipping_lines'][0]['method_title'] = 'Regular Shipping';
                        $data['shipping_lines'][0]['total']        = $vendor_state_rates[$shipping_country][$shipping_state];
                    }
                }
            }
            
        }

        // ------------------------------------------------------------------------------------------

		return $data; 
	}

    /**
     *  Change API url error response
    */
    public function change_api_url_error_response( $response, $object, $request ) {
        if ($response['code'] == 'rest_no_route') {
            // Error Code 1001 is used for validate order API URL
            $this->get_order_api_error_by_code('1001');
        }else{
            return $response;
        }
    }

    /**
     *  Get vendor id from line items
    */
    public function get_vendorid_from_order_api_line_items($line_items){
        if ( count($line_items) ) {
            $product_id = $line_items[0]['product_id'];
            $vendor_id = get_post_field( 'post_author', $product_id );
            return $vendor_id;
        }
    }

    /**
     *  Create Order API data validation codes
    */
    public function get_order_api_error_by_code($code){
        $error['1001']=array('success'=>false,'errors'=>[array('message'=>'No route was found matching the URL and request method','code'=>1001)]);
        $error['1002']=array('success'=>false,'errors'=>[array('message'=>'Not Enough AutoBooster Credits','code'=>1002)]);
        $error['1003']=array('success'=>false,'errors'=>[array('message'=>'No Products found','code'=>1003)]);
        $error['1004']=array('success'=>false,'errors'=>[array('message'=>'No user exist','code'=>1004)]);
        $error['1005']=array('success'=>false,'errors'=>[array('message'=>'Address not supported','code'=>1005)]);
        $error['1006']=array('success'=>false,'errors'=>[array('message'=>'Item went out of Stock','code'=>1006)]);
        echo json_encode($error[$code]);die;
    }

}
new BaseNewOrderApi();
?>