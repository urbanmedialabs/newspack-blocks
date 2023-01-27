<?php
/**
 * Server-side rendering of the `newspack-blocks/donate` block.
 *
 * @package WordPress
 */

defined( 'ABSPATH' ) || exit;

require_once NEWSPACK_BLOCKS__PLUGIN_DIR . 'src/blocks/donate/frontend/class-newspack-blocks-donate-renderer-frequency-based.php';
require_once NEWSPACK_BLOCKS__PLUGIN_DIR . 'src/blocks/donate/frontend/class-newspack-blocks-donate-renderer-tiers-based.php';

/**
 * Server-side rendering of the `newspack-blocks/donate` block.
 */
class Newspack_Blocks_Donate_Renderer {
	/**
	 * Whether the modal checkout is used by donate any block.
	 *
	 * @var boolean
	 */
	private static $has_modal_checkout = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_modal_checkout_scripts' ] );
		add_action( 'wp_footer', [ __CLASS__, 'render_modal_checkout_iframe' ] );
		add_filter( 'woocommerce_get_return_url', [ __CLASS__, 'woocommerce_get_return_url' ], 10, 2 );
		add_action( 'template_include', [ __CLASS__, 'get_modal_checkout_template' ] );
		add_filter( 'wc_get_template', [ __CLASS__, 'wc_get_template' ], 10, 2 );
		add_filter( 'woocommerce_checkout_fields', [ __CLASS__, 'woocommerce_checkout_fields' ] );
	}

	/**
	 * Get the keys of the billing fields to render for logged out users.
	 *
	 * @return array
	 */
	public static function get_billing_fields_keys() {
		$fields = [
			'billing_first_name',
			'billing_last_name',
			'billing_email',
		];
		/**
		 * Filters the billing fields used on modal checkout.
		 *
		 * @param array $fields Billing fields.
		 */
		return apply_filters( 'newspack_blocks_donate_billing_fields_keys', $fields );
	}

	/**
	 * Modify fields for modal checkout.
	 *
	 * @param array $fields Checkout fields.
	 *
	 * @return array
	 */
	public static function woocommerce_checkout_fields( $fields ) {
		if ( ! class_exists( 'Newspack\Donations' ) || ! method_exists( 'Newspack\Donations', 'is_donation_cart' ) ) {
			return $fields;
		}
		if ( ! \Newspack\Donations::is_donation_cart() ) {
			return $fields;
		}
		if ( is_user_logged_in() ) {
			$billing_fields = [ 'billing_email' ];
		} else {
			$billing_fields = self::get_billing_fields_keys();
		}
		if ( ! empty( $fields['billing'] ) ) {
			$shipping_keys = array_keys( $fields['billing'] );
			foreach ( $shipping_keys as $key ) {
				if ( in_array( $key, $billing_fields, true ) ) {
					continue;
				}
				unset( $fields['billing'][ $key ] );
			}
		}
		return $fields;
	}

	/**
	 * Enqueue frontend scripts and styles.
	 */
	public static function enqueue_modal_checkout_scripts() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}
		if ( ! isset( $_REQUEST['modal_checkout'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$filename    = 'donateCheckoutModal';
		$handle_slug = 'modal-checkout';
		$handle      = Newspack_Blocks::SCRIPT_HANDLES[ $handle_slug ];
		$script_data = Newspack_Blocks::script_enqueue_helper( NEWSPACK_BLOCKS__BLOCKS_DIRECTORY . '/' . $filename . '.js' );
		wp_enqueue_script(
			$handle,
			$script_data['script_path'],
			[],
			NEWSPACK_BLOCKS__VERSION,
			true
		);
		wp_script_add_data( $handle, 'async', true );
		wp_script_add_data( $handle, 'amp-plus', true );
		$style_path = NEWSPACK_BLOCKS__BLOCKS_DIRECTORY . $filename . ( is_rtl() ? '.rtl' : '' ) . '.css';
		wp_enqueue_style(
			$handle,
			plugins_url( $style_path, NEWSPACK_BLOCKS__PLUGIN_FILE ),
			[],
			NEWSPACK_BLOCKS__VERSION
		);
	}

	/**
	 * Enqueue frontend scripts and styles.
	 *
	 * @param string $handle_slug The slug of the script to enqueue.
	 */
	private static function enqueue_scripts( $handle_slug ) {
		$dependencies = [ 'wp-i18n' ];

		if ( 'streamlined' === $handle_slug ) {
			if ( method_exists( '\Newspack\Recaptcha', 'can_use_captcha' ) && \Newspack\Recaptcha::can_use_captcha() ) {
				$dependencies[] = \Newspack\Recaptcha::SCRIPT_HANDLE;
			}
		}

		switch ( $handle_slug ) {
			case 'streamlined':
				$filename = 'donateStreamlined';
				break;
			case 'frequency-based':
				$filename = 'frequencyBased';
				break;
			case 'tiers-based':
				$filename = 'tiersBased';
				break;
			default:
				$filename = false;
				break;
		}

		if ( false === $filename ) {
			return;
		}

		$script_data = Newspack_Blocks::script_enqueue_helper( NEWSPACK_BLOCKS__BLOCKS_DIRECTORY . '/' . $filename . '.js' );
		wp_enqueue_script(
			Newspack_Blocks::SCRIPT_HANDLES[ $handle_slug ],
			$script_data['script_path'],
			$dependencies,
			NEWSPACK_BLOCKS__VERSION,
			true
		);

		$style_path = NEWSPACK_BLOCKS__BLOCKS_DIRECTORY . $filename . ( is_rtl() ? '.rtl' : '' ) . '.css';
		wp_enqueue_style(
			Newspack_Blocks::SCRIPT_HANDLES[ $handle_slug ],
			plugins_url( $style_path, NEWSPACK_BLOCKS__PLUGIN_FILE ),
			[],
			NEWSPACK_BLOCKS__VERSION
		);
	}

	/**
	 * Renders the `newspack-blocks/donate` block on server.
	 *
	 * @param array $attributes The block attributes.
	 *
	 * @return string
	 */
	public static function render( $attributes ) {
		if ( ! class_exists( 'Newspack\Donations' ) ) {
			return '';
		}

		$configuration = Newspack_Blocks_Donate_Renderer_Frequency_Based::get_configuration( $attributes );
		if ( \is_wp_error( $configuration ) ) {
			return '';
		}

		if ( $configuration['is_rendering_stripe_payment_form'] ) {
			self::enqueue_scripts( 'streamlined' );
		}

		Newspack_Blocks::enqueue_view_assets( 'donate' );
		wp_script_add_data( 'newspack-blocks-donate', 'async', true );
		wp_script_add_data( 'newspack-blocks-donate', 'amp-plus', true );

		if ( true === $attributes['useModalCheckout'] ) {
			self::$has_modal_checkout = true;
		}

		if ( $configuration['is_tier_based_layout'] ) {
			self::enqueue_scripts( 'tiers-based' );
			return Newspack_Blocks_Donate_Renderer_Tiers_Based::render( $attributes );
		} else {
			self::enqueue_scripts( 'frequency-based' );
			return Newspack_Blocks_Donate_Renderer_Frequency_Based::render( $attributes );
		}
	}

	/**
	 * Render the modal checkout iframe.
	 */
	public static function render_modal_checkout_iframe() {
		if ( ! self::$has_modal_checkout ) {
			return;
		}
		?>
		<div class="newspack-blocks-donate-checkout-modal" style="display: none;">
			<div class="newspack-blocks-donate-checkout-modal__content">
				<a href="#" class="newspack-blocks-donate-checkout-modal__close">
					<span class="screen-reader-text"><?php esc_html_e( 'Close', 'newspack-blocks' ); ?></span>
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false">
						<path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41z"/>
					</svg>
				</a>
				<div class="newspack-blocks-donate-checkout-modal__spinner">
					<span class="spinner is-active"></span>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Return URL for modal checkout "thank you" page.
	 *
	 * @param string $url The URL to redirect to.
	 *
	 * @return string
	 */
	public static function woocommerce_get_return_url( $url ) {
		if ( ! isset( $_REQUEST['modal_checkout'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $url;
		}
		return add_query_arg(
			[
				'modal_checkout' => '1',
				'email'          => isset( $_REQUEST['billing_email'] ) ? rawurlencode( sanitize_email( wp_unslash( $_REQUEST['billing_email'] ) ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'order'          => false,
				'key'            => false,
			],
			$url
		);
	}

	/**
	 * Use stripped down template for modal checkout.
	 *
	 * @param string $template The template to render.
	 *
	 * @return string
	 */
	public static function get_modal_checkout_template( $template ) {
		if ( ! function_exists( 'is_checkout' ) || ! function_exists( 'is_order_received_page' ) ) {
			return $template;
		}
		if ( ! is_checkout() && ! is_order_received_page() ) {
			return $template;
		}
		if ( ! isset( $_REQUEST['modal_checkout'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $template;
		}
		return NEWSPACK_BLOCKS__PLUGIN_DIR . 'src/blocks/donate/templates/modal-checkout.php';
	}

	/**
	 * Use modal checkout template when rendering the checkout form.
	 *
	 * @param string $located       Template file.
	 * @param string $template_name Template name.
	 *
	 * @return string Template file.
	 */
	public static function wc_get_template( $located, $template_name ) {
		if ( 'checkout/form-checkout.php' === $template_name && isset( $_REQUEST['modal_checkout'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$located = NEWSPACK_BLOCKS__PLUGIN_DIR . 'src/blocks/donate/templates/modal-checkout-form.php';
		}
		return $located;
	}
}
new Newspack_Blocks_Donate_Renderer();
