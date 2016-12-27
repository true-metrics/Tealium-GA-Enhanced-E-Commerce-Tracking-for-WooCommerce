<?php

/**
 * Enhanced Ecommerce Data for WooCommerce
 *
 * Allows tracking code to be inserted into store pages.
 *
 * @class 		TRUEMETR_WC_Enhanced_Ecommerce_Google_Analytics
 * @extends		WC_Integration
 * @author		Vitali Korezki <vitali.korezki@true-metrics.com>
 */
class TRUEMETR_WC_Enhanced_Ecommerce_Google_Analytics extends WC_Integration {

	/**
	 * Init and hook in the integration.
	 *
	 * @access public
	 * @return void
	 */
	//set plugin version
	public $plugin_version = '1.0';
	public function __construct() {
		
		 //Set Global Variables
		global $homepage_json_fp,$homepage_json_ATC_link, $homepage_json_rp,$prodpage_json_relProd,$catpage_json,
			   $prodpage_json_ATC_link,$catpage_json_ATC_link;
		
		//define plugin ID       
		$this->id = "truemetr_enhanced_ecommerce_google_analytics";
		$this->method_title = __("(TRUEMETR) Enhanced Ecommerce Google Analytics", "truemetr-enhanced-e-commerce-for-woocommerce-store");
		$this->method_description = __("Enhanced Ecommerce is a new feature of Universal Analytics that generates detailed statistics about the users journey from product page to thank you page on your e-store. <br/><a href='http://www.tatvic.com/blog/enhanced-ecommerce/' target='_blank'>Know more about Enhanced Ecommerce.</a><br/><br/><b>Quick Tip:</b> We recently launched an Advanced Google Analytics Plugin for WooCommerce! The plugin offers tracking of 9 Reports of Enhanced Ecommerce, User ID Tracking, 15+ Custom Dimenensions & Metrics, Content Grouping & much more. <a href='http://bit.ly/1yFqA04' target='_blank'>Learn More</a>", "woocommerce");


		//session for product position count //session_start removed bcoz it gives warning
		$_SESSION['t_npcnt']=0;
		$_SESSION['t_fpcnt']=0;
		// Load the integration form
		$this->init_form_fields();
		//load all the settings
		$this->init_settings();

			// Define user set variables -- Always use short names    
		$this->ga_id = $this->get_option("ga_id");
		$this->ga_Dname = $this->get_option("ga_Dname");
		$this->ga_LC = get_woocommerce_currency(); //Local Currency yuppi! Got from Back end 
		//set local currency variable on all page
		$this->wc_version_compare("tracking_lc=" . json_encode($this->ga_LC) . ";");
		$this->add_tracking_code = $this->get_option("add_tracking_code");
		$this->ga_gCkout = $this->get_option("ga_gCkout") == "yes" ? true : false; //guest checkout
		$this->add_user_tracking = $this->get_option("add_user_tracking") == "yes" ? true : false; //guest checkout
		$this->add_enhanced_ecommerce = $this->get_option("add_enhanced_ecommerce");
		$this->add_advertizing_code = $this->get_option("add_advertizing_code") == "yes" ? true : false;
		$this->add_impressions = $this->get_option("add_impressions");

			   
		 //Save Changes action for admin settings
		// add_action("woocommerce_update_options_integration_" . $this->id, array($this, "process_admin_options"));
		 
		// API Call to LS with e-mail
		// Tracking code
		// add_action("wp_head", array($this, "ee_settings"));
		add_action("woocommerce_thankyou", array($this, "ecommerce_tracking_code"));

		// Enhanced Ecommerce product impression hook
		add_action("wp_footer", array($this, "t_products_impre_clicks"));
		
		add_action("woocommerce_after_shop_loop_item", array($this, "bind_product_metadata")); //for cat, shop, prod(related),search and home page
		add_action("woocommerce_after_single_product", array($this, "product_detail_view"));
		add_action("woocommerce_after_cart", array($this, "remove_cart_tracking"));
		//add_action("woocommerce_before_checkout_billing_form", array($this, "checkout_step_1_tracking"));
		//add_action("woocommerce_after_checkout_billing_form", array($this, "checkout_step_2_tracking"));
		//add_action("woocommerce_after_checkout_billing_form", array($this, "checkout_step_3_tracking"));

		// Event tracking code
		// add_action("woocommerce_after_add_to_cart_button", array($this, "add_to_cart"));
		
		//Enable display feature code checkbox 
		// add_action("admin_footer", array($this, "admin_check_UA_enabled"));

		//add version details in footer
		add_action("wp_footer", array($this, "add_plugin_details"));
		
		//Add Dev ID
		add_action("wp_head", array($this, "add_dev_id"), 1);
		
		 //Advanced Store data Tracking
		add_action("wp_footer", array($this, "tracking_store_meta_data"));
		
		
		add_action( 'tealium_addToDataObject', array($this, "add_data_layer") );
	}
	
	
	/**
	 * Enhanced E-commerce tracking for product detail view
	 *
	 * @access public
	 * @return void
	 */
	public function add_data_layer() {
		global $utagdata;
		
		// var_dump($utagdata);
		// die('test');
		
		switch ($utagdata['pageType']) {
			case 'product':
				$this->add_data_layer_product_detail_view();
				break;
			
			default:
				$this->add_data_layer_default();
				break;
		}
	}
	
	/**
	 * Enhanced E-commerce tracking for product detail view
	 *
	 * @access public
	 * @return void
	 */
	public function add_data_layer_default() {
		global $utagdata;
		
		if (isset($utagdata['product_id'])) {
			// if array already exists
			if (is_array($utagdata['product_id'])) {
				foreach($utagdata['product_id'] as $index => $product_id) {
					$productdata = $this->product_get_data_array($product_id);
					
					foreach($productdata as $udoname => $udovalue) {
						if (!isset($utagdata[$udoname])) {
							$utagdata[$udoname] = array( $udovalue );
						}
						elseif (is_array($utagdata[$udoname])) {
							$utagdata[$udoname][$index] = $udovalue;
						}
					}
				}
			}
			else {
				// TBD single value
			}
		}
	}
	
	
	/**
	 * Enhanced E-commerce tracking for product detail view
	 *
	 * @access public
	 * @return void
	 */
	public function add_data_layer_product_detail_view() {
		global $utagdata;
		global $post;
		
		// $product = wc_get_product( $post->ID );
		
		$productdata = $this->product_get_data_array($post->ID);
		
		foreach($utagdata as $udoname => $udovalue) {
			if (strpos($udoname, 'product') === 0) {
				unset($utagdata[$udoname]);
			}
		}
		
		foreach($productdata as $udoname => $udovalue) {
			if (!isset($utagdata[$udoname])) {
				$utagdata[$udoname] = array( $udovalue );
			}
			elseif (is_array($utagdata[$udoname])) {
				$utagdata[$udoname][] = $udovalue;
			}
		}
	}
	
	function product_get_data_array( $product_id ) {
		$product = wc_get_product( $product_id );
		
		$categorylist = $this->product_get_category_array( $product );
		$categories = join(',', $categorylist);
		
		$productdata = array();
		$productdata['product_id'] = esc_html($product->id);
		$productdata['product_sku'] = $product->get_sku() ? $product->get_sku() : $product->id;
		$productdata['product_name'] = $product->get_title();
		$productdata['product_category'] = esc_js($categorylist[0]);
		$productdata['product_unit_price'] = $product->get_price();
		
		return $productdata;
	}
	
	function product_get_category_array( $_product ) {
		if ( is_array( $_product->variation_data ) && ! empty( $_product->variation_data ) ) {
			$code = "'" . esc_js( woocommerce_get_formatted_variation( $_product->variation_data, true ) ) . "',";
		} else {
			$out = array();
			$categories = get_the_terms( $_product->id, 'product_cat' );
			if ( $categories ) {
				foreach ( $categories as $category ) {
					$out[] = $category->name;
				}
			}
			// $code = "'" . esc_js( join( "/", $out ) ) . "',";
		}
	
		return $out;
	}
	
	/**
	 * Get store meta data for trouble shoot
	 * @access public
	 * @return void
	 */
	function tracking_store_meta_data() {
		//only on home page
		global $woocommerce;
		$tracking_sMetaData = array();

			$tracking_sMetaData = array(
				'woocommerce_version' => $woocommerce->version,
				'wordpress_version' => get_bloginfo('version'),
				'plugin_version' => $this->plugin_version,
				'plugin_configs' => array(
					'add_enhanced_ecommerce' => $this->add_enhanced_ecommerce,
					'add_advertizing_code' => $this->add_advertizing_code,
					'add_user_tracking'=>$this->add_user_tracking,
					'add_tracking_code'=>$this->add_tracking_code,
					'add_impressions' => $this->add_impressions,                  
				)
			);
			$this->wc_version_compare("tracking_smd=" . json_encode($tracking_sMetaData) . ";");        
	}

	 /**
	 * add dev id
	 *
	 * @access public
	 * @return void
	 */
	function add_dev_id() {
		// echo "<script>(window.gaDevIds=window.gaDevIds||[]).push('5CDcaG');</script>";
	}

	/**
	 * display details of plugin
	 *
	 * @access public
	 * @return void
	 */
	function add_plugin_details() {
		echo '<!--Enhanced Ecommerce Google Analytics Plugin for Woocommerce TRUEMETR Plugin Version:'.$this->plugin_version.'-->';
	}

	/**
	 * Check if tracking is disabled
	 *
	 * @access private
	 * @param mixed $type
	 * @return bool
	 */
	private function disable_tracking($type) {
		// if (is_admin() || current_user_can("manage_options") || (!$this->ga_id ) || "no" == $type) {
		if (is_admin() || current_user_can("manage_options") || "no" == $type) {
			return true;
		}
	}

	/**
	 * woocommerce version compare
	 *
	 * @access public
	 * @return void
	 */
	function wc_version_compare($codeSnippet) {
		global $woocommerce;
		if (version_compare($woocommerce->version, "2.1", ">=")) {
			wc_enqueue_js($codeSnippet);
		} else {
			$woocommerce->add_inline_js($codeSnippet);
		}
	}

	/**
	 * Initialise Settings Form Fields
	 *
	 * @access public
	 * @return void
	 */
	function init_form_fields() {
		$this->form_fields = array(
			"ga_id" => array(
				"title" => __("Google Analytics ID", "woocommerce"),
				"description" => __("Enter your Google Analytics ID here. You can login into your Google Analytics account to find your ID. e.g.<code>UA-XXXXX-X</code>", "woocommerce"),
				"type" => "text",
				"placeholder" => "UA-XXXXX-X",
				"desc_tip"	=>  true,
				"default" => get_option("ga_id") // Backwards compat
			),
			"ga_Dname" => array(
				"title" => __("Set Domain Name", "woocommerce"),
				"description" => sprintf(__("Enter your domain name here (Optional)")),
				"type" => "text",
				"placeholder" => "",
				"desc_tip"	=>  true,
				"default" => get_option("ga_Dname") ? get_option("ga_Dname") : "auto"
			),
			"add_tracking_code" => array(
				"title" => __("Tracking code", "woocommerce"),
				"label" => __("Add Universal Analytics Tracking Code (Optional)", "woocommerce"),
				"description" => sprintf(__("This feature adds Universal Analytics Tracking Code to your Store. You don't need to enable this if using a 3rd party analytics plugin.", "woocommerce")),
				"type" => "checkbox",
				"checkboxgroup" => "start",
				"desc_tip"	=>  true,
				"default" => get_option("add_tracking_code") ? get_option("add_tracking_code") : "no"  // Backwards compat
			),
			"add_advertizing_code" => array(
				"label" => __("Add Display Advertising Feature Code (Optional)", "woocommerce"),
				"type" => "checkbox",
				"checkboxgroup" => "",
				"description" => sprintf(__("This feature enables remarketing with Google Analytics & Demographic reports. Adding the code is the first step in a 3 step process. <a href='https://support.google.com/analytics/answer/2819948?hl=en' target='_blank'>Learn More</a><br/>This feature can only be enabled if you have enabled UA Tracking from our Plugin. If not, you can still manually add the display advertising code by following the instructions from this <a href='https://developers.google.com/analytics/devguides/collection/analyticsjs/display-features' target='_blank'>link</a>", "woocommerce")),
				"default" => get_option("add_advertizing_code") ? get_option("add_advertizing_code") : "no"  // Backwards compat
			),
			"add_enhanced_ecommerce" => array(
				"label" => __("Add Enhanced Ecommerce Tracking Code", "woocommerce"),
				"type" => "checkbox",
				"checkboxgroup" => "",
				"desc_tip"	=>  true,
				"description" => sprintf(__("This feature adds Enhanced Ecommerce Tracking Code to your Store", "woocommerce")),
				"default" => get_option("add_enhanced_ecommerce") ? get_option("add_enhanced_ecommerce")  : "no"  // Backwards compat
			),
			"add_user_tracking" => array(
				"label" => __("Add Code to Track the Login Step of Guest Users (Optional)", "woocommerce"),
				"type" => "checkbox",
				"checkboxgroup" => "",
				"desc_tip"	=>  true,
				"description" => sprintf(__("If you have Guest Check out enable, we recommend you to add this code", "woocommerce")),
				"default" => get_option("add_user_tracking") ? get_option("add_user_tracking") : "no"  // Backwards compat
			),
			"add_impressions" => array(
				"title" => __("Impression Threshold", "woocommerce"),
				"description" => sprintf(__("This feature sets Impression threshold for category page. It sends hit after these many numbers of products impressions", "woocommerce")),
				"type" => "number",
				"desc_tip" =>  true,
		"css"=>"width:112px !important;",
		'custom_attributes' => array(
				'min' => "1",
				),
				"default" => get_option("add_impressions") ? get_option("add_impressions") : "6"  // Backwards compat
			),          
		);
	}

	/**
	 * Google Analytics eCommerce tracking
	 *
	 * @access public
	 * @param mixed $order_id
	 * @return void
	 */
	function ecommerce_tracking_code($order_id) {
		global $woocommerce;

		// if ($this->disable_tracking($this->add_enhanced_ecommerce) || current_user_can("manage_options") || get_post_meta($order_id, "_tracked", true) == 1)
		//     return;

		// $tracking_id = $this->ga_id;
		// if (!$tracking_id)
		//     return;

		// Doing eCommerce tracking so unhook standard tracking from the footer
		// remove_action("wp_footer", array($this, "ee_settings"));

		// Get the order and output tracking code
		$order = new WC_Order($order_id);
		//Get Applied Coupon Codes
		$coupons_list = '';
		if ($order->get_used_coupons()) {
			$coupons_count = count($order->get_used_coupons());
			$i = 1;
			foreach ($order->get_used_coupons() as $coupon) {
				$coupons_list .= $coupon;
				if ($i < $coupons_count)
					$coupons_list .= ', ';
				$i++;
			}
		}

		//get domain name if value is set
		if (!empty($this->ga_Dname)) {
			$set_domain_name = esc_js($this->ga_Dname);
		} else {
			$set_domain_name = "auto";
		}

		//add display features
		if ($this->add_advertizing_code) {
			$ga_display_feature_code = 'ga("require", "displayfeatures");';
		} else {
			$ga_display_feature_code = "";
		}

		//add Pageview on order page if user checked Add Standard UA code
		if ($this->add_tracking_code) {
			$ga_pageview = 'ga("send", "pageview");';
		} else {
			$ga_pageview = "";
		}

		/*$code = '(function(i,s,o,g,r,a,m){i["GoogleAnalyticsObject"]=r;i[r]=i[r]||function(){
			(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
			m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
			})(window,document,"script","//www.google-analytics.com/analytics.js","ga");
						
			ga("create", "' . esc_js($tracking_id) . '", "' . $set_domain_name . '");
						' . $ga_display_feature_code . '
			ga("require", "ec", "ec.js");
						' . $ga_pageview . '
						';*/
		// Order items
		if ($order->get_items()) {
			foreach ($order->get_items() as $item) {
				$_product = $order->get_product_from_item($item);

				if (isset($_product->variation_data)) {
				  $categories=esc_js(woocommerce_get_formatted_variation($_product->variation_data, true));
				} else {
					$out = array();
					$categories = get_the_terms($_product->id, "product_cat");
					if ($categories) {
						foreach ($categories as $category) {
							$out[] = $category->name;
						}
					}
					$categories=esc_js(join(",", $out));
				}

				//orderpage Prod json
				$orderpage_prod_Array[get_permalink($_product->id)]=array(
						"product_id" => esc_html($_product->id),
						"product_sku" => esc_js($_product->get_sku() ? $_product->get_sku() : $_product->id),
						"product_name" => esc_js($item["name"]),
						"product_price" => esc_js($order->get_item_total($item)),
						"product_category" => $categories,
						"product_quantity"=>esc_js($item["qty"])
					  );
			}
			//make json for prod meta data on order page
		   $this->wc_version_compare("tracking_oc=" . json_encode($orderpage_prod_Array) . ";");
		}


			//get shipping cost based on version >2.1 get_total_shipping() < get_shipping
			if (version_compare($woocommerce->version, "2.1", ">=")) {
				$tracking_sc = $order->get_total_shipping();
			} else {
				$tracking_sc = $order->get_shipping();
			}
			//orderpage transcation data json
				$orderpage_trans_Array=array(
								"id"=> esc_js($order->get_order_number()),      // Transaction ID. Required
				"affiliation"=> esc_js(get_bloginfo('name')), // Affiliation or store name
				"revenue"=>esc_js($order->get_total()),        // Grand Total
								"tax"=> esc_js($order->get_total_tax()),        // Tax
				"shipping"=> esc_js($tracking_sc),    // Shipping
								"coupon"=>$coupons_list  
					  );
				 //make json for trans data on order page
		   $this->wc_version_compare("tracking_td=" . json_encode($orderpage_trans_Array) . ";");

//          $code.='
//                 //set local currencies
//             ga("set", "&cu", tracking_lc);  
//             for(var t_item in tracking_oc){
//                 ga("ec:addProduct", { 
//                     "id": tracking_oc[t_item].product_sku,
//                     "name": tracking_oc[t_item].product_name, 
//                     "category": tracking_oc[t_item].tracking_c,
//                     "price": tracking_oc[t_item].tracking_p,
//                     "quantity": tracking_oc[t_item].tracking_q,
// 			});
//             }
//             ga("ec:setAction","purchase", {
// 				"id": tracking_td.id,
// 				"affiliation": tracking_td.affiliation,
// 				"revenue": tracking_td.revenue,
//                                 "tax": tracking_td.tax,
// 				"shipping": tracking_td.shipping,
//                                 "coupon": tracking_td.coupon
// 			});
						
//         ga("send", "event", "Enhanced-Ecommerce","load", "order_confirmation", {"nonInteraction": 1});      
//     ';

		//check woocommerce version
		$this->wc_version_compare($code);
		update_post_meta($order_id, "_tracked", 1);
	}

	/**
	 * Enhanced E-commerce tracking for product detail view
	 *
	 * @access public
	 * @return void
	 */
	public function product_detail_view() {

		// if ($this->disable_tracking($this->add_enhanced_ecommerce)) {
		//     return;
		// }

		global $product;
		$category = get_the_terms($product->ID, "product_cat");
		$categories = "";
		if ($category) {
			foreach ($category as $term) {
				$categories.=$term->name . ",";
			}
		}
		//remove last comma(,) if multiple categories are there
		$categories = rtrim($categories, ",");
		//product detail view json
		$prodpage_detail_json = array(
			"product_id" => esc_html($product->id),
			"product_sku" => $product->get_sku() ? $product->get_sku() : $product->id,                   
			"product_name" => $product->get_title(),
			"product_category" => $categories,
			"product_price" => $product->get_price()
		);
		if (empty($prodpage_detail_json)) { //prod page array
			$prodpage_detail_json = array();
		}
		//prod page detail view json
		$this->wc_version_compare("tracking_po=" . json_encode($prodpage_detail_json) . ";");
		
		
		// $code = '
		// ga("require", "ec", "ec.js");    
		// ga("ec:addProduct", {
		//     "id": tracking_po.product_sku,                   // Product details are provided in an impressionFieldObject.
		//     "name": tracking_po.product_name,
		//     "category":tracking_po.tracking_c,
		//   });
		//   ga("ec:setAction", "detail");
		//   ga("send", "event", "Enhanced-Ecommerce", "load","product_impression_pp", {"nonInteraction": 1});
		// ';
		// //check woocommerce version
		// if(is_product()){
		//     $this->wc_version_compare($code);
		// }
	}

	/**
	 * Enhanced E-commerce tracking for product impressions on category pages (hidden fields) , product page (related section)
	 * home page (featured section and recent section)
	 *
	 * @access public
	 * @return void
	 */
	public function bind_product_metadata() {
		global $product;
		$category = get_the_terms($product->ID, "product_cat");
		$categories = "";
		if ($category) {
			foreach ($category as $term) {
				$categories.=$term->name . ",";
			}
		}
		//remove last comma(,) if multiple categories are there
		$categories = rtrim($categories, ",");
		//declare all variable as a global which will used for make json
		global $homepage_json_fp,$homepage_json_ATC_link, $homepage_json_rp,$prodpage_json_relProd,$catpage_json,$prodpage_json_ATC_link,$catpage_json_ATC_link;
		//is home page then make all necessory json
		if (is_home()) {
			if (!is_array($homepage_json_fp) && !is_array($homepage_json_rp) && !is_array($homepage_json_ATC_link)) {
				$homepage_json_fp = array();
				$homepage_json_rp = array();
				$homepage_json_ATC_link=array();                
			}
				// ATC link Array
				$homepage_json_ATC_link[$product->add_to_cart_url()]=array("ATC-link"=>get_permalink($product->id));
			  //check if product is featured product or not  
			if ($product->is_featured()) {
			   //check if product is already exists in homepage featured json    
			   if(!array_key_exists(get_permalink($product->id),$homepage_json_fp)){
				$homepage_json_fp[get_permalink($product->id)] = array(
						"product_id" => esc_html($product->id),
						"product_sku" => esc_html($product->get_sku() ? $product->get_sku() : $product->id),
						"product_name" => esc_html($product->get_title()),
						"product_name" => esc_html($product->get_price()),
						"product_category" => esc_html($categories),
						"tracking_po" => ++$_SESSION['t_fpcnt'],
						"ATC-link"=>$product->add_to_cart_url()
				);
				//else add product in homepage recent product json
			   }else {
					$homepage_json_rp[get_permalink($product->id)] =array(
						"product_id" => esc_html($product->id),
						"product_sku" => esc_html($product->get_sku() ? $product->get_sku() : $product->id),
						"product_name" => esc_html($product->get_title()),
						"product_price" => esc_html($product->get_price()),
						"tracking_po" => ++$_SESSION['t_npcnt'],
						"product_category" => esc_html($categories)
				);
			   }
			} else {
				//else prod add in homepage recent json    
				$homepage_json_rp[get_permalink($product->id)] =array(
						"product_id" => esc_html($product->id),
						"product_sku" => esc_html($product->get_sku() ? $product->get_sku() : $product->id),
						"product_name" => esc_html($product->get_title()),
						"product_price" => esc_html($product->get_price()),
						"tracking_po" => ++$_SESSION['t_npcnt'],
						"product_category" => esc_html($categories)
				);
			}
		}
		//if product page then related product page array
		else if(is_product()){
			if(!is_array($prodpage_json_relProd) && !is_array($prodpage_json_ATC_link)){
				$prodpage_json_relProd = array();
				$prodpage_json_ATC_link = array();
	}
				// ATC link Array
				$prodpage_json_ATC_link[$product->add_to_cart_url()]=array("ATC-link"=>get_permalink($product->id));

			$prodpage_json_relProd[get_permalink($product->id)] = array(
						"product_id" => esc_html($product->id),
						"product_sku" => esc_html($product->get_sku() ? $product->get_sku() : $product->id),
						"product_name" => esc_html($product->get_title()),
						"product_price" => esc_html($product->get_price()),
						"product_category" => esc_html($categories),
						"tracking_po" => ++$_SESSION['t_npcnt'],
				);
		}
		//category page, search page and shop page json
		else if (is_product_category() || is_search() || is_shop()) {
			 if (!is_array($catpage_json) && !is_array($catpage_json_ATC_link)){
				 $catpage_json=array();
				 $catpage_json_ATC_link=array();
			 }
			 //cat page ATC array
			 $catpage_json_ATC_link[$product->add_to_cart_url()]=array("ATC-link"=>get_permalink($product->id));
			 
			 $catpage_json[get_permalink($product->id)] =array(
						"product_id" => esc_html($product->id),
						"product_sku" => esc_html($product->get_sku() ? $product->get_sku() : $product->id),
						"product_name" => esc_html($product->get_title()),
						"product_price" => esc_html($product->get_price()),
						"product_category" => esc_html($categories),
						"tracking_po" => ++$_SESSION['t_npcnt'], 
				);
			}
	}

	/**
	 * Enhanced E-commerce tracking for product impressions,clicks on Home pages
	 *
	 * @access public
	 * @return void
	 */
	function t_products_impre_clicks() {
		// if ($this->disable_tracking($this->add_enhanced_ecommerce)) {
		//     return;
		// }
		//get impression threshold
		$impression_threshold = $this->add_impressions;

		//Product impression on Home Page
		global $homepage_json_fp,$homepage_json_ATC_link, $homepage_json_rp,$prodpage_json_relProd,$catpage_json,$prodpage_json_ATC_link,$catpage_json_ATC_link;
		//home page json for featured products and recent product sections
		//check if php array is empty
		if(empty($homepage_json_ATC_link)){
			$homepage_json_ATC_link=array(); //define empty array so if empty then in json will be []
		}
		if(empty($homepage_json_fp)){
			$homepage_json_fp=array(); //define empty array so if empty then in json will be []
		}
		if(empty($homepage_json_rp)){ //home page recent product array
			$homepage_json_rp=array(); 
		}
		if(empty($prodpage_json_relProd)){ //prod page related section array
			$prodpage_json_relProd=array();
		}
		if(empty($prodpage_json_ATC_link)){
			$prodpage_json_ATC_link=array(); //prod page ATC link json
		}
		if(empty($catpage_json)){ //category page array
			$catpage_json=array();
		}
		if(empty($catpage_json_ATC_link)){ //category page array
			$catpage_json_ATC_link=array();
		}
		//home page json
		$this->wc_version_compare("homepage_json_ATC_link=" . json_encode($homepage_json_ATC_link) . ";");
		$this->wc_version_compare("tracking_fp=" . json_encode($homepage_json_fp) . ";");
		$this->wc_version_compare("tracking_rcp=" . json_encode($homepage_json_rp) . ";");
		//product page json
		$this->wc_version_compare("tracking_rdp=" . json_encode($prodpage_json_relProd) . ";");
		$this->wc_version_compare("prodpage_json_ATC_link=" . json_encode($prodpage_json_ATC_link) . ";");
		//category page json
		$this->wc_version_compare("tracking_pgc=" . json_encode($catpage_json) . ";");
		$this->wc_version_compare("catpage_json_ATC_link=" . json_encode($catpage_json_ATC_link) . ";");
	}

	/**
	 * Enhanced E-commerce tracking for remove from cart
	 *
	 * @access public
	 * @return void
	 */
	public function remove_cart_tracking() {
		// if ($this->disable_tracking($this->add_enhanced_ecommerce)) {
		//     return;
		// }
		global $woocommerce;
		$cartpage_prod_array_main = array();
		//echo "<pre>".print_r($woocommerce->cart->cart_contents,TRUE)."</pre>";
		foreach ($woocommerce->cart->cart_contents as $key => $item) {
			$prod_meta = get_product($item["product_id"]);
			
			$cart_remove_link=html_entity_decode($woocommerce->cart->get_remove_url($key));
					   
			$category = get_the_terms($item["product_id"], "product_cat");
			$categories = "";
			if ($category) {
				foreach ($category as $term) {
					$categories.=$term->name . ",";
				}
			}
			//remove last comma(,) if multiple categories are there
			$categories = rtrim($categories, ",");
			$cartpage_prod_array_main[$cart_remove_link] =array(
					"product_id" => esc_html($prod_meta->id),
					"product_sku" => esc_html($prod_meta->get_sku() ? $prod_meta->get_sku() : $prod_meta->id),
					"product_name" => esc_html($prod_meta->get_title()),
					"product_price" => esc_html($prod_meta->get_price()),
					"product_category" => esc_html($categories),
					"product_quantity"=>$woocommerce->cart->cart_contents[$key]["quantity"]
			);

		}

		//Cart Page item Array to Json
		$this->wc_version_compare("tracking_cc=" . json_encode($cartpage_prod_array_main) . ";");
	}

	/**
	 * Get oredered Items for check out page.
	 *
	 * @access public
	 * @return void
	 */
	public function get_ordered_items() {
		global $woocommerce;
		$code = "";
		//get all items added into the cart
		foreach ($woocommerce->cart->cart_contents as $item) {
			$p = get_product($item["product_id"]);

			$category = get_the_terms($item["product_id"], "product_cat");
			$categories = "";
			if ($category) {
				foreach ($category as $term) {
					$categories.=$term->name . ",";
				}
			}
			//remove last comma(,) if multiple categories are there
			$categories = rtrim($categories, ",");
			 $chkout_json[get_permalink($p->id)] = array(
				"product_id" => esc_html($p->id),
				"product_sku" => esc_js($p->get_sku() ? $p->get_sku() : $p->id),
				"product_name" => esc_js($p->get_title()),
				"product_price" => esc_js($p->get_price()),
				"product_category" => $categories,
				"product_quantity" => esc_js($item["quantity"]),
				"isfeatured"=>$p->is_featured()
			);
		}
		//return $code;
		//make product data json on check out page
		$this->wc_version_compare("checkout_products=" . json_encode($chkout_json) . ";");
	}
   

}

?>