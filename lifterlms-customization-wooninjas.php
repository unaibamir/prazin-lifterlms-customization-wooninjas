<?php
/**
 * Plugin Name: LifterLMS Customization by WooNinjas
 * Description: LifterLMS Customization by WooNinjas
 * Plugin URI: https://wooninjas.com
 * Author: Author
 * Author URI: https://wooninjas.com
 * Version: 1.0.0
 * License: GPL2
 * Text Domain: text-domain
 * Domain Path: domain/path
 */



if ( ! class_exists( 'LifterLMS_Woo_Customization') ) :

class LifterLMS_Woo_Customization {

	/**
	 * Singleton class instance
	 * @var  obj
	 * @since  1.0.0
	 * @version  1.0.0
	 */
	protected static $_instance = null;

	/**
	 * Main Instance of LifterLMS_Woo_Customization
	 * Ensures only one instance of LifterLMS_Woo_Customization is loaded or can be loaded.
	 * @see LLMS_Gateway_PayPal()
	 * @return LifterLMS_Woo_Customization - Main instance
	 * @since  1.0.0
	 * @version  1.0.0
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}


	/**
	 * Constructor
	 * @return   void
	 * @since    1.0.0
	 * @version  1.1.0
	 */
	private function __construct() {

		$this->define_constants();
		$this->require_files();
		$this->hooks();

	}

	/**
	 * Define plugin constants
	 * @return   void
	 * @since    1.0.0
	 * @version  1.1.0
	 */
	private function define_constants() {

		if ( ! defined( 'LLMS_WOO_DISC_PLUGIN_FILE' ) ) {
			define( 'LLMS_WOO_DISC_PLUGIN_FILE', __FILE__ );
		}

		if ( ! defined( 'LLMS_WOO_DISC_PLUGIN_DIR' ) ) {
			define( 'LLMS_WOO_DISC_PLUGIN_DIR', WP_PLUGIN_DIR . '/' . plugin_basename( dirname( __FILE__ ) ) . '/' );
		}

	}

	public function require_files(){

	}

	public function hooks()
	{

		add_action( "init", array( $this, "testing" ));
		add_action( 'init', array( $this, 'init' ), 9999 );
		add_filter( "cron_schedules", [ "LifterLMS_Woo_Customization", "custom_cron_schedule" ] );
		add_filter( "lifterlms_get_settings_pages", array( $this, "add_settings_file" ), 9, 1 );
		add_action( "llms_woo_check_near_expire_memberships", array( $this, "llms_woo_check_near_expire_memberships" ));
		add_action( "llms_email_after_css", array( $this, "addEmailHtmlCss" ) );

		// resubscribe membership based on email link which was sent to customer
		add_action( "init", array( $this, "resubscribe_membership" ) );

		add_filter( "llms_is_user_enrolled", array( $this, 'filter_user_new_order' ), 99, 5 );
	}

	/**
	 * Initialize, require, add hooks & filters
	 * @return  void
	 * @since    1.0.0
	 * @version  1.0.0
	 */
	public function init() {
		
	}

	public function testing() {
		if( !isset($_GET["unaib_testing"]) ) {
			return;
		}

		$orders 				= $this->get_near_expiring_orders();
		
		dd($orders);
		
	}

	public static function custom_cron_schedule( $schedules ) {
		$schedules['every_six_hours'] = array(
            'interval' => 21600, // Every 6 hours
            'display'  => __( 'Every 6 hours' ),
		);
		$schedules['every_three_hours'] = array(
			'interval' => 10800, // Every 3 hours
			'display'  => __('Every 3 hours'),
		);
        return $schedules;
	}

	public function llms_woo_check_near_expire_memberships() {
		
		$auto_upgrade 			= get_option("lifterlms_woo_membership_recurring_discount", "no");
		
		if( empty($auto_upgrade) || $auto_upgrade == "no" ) {
			return;
		}

		$email_content 			= get_option("lifterlms_woo_discount_email_content");
		$email_subject 			= get_option("lifterlms_woo_discount_email_subject");
		$email_heading 			= get_option("lifterlms_woo_discount_email_heading");

		$orders 				= $this->get_near_expiring_orders();

		if( empty($orders) ) {
			llms_log("LLMS - Wooninjas - No orders found.");
			return;
		}

		foreach ($orders as $key => $order_post) {

			$order_id 					=	$order_post->ID;
			$discount_email_sent 		= 	get_post_meta( $order_id, '_llms_discount_email_sent', true );
			
			if( $discount_email_sent == "yes" ) {
				llms_log("LLMS - Wooninjas - Discount email already sent. Order ID: ".$order_id.".");
				continue;
			}

			$order 						= 	new LLMS_Order( $order_id );
			$product_id 				= 	$order->get( 'product_id' );
			$product 					= 	get_post($product_id);
			$plan_id 					= 	$order->get( 'plan_id' );
			$plan 						= 	get_post($plan_id);
			$membership_name			=	$product->post_title . ' ( ' . $plan->post_title . ' ) ';
			$user_id 					=	$order->get( 'user_id' );
			$user 						= 	get_user_by( "ID", $user_id );
			$discount_link 				= 	$this->generate_renewal_discount_link($user_id, $order_id);
			$price 						=	get_option("lifterlms_woo_membership_recurring_discount_price", 79);
			$discount_price 			=	LLMS_Number::format_money( $price );
			$updated_billing_unit 		=	get_option("lifterlms_woo_membership_duration_unit", 12);
			$updated_billing_frequency 	=	get_option("lifterlms_woo_membership_duration_frequency", "month");
			$nice_updated_billing 		= 	$updated_billing_unit . " " . $updated_billing_frequency;

			// initialize LLMS Mailer
			$mailer 					= 	LLMS()->mailer()->get_email( 'llms_woo_discount_email' );

			// mailer add recipient
			$mailer->add_recipient( $user_id );

			// set mailer merge tags
			$mailer->add_merge_data( array(
				"%name%"					=> 	$user->display_name,
				"%username%"				=>	$user->user_login,
				"%useremail%"				=>	$user->user_email,
				"%firstname%"				=>	$user->first_name,
				"%lastname%"				=>	$user->last_name,
				"%displayname%"				=>	$user->display_name,
				"%expiration%"				=>	$order->get_access_expiration_date(),
				"%blogname%"				=>	get_bloginfo('name'),
				"%discount_price%"			=>	$discount_price,
				"%email_discount_link%" 	=>	$discount_link,
				"%membership_name%" 		=>	$membership_name,
				"%membership_duration%" 	=>	$nice_updated_billing,
			));


			$mailer->set_heading( $email_heading );
			$mailer->set_subject( $email_subject );
			$mailer->set_body( $email_content );
			
			if($mailer->send() ) {
				update_post_meta($order_id, "_llms_discount_email_sent", "yes", "");
				update_post_meta($order_id, "_llms_discount_email_sent_date", date_i18n( "Y-m-d H:i:s" ), "");
				llms_log( sprintf( __('LLMS - Wooninjas - Discount mail sent to user email: %s, Order ID: %d', 'lifterlms'), $user->user_email, $order_id) );
			}

		}
	}

	public function get_near_expiring_orders() {

		$before_email_days 		= get_option("lifterlms_woo_membership_before_email_days", 1);

		$min_date 				= date_i18n( 'Y-m-d H:i:s', strtotime( "+" . $before_email_days." days", current_time('timestamp') ) );
		$max_date 				= date_i18n( 'Y-m-d H:i:s', strtotime( "+" . $before_email_days." days 3 hours", current_time('timestamp') ) );
		
		$order_args = array(
			'post_type'   		=> 'llms_order',
			'post_status' 		=> array( 'llms-active', 'llms-completed' ),
			'order'             => 'DESC',
			'orderby'           => 'date',
			'posts_per_page' 	=> -1,
			'meta_query'     	=> array(
				/*array(
					'key'   	=> '_llms_order_type',
					'value' 	=> 'recurring',
				),*/
				array(
					'key'		=> '_llms_date_access_expires',
					'value' 	=> array( $min_date, $max_date),
					'type'		=> 'DATETIME',
					'compare'	=> 'BETWEEN'
				),
				/*array(
					'key'		=> 	'_llms_payment_gateway',
					'value'		=>	'manual',
					'compare'	=>	'!='
				),*/
				array(
					'key'		=>	'_llms_discount_email_sent',
					'compare'	=>	'NOT EXISTS'
				)
			)
		);
		
		$orders_query 			= new WP_Query( $order_args );
		$orders 				= $orders_query->get_posts();

		return $orders;

	}


	public function generate_renewal_discount_link( $user_id, $order_id ) {

        $userDataArray  = [
            'time'				=>	time(),
            'user_id'           => 	$user_id,
            'order_id'     		=> 	$order_id,
            'action'            => 	"renewal_discount",
        ];

        $userDataJSON   = json_encode($userDataArray);
        $pass           = 'WooLifterRenewalDiscount';
        $method         = 'aes-128-cbc';
        $initVector     = "0123456789012345";

        $encrypted      = base64_encode(openssl_encrypt($userDataJSON, $method, $pass, false, $initVector));

        return add_query_arg( "woo_key", $encrypted, home_url( "/" ) );
    }


    public function getDecodeGeneratedData( $key ) {

		$encrypted_key  = $key;
        $encrypted_key  = base64_decode($encrypted_key);
        $pass           = 'WooLifterRenewalDiscount';
        $method         = 'aes-128-cbc';
        $initVector     = "0123456789012345";

        $decrypted      = openssl_decrypt($encrypted_key, $method, $pass, false, $initVector);
        $user_data      = json_decode( $decrypted );

        if( is_array( $user_data ) || is_object($user_data) && !empty( $user_data ) ) {
        	return (array) $user_data;
        } else {
        	return false;
        }
    }

    public function resubscribe_membership() {

    	$auto_upgrade 				= get_option("lifterlms_woo_membership_recurring_discount", "no");
		if( empty($auto_upgrade) || $auto_upgrade == "no" ) { return; }

    	if( !isset($_GET["woo_key"]) ) { return; }
    	if( isset($_GET["woo_key"]) && empty( $_GET["woo_key"] ) ) { return; }
    	$key 			= 	$_GET["woo_key"];

    	// get decoded data from the generated link via email
    	$user_data 	 	= 	$this->getDecodeGeneratedData( $key );
    	if( $user_data && !isset($user_data["user_id"], $user_data["order_id"], $user_data["action"]) ) {
    		return;
    	}

    	extract($user_data);

		//$date_format    			= 	get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
    	$order_c 					= 	new LLMS_Controller_Orders();
    	$order 						= 	new LLMS_Order( $order_id );
    	$order_resubscribed 		= 	$order->get("customer_order_resubscribed");
    	$user_id 					=	$order->get( 'user_id' );
		$user 						= 	get_user_by( "ID", $user_id );
		$current_user_id  			=  	get_current_user_id();

		if( is_user_logged_in() ) {

			// lets see if the current user and the previous order user is same. If not, return with error
			if( $current_user_id != $user_id ) {
				wp_safe_redirect( add_query_arg( "woo_status", "wrong_subscribe", home_url() ));
				exit;
			}
		} else {
			$this->woo_user_auto_login( $user_id, $user );
		}

		$plan_id 					= 	$order->get('plan_id');
		$plan 						= 	new LLMS_Access_Plan( $plan_id );
		$plan_checkout_url 			= 	$plan->get_checkout_url( false );
		$updated_billing_unit 		=	get_option("lifterlms_woo_membership_duration_unit", 12);
		$updated_billing_frequency 	=	get_option("lifterlms_woo_membership_duration_frequency", "month");
		$nice_updated_billing 		= 	$updated_billing_unit . " " . $updated_billing_frequency;
		$nice_updated_date 			= 	date_i18n( "Y-m-d H:i:s", strtotime($nice_updated_billing) );
		$updated_billing_price 		=	get_option("lifterlms_woo_membership_recurring_discount_price", 79);
		$existing_expiry_date 		= 	$order->get_access_expiration_date();
		$order_type 				= 	$order->get('order_type');
		$payment_gateway  			= 	$order->get("payment_gateway");

		if( !empty($existing_expiry_date) && ( $existing_expiry_date != "To be Determined" || $existing_expiry_date != "Lifetime Access" ) ) {
			// calculating different btw dates
			$date_difference 		= date_diff( date_create(date_i18n( 'Y-m-d' )), date_create($existing_expiry_date) )->format("%a");
		} else {
			$date_difference 		= 2;
		}

    	if( !empty($order_resubscribed) && $order_resubscribed == "yes" ) {
    		llms_log( __( 'LLMS WooNinjas - User has already resubscribed. Order ID: '.$order_id.', User ID: '. $user_id, 'lifterlms' ) );
    		wp_safe_redirect( add_query_arg( "woo_status", "already_resubscribe", home_url() ));
			exit;
    	}

    	if( $date_difference > 2 ) {
    		llms_log( __( 'LLMS WooNinjas - Not near expiry. User tried it earlier somehow. Order ID: '.$order_id.', User ID: '. $user_id, 'lifterlms' ) );
    		wp_safe_redirect( add_query_arg( "woo_status", "not_near_expiry", home_url() ));
			exit;
    	}

    	// lets redirect the user to the checkout page with discounted price and duration
    	wp_safe_redirect( add_query_arg( "woo_status", "order_resubscribe", $plan_checkout_url ) );
		exit;
		
		if( $payment_gateway == "manual" ) {
			wp_safe_redirect( add_query_arg( "woo_status", "order_resubscribe", $plan_checkout_url ) );
			exit;
		}
    	//dd($order_resubscribed);
		$order_charged 				= 	false;
		
		// return if user is still logged in
		if( !is_user_logged_in() ) {
			return;
		}

		// Now everything is okay, we can proceed now
		$order->set_status("pending");
		$order->set("total", 				$updated_billing_price			);
		$order->set("sale_price", 			$updated_billing_price			);
		$order->set("sale_value", 			0								);
		$order->set("original_total", 		$updated_billing_price			);
		$order->set("billing_frequency", 	$updated_billing_unit			);
		$order->set("billing_period", 		$updated_billing_frequency		);
		$order->set("billing_length", 		0								);

		// set order/membership expiry date
		$order->set( 'date_access_expires', $nice_updated_date );
		$order->set_date( "access_expires", $nice_updated_date );

		update_post_meta( $order_id, "_llms_date_access_expires", $nice_updated_date, '' );

		if( $order_type == "recurring" ) {
			$order->set_date( 'next_payment', $nice_updated_date );
			$order->maybe_schedule_payment( true );
		}
		
		$order->set( 'customer_order_resubscribed', "yes" );
		
		if( $order_type == "recurring" ) {
			$order_charged 			= $order_c->recurring_charge( $order_id );
		}

		if( $order_type == "single" ) {
			$order_charged 			= $this->recurring_charge_customer_single( $order_id, $order, $user );
		}


		// return if the customer is not charged based on selected payment gateway, it is required.
		if( ! $order_charged ) {
			llms_log( sprintf( __( 'LLMS WooNinjas - Could not charged customer. Order ID: %d, Payment Gateway: %s', 'lifterlms' ), $order_id, $order->get( 'payment_gateway' ) ) );
			return;
		}

		// this is to ensure that student/customer is still enrolled in related membership courses
		llms_enroll_student( $user_id, $order->get( 'product_id' ), 'order_' . $order->get( 'id' ) );
		$order->set_status("active");
		
		$this->send_thank_you_email( $user_id, $order_id );

		wp_safe_redirect( $order->get_view_link() );
		exit;
    }


    public function recurring_charge_customer_single( $order_id, $order, $user ) {
    	$payment_gateway  	= 	$order->get("payment_gateway");

    	if( $payment_gateway == "stripe" ) {
    		return $this->charge_stripe_customer_single( $order_id, $order, $user );
    	}

    	if( $payment_gateway == "paypal" ) {
    		return $this->charge_paypal_customer_single( $order_id, $order, $user );
    	}
    }

    public function charge_stripe_customer_single( $order_id, $order, $user, $type = "stripe" ) {

    	$total    			= $order->get_price( 'total', array(), 'float' );
		$currency 			= $order->get( 'currency' );

		$stripe_gateway 	= new LLMS_Payment_Gateway_Stripe();
		//$customer_id 		= $stripe_gateway->handle_customer( $order->get( 'user_id' ), "" );
		$customer    		= new LLMS_Stripe_Customer( $order->get( 'user_id' ), LLMS()->payment_gateways()->get_gateway_by_id( 'stripe' ) );
		$customer_id 		= $customer->get_customer_id();

		if ( is_wp_error( $customer_id ) ) {
			$this->log( 'LLMS WooNinjas - Stripe `charge_stripe_customer_single()` finished with errors', 'stripe' );
			llms_add_notice( $customer_id->get_error_message(), 'error' );
			return false;
		}

		$intents = new LLMS_Stripe_Intents( $order );

		$intent = $intents->create( 'initial' );

		if ( is_wp_error( $intent ) ) {
			llms_add_notice( $intent->get_error_message(), 'error' );
			return false;
		}

		if ( 'succeeded' === $intent->status ) {

			$intents->complete( $intent, 'recurring' );
			$stripe_gateway->log( $intent, 'LLMS WooNinjas - Stripe `charge_stripe_customer_single()` finished' );
			//$stripe_gateway->complete_transaction( $order );
			return true;

		} elseif ( 'requires_action' === $intent->status ) {

			llms_redirect_and_exit( llms_confirm_payment_url( $order->get( 'order_key' ) ) );

		}
    }


    public function charge_paypal_customer_single( $order_id, $order, $user ) {

    	$paypal_gateway 	= 	LLMS()->payment_gateways()->get_gateway_by_id( 'paypal' );

    	//$this->log( 'PayPal `handle_pending_order()` started', $order, $plan, $person, $coupon );

		/*$validate = $this->validate_transaction( $order, $plan );
		if ( is_wp_error( $validate ) ) {
			return llms_add_notice( $validate->get_error_message(), 'error' );
		}*/

		$req = new LLMS_PayPal_Request( $paypal_gateway );
		$r = $req->set_express_checkout( $order );

		if ( is_wp_error( $r ) ) {

			$paypal_gateway->log( $r, 'PayPal `handle_pending_order()` finished with errors' );

			return llms_add_notice( $r->get_error_message(), 'error' );

		} else {

			$paypal_gateway->log( $r, 'PayPal `handle_pending_order()` finished' );

			do_action( 'lifterlms_handle_pending_order_complete', $order );

			wp_redirect( $r );

			exit();

		}

    }


    public function woo_user_auto_login( $user_id, $user ) {
	    if (!is_user_logged_in()) {

	    	$user_login = $user->user_login;

	        //login
	        wp_set_current_user( $user_id, $user_login );
	        wp_set_auth_cookie( $user_id );
	        do_action( 'wp_login', $user_login, $user );
	    }
	}

	public function add_settings_file( $settings ) {
		$settings[] = include LLMS_WOO_DISC_PLUGIN_DIR . 'includes/class.llms.woo.customization.setting.php';
		return $settings;
	}

	public function addEmailHtmlCss() {
	?>
	td.main-content a[href*="woo_key"] {
		/* These are technically the same, but use both */
		overflow-wrap: break-word;
		word-wrap: break-word;

		-ms-word-break: break-all;
		/* This is the dangerous one in WebKit, as it breaks things wherever */
		word-break: break-all;
		/* Instead use this non-standard one: */
		word-break: break-word;

		/* Adds a hyphen where the word breaks, if supported (No Blink) */
		-ms-hyphens: auto;
		-moz-hyphens: auto;
		-webkit-hyphens: auto;
		hyphens: auto;
	}
	<?php
	}


	public function send_thank_you_email( $user_id, $order_id ) {

		// initialize LLMS Mailer for Thank you note
		$mailer 					= 	LLMS()->mailer()->get_email( 'llms_woo_thankyou_email' );
		$order 						= 	new LLMS_Order( $order_id );
		$product_id 				= 	$order->get( 'product_id' );
		$product 					= 	get_post($product_id);
		$plan_id 					= 	$order->get( 'plan_id' );
		$plan 						= 	get_post($plan_id);
		$membership_name			=	$product->post_title . ' ( ' . $plan->post_title . ' ) ';
		$user 						= 	get_user_by( "ID", $user_id );
		$price 						=	get_option("lifterlms_woo_membership_recurring_discount_price", 79);
		$discount_price 			=	LLMS_Number::format_money( $price );
		$updated_billing_unit 		=	get_option("lifterlms_woo_membership_duration_unit", 12);
		$updated_billing_frequency 	=	get_option("lifterlms_woo_membership_duration_frequency", "month");
		$nice_updated_billing 		= 	$updated_billing_unit . " " . $updated_billing_frequency;
		$email_content 				= 	get_option("woo_membership_thankyou_email_content");
		$email_subject 				= 	get_option("woo_membership_thankyou_email_subject");
		$email_heading 				= 	get_option("woo_membership_thankyou_email_heading");

		// mailer add recipient
		$mailer->add_recipient( $user_id );

		// set mailer merge tags
		$mailer->add_merge_data(
		array(
				"%name%"					=> 	$user->display_name,
				"%username%"				=>	$user->user_login,
				"%useremail%"				=>	$user->user_email,
				"%firstname%"				=>	$user->first_name,
				"%lastname%"				=>	$user->last_name,
				"%displayname%"				=>	$user->display_name,
				"%blogname%"				=>	get_bloginfo('name'),
				"%discount_price%"			=>	$discount_price,
				"%membership_name%" 		=>	$membership_name,
				"%membership_duration%" 	=>	$nice_updated_billing,
			)
		);


		$mailer->set_heading( $email_heading );
		$mailer->set_subject( $email_subject );
		$mailer->set_body( $email_content );

		// once all is set, send the thank you email
		if ($mailer->send()) {
			update_post_meta($order_id, "_llms_discount_thankyou_email_sent", "yes");
			llms_log(sprintf(__('LLMS - Wooninjas - Discount Thank You mail sent to user email: %s, Order ID: %d', 'lifterlms'), $user->user_email, $order_id));
		}

	}


	public function filter_user_new_order( $ret, $student, $product_ids, $relation, $use_cache ) {
		if( is_llms_woo_checkout() ) {
			$ret = false;
		}
		return $ret;
	}
}

endif;


if( !function_exists("dd") ) {
    function dd( $data, $exit_data = true) {
        echo '<pre>'.print_r($data, true).'</pre>';
        if($exit_data == false)
            echo '';
        else
            exit;
    }
}

function LifterLMS_Woo_Init() {
	return LifterLMS_Woo_Customization::instance();
}
//return LifterLMS_Woo_Init();

add_action( 'plugins_loaded', 'LifterLMS_Woo_Init', 9999 );

add_filter( "llms_get_gateway_invoice_prefix", function( $invoice_prefix ){
	return $invoice_prefix . time() . ' - ';
}, 1000);

function is_llms_woo_checkout() {

	// lets check if the checkout page is opened by the renewal notification link
	if( isset($_GET["woo_status"]) && !empty($_GET["woo_status"]) && $_GET["woo_status"] == "order_resubscribe" ) {

		// get current plan and products from the checkout page
		$current_plan_id 	= isset($_GET["plan"]) && !empty($_GET["plan"]) ? $_GET["plan"]  : 0 ;
		$current_product 	= new LLMS_Access_Plan( $current_plan_id );
		$current_product_id = $current_product->get("product_id");

		// only logged in user can go further
		if( is_user_logged_in() ) {

			$user 			= wp_get_current_user();
			$student 		= new LLMS_Student( $user ); // get LLMS Student model
			$student_orders = $student->get_orders(array(
				'statuses'	=> array(
					'llms-completed',
					'llms-active'
				)
			));
			$orders 		= $student_orders["orders"];

			// if there are no previous orders, it seems we are good to go
			if( empty($student_orders["count"]) || $student_orders["count"] < 1 ) {
				return false;
			}

			foreach ( $orders as $order ) {

				$product_id  			= $order->get( 'product_id' );
				$mail_sent 				= $order->get( 'discount_email_sent' );
				$email_sent_time 		= $order->get( 'discount_email_sent_date' );
				$date_difference 		= 2;

				if( !empty($email_sent_time) ) {
					$email_sent_time		= date_i18n( 'Y-m-d', strtotime($email_sent_time) );
					$date_difference 		= date_diff( date_create( date_i18n( 'Y-m-d' ) ), date_create( $email_sent_time ) )->format("%a"); // calculating different btw dates
				}

				// we need to ensure that the customer is renewing from the previous order. AND
				// see if the current product and the previous order product is same or not
				if( !empty($mail_sent) && $mail_sent == "yes" && $current_product_id == $product_id ) {
					return true;
					// if plan is same but the previous order expiry is not near, no discount
					if( $date_difference <= 2 ) {
						//return true;
					}
				}

				return false;
			}
		}
	} else {
		return false;
	}
}

add_filter( "llms_plan_get_price", "llms_plan_get_price", 9999, 5 );
add_filter( "llms_get_access_plan_price_price", "llms_plan_get_price", 9999, 5 );
add_filter( "llms_get_order_total_price", "llms_plan_get_price", 9999, 5 );
function llms_plan_get_price( $ret, $key, $price_args, $format, $model ){

	/*if( isset($_GET["order"]) && !empty($_GET["order"]) && llms_get_order_by_key( $_GET["order"] ) ) {
		
		$ret = get_option("lifterlms_woo_membership_recurring_discount_price", 79);

		if ( 'html' == $format || 'raw' === $format ) {
			$ret = llms_price( $ret, $price_args );
			if ( 'raw' === $format ) {
				$ret = strip_tags( $ret );
			}
		} elseif ( 'float' === $format ) {
			$ret = floatval( number_format( $ret, get_lifterlms_decimals(), '.', '' ) );
		} else {
			$ret = $ret;
		}
	}*/

	if( is_llms_woo_checkout() ) {
		$ret = get_option("lifterlms_woo_membership_recurring_discount_price", 79);
		
		if ( 'html' == $format || 'raw' === $format ) {
			$ret = llms_price( $ret, $price_args );
			if ( 'raw' === $format ) {
				$ret = strip_tags( $ret );
			}
		} elseif ( 'float' === $format ) {
			$ret = floatval( number_format( $ret, get_lifterlms_decimals(), '.', '' ) );
		} else {
			$ret = $ret;
		}
	}

	return $ret;

};

add_filter( "llms_get_access_plan_access_period", function( $value, $model ){
	if( is_llms_woo_checkout() ) {
		$value = get_option( 'lifterlms_woo_membership_duration_frequency', 'month' );
	}
	return $value;
}, 9999, 2);
add_filter( "llms_get_access_plan_period", function( $value, $model ){
	if( is_llms_woo_checkout() ) {
		$value = get_option( 'lifterlms_woo_membership_duration_frequency', 'month' );
	}
	return $value;
}, 9999, 2);

add_filter( "llms_get_access_plan_access_frequency", function( $value, $model ){
	if( is_llms_woo_checkout() ) {
		return 0;
	}
	return $value;
}, 9999, 2);
add_filter( "llms_get_access_plan_frequency", function( $value, $model ){
	if( is_llms_woo_checkout() ) {
		return 0;
	}
	return $value;
}, 9999, 2);

add_filter( "llms_get_access_plan_access_length", function( $value, $model ){
	if( is_llms_woo_checkout() ) {
		$value = get_option( 'lifterlms_woo_membership_duration_unit', 12 );
	}
	return $value;
}, 9999, 2);

add_filter( "llms_get_access_plan_length", function( $value, $model ){
	if( is_llms_woo_checkout() ) {
		$value = get_option( 'lifterlms_woo_membership_duration_unit', 12 );
	}
	return $value;
}, 9999, 2);

add_filter( "llms_get_access_plan_access_expiration", function( $value, $model ){
	if( is_llms_woo_checkout() ) {
		return 'limited-period';
	}
	return $value;
}, 9999, 2);

add_filter( "llms_get_access_plan_access_unit", function( $value, $model ){
	if( is_llms_woo_checkout() ) {
		$value = get_option( 'lifterlms_woo_membership_duration_unit', 12 );
	}
	return $value;
}, 9999, 2);

add_filter( "llms_get_access_plan_length", function( $value, $model ){
	if( is_llms_woo_checkout() ) {
		return 0;
	}
	return $value;
}, 9999, 2);

add_filter( "llms_get_access_plan_order_type", function( $value, $model ){
	if( is_llms_woo_checkout() ) {
		return 'single';
	}
	return $value;
}, 9999, 2);

add_filter( "llms_get_access_plan_on_sale", function( $value, $model ){
	if( is_llms_woo_checkout() ) {
		return false;
	}
	return $value;
}, 9999, 2);

add_filter( "llms_get_product_expiration_details", 'woo_llms_filter_expiration_details', 9999, 2);
function woo_llms_filter_expiration_details( $ret, $plan_model ){

	$expiration = $plan_model->get( 'access_expiration' );
	
	if( isset($_GET["order"]) && !empty($_GET["order"]) && llms_get_order_by_key( $_GET["order"] ) && $expiration == "limited-period" ) {
		/*$billing_unit 		=	get_option("lifterlms_woo_membership_duration_unit", 12);
		$billing_frequency 	=	get_option("lifterlms_woo_membership_duration_frequency", "month");
		$nice_billing 		= 	$billing_unit . " " . $billing_frequency;

		$ret = sprintf( _x( '%1$s of access', 'Access period description', 'lifterlms' ), $nice_billing );*/
	}


	if( is_llms_woo_checkout() && $expiration == "limited-period" ) {		

		$billing_unit 		=	get_option("lifterlms_woo_membership_duration_unit", 12);
		$billing_frequency 	=	get_option("lifterlms_woo_membership_duration_frequency", "month");
		$nice_billing 		= 	$billing_unit . " " . $billing_frequency;

		$ret = sprintf( _x( '%1$s of access', 'Access period description', 'lifterlms' ), $nice_billing );
	}

	return $ret;

};

add_action( "llms_dispatch_notification_processors", "llms_woo_fix_access_date_issue" );
function llms_woo_fix_access_date_issue() {
	// nonce the post
	if ( ! llms_verify_nonce( '_wpnonce', 'confirm_pending_order' ) ) {
		return;
	}

	if ( empty( $_POST['action'] ) || 'confirm_pending_order' !== $_POST['action'] ) {
		return;
	}

	// ensure we have an order key we can locate the order with
	$key = llms_filter_input( INPUT_POST, 'llms_order_key', FILTER_SANITIZE_STRING );
	if ( ! $key ) {
		return llms_add_notice( __( 'Could not locate an order to confirm.', 'lifterlms' ), 'error' );
	}

	// lookup the order & return error if not found
	/*$order = llms_get_order_by_key( $key );
	if ( ! $order || ! $order instanceof LLMS_Order ) {
		return llms_add_notice( __( 'Could not locate an order to confirm.', 'lifterlms' ), 'error' );
	}

	$updated_billing_unit 		=	get_option("lifterlms_woo_membership_duration_unit", 12);
	$updated_billing_frequency 	=	get_option("lifterlms_woo_membership_duration_frequency", "month");
	$nice_updated_billing 		= 	$updated_billing_unit . " " . $updated_billing_frequency;
	$nice_updated_date 			= 	date_i18n( "Y-m-d H:i:s", strtotime($nice_updated_billing) );

	// set order/membership expiry date
	$order->set( 'access_expiration', 'limited-period' );

	$order->set_date( "access_expires", $nice_updated_date );
	$order->set( 'date_access_expires', $nice_updated_date );

	if( $order_type == "recurring" ) {
		$order->set_date( 'next_payment', $nice_updated_date );
		$order->maybe_schedule_payment( true );
	}

	if( $order->get( 'payment_gateway' ) != "manual" ) {


	}*/

}

function llms_woo_activation() {

	add_filter( "cron_schedules", [ "LifterLMS_Woo_Customization", "custom_cron_schedule" ] );

	if ( ! wp_next_scheduled( 'llms_woo_check_near_expire_memberships' ) ) {
        wp_schedule_event( current_time( 'timestamp' ), 'every_three_hours', 'llms_woo_check_near_expire_memberships' );
    }

	do_action( 'llms_woo_plugin_activated' );
}
register_activation_hook(__FILE__, 'llms_woo_activation');