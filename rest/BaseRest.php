<?php
/**
 * Base Rest class.
 *
 * The Bases Rest Class and  may extend by chlid class to get the comman functionlity .
 *
 * @class    BaseRest
 * @category Class
 * @author   Codingkart
 */  
class BasesdfsdfsdfsdfRest extends WC_REST_Products_Controller
{
     /**
     * Constructor for the BaseRest class
     *
     * Sets up all the appropriate hooks and actions
     * 
     */
    public function __construct() {
        // change text on withdraw methods
		add_filter( 'woocommerce_rest_check_permissions', array( $this,  'codingkart_woocommerce_rest_check_permissions' ) , 90, 4);

        // Update product API response.
        add_filter( 'woocommerce_rest_prepare_product_object', array($this,'codingkart_update_products_api_response'), 10, 3 );     
	}

	
	/**
     * change text on withdraw methods
     */
    public function codingkart_woocommerce_rest_check_permissions( $permission, $context, $object_id, $post_type ){
    	return true;
	}

    /**
     *  call API
     */
    public function codingkart_rest_callAPI($method, $url, $data){
       $curl = curl_init();
    
       switch ($method){
          case "POST":
             curl_setopt($curl, CURLOPT_POST, 1);
             if ($data)
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
             break;
          case "PUT":
             curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
             if ($data)
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);                              
             break;
          default:
             if ($data)
                $url = sprintf("%s?%s", $url, http_build_query($data));
       }
    
       // OPTIONS:
       curl_setopt($curl, CURLOPT_URL, $url);
       curl_setopt($curl, CURLOPT_HTTPHEADER, array(
          'APIKEY: 111111111111111111111',
          'Content-Type: application/json',
       ));
       curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
       curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    
       // EXECUTE:
       $result = curl_exec($curl);
       if(!$result){die("Connection Failure");}
       curl_close($curl);
       return $result;
    }

    /**
     * get the api reponse
     */
    public function codingkart_woocommerce_get_api_response($url, $id){
        $data = file_get_contents($url.$id);
        return $data;
    }

    /**
     * Get the images for a product or product variation.
     *
     * @param WC_Product|WC_Product_Variation $product Product instance.
     * @return array
     */
    public function get_images( $product ) {
        $images         = array();
        $attachment_ids = array();

        // Add featured image.
        if ( has_post_thumbnail( $product->get_id() ) ) {
            $attachment_ids[] = $product->get_image_id();
        }

        // Add gallery images.
        $attachment_ids = array_merge( $attachment_ids, $product->get_gallery_image_ids() );

        // Build image data.
        $counter = 1;
        foreach ( $attachment_ids as $position => $attachment_id ) {
            $attachment_post = get_post( $attachment_id );
            if ( is_null( $attachment_post ) ) {
                continue;
            }

            $attachment = wp_get_attachment_image_src( $attachment_id, 'full' );
            if ( ! is_array( $attachment ) ) {
                continue;
            }

            if ($counter == 1) {
                //echo "True";
                $pos = "True";
            }else{
                //echo "False";
                $pos = "False";
            }

            $images[] = array(
                //'id'                => (int) $attachment_id,
                //'date_created'      => wc_rest_prepare_date_response( $attachment_post->post_date, false ),
                //'date_created_gmt'  => wc_rest_prepare_date_response( strtotime( $attachment_post->post_date_gmt ) ),
                //'date_modified'     => wc_rest_prepare_date_response( $attachment_post->post_modified, false ),
                //'date_modified_gmt' => wc_rest_prepare_date_response( strtotime( $attachment_post->post_modified_gmt ) ),
                'image_data'          => current( $attachment ),
                //'name'              => get_the_title( $attachment_id ),
                //'alt'               => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
                'is_main'             => $pos,
            );

            $counter++;
        }

        // Set a placeholder image if the product has no images set.
        if ( empty( $images ) ) {
            $images[] = array(
                //'id'                => (int) $attachment_id,
                //'date_created'      => wc_rest_prepare_date_response( $attachment_post->post_date, false ),
                //'date_created_gmt'  => wc_rest_prepare_date_response( strtotime( $attachment_post->post_date_gmt ) ),
                //'date_modified'     => wc_rest_prepare_date_response( $attachment_post->post_modified, false ),
                //'date_modified_gmt' => wc_rest_prepare_date_response( strtotime( $attachment_post->post_modified_gmt ) ),
                'image_data'          => current( $attachment ),
                //'name'              => get_the_title( $attachment_id ),
                //'alt'               => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
                'is_main'             => 'False',
            );
        }

        return $images;
    }

    /**
     * Update product API response.
     *
     * @param Object $response The response object.
     * @param $post
     * @param $request
     * @return Object
     */
    public function codingkart_update_products_api_response( $response, $product, $request ) {
        
        $author_id = get_post_field( 'post_author', $response->data['id'] );
        $store = dokan()->vendor->get( $author_id );

        // custom fields
        $brand         = get_post_meta($product->get_id(), '_product_brand', true);
        $manufacture   = get_post_meta($product->get_id(), '_product_manufacture', true);
        $model         = get_post_meta($product->get_id(), '_product_model', true);

        if ($brand == '') {
          $brand = 'testbrand';
        }else{
          $brand;
        }

        $data = array(
            'buy_site_id'           => 18,
			      'source_price'          => $product->get_price( $context ), 
			      'buy_site_item_id'      => $product->get_id(),
            'title'                 => $product->get_name( $context ),
            'currency'              => 'USD',
            'shipping_currency'     => 'USD',
            'shipping_price'        => 0,
            'slug'                  => $product->get_slug( $context ),
            'url'                   => $product->get_permalink(),
            'date_created'          => wc_rest_prepare_date_response( $product->get_date_created( $context ), false ),
            'date_created_gmt'      => wc_rest_prepare_date_response( $product->get_date_created( $context ) ),
            'date_modified'         => wc_rest_prepare_date_response( $product->get_date_modified( $context ), false ),
            'date_modified_gmt'     => wc_rest_prepare_date_response( $product->get_date_modified( $context ) ),
            'type'                  => $product->get_type(),
            'status'                => $product->get_status( $context ),
            'featured'              => $product->is_featured(),
            'catalog_visibility'    => $product->get_catalog_visibility( $context ),
            'description'           => 'view' === $context ? wpautop( do_shortcode( $product->get_description() ) ) : $product->get_description( $context ),
            'short_description'     => 'view' === $context ? apply_filters( 'woocommerce_short_description', $product->get_short_description() ) : $product->get_short_description( $context ),
            'sku'                   => $product->get_sku( $context ),
            'price'                 => $product->get_price( $context ),
            'regular_price'         => $product->get_regular_price( $context ),
            'sale_price'            => $product->get_sale_price( $context ) ? $product->get_sale_price( $context ) : '',
            'date_on_sale_from'     => wc_rest_prepare_date_response( $product->get_date_on_sale_from( $context ), false ),
            'date_on_sale_from_gmt' => wc_rest_prepare_date_response( $product->get_date_on_sale_from( $context ) ),
            'date_on_sale_to'       => wc_rest_prepare_date_response( $product->get_date_on_sale_to( $context ), false ),
            'date_on_sale_to_gmt'   => wc_rest_prepare_date_response( $product->get_date_on_sale_to( $context ) ),
            'price_html'            => $product->get_price_html(),
            'on_sale'               => $product->is_on_sale( $context ),
            'purchasable'           => $product->is_purchasable(),
            'total_sales'           => $product->get_total_sales( $context ),
            'virtual'               => $product->is_virtual(),
            'downloadable'          => $product->is_downloadable(),
            'downloads'             => $this->get_downloads( $product ),
            'download_limit'        => $product->get_download_limit( $context ),
            'download_expiry'       => $product->get_download_expiry( $context ),
            'external_url'          => $product->is_type( 'external' ) ? $product->get_product_url( $context ) : '',
            'button_text'           => $product->is_type( 'external' ) ? $product->get_button_text( $context ) : '',
            'tax_status'            => $product->get_tax_status( $context ),
            'tax_class'             => $product->get_tax_class( $context ),
            'manage_stock'          => $product->managing_stock(),
            'stock_quantity'        => $product->get_stock_quantity( $context ),
            'in_stock'              => $product->is_in_stock(),
            'backorders'            => $product->get_backorders( $context ),
            'backorders_allowed'    => $product->backorders_allowed(),
            'backordered'           => $product->is_on_backorder(),
            'sold_individually'     => $product->is_sold_individually(),
            //'weight'                => $product->get_weight( $context ),
            'dimensions'            => array(
                    'package_dimensions'=>array(
                    'width'  => $product->get_width( $context ),
                    'length' => $product->get_length( $context ),
                    //'weight' => $product->get_weight( $context ),
                    'height' => $product->get_height( $context ),
                ),
            ),
            'shipping_required'     => $product->needs_shipping(),
            'shipping_taxable'      => $product->is_shipping_taxable(),
            'shipping_class'        => $product->get_shipping_class(),
            'shipping_class_id'     => $product->get_shipping_class_id( $context ),
            'reviews_allowed'       => $product->get_reviews_allowed( $context ),
            'average_rating'        => 'view' === $context ? wc_format_decimal( $product->get_average_rating(), 2 ) : $product->get_average_rating( $context ),
            'rating_count'          => $product->get_rating_count(),
            'related_ids'           => array_map( 'absint', array_values( wc_get_related_products( $product->get_id() ) ) ),
            'upsell_ids'            => array_map( 'absint', $product->get_upsell_ids( $context ) ),
            'cross_sell_ids'        => array_map( 'absint', $product->get_cross_sell_ids( $context ) ),
            'parent_id'             => $product->get_parent_id( $context ),
            'purchase_note'         => 'view' === $context ? wpautop( do_shortcode( wp_kses_post( $product->get_purchase_note() ) ) ) : $product->get_purchase_note( $context ),
            'categories'            => $this->get_taxonomy_terms( $product ),
            'booster'               => $this->get_taxonomy_terms( $product, 'tag' ),
            'images'                => $this->get_images( $product ),
            'attributes'            => $this->get_attributes( $product ),
            'default_attributes'    => $this->get_default_attributes( $product ),
            'grouped_products'      => array(),
            'menu_order'            => $product->get_menu_order( $context ),
            'meta_data'             => $product->get_meta_data(),
            //'brand'                 => $brand,
            'brand'                 => 'testbrand',
            'model'                 => $model,
            'manufacturer'          => $manufacture,
            'store'                 => array('id'=> $store->get_id(), 'name'=> $store->get_name(), 'shop_name' => $store->get_shop_name(), 'url' => $store->get_shop_url(), 'address'   => $store->get_address()),
            'item_specifics'        => array( array('Name'=> 'Style', 'Value'=> 'Summer'), array('Name'=> 'Size Type', 'Value'=> "Regular"), array('Name'=> 'Size', 'Value'=> "L"), array('Name'=> "Size (Men's)", 'Value'=> "L"), array('Name'=> 'MPN', 'Value'=> 'Does Not Apply'), array('Name'=> 'Brand', 'Value'=> "testbrand") ),
        );
    
        // add variations array to variable product
        if ($product->get_type() == 'variable') {
            $data['variants'] = $product->get_available_variations();
        }
        
        // Add individual attribute to simple product
        if ($product->get_type() == 'simple') {
            $attributes = $this->get_attributes( $product );
            foreach ($attributes as $key => $value) {
              $attr_value = implode(", ",$value['options']);
              $data[$value['name']] = $attr_value;
            }
        }

        return $data;
    }

    /**
     * Check api url stating or live 
     */
    public function codingkart_get_api_url_stating_or_live(){
        $site_url = site_url();
        if ($site_url == 'https://autodstools.com/') {
            $url = 'https://autodstools.com/';
        }else{
            $url = 'https://stage.autodstools.com/';
        }

        return $url;
    }

}
new BaseRest();
?>