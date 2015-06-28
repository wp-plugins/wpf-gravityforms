<?php
/*
Plugin Name: wpFortify for Gravity Forms
Plugin URI: http://wordpress.org/plugins/wpf-gravityforms/
Description: wpFortify provides a hosted SSL checkout page for Stripe payments. A free wpFortify account is required for this plugin to work.
Version: 0.2.1
Author: wpFortify
Author URI: https://wpfortify.com
License: GPLv2+
License URI: http://www.gnu.org/licenses/gpl-2.0.html

*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'GFForms' ) ) {

    GFForms::include_addon_framework();

    class WPF_GF extends GFAddOn {

        protected $_version = '0.2.1';
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
				'<a href="' . admin_url( 'admin.php?page=gf_settings&subview=wpFortify+%28Stripe%29' ) . '">' . __( 'Settings', $this->_slug ) . '</a>'
			);

			return array_merge( $plugin_links, $links );

		}

		/**
		 * Plugin Settings.
		 */
        public function plugin_settings_fields() {

			return array(

				array(
                    'title'  => __( 'wpFortify', $this->_slug ),
                    'fields' => array(
						array(
							'label'    => __( 'Secret Key', $this->_slug ),
							'type'     => 'text',
							'name'     => 'wpf_secret_key',
							'tooltip'  => __( 'Enter the access keys from your wpFortify account.', $this->_slug ),
							'class'    => 'medium',
							'required' => true
						),
						array(
							'label'    => __( 'Public Key', $this->_slug ),
							'type'     => 'text',
							'name'     => 'wpf_public_key',
							'tooltip'  => __( 'Enter the access keys from your wpFortify account.', $this->_slug ),
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
							'label'   => __( 'Enable/Disable', $this->_slug ),
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
							'label'    => __( 'Transaction Type', $this->_slug ),
							'type'     => 'select',
							'onchange' => "jQuery(this).parents('form').submit();",
							'choices'  => array(
								array( 'label' => __( 'Select a transaction type', $this->_slug ), 'value' => '' ),
								array( 'label' => __( 'Basic', $this->_slug ), 'value' => 'basic' )
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
							'label'         => __( 'Email', $this->_slug ),
							'type'          => 'select',
							'choices'       => $this->get_field_map_choices( $form['id'] ),
							'required'      => true
						),
						array(
							'name'          => 'paymentAmount',
							'label'         => __( 'Payment Amount', $this->_slug ),
							'type'          => 'select',
							'tooltip'       => __( 'Please make sure to use a "Total" field.', $this->_slug ),
							'choices'       => $this->get_field_map_choices( $form['id'] ),
							'required'      => true
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
							'label'   => __( 'Test mode', $this->_slug ),
							'type'    => 'checkbox',
							'name'    => 'testmode',
							'tooltip' => __( 'Place the payment gateway in test mode.', $this->_slug ),
							'choices' => array(
								array(
									'label' => 'Enable Test Mode',
									'name'  => 'testmode'
								)
							)
						),
						array(
                            'name'     => 'currency',
							'label'    => __( 'Currency', $this->_slug ),
                            'type'     => 'select',
							'required' => true,
                            'choices'  => array(
								array( 'label' => __( 'Please select a currency', $this->_slug ), 'value' => '' ),
								array( 'label' => 'USD', 'value' => 'usd' ),
	                            array( 'label' => 'AUD', 'value' => 'aud' ),
	                            array( 'label' => 'CAD', 'value' => 'cad' ),
	                            array( 'label' => 'EUR', 'value' => 'eur' ),
	                            array( 'label' => 'GBP', 'value' => 'gbp' ),
	                            array( 'label' => 'SEK', 'value' => 'sek' ),
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
							'name'    => 'custom_checkout',
							'label'   => __( 'Custom Checkout', $this->_slug ),
							'tooltip' => __( 'Optional: Enter the URL to your custom checkout page. Example: <code>https://example.wpfortify.com/</code>', $this->_slug ),
							'type'    => 'text',
							'class'   => 'medium'
						),
						array(
							'name'    => 'image_url',
							'label'   => __( 'Custom Image', $this->_slug ),
							'tooltip' => __( 'Optional: Enter the URL to the secure image from your wpFortify account. Example: <code>https://wpfortify.com/media/example.png</code>', $this->_slug ),
							'type'    => 'text',
							'class'   => 'medium'
						),
						array(
							'name'    => 'site_title',
							'label'   => __( 'Checkout Title', $this->_slug ),
							'tooltip' => __( 'Optional: Enter a new title. Default is "', $this->_slug ) . get_bloginfo() . '".',
							'type'    => 'text',
							'class'   => 'medium'
						),
						array(
							'name'    => 'description',
							'label'   => __( 'Checkout Description', $this->_slug ),
							'tooltip' => __( 'Optional: Enter a new description. Default is "Order #123 ($456)". Available filters: <code>{{order_id}} {{order_amount}}</code>. Example: <code>Order #{{order_id}} (${{order_amount}}</code>', $this->_slug ),
							'type'    => 'text',
							'class'   => 'medium'
						),
						array(
							'name'    => 'button',
							'label'   => __( 'Checkout Button', $this->_slug ),
							'tooltip' => __( 'Optional: Enter new button text. Default is "Pay with Card". Available filters: <code>{{order_id}} {{order_amount}}</code>. Example: <code>Pay with Card (${{order_amount}})</code>', $this->_slug ),
							'type'    => 'text',
							'class'   => 'medium'
						),
						array(
							'name'    => 'return_url',
							'label'   => __( 'Return URL', $this->_slug ),
							'tooltip' => __( 'Optional: Default is the site URL', $this->_slug ),
							'type'    => 'text',
							'class'   => 'medium'
						),
						array(
							'name'    => 'cancel_url',
							'label'   => __( 'Cancel URL', $this->_slug ),
							'tooltip' => __( 'Optional: Default is the site URL', $this->_slug ),
							'type'    => 'text',
							'class'   => 'medium'
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
				$site_title  = get_bloginfo();
				$description = sprintf( '%s %s ($%s)', __( 'Order #', $this->_slug ), $lead['id'], $lead[$order['paymentAmount']] );
				$button      = __( 'Pay with Card', $this->_slug );

				if ( !$return_url ){

					$return_url = $site_url;

				}

				if ( !$cancel_url ){

					$cancel_url = $site_url;

				}

				if ( $order['site_title'] ) {

					$site_title = $order['site_title'];

				}

				if ( $order['description'] ) {

					$description = str_replace( array( '{{order_id}}', '{{order_amount}}' ), array( $lead['id'], $lead[$order['paymentAmount']] ), $order['description'] );

				}

				if ( $order['button'] ) {

					$button = str_replace( array( '{{order_id}}', '{{order_amount}}' ), array( $lead['id'], $lead[$order['paymentAmount']] ), $order['button'] );

				}

				// Data for wpFortify
				$wpf_charge = array (
					'wpf_charge' => array(
						'plugin'       => $this->_slug,
						'action'       => 'charge_card',
						'site_title'   => $site_title,
						'site_url'     => $site_url,
						'listen_url'   => $site_url . '/?' . $this->_slug . '=callback',
						'return_url'   => $return_url,
						'cancel_url'   => $cancel_url,
						'image_url'    => $order['image_url'],
						'customer_id'  => '',
						'card_id'      => '',
						'email'        => $lead[$order['email']],
						'amount'       => $lead[$order['paymentAmount']],
						'description'  => $description,
						'button'       => $button,
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

			if ( isset( $_GET[ $this->_slug ] ) && $_GET[ $this->_slug ] == 'callback' ) {

				$response = $this->wpf_unmask( file_get_contents( 'php://input' ) );

				if ( $response->id ) {

					$entry_id       = $response->metadata->order_id;
					$transaction_id = $response->id;
					$payment_status = 'Paid';
					$note           = sprintf( __( 'Payment completed: %s.', $this->_slug ), $transaction_id );

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

				return new WP_Error( 'wpfortify_error', __( 'There was a problem connecting to the payment gateway, please try again.', $this->_slug ) );

			}

			if ( empty( $wpf_api['body'] ) ) {

				return new WP_Error( 'wpfortify_error', __( 'Empty response.', $this->_slug ) );

			}

			$response = $this->wpf_unmask( $wpf_api['body'] );

			if ( ! empty( $response->error ) ) {

				return new WP_Error( 'wpfortify_error', $response->error );

			} elseif ( empty( $response ) ) {

				return new WP_Error( 'wpfortify_error', __( 'Invalid response.', $this->_slug ) );

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

			if ( $iv && $data_decoded ) {

				$unmask = rtrim( mcrypt_decrypt( MCRYPT_RIJNDAEL_128, md5( $this->get_plugin_setting( 'wpf_secret_key' ) ), $data_decoded, MCRYPT_MODE_CBC, $iv ), "\0\4" );
				$hash = substr( $unmask, -32 );
				$unmask = substr( $unmask, 0, -32 );

				if ( md5( $unmask ) == $hash ) {

					return json_decode( $unmask );

				}

			}

		}

    }

    new WPF_GF();

}