<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


if ( ! class_exists( 'SS_Shipping_WC_Method' ) ) :

class SS_Shipping_WC_Method extends WC_Shipping_Method {

	private $shipping_method = array();

	/**
	 * Init and hook in the integration.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id = SS_SHIPPING_METHOD_ID;
		$this->instance_id = absint( $instance_id );
		$this->method_title = __( 'Seven Senders', 'seven-senders-shipping' );
		$this->method_description = __( 'Advanced shipping solution.', 'seven-senders-shipping' );

		$this->init();
	}

	/**
	 * init function.
	 */
	public function init() {
		
		// $this->init_instance_form_fields();
		$this->init_form_fields();

		$this->init_settings();

		// Set title so can be viewed in zone screen
		// $this->title = $this->get_option( 'title' );

		// add_action( 'admin_notices', array( $this, 'environment_check' ) );
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
		// Admin script
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_scripts' ) );
	}

	public function load_admin_scripts( $hook ) {
	    
	    if( 'woocommerce_page_wc-settings' != $hook ) {
			// Only applies to WC Settings panel
			return;
	    }

		$test_con_data = array( 
    					'ajax_url' => admin_url( 'admin-ajax.php' ),
    					'test_con_nonce' => wp_create_nonce( 'ss-test-con' ) 
    				);

		wp_enqueue_script( 'smart-send-test-connection', SS_SHIPPING_PLUGIN_DIR_URL . '/assets/js/ss-shipping-test-connection.js', array('jquery'), SS_SHIPPING_VERSION );
		wp_localize_script( 'smart-send-test-connection', 'ss_test_con_obj', $test_con_data );
	}

	/**
	 * Initialize integration settings form fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$log_path = SS_SHIPPING_WC()->get_log_url();
		$countries = WC()->countries->get_countries();
		$countries = array_merge( array('0' => __( '- select country -', 'pr-shipping-dhl' )  ), $countries );

		$this->form_fields = array(
			'ss_enabled' => array(
				'title'             => __( 'Enable', 'seven-senders-shipping' ),
				'type'              => 'checkbox',
				'label'             => __( 'Enable Seven Senders', 'seven-senders-shipping' ),
				'default'           => 'yes',
				'description'       => __( 'Enable the seven senders method to creat orders and labels.', 'seven-senders-shipping' ),
				'desc_tip'        	=> true
			),
			'api_token'            	=> array(
				'title'           	=> __( 'Access Key', 'seven-senders-shipping' ),
				'type'            	=> 'text',
                'default'           => '',
				'description'     	=> sprintf( __( 'First save the access key before validating with the button below.<br/>Sign up for a Seven Senders account <a href="%s" target="_blank">here</a>.', 'seven-senders-shipping' ), esc_url( 'https://sendwise.sevensenders.com/' ) ),
				'desc_tip'        	=> false
			),
			'api_token_validate' => array(
				'title'             => SS_BUTTON_TEST_CONNECTION,
				'type'              => 'button',
				'custom_attributes' => array(
					'onclick' => "ssTestConnection('#woocommerce_seven_senders_shipping_api_token_validate');",
				),
				'description'       => __( 'To validate the API token, save the settings then click the button.', 'seven-senders-shipping' ),
				'desc_tip'          => true,
			),
			'ss_debug' => array(
				'title'             => __( 'Debug Log', 'seven-senders-shipping' ),
				'type'              => 'checkbox',
				'label'             => __( 'Enable logging', 'seven-senders-shipping' ),
				'default'           => 'no',
				'description'       => sprintf( __( 'A log file containing the communication to the Seven Senders server will be maintained if this option is checked. This can be used in case of technical issues and can be found %shere%s.', 'seven-senders-shipping' ), '<a href="' . $log_path . '" target = "_blank">', '</a>' )
			),
			'ss_shipper'           => array(
				'title'           => __( 'Shipper Address', 'pr-shipping-dhl' ),
				'type'            => 'title',
				'description'     => __( 'Enter Shipper Address below.', 'pr-shipping-dhl' ),
			),
			'ss_shipper_first_name' => array(
				'title'             => __( 'First Name', 'pr-shipping-dhl' ),
				'type'              => 'text',
				'description'       => __( 'Enter Shipper First Name.', 'pr-shipping-dhl' ),
				'desc_tip'          => true,
				'default'           => ''
			),
			'ss_shipper_last_name' => array(
				'title'             => __( 'Last Name', 'pr-shipping-dhl' ),
				'type'              => 'text',
				'description'       => __( 'Enter Shipper Last Name.', 'pr-shipping-dhl' ),
				'desc_tip'          => true,
				'default'           => ''
			),
			'ss_shipper_company_name' => array(
				'title'             => __( 'Company', 'pr-shipping-dhl' ),
				'type'              => 'text',
				'description'       => __( 'Enter Shipper Company.', 'pr-shipping-dhl' ),
				'desc_tip'          => true,
				'default'           => ''
			),
			'ss_shipper_street' => array(
				'title'             => __( 'Street Address', 'pr-shipping-dhl' ),
				'type'              => 'text',
				'description'       => __( 'Enter Shipper Street Address.', 'pr-shipping-dhl' ),
				'desc_tip'          => true,
				'default'           => ''
			),
			'ss_shipper_house_no' => array(
				'title'             => __( 'Street Address Number', 'pr-shipping-dhl' ),
				'type'              => 'text',
				'description'       => __( 'Enter Shipper Street Address Number.', 'pr-shipping-dhl' ),
				'desc_tip'          => true,
				'default'           => ''
			),
			'ss_shipper_city' => array(
				'title'             => __( 'City', 'pr-shipping-dhl' ),
				'type'              => 'text',
				'description'       => __( 'Enter Shipper City.', 'pr-shipping-dhl' ),
				'desc_tip'          => true,
				'default'           => ''
			),
			'ss_shipper_country' => array(
				'title'             => __( 'Country', 'pr-shipping-dhl' ),
				'type'              => 'select',
				'description'       => __( 'Enter Shipper Country.', 'pr-shipping-dhl' ),
				'desc_tip'          => true,
				'default'           => '',
				'options'           => $countries
			),
			'ss_shipper_zip' => array(
				'title'             => __( 'Postcode', 'pr-shipping-dhl' ),
				'type'              => 'text',
				'description'       => __( 'Enter Shipper Postcode.', 'pr-shipping-dhl' ),
				'desc_tip'          => true,
				'default'           => ''
			),
			'ss_shipper_phone' => array(
				'title'             => __( 'Phone Number', 'pr-shipping-dhl' ),
				'type'              => 'text',
				'description'       => __( 'Enter Phone Number.', 'pr-shipping-dhl' ),
				'desc_tip'          => true,
				'default'           => ''
			),
			'ss_shipper_email' => array(
				'title'             => __( 'Email', 'pr-shipping-dhl' ),
				'type'              => 'text',
				'description'       => __( 'Enter Email.', 'pr-shipping-dhl' ),
				'desc_tip'          => true,
				'default'           => ''
			),
		);
	}

	/**
	 * Generate Button HTML.
	 *
	 * @access public
	 * @param mixed $key
	 * @param mixed $data
	 * @since 1.0.0
	 * @return string
	 */
	public function generate_button_html( $key, $data ) {
		$field    = $this->plugin_id . $this->id . '_' . $key;
		$defaults = array(
			'class'             => 'button-secondary',
			'css'               => '',
			'custom_attributes' => array(),
			'desc_tip'          => false,
			'description'       => '',
			'title'             => '',
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
				<?php echo $this->get_tooltip_html( $data ); ?>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
					<button class="<?php echo esc_attr( $data['class'] ); ?>" type="button" name="<?php echo esc_attr( $field ); ?>" id="<?php echo esc_attr( $field ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" <?php echo $this->get_custom_attribute_html( $data ); ?>><?php echo wp_kses_post( $data['title'] ); ?></button>
					<?php echo $this->get_description_html( $data ); ?>
				</fieldset>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Processes and saves options.
	 * If there is an error thrown, will continue to save and validate fields, but will leave the erroring field out.
	 */
	public function process_admin_options() {
		
		// Refresh token if settings saved, in case mode has changed
		try {			
			SS_SHIPPING_WC()->ss_refresh_token();
		} catch (Exception $e) {

			// echo $this->get_message( __('Could not reset connection: ', 'pr-shipping-dhl') . $e->getMessage() );
			// Display nothing since the refresh was not executed
			// throw $e;
		}

		return parent::process_admin_options();
	}
}

endif;
