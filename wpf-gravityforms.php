<?php
/*
Plugin Name: wpFortify for Gravity Forms
Plugin URI: http://wordpress.org/plugins/wpf-gravityforms/
Description: wpFortify provides a hosted SSL checkout page for Stripe payments. A free wpFortify account is required for this plugin to work.
Version: 0.1.0
Author: wpFortify
Author URI: https://wpfortify.com
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html

*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'GFForms' ) ) {

    GFForms::include_addon_framework();

    class WPF_GF extends GFAddOn {

        protected $_version = '0.1.0';
        protected $_min_gravityforms_version = '1.8.9';
        protected $_slug = 'wpf-gravityforms';
        protected $_full_path = __FILE__;
        protected $_title = 'wpFortify for Gravity Forms';
        protected $_short_title = 'wpFortify (Stripe)';

		/**
		 * Initialize.
		 */
		public function init(){

			parent::init();
			load_plugin_textdomain( $this->_slug, FALSE, $this->_slug . '/languages' );

		}

		public function init_admin(){

			parent::init_admin();
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );

		}		 
		 
		public function init_frontend(){

			parent::init_frontend();
			add_filter( 'gform_confirmation', array( $this, 'process_the_charge' ), 10, 4);
			add_action( 'wp_loaded', array( $this, 'wpf_callback' ) );

		}

		/**
		 * Add setting link to plugins page
		 */
		public function plugin_action_links( $links ) {
			
			$plugin_links = array(
				'<a href="' . admin_url( 'admin.php?page=gf_settings&subview=wpFortify+%28Stripe%29' ) . '">' . __( 'Settings', 'wpf-gravityforms' ) . '</a>'
			);
			
			return array_merge( $plugin_links, $links );
		
		}		
		
		/**
		 * Plugin Settings.
		 */
        public function plugin_settings_fields() {

			return array(

				array(
                    'title'  => __( 'wpFortify', 'wpf-gravityforms' ),
                    'fields' => array(
						array(
							'label'    => __( 'Secret Key', 'wpf-gravityforms' ),
							'type'     => 'text',
							'name'     => 'wpf_secret_key',
							'tooltip'  => __( 'Enter the access keys from your wpFortify account.', 'wpf-gravityforms' ),
							'class'    => 'medium',
							'required' => true
						),
						array(
							'label'    => __( 'Public Key', 'wpf-gravityforms' ),
							'type'     => 'text',
							'name'     => 'wpf_public_key',
							'tooltip'  => __( 'Enter the access keys from your wpFortify account.', 'wpf-gravityforms' ),
							'class'    => 'medium',
							'required' => true
						)

					),

				)

            );

		}

		/**
		 * Form Settings.
		 */
		public function form_settings_fields( $form ) {

			return array(

				array(
					'title' => 'General Settings',
					'fields' => array(
						array(
							'label'   => __( 'Enable/Disable', 'wpf-gravityforms' ),
							'type'    => 'checkbox',
							'name'    => 'enable',
							'choices' => array(
								array(
									'label' => 'Enable wpFortify.',
									'name'  => 'enable'
								)
							)
						),
						array(
							'name'     => 'transaction_type',
							'label'    => __( 'Transaction Type', 'wpf-gravityforms' ),
							'type'     => 'select',
							'onchange' => "jQuery(this).parents('form').submit();",
							'choices'  => array(
								array( 'label' => __( 'Select a transaction type', 'wpf-gravityforms' ), 'value' => '' ),
								array( 'label' => __( 'Basic', 'wpf-gravityforms' ), 'value' => 'basic' )
							)
						)
					)
				),

				array(
					'title' => 'Basic',
					'dependency' => array(
						'field'  => 'transaction_type',
						'values' => array( 'basic' )
					),
					'fields' => array(
						array(
							'name'          => 'email',
							'label'         => __( 'Email', 'wpf-gravityforms' ),
							'type'          => 'select',
							'choices'       => $this->get_field_map_choices( $form['id'] ),
							'required'      => true
						),
						array(
							'name'          => 'paymentAmount',
							'label'         => __( 'Payment Amount', 'wpf-gravityforms' ),
							'type'          => 'select',
							'choices'       => $this->get_field_map_choices( $form['id'] ),
							'required'      => true,
							'default_value' => 'form_total'
						),
					)
				),

				array(
					'title' => 'Checkout Settings',
					'dependency' => array(
						'field'  => 'transaction_type',
						'values' => array( 'basic' )
					),
					'fields' => array(
						array(
							'label'   => __( 'Test mode', 'wpf-gravityforms' ),
							'type'    => 'checkbox',
							'name'    => 'testmode',
							'tooltip' => __( 'Place the payment gateway in test mode.', 'wpf-gravityforms' ),
							'choices' => array(
								array(
									'label' => 'Enable Test Mode',
									'name'  => 'testmode'
								)
							)
						),
						array(
                            'name'     => 'currency',
							'label'    => __( 'Currency', 'wpf-gravityforms' ),
                            'type'     => 'select',
							'required' => true,
                            'choices'  => array(
								array( 'label' => __( 'Please select a currency', 'wpf-gravityforms' ), 'value' => '' ),
								array( 'label' => 'USD', 'value' => 'usd' ),
								array( 'label' => 'CAD', 'value' => 'cad' ),
								array( 'label' => 'GBP', 'value' => 'gbp' ),
								array( 'label' => 'EUR', 'value' => 'eur' ),
								array( 'label' => 'ASD', 'value' => 'asd' ),
							)
                        ),
					)
				),

				array(
					'title' => 'Optional Settings',
					'dependency' => array(
						'field'  => 'transaction_type',
						'values' => array( 'basic' )
					),
					'fields' => array(
						array(
							'name'  => 'custom_checkout',
							'label' => __( 'Custom Checkout', 'wpf-gravityforms' ),
							'tooltip' => __( 'Optional: Enter the URL to your custom checkout page. Example: <code>https://example.wpfortify.com/</code>', 'wpf-gravityforms' ),
							'type'  => 'text',
							'class' => 'medium'
						),
						array(
							'name'  => 'image_url',
							'label' => __( 'Custom Image', 'wpf-gravityforms' ),
							'tooltip' => __( 'Optional: Enter the URL to the secure image from your wpFortify account. Example: <code>https://wpfortify.com/media/example.png</code>', 'wpf-gravityforms' ),
							'type'  => 'text',
							'class' => 'medium'
						),
						array(
							'name'  => 'description',
							'label' => __( 'Description', 'wpf-gravityforms' ),
							'tooltip' => __( 'Optional: Default is "Order #123"', 'wpf-gravityforms' ),
							'type'  => 'text',
							'class' => 'medium'
						),
						array(
							'name'  => 'return_url',
							'label' => __( 'Return URL', 'wpf-gravityforms' ),
							'tooltip' => __( 'Optional: Default is the site URL', 'wpf-gravityforms' ),
							'type'  => 'text',
							'class' => 'medium'
						),
						array(
							'name'  => 'cancel_url',
							'label' => __( 'Cancel URL', 'wpf-gravityforms' ),
							'tooltip' => __( 'Optional: Default is the site URL', 'wpf-gravityforms' ),
							'type'  => 'text',
							'class' => 'medium'
						),
					)
				)

			);

		}

		/**
		 * Process the charge.
		 */
		function process_the_charge( $confirmation, $form, $lead, $ajax ){

			$order = $this->get_form_settings( $form );

			if ( $order['enable'] ){

				$testmode    = $order['testmode'] === '1' ? true : false;
				$site_url    = get_bloginfo( 'url' );
				$return_url  = $order['return_url'];
				$cancel_url  = $order['cancel_url'];
				$description = $order['description'];

				if ( !$return_url ){

					$return_url = $site_url;

				}

				if ( !$cancel_url ){

					$cancel_url = $site_url;

				}

				if ( !$description ){

					$description = sprintf( 'Order #%s', $lead['id'] );

				}

				// Data for wpFortify
				$wpf_charge = array (
					'wpf_charge' => array(
						'plugin'       => 'wpf-gravityforms',
						'action'       => 'charge_card',
						'site_title'   => get_bloginfo(),
						'site_url'     => $site_url,
						'listen_url'   => $site_url . '/?wpfortify=listen',
						'return_url'   => $return_url,
						'cancel_url'   => $cancel_url,
						'image_url'    => $order['image_url'],
						'customer_id'  => '',
						'card_id'      => '',
						'email'        => $lead[$order['email']],
						'amount'       => $lead[$order['paymentAmount']],
						'description'  => $description,
						'currency'     => $order['currency'],
						'testmode'     => $testmode,
						'capture'      => true,
						'metadata'     => array(
							'order_id' => $lead['id']
						)
					)
				);

				$response = $this->wpf_api( 'token', $wpf_charge );

				if ( is_wp_error( $response ) ) {

					return $response->get_error_message();

				}

				if( $response->token ) {

					$url = $order['custom_checkout'];

					if ( !$url ){

						$url = 'https://checkout.wpfortify.com/';

					}

					$check_out = sprintf( '%s/token/%s/', untrailingslashit( $url ), $response->token );

					// Redirect to wpFortify
					return array( 'redirect' => $check_out );

				}

			} else {

				return $confirmation;

			}

		}

		/**
		 * Listen for wpFortify.
		 */
        public function wpf_callback() {

			if ( isset( $_GET['wpfortify'] ) && $_GET['wpfortify'] == 'listen' ) {

				$response = $this->wpf_unmask( file_get_contents( 'php://input' ) );

				if ( $response->id ) {

					$entry_id       = $response->metadata->order_id;
					$transaction_id = $response->id;
					$payment_status = 'Paid';
					$note           = sprintf( __( 'Payment completed: %s.', 'wpf-gravityforms' ), $transaction_id );

					GFFormsModel::add_note( $entry_id, 0, 'wpFortify', $note, 'success' );
					GFAPI::update_entry_property( $entry_id, 'payment_status', $payment_status );
					GFAPI::update_entry_property( $entry_id, 'transaction_id', $transaction_id );

					echo $this->wpf_mask( array( 'status' => 'order_updated' ) );
					exit;

				} else {

					echo $this->wpf_mask( array( 'error' => 'No charge ID' ) );
					exit;

				}

			}

        }


		/**
		 * wpFortify API
		 */
		function wpf_api( $endpoint, $array ) {

			$wpf_api = wp_remote_post( sprintf( 'https://api.wpfortify.com/%s/%s/', $endpoint, $this->get_plugin_setting( 'wpf_public_key' ) ), array( 'body' => $this->wpf_mask( $array ) ) );

			if ( is_wp_error( $wpf_api ) ) {

				return new WP_Error( 'wpfortify_error', __( 'There was a problem connecting to the payment gateway, please try again.', 'wpf-gravityforms' ) );

			}

			if ( empty( $wpf_api['body'] ) ) {

				return new WP_Error( 'wpfortify_error', __( 'Empty response.', 'wpf-gravityforms' ) );

			}

			$response = $this->wpf_unmask( $wpf_api['body'] );

			if ( ! empty( $response->error ) ) {

				return new WP_Error( 'wpfortify_error', $response->error );

			} elseif ( empty( $response ) ) {

				return new WP_Error( 'wpfortify_error', __( 'Invalid response.', 'wpf-gravityforms' ) );

			} else {

				return $response;

			}

		}

		/**
		 * Mask data for wpFortify
		 */
		function wpf_mask( $data ) {

			$iv = mcrypt_create_iv( mcrypt_get_iv_size( MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC ), MCRYPT_RAND );
			$json_data = json_encode( $data );
			$mask = mcrypt_encrypt( MCRYPT_RIJNDAEL_128, md5( $this->get_plugin_setting( 'wpf_secret_key' ) ), $json_data . md5( $json_data ), MCRYPT_MODE_CBC, $iv );

			return rtrim( base64_encode( base64_encode( $iv ) . '-' . base64_encode( $mask ) ), '=' );

		}

		/**
		 * Unmask data from wpFortify
		 */
		function wpf_unmask( $data ) {

			list( $iv, $data_decoded ) = array_map( 'base64_decode', explode( '-', base64_decode( $data ), 2 ) );
			$unmask = rtrim( mcrypt_decrypt( MCRYPT_RIJNDAEL_128, md5( $this->get_plugin_setting( 'wpf_secret_key' ) ), $data_decoded, MCRYPT_MODE_CBC, $iv ), "\0\4" );
			$hash = substr( $unmask, -32 );
			$unmask = substr( $unmask, 0, -32 );

			if ( md5( $unmask ) == $hash ) {

				return json_decode( $unmask );

			}

		}

    }

    new WPF_GF();

}