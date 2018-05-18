<?php
/**
 * Disable direct access/execution to/of the widget code.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'Jetpack_Simple_Payments_Widget' ) ) {

	//Register Jetpack_Simple_Payments_Widget
	function register_widget_jetpack_simple_payments() {
		register_widget( 'Jetpack_Simple_Payments_Widget' );
	}
	add_action( 'widgets_init', 'register_widget_jetpack_simple_payments' );

	/**
	 * Simple Payments Widget
	 *
	 * Displays a Simple Payment Button.
	 */
	class Jetpack_Simple_Payments_Widget extends WP_Widget {
		private static $currencie_symbols = array(
			'USD' => '$',
			'GBP' => '&#163;',
			'JPY' => '&#165;',
			'BRL' => 'R$',
			'EUR' => '&#8364;',
			'NZD' => 'NZ$',
			'AUD' => 'A$',
			'CAD' => 'C$',
			'INR' => '₹',
			'ILS' => '₪',
			'RUB' => '₽',
			'MXN' => 'MX$',
			'SEK' => 'Skr',
			'HUF' => 'Ft',
			'CHF' => 'CHF',
			'CZK' => 'Kč',
			'DKK' => 'Dkr',
			'HKD' => 'HK$',
			'NOK' => 'Kr',
			'PHP' => '₱',
			'PLN' => 'PLN',
			'SGD' => 'S$',
			'TWD' => 'NT$',
			'THB' => '฿',
		);

		/**
		 * Constructor.
		 */
		function __construct() {
			$widget = array(
				'classname' => 'simple-payments',
				'form_product_description' => __( 'Add a simple payment button.' ),
				'customize_selective_refresh' => true,
			);

			parent::__construct( 'Jetpack_Simple_Payments_Widget', __( 'Simple Payments' ), $widget );

			// if ( is_active_widget( false, false, $this->id_base ) || is_customize_preview() ) {
			// 	add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_style' ) );
			// }
			add_action( 'admin_enqueue_scripts', array( __class__, 'admin_enqueue_scripts' ) );

			add_filter( 'customize_refresh_nonces', array( $this, 'filter_nonces' ) );
			add_action( 'wp_ajax_customize-simple-payments-button-add-new', array( $this, 'ajax_add_new_payment_button' ) );
		}

		public static function admin_enqueue_scripts( $hook_suffix ){
			if ( 'widgets.php' == $hook_suffix ) {
				wp_enqueue_style( 'simple-payments-widget-customizer', plugin_dir_url( __FILE__ ) . '/simple-payments/customizer.css' );

				wp_enqueue_media();
				wp_enqueue_script( 'simple-payments-widget-customizer', plugin_dir_url( __FILE__ ) . '/simple-payments/customizer.js', array( 'jquery' ), false, true );
			}
		}

		public function ajax_add_new_payment_button() {
			if ( ! check_ajax_referer( 'customize-simple-payments', 'customize-simple-payments-nonce', false ) ) {
				wp_send_json_error( 'bad_nonce', 400 );
			}

			if ( ! current_user_can( 'customize' ) ) {
				wp_send_json_error( 'customize_not_allowed', 403 );
			}

			if ( empty( $_POST['params'] ) || ! is_array( $_POST['params'] ) ) {
				wp_send_json_error( 'missing_params', 400 );
			}

			$product_id = wp_insert_post( array(
				'ID' => 0,
				'post_type' => 'jp_pay_product',
				'post_status' => 'publish',
				'post_title' => 'this is the title', //sanitize_text_field( $new_instance['form_product_title'] ),
				'post_content' => 'this is the content', //sanitize_textarea_field( $new_instance['form_product_description'] ),
				'_thumbnail_id' => -1, //isset( $new_instance['form_product_image'] ) ? $new_instance['form_product_image'] : -1,
				'meta_input' => array(
					'spay_currency' => 'USD', //$new_instance['form_product_currency'],
					'spay_price' => 10, //$new_instance['form_product_price'],
					'spay_multiple' => 1, //isset( $new_instance['form_product_multiple'] ) ? intval( $new_instance['form_product_multiple'] ) : 0,
					'spay_email' => 'rodrigo.iloro+dev@automattic.com', //is_email( $new_instance['form_product_email'] ),
				),
			) );

			wp_send_json_success( [
				'product_post_id' => $product_id,
				'product_post_title' => 'this is the title'//sanitize_text_field( $new_instance['form_product_title'] ),
			] );
		}

		/**
		 * Adds a nonce for customizing menus.
		 *
		 * @param array $nonces Array of nonces.
		 * @return array $nonces Modified array of nonces.
		 */
		public function filter_nonces( $nonces ) {
			$nonces['customize-simple-payments'] = wp_create_nonce( 'customize-simple-payments' );
			return $nonces;
		}

		/**
		 * Return an associative array of default values.
		 *
		 * These values are used in new widgets.
		 *
		 * @return array Default values for the widget options.
		 */
		public function defaults() {
			return array(
				'widget_title' => '',
				'product_id' => 0,
				'product_post_id' => 0,
				'form_product_title' => '',
				'form_product_description' => '',
				'form_product_image' => '',
				'form_product_currency' => '',
				'form_product_price' => '',
				'form_product_multiple' => '',
				'form_product_email' => '',
			);
		}

		/**
		 * Front-end display of widget.
		 *
		 * @see WP_Widget::widget()
		 *
		 * @param array $args     Widget arguments.
		 * @param array $instance Saved values from database.
		 */
		function widget( $args, $instance ) {
			error_log('> widget');
			echo $args['before_widget'];

			$widget_title = apply_filters( 'widget_title', $instance['widget_title'] );
			if ( ! empty( $widget_title ) ) {
				echo $args['before_title'] . $widget_title . $args['after_title'];
			}

			echo '<div class="jetpack-simple-payments-content">';

			if( ! empty( $instance['product_post_id'] ) ) {
				$attrs = array( 'id' => $instance['product_post_id'] );
			} else {
				$product_posts = get_posts( [
					'numberposts' => 1,
					'orderby' => 'date',
					'post_type' => 'jp_pay_product',
				] );

				$attrs = array( 'id' => $product_posts[0]->ID );
			}

			// if( is_customize_preview() ) {
			// 	require( dirname( __FILE__ ) . '/simple-payments/templates/widget.php' );
			// } else {
				$jsp = Jetpack_Simple_Payments::getInstance();
				echo $jsp->parse_shortcode( $attrs );
			// }

			echo '</div><!--simple-payments-->';

			echo $args['after_widget'];
		}

		/**
		 * Sanitize widget form values as they are saved.
		 *
		 * @see WP_Widget::update()
		 *
		 * @param array $new_instance Values just sent to be saved.
		 * @param array $old_instance Previously saved values from database.
		 *
		 * @return array Updated safe values to be saved.
		 */
		function update( $new_instance, $old_instance ) {
			error_log('> update');

			// $product_id = (int) $old_instance['product_post_id'];
			$widget_title = ! empty( $new_instance['widget_title'] ) ? sanitize_text_field( $new_instance['widget_title'] ) : '';
			$product_id = ( int ) $new_instance['product_post_id'];
			$form_product_title = sanitize_text_field( $new_instance['form_product_title'] );
			$form_product_description = sanitize_text_field( $new_instance['form_product_description'] );
			$form_product_image = sanitize_text_field( $new_instance['form_product_image'] );
			$form_product_currency = sanitize_text_field( $new_instance['form_product_currency'] );
			$form_product_price = sanitize_text_field( $new_instance['form_product_price'] );
			$form_product_multiple = sanitize_text_field( $new_instance['form_product_multiple'] );
			$form_product_email = sanitize_text_field( $new_instance['form_product_email'] );

			error_log('>> old instance:'.$old_instance['widget_title']);
			error_log('>> new instance:'.$new_instance['widget_title']);

			if ( strcmp( $new_instance['form_action'], $old_instance['form_action'] ) !== 0 ) {
				switch ( $new_instance['form_action' ] ) {
					case 'edit': //load the form with existing values
						error_log('>> edit');

						$product_id = ! empty( $product_id ) ?: ( int ) $old_instance['product_post_id'];
						$product_post = get_post( $product_id );

						if( ! empty( $product_post ) ) {
							$widget_title = ! empty( $widget_title ) ?: $old_instance['widget_title'];
							$form_product_title = get_the_title( $product_post );
							$form_product_description = $product_post->post_content;
							$form_product_image = get_post_thumbnail_id( $product_id, 'full' );
							$form_product_currency = get_post_meta( $product_id, 'spay_currency', true );
							$form_product_price = get_post_meta( $product_id, 'spay_price', true );
							$form_product_multiple = get_post_meta( $product_id, 'spay_multiple', true ) || '0';
							$form_product_email = get_post_meta( $product_id, 'spay_email', true );
						}
						break;
					case 'clear': //clear form
						error_log('>> delete');

						$form_product_title = '';
						$form_product_description = '';
						$form_product_image = '';
						$form_product_currency = '';
						$form_product_price = '';
						$form_product_multiple = '';
						$form_product_email = '';
						break;
				}
			}

			return array(
				'widget_title' => $widget_title,
				'product_post_id' => $product_id,
				'form_action' => '',
				'form_product_title' => $form_product_title,
				'form_product_description' => $form_product_description,
				'form_product_image' => $form_product_image,
				'form_product_currency' => $form_product_currency,
				'form_product_price' => $form_product_price,
				'form_product_multiple' => $form_product_multiple,
				'form_product_email' => $form_product_email,
			);
		}

		/**
		 * Back-end widget form.
		 *
		 * @see WP_Widget::form()
		 *
		 * @param array $instance Previously saved values from database.
		 */
		function form( $instance ) {
			error_log('> form');
			$instance = wp_parse_args( $instance, $this->defaults() );

			$product_posts = get_posts( [
				'numberposts' => 100,
				'orderby' => 'date',
				'post_type' => 'jp_pay_product'
			] );

			require( dirname( __FILE__ ) . '/simple-payments/templates/form.php' );
		}
	}
}
