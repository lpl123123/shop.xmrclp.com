<?php
/**
 * Custom amount online payment page.
 *
 * @package ShopCustomFeatures
 */

defined( 'ABSPATH' ) || exit;

/**
 * Dynamic amount payment via WooCommerce checkout.
 */
class SCF_Custom_Payment {

	/**
	 * Singleton instance.
	 *
	 * @var SCF_Custom_Payment|null
	 */
	private static $instance = null;

	/**
	 * Cart item meta key for custom amount.
	 */
	const CART_META_KEY = 'scf_custom_amount';

	/**
	 * Get singleton instance.
	 *
	 * @return SCF_Custom_Payment
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'register_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'template_redirect', array( $this, 'handle_payment_submission' ) );

		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 2 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'get_cart_item_from_session' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_custom_price' ), 20 );
		add_filter( 'woocommerce_cart_item_name', array( $this, 'display_cart_item_name' ), 10, 3 );
		add_filter( 'woocommerce_order_item_name', array( $this, 'display_order_item_name' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );
	}

	/**
	 * Register payment page shortcode.
	 */
	public function register_shortcode() {
		add_shortcode( 'shop_custom_payment', array( $this, 'render_payment_form' ) );
	}

	/**
	 * Enqueue payment page assets.
	 */
	public function enqueue_assets() {
		global $post;

		if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'shop_custom_payment' ) ) {
			return;
		}

		wp_enqueue_style(
			'scf-payment',
			SCF_PLUGIN_URL . 'assets/css/payment.css',
			array(),
			SCF_VERSION
		);
	}

	/**
	 * Get custom payment product ID.
	 *
	 * @return int
	 */
	private function get_payment_product_id() {
		return (int) get_option( 'scf_custom_payment_product_id', 0 );
	}

	/**
	 * Render custom payment form shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_payment_form( $atts ) {
		$atts = shortcode_atts(
			array(
				'title'       => __( '在线支付', 'shop-custom-features' ),
				'description' => __( '请输入您需要支付的金额，确认后将跳转到结算页面完成支付。', 'shop-custom-features' ),
				'min_amount'  => '0.01',
				'max_amount'  => '99999',
			),
			$atts,
			'shop_custom_payment'
		);

		$currency_symbol = get_woocommerce_currency_symbol();
		$action_url      = add_query_arg( 'scf_custom_payment', '1', get_permalink() );

		ob_start();
		?>
		<div class="scf-custom-payment">
			<h2 class="scf-custom-payment__title"><?php echo esc_html( $atts['title'] ); ?></h2>
			<?php if ( $atts['description'] ) : ?>
				<p class="scf-custom-payment__desc"><?php echo esc_html( $atts['description'] ); ?></p>
			<?php endif; ?>

			<form class="scf-custom-payment__form" method="post" action="<?php echo esc_url( $action_url ); ?>">
				<?php wp_nonce_field( 'scf_custom_payment', 'scf_custom_payment_nonce' ); ?>
				<input type="hidden" name="scf_custom_payment_submit" value="1">

				<div class="scf-custom-payment__field">
					<label for="scf_payment_amount"><?php esc_html_e( '支付金额', 'shop-custom-features' ); ?></label>
					<div class="scf-custom-payment__amount-wrap">
						<span class="scf-custom-payment__currency"><?php echo esc_html( $currency_symbol ); ?></span>
						<input
							type="number"
							id="scf_payment_amount"
							name="scf_payment_amount"
							step="0.01"
							min="<?php echo esc_attr( $atts['min_amount'] ); ?>"
							max="<?php echo esc_attr( $atts['max_amount'] ); ?>"
							placeholder="0.00"
							required
						>
					</div>
				</div>

				<div class="scf-custom-payment__field">
					<label for="scf_payment_note"><?php esc_html_e( '备注（可选）', 'shop-custom-features' ); ?></label>
					<input type="text" id="scf_payment_note" name="scf_payment_note" maxlength="200" placeholder="<?php esc_attr_e( '例如：订单号、姓名等', 'shop-custom-features' ); ?>">
				</div>

				<div class="scf-custom-payment__preview" id="scf-payment-preview" hidden>
					<h3><?php esc_html_e( '支付商品预览', 'shop-custom-features' ); ?></h3>
					<div class="scf-custom-payment__product">
						<div class="scf-custom-payment__product-name"><?php esc_html_e( '在线支付', 'shop-custom-features' ); ?></div>
						<div class="scf-custom-payment__product-price">
							<span id="scf-preview-amount">0.00</span>
							<?php echo esc_html( $currency_symbol ); ?>
						</div>
					</div>
				</div>

				<button type="submit" class="button scf-custom-payment__submit">
					<?php esc_html_e( '立即支付', 'shop-custom-features' ); ?>
				</button>
			</form>
		</div>
		<script>
		(function () {
			var input = document.getElementById('scf_payment_amount');
			var preview = document.getElementById('scf-payment-preview');
			var previewAmount = document.getElementById('scf-preview-amount');
			if (!input || !preview || !previewAmount) return;
			input.addEventListener('input', function () {
				var value = parseFloat(input.value);
				if (!isNaN(value) && value > 0) {
					preview.hidden = false;
					previewAmount.textContent = value.toFixed(2);
				} else {
					preview.hidden = true;
				}
			});
		})();
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Handle payment form submission.
	 */
	public function handle_payment_submission() {
		if ( ! isset( $_POST['scf_custom_payment_submit'] ) ) {
			return;
		}

		if ( ! isset( $_POST['scf_custom_payment_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['scf_custom_payment_nonce'] ) ), 'scf_custom_payment' ) ) {
			wc_add_notice( __( '安全验证失败，请重试。', 'shop-custom-features' ), 'error' );
			return;
		}

		$amount = isset( $_POST['scf_payment_amount'] ) ? floatval( wp_unslash( $_POST['scf_payment_amount'] ) ) : 0;
		$note   = isset( $_POST['scf_payment_note'] ) ? sanitize_text_field( wp_unslash( $_POST['scf_payment_note'] ) ) : '';

		if ( $amount <= 0 ) {
			wc_add_notice( __( '请输入有效的支付金额。', 'shop-custom-features' ), 'error' );
			return;
		}

		$product_id = $this->get_payment_product_id();

		if ( ! $product_id ) {
			$product_id = SCF_Install::ensure_payment_product();
		}

		if ( ! $product_id ) {
			wc_add_notice( __( '支付商品未配置，请联系管理员。', 'shop-custom-features' ), 'error' );
			return;
		}

		WC()->cart->empty_cart();

		$cart_item_data = array(
			self::CART_META_KEY => $amount,
		);

		if ( $note ) {
			$cart_item_data['scf_payment_note'] = $note;
		}

		$added = WC()->cart->add_to_cart( $product_id, 1, 0, array(), $cart_item_data );

		if ( ! $added ) {
			wc_add_notice( __( '无法添加到购物车，请重试。', 'shop-custom-features' ), 'error' );
			return;
		}

		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	/**
	 * Add custom amount to cart item data.
	 *
	 * @param array $cart_item_data Cart item data.
	 * @param int   $product_id     Product ID.
	 * @return array
	 */
	public function add_cart_item_data( $cart_item_data, $product_id ) {
		if ( $product_id !== $this->get_payment_product_id() ) {
			return $cart_item_data;
		}

		if ( isset( $cart_item_data[ self::CART_META_KEY ] ) ) {
			$cart_item_data['unique_key'] = md5( microtime() . wp_rand() );
		}

		return $cart_item_data;
	}

	/**
	 * Restore cart item data from session.
	 *
	 * @param array $cart_item Cart item.
	 * @param array $values    Session values.
	 * @return array
	 */
	public function get_cart_item_from_session( $cart_item, $values ) {
		if ( isset( $values[ self::CART_META_KEY ] ) ) {
			$cart_item[ self::CART_META_KEY ] = $values[ self::CART_META_KEY ];
		}

		if ( isset( $values['scf_payment_note'] ) ) {
			$cart_item['scf_payment_note'] = $values['scf_payment_note'];
		}

		return $cart_item;
	}

	/**
	 * Apply custom price to cart item.
	 *
	 * @param WC_Cart $cart Cart object.
	 */
	public function apply_custom_price( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item[ self::CART_META_KEY ] ) && isset( $cart_item['data'] ) ) {
				$cart_item['data']->set_price( (float) $cart_item[ self::CART_META_KEY ] );
			}
		}
	}

	/**
	 * Display custom amount in cart item name.
	 *
	 * @param string $name     Item name.
	 * @param array  $cart_item Cart item.
	 * @param string $cart_item_key Cart item key.
	 * @return string
	 */
	public function display_cart_item_name( $name, $cart_item, $cart_item_key ) {
		if ( isset( $cart_item[ self::CART_META_KEY ] ) ) {
			$amount = wc_price( $cart_item[ self::CART_META_KEY ] );
			$name   = sprintf(
				/* translators: %s: payment amount */
				__( '在线支付 (%s)', 'shop-custom-features' ),
				wp_strip_all_tags( $amount )
			);

			if ( ! empty( $cart_item['scf_payment_note'] ) ) {
				$name .= '<br><small>' . esc_html( $cart_item['scf_payment_note'] ) . '</small>';
			}
		}

		return $name;
	}

	/**
	 * Display custom amount in order item name.
	 *
	 * @param string         $name Order item name.
	 * @param WC_Order_Item $item Order item.
	 * @return string
	 */
	public function display_order_item_name( $name, $item ) {
		$amount = $item->get_meta( '_scf_custom_amount' );

		if ( $amount ) {
			$name = sprintf(
				/* translators: %s: payment amount */
				__( '在线支付 (%s)', 'shop-custom-features' ),
				wp_strip_all_tags( wc_price( $amount ) )
			);
		}

		return $name;
	}

	/**
	 * Save custom amount to order line item.
	 *
	 * @param WC_Order_Item_Product $item Order item.
	 * @param string                $cart_item_key Cart item key.
	 * @param array                 $values Cart values.
	 * @param WC_Order              $order Order.
	 */
	public function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
		if ( isset( $values[ self::CART_META_KEY ] ) ) {
			$item->add_meta_data( '_scf_custom_amount', $values[ self::CART_META_KEY ], true );
		}

		if ( ! empty( $values['scf_payment_note'] ) ) {
			$item->add_meta_data( '_scf_payment_note', $values['scf_payment_note'], true );
		}
	}
}
