<?php
/**
 * Plugin Name: Seven Senders Shipping for WooCommerce
 * Plugin URI: https://github.com/
 * Description: Seven Senders Shipping for WooCommerce
 * Author: Seven Senders
 * Author URI: http://www.sevensenders.com
 * Version: 1.0.0
 * WC requires at least: 3.0
 * WC tested up to: 3.4
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Installation functions
 *
 * Create temporary folder and files. Seven Senders labels will be stored here as required
 *
 */
 function create_seven_senders_label_folder() {
	// error_log('create_ss_label_folder');
    // Install files and folders for uploading files and prevent hotlinking
    $upload_dir =  wp_upload_dir();

    // error_log(print_r($_SERVER,true));
    $files = array(
        array(
            'base'      => $upload_dir['basedir'] . '/woocommerce_seven_senders_label',
            'file'      => '.htaccess',
            'content'   => 'deny from all'
        ),
        array(
            'base'      => $upload_dir['basedir'] . '/woocommerce_seven_senders_label',
            'file'      => 'index.html',
            'content'   => ''
        )
    );

    foreach ( $files as $file ) {

        if ( wp_mkdir_p( $file['base'] ) && ! file_exists( trailingslashit( $file['base'] ) . $file['file'] ) ) {

            if ( $file_handle = @fopen( trailingslashit( $file['base'] ) . $file['file'], 'w' ) ) {
                fwrite( $file_handle, $file['content'] );
                fclose( $file_handle );
            }

        }

    }
}

register_activation_hook( __FILE__, 'create_seven_senders_label_folder' );


if ( ! class_exists( 'SS_Shipping_WC' ) ) :

class SS_Shipping_WC {

	private $version = "1.0.0";

	/**
	 * Instance to call certain functions globally within the plugin
	 *
	 * @var SS_Shipping_WC
	 */
	protected static $_instance = null;
	
	/**
	 * Seven Senders Shipping Order for label and tracking.
	 *
	 * @var SS_Shipping_WC_Order
	 */
	public $ss_shipping_wc_order = null;/**
	 
	 * Seven Senders Shipping Product
	 *
	 * @var SS_Shipping_WC_Order
	 */
	public $ss_shipping_wc_product = null;

	
	/**
	 * Seven Senders Shipping Order for label and tracking.
	 *
	 * @var SS_Shipping_Logger
	 */
	protected $logger = null;


	/**
	 * Seven Senders api handle
	 *
	 * @var object
	 */
	protected $api_handle = null;

	/**
	 * Seven Senders api handle
	 *
	 * @var object
	 */
	protected $shipping_ss_settings = array();

	/**
	* Construct the plugin.
	*/
	public function __construct() {
		$this->define_constants();
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Main Seven Senders Shipping Instance.
	 *
	 * Ensures only one instance is loaded or can be loaded.
	 *
	 * @static
	 * @see SS_Shipping_WC()
	 * @return SS_Shipping_WC - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Define WC Constants.
	 */
	private function define_constants() {
		$upload_dir = wp_upload_dir();

		// Path related defines
		$this->define( 'SS_SHIPPING_PLUGIN_FILE', __FILE__ );
		$this->define( 'SS_SHIPPING_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
		$this->define( 'SS_SHIPPING_PLUGIN_DIR_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
		$this->define( 'SS_SHIPPING_PLUGIN_DIR_URL', untrailingslashit( plugins_url( '/', __FILE__ ) ) );
		$this->define( 'SS_SHIPPING_VERSION', $this->version );
		$this->define( 'SS_SHIPPING_LOG_DIR', $upload_dir['basedir'] . '/wc-logs/' );
		$this->define( 'SS_SHIPPING_METHOD_ID', 'seven_senders_shipping' );
		$this->define( 'SS_BUTTON_TEST_CONNECTION', __('Validate API Token', 'seven-senders-shipping' ) );
	}
	
	/**
	 * Include required core files used in admin and on the frontend.
	 */
	public function includes() {
		// Auto loader class
		include_once( 'includes/class-ss-shipping-autoloader.php' );
		// Global Seven Senders API functions
		include_once( 'includes/seven-senders-api/ss-api-functions.php' );
	}

	public function init_hooks() {
		add_action( 'init', array( $this, 'init' ), 0 );
		add_action( 'init', array( $this, 'load_textdomain' ) );

		add_filter( 'plugin_action_links_' . SS_SHIPPING_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
		// add_filter( 'plugin_row_meta', array( $this, 'ss_shipping_plugin_row_meta'), 10, 2 );
		
		add_action( 'admin_enqueue_scripts', array( $this, 'ss_shipping_theme_enqueue_styles') );		
		add_filter( 'woocommerce_shipping_methods', array( $this, 'add_shipping_method' ) );

		// Test connection
        add_action( 'wp_ajax_ss_test_connection', array( $this, 'ss_test_connection_callback' ) );


        add_action( 'init', array( $this, 'add_download_label_endpoint' ) );
        add_action( 'parse_query', array( $this, 'process_download_label' ) );
	}

	/**
	 * Creates a custom endpoint to download the label
	 */
	public function add_download_label_endpoint() {
		add_rewrite_endpoint( 'download_label', EP_ROOT );
	}

	/**
	 * Processes the download label request
	 *
	 * @return void
	 */
	public function process_download_label() {
		// Ensure anyone downloading a file can edit orders
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}

	    global $wp_query;

	    // If we fail to add the "download_label" then we bail, otherwise, we
	    // will continue with the process below.
	    if ( ! isset( $wp_query->query_vars['download_label'] ) ) return;

	    // Pull the Order ID from the "download_label" endpoint passed as parameter
	    $order_id = $wp_query->query_vars['download_label'];

	    // Get the shipping order instance
	    $ss_shipping_wc_order = $this->get_ss_shipping_wc_order();

	    // Get the path of the generated label that is associated with the current order
	    $label_path = $ss_shipping_wc_order->get_ss_shipping_label( $order_id );
	    if ( ! empty( $label_path ) ) {
	    	$filename = basename( $label_path );

	    	if ( ! empty( $filename ) ) {
	    		header( 'Content-Description: File Transfer' );
			    header( 'Content-Type: application/octet-stream' );
			    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
			    header( 'Expires: 0' );
			    header( 'Cache-Control: must-revalidate' );
			    header( 'Pragma: public' );
			    header( 'Content-Length: ' . filesize( $label_path ) );
			    readfile( $label_path );
	    	}
	    }

	    exit;
	}

	/**
	* Initialize the plugin.
	*/
	public function init() {
		
		// Checks if WooCommerce 2.6 is installed.
		if ( defined( 'WOOCOMMERCE_VERSION' ) && version_compare( WOOCOMMERCE_VERSION, '2.6', '>=' ) ) {

			$this->shipping_ss_settings = $this->get_ss_shipping_settings();

			// Display order and product fields if Seven Senders enabled
			if ( isset($this->shipping_ss_settings['ss_enabled']) && $this->shipping_ss_settings['ss_enabled'] == 'yes') {
				
				$this->ss_shipping_wc_order = new SS_Shipping_WC_Order();
				// $this->ss_shipping_wc_product = new SS_Shipping_WC_Product();
			}

		} else {
			// Throw an admin error informing the user this plugin needs WooCommerce to function
			add_action( 'admin_notices', array( $this, 'notice_wc_required' ) );
		}

	}

	/**
	 * Localisation
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'seven-senders-shipping', false, dirname( plugin_basename(__FILE__) ) . '/lang/' );
	}

	/**
	 * Load Admin CSS 
	 */
	public function ss_shipping_theme_enqueue_styles() {
		wp_enqueue_style( 'ss-shipping-admin-css', SS_SHIPPING_PLUGIN_DIR_URL . '/assets/css/ss-shipping-admin.css' );
	}

	/**
	 * Define constant if not already set.
	 *
	 * @param  string $name
	 * @param  string|bool $value
	 */
	public function define( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}


	/**
	 * Show action links on the plugin screen.
	 *
	 * @param	mixed $links Plugin Action links
	 * @return	array
	 */
	public static function plugin_action_links( $links ) {
		$action_links = array(
			'settings' => '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=shipping&section=seven_senders_shipping' ) . '" aria-label="' . esc_attr__( 'View WooCommerce settings', 'seven-senders-shipping' ) . '">' . esc_html__( 'Settings', 'seven-senders-shipping' ) . '</a>',
		);

		return array_merge( $action_links, $links );
	}

	/**
	 * Show row meta on the plugin screen.
	 *
	 * @param	mixed $links Plugin Row Meta
	 * @param	mixed $file  Plugin Base file
	 * @return	array
	 */
	function ss_shipping_plugin_row_meta( $links, $file ) {

		if ( SS_SHIPPING_PLUGIN_BASENAME == $file ) {
			$row_meta = array(
				'installation'	=> '<a href="' . esc_url( apply_filters( 'smartsend_logistics_installation_url', 'https://smartsend.io/woocommerce/installation/' ) ) . '" title="' . esc_attr( __( 'Installation guide','seven-senders-shipping' ) ) . '" target="_blank">' . __( 'Installation guide','seven-senders-shipping' ) . '</a>',
				'configuration'	=> '<a href="' . esc_url( apply_filters( 'smartsend_logistics_configuration_url', 'https://smartsend.io/woocommerce/configuration/' ) ) . '" title="' . esc_attr( __( 'Configuration guide','seven-senders-shipping' ) ) . '" target="_blank">' . __( 'Configuration guide','seven-senders-shipping' ) . '</a>',
				'support'		=> '<a href="' . esc_url( apply_filters( 'smartsend_logistics_support_url', 'https://smartsend.io/support/' ) ) . '" title="' . esc_attr( __( 'Support','seven-senders-shipping' ) ) . '" target="_blank">' . __( 'Support','seven-senders-shipping' ) . '</a>',
			);
			
			return array_merge( $links, $row_meta );
		}

		return (array) $links;
	}
	
	/**
	 * Add a new integration to WooCommerce.
	 */
	public function add_shipping_method( $shipping_method ) {
		$ss_shipping_shipping_method = 'SS_Shipping_WC_Method';
		$shipping_method['seven_senders_shipping'] = $ss_shipping_shipping_method;

		return $shipping_method;
	}

	/**
	 * Admin error notifying user that WC is required
	 */
	public function notice_wc_required() {
	?>
		<div class="error">
			<p><?php _e( 'Seven Senders Shipping requires WooCommerce 2.6 and above to be installed and activated!', 'seven-senders-shipping' ); ?></p>
		</div>
	<?php
	}

	/**
	 * Get Seven Senders Shipping settings
	 */
	public function get_ss_shipping_settings( ) {
		return get_option('woocommerce_' . SS_SHIPPING_METHOD_ID . '_settings');
	}

	/**
	 * Set Seven Senders Shipping authentication response
	 */
	public function set_ss_shipping_auth( $response ) {
		return update_option( 'seven_senders_auth', $response );
	}

	/**
	 * Get Seven Senders Shipping settings
	 */
	public function get_ss_shipping_auth() {
		return get_option('seven_senders_auth');
	}

	/**
	 * Log debug message
	 */
	public function log_msg( $msg )	{
		// $this->shipping_ss_settings = $this->get_ss_shipping_settings();
		$ss_debug = isset( $this->shipping_ss_settings['ss_debug'] ) ? $this->shipping_ss_settings['ss_debug'] : 'yes';
			
		if( ! $this->logger ) {
			$this->logger = new SS_Shipping_Logger( $ss_debug );
		}

		$this->logger->write( $msg );		
	}

	/**
	 * Get debug log file URL
	 */
	public function get_log_url( )	{
      	// $this->shipping_ss_settings = $this->get_ss_shipping_settings();
		$ss_debug = isset( $this->shipping_ss_settings['ss_debug'] ) ? $this->shipping_ss_settings['ss_debug'] : 'yes';
		
		if( ! $this->logger ) {
			$this->logger = new SS_Shipping_Logger( $ss_debug );
		}
		
		return $this->logger->get_log_url( );		
	}

	/**
	 * Get Smart Shipping Order Object
	 */
	public function get_ss_shipping_wc_order() {
		return $this->ss_shipping_wc_order;
	}

	/**
	 * Refresh token
	 */
	public function ss_refresh_token() {
		$this->shipping_ss_settings = $this->get_ss_shipping_settings();

		if ( empty( $this->shipping_ss_settings['api_token'] ) ) {
			throw new Exception( __('Access Key not saved in settings.', 'seven-senders-shipping' ) );
		}

		try {
			
			$response = ss_get_token_authorization( $this->shipping_ss_settings['api_token'] );

			$this->set_ss_shipping_auth( $response );
		}  catch (Exception $e) {
			throw $e;
		}
	}

	/**
	 * Get token
	 */
	public function ss_get_token() {
		$ss_auth = $this->get_ss_shipping_auth();
		return isset( $ss_auth->token ) ? $ss_auth->token : false;
	}

	/**
	 * Is Mode A
	 */
	public function is_ss_mode_a() {
		$ss_auth = $this->get_ss_shipping_auth();
		
		if ( ($ss_auth->allowed_to_track_all_shipments == false ) && 
			 ( $ss_auth->allowed_to_send_seven_senders_shipments == true ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Is Mode B
	 */
	public function is_ss_mode_b() {
		$ss_auth = $this->get_ss_shipping_auth();
		
		if ( ($ss_auth->allowed_to_track_all_shipments == true ) && 
			 ( $ss_auth->allowed_to_send_seven_senders_shipments == false ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Is Mode C
	 */
	public function is_ss_mode_c() {
		$ss_auth = $this->get_ss_shipping_auth();
		
		if ( ($ss_auth->allowed_to_track_all_shipments == true ) && 
			 ( $ss_auth->allowed_to_send_seven_senders_shipments == true ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Test connection AJAX call
	 */
	public function ss_test_connection_callback() {
		check_ajax_referer( 'ss-test-con', 'test_con_nonce' );

		// $this->shipping_ss_settings = $this->get_ss_shipping_settings();

		try {
			$this->ss_refresh_token();

			$connection_msg = __(' API Token verified: Connected to Seven Senders.', 'seven-senders-shipping');
			$error = 0;
		} catch (Exception $e) {
			$connection_msg = __(' API Token validation failed: Make sure to save the settings before testing the connection.', 'seven-senders-shipping');
			$error = 1;
		}

		$this->log_msg( $connection_msg );

		wp_send_json( array( 
			'message' 			=> $connection_msg,
			'error' 			=> $error,
			'button_txt'		=> SS_BUTTON_TEST_CONNECTION
			) );

		wp_die();
	}

    public function ss_label_folder_check() {
        $upload_dir =  wp_upload_dir();
        if ( !file_exists( $upload_dir['basedir'] . '/woocommerce_seven_senders_label/.htaccess' ) ) {
            create_seven_senders_label_folder();
        }
    }

    public function get_ss_label_folder_dir() {
        $upload_dir =  wp_upload_dir();
        if ( file_exists( $upload_dir['basedir'] . '/woocommerce_seven_senders_label/.htaccess' ) ) {
            return $upload_dir['basedir'] . '/woocommerce_seven_senders_label/';
        }
        return '';
    }

    public function get_ss_label_folder_url() {
        $upload_dir =  wp_upload_dir();
        if ( file_exists( $upload_dir['basedir'] . '/woocommerce_seven_senders_label/.htaccess' ) ) {
            return $upload_dir['baseurl'] . '/woocommerce_seven_senders_label/';
        }
        return '';
    }

    public function get_base_country() {
		$country_code = wc_get_base_location();
		return $country_code['country'];
	}
}

endif;

function SS_SHIPPING_WC() {
	return SS_Shipping_WC::instance();
}

$SS_Shipping_WC = SS_SHIPPING_WC();