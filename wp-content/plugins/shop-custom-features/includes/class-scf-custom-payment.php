<?php
/**
 * Custom amount online payment page.
 *
 * @package ShopCustomFeatures
 */

defined( 'ABSPATH' ) || exit;

/**
 * Dynamic amount payment with product matching via WooCommerce checkout.
 */
class SCF_Custom_Payment {

	/**
	 * Singleton instance.
	 *
	 * @var SCF_Custom_Payment|null
	 */
	private static $instance = null;

	/**
	 * Cart item meta key for custom amount fallback.
	 */
	const CART_META_KEY = 'scf_custom_amount';

	/**
	 * Cart item meta key for payment method preference.
	 */
	const PAYMENT_METHOD_KEY = 'scf_payment_method';

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
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'template_redirect', array( $this, 'handle_payment_submission' ) );

		add_action( 'wp_ajax_scf_match_product_by_amount', array( $this, 'ajax_match_product' ) );
		add_action( 'wp_ajax_nopriv_scf_match_product_by_amount', array( $this, 'ajax_match_product' ) );

		add_action( 'woocommerce_after_shop_loop_item', array( $this, 'render_buy_button_loop' ), 15 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_buy_button_single' ), 31 );

		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 2 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'get_cart_item_from_session' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_custom_price' ), 20 );
		add_filter( 'woocommerce_cart_item_name', array( $this, 'display_cart_item_name' ), 10, 3 );
		add_filter( 'woocommerce_order_item_name', array( $this, 'display_order_item_name' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_payment_method_on_order' ), 10, 2 );
	}

	/**
	 * Register admin menu.
	 */
	public function register_admin_menu() {
		add_submenu_page(
			'woocommerce',
			__( '在线支付设置', 'shop-custom-features' ),
			__( '在线支付设置', 'shop-custom-features' ),
			'manage_woocommerce',
			'scf-payment-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register payment settings.
	 */
	public function register_settings() {
		register_setting( 'scf_payment_settings', 'scf_payment_page_id', array( 'type' => 'integer', 'default' => 0 ) );
		register_setting( 'scf_payment_settings', 'scf_payment_shop_name', array( 'type' => 'string', 'default' => get_bloginfo( 'name' ) ) );
		register_setting( 'scf_payment_settings', 'scf_payment_show_buy_button', array( 'type' => 'boolean', 'default' => true ) );
	}

	/**
	 * Render payment settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$pages = get_pages(
			array(
				'sort_column' => 'post_title',
				'sort_order'  => 'ASC',
			)
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( '支付设置', 'shop-custom-features' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'scf_payment_settings' ); ?>
				<table class="form-table">
					<tr>
						<th><label for="scf_payment_page_id"><?php esc_html_e( '在线支付页面', 'shop-custom-features' ); ?></label></th>
						<td>
							<select name="scf_payment_page_id" id="scf_payment_page_id">
								<option value="0"><?php esc_html_e( '— 请选择 —', 'shop-custom-features' ); ?></option>
								<?php foreach ( $pages as $page ) : ?>
									<option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( get_option( 'scf_payment_page_id', 0 ), $page->ID ); ?>>
										<?php echo esc_html( $page->post_title ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( '选择一个包含 [shop_custom_payment] 短代码的页面。', 'shop-custom-features' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="scf_payment_shop_name"><?php esc_html_e( '收款商户名称', 'shop-custom-features' ); ?></label></th>
						<td>
							<input type="text" id="scf_payment_shop_name" name="scf_payment_shop_name" value="<?php echo esc_attr( get_option( 'scf_payment_shop_name', get_bloginfo( 'name' ) ) ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( '显示在支付页「付款给 xxx」。', 'shop-custom-features' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( '商品页买单按钮', 'shop-custom-features' ); ?></th>
						<td>
							<input type="hidden" name="scf_payment_show_buy_button" value="0">
							<label>
								<input type="checkbox" name="scf_payment_show_buy_button" value="1" <?php checked( get_option( 'scf_payment_show_buy_button', true ) ); ?>>
								<?php esc_html_e( '在商品列表和详情页显示「买单」按钮', 'shop-custom-features' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Register payment page shortcode.
	 */
	public function register_shortcode() {
		add_shortcode( 'shop_custom_payment', array( $this, 'render_payment_form' ) );
	}

	/**
	 * Get configured payment page URL.
	 *
	 * @return string
	 */
	public function get_payment_page_url() {
		$page_id = (int) get_option( 'scf_payment_page_id', 0 );

		if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
			return get_permalink( $page_id );
		}

		global $post;

		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'shop_custom_payment' ) ) {
			return get_permalink( $post );
		}

		return home_url( '/' );
	}

	/**
	 * Build buy button URL.
	 *
	 * @param WC_Product $product Product object.
	 * @return string
	 */
	public function get_buy_button_url( $product ) {
		$url = add_query_arg(
			array(
				'amount'     => $product->get_price(),
				'product_id' => $product->get_id(),
			),
			$this->get_payment_page_url()
		);

		return $url;
	}

	/**
	 * Render buy button in product loop.
	 */
	public function render_buy_button_loop() {
		if ( ! $this->should_show_buy_button() ) {
			return;
		}

		global $product;

		if ( ! $product || ! $product->is_purchasable() ) {
			return;
		}

		$this->output_buy_button( $product );
	}

	/**
	 * Render buy button on single product page.
	 */
	public function render_buy_button_single() {
		if ( ! $this->should_show_buy_button() ) {
			return;
		}

		global $product;

		if ( ! $product || ! $product->is_purchasable() ) {
			return;
		}

		echo '<div class="scf-buy-button-wrap">';
		$this->output_buy_button( $product );
		echo '</div>';
	}

	/**
	 * Check if buy button should display.
	 *
	 * @return bool
	 */
	private function should_show_buy_button() {
		return (bool) get_option( 'scf_payment_show_buy_button', true ) && $this->get_payment_page_url();
	}

	/**
	 * Output buy button markup.
	 *
	 * @param WC_Product $product Product object.
	 */
	private function output_buy_button( $product ) {
		$url = esc_url( $this->get_buy_button_url( $product ) );
		printf(
			'<a href="%1$s" class="button scf-buy-button">%2$s</a>',
			$url,
			esc_html__( '买单', 'shop-custom-features' )
		);
	}

	/**
	 * Enqueue payment page assets.
	 */
	public function enqueue_assets() {
		global $post;

		$is_payment_page = is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'shop_custom_payment' );

		if ( $is_payment_page ) {
			wp_enqueue_style(
				'scf-payment',
				SCF_PLUGIN_URL . 'assets/css/payment.css',
				array(),
				SCF_VERSION
			);

			wp_enqueue_script(
				'scf-payment',
				SCF_PLUGIN_URL . 'assets/js/payment.js',
				array(),
				SCF_VERSION,
				true
			);

			wp_localize_script(
				'scf-payment',
				'scfPayment',
				array(
					'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
					'nonce'     => wp_create_nonce( 'scf_payment_nonce' ),
					'currency'  => get_woocommerce_currency_symbol(),
					'minAmount' => 0.01,
					'maxAmount' => 999999,
					'labels'    => array(
						'matching'    => __( '正在匹配商品...', 'shop-custom-features' ),
						'matched'     => __( '已匹配商品', 'shop-custom-features' ),
						'notFound'    => __( '未找到该金额对应的商品，请检查金额或联系客服', 'shop-custom-features' ),
						'invalid'     => __( '请输入有效金额', 'shop-custom-features' ),
						'payNow'      => __( 'PayNow 我要买单', 'shop-custom-features' ),
						'payAmount'   => __( '支付', 'shop-custom-features' ),
					),
				)
			);
		}

		if ( $this->should_show_buy_button() ) {
			wp_enqueue_style(
				'scf-buy-button',
				SCF_PLUGIN_URL . 'assets/css/payment.css',
				array(),
				SCF_VERSION
			);
		}
	}

	/**
	 * Get hidden fallback payment product ID.
	 *
	 * @return int
	 */
	private function get_payment_product_id() {
		return (int) get_option( 'scf_custom_payment_product_id', 0 );
	}

	/**
	 * Find a published product matching the given amount.
	 *
	 * @param float $amount Payment amount.
	 * @return WC_Product|null
	 */
	public function find_product_by_amount( $amount ) {
		global $wpdb;

		$amount    = (float) wc_format_decimal( $amount, wc_get_price_decimals() );
		$hidden_id = $this->get_payment_product_id();
		$decimals  = wc_get_price_decimals();

		if ( $amount <= 0 ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$product_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT pm.post_id
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = '_price'
				AND ROUND(CAST(pm.meta_value AS DECIMAL(20,4)), %d) = ROUND(%f, %d)
				AND p.post_status = 'publish'
				AND p.post_type IN ('product', 'product_variation')
				AND pm.post_id != %d
				ORDER BY p.post_modified DESC
				LIMIT 1",
				$decimals,
				$amount,
				$decimals,
				$hidden_id
			)
		);

		if ( ! $product_id ) {
			return null;
		}

		$product = wc_get_product( (int) $product_id );

		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return null;
		}

		return $product;
	}

	/**
	 * Format price as plain text for JavaScript display.
	 *
	 * @param float|string $price Product price.
	 * @return string
	 */
	private function format_price_plain( $price ) {
		return html_entity_decode(
			wp_strip_all_tags( wc_price( $price ) ),
			ENT_QUOTES,
			'UTF-8'
		);
	}

	/**
	 * Format product data for frontend JSON.
	 *
	 * @param WC_Product $product Product object.
	 * @return array
	 */
	private function format_product_data( $product ) {
		$image_id  = $product->get_image_id();
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : wc_placeholder_img_src( 'woocommerce_thumbnail' );

		return array(
			'id'            => $product->get_id(),
			'name'          => $product->get_name(),
			'price'         => wc_format_decimal( $product->get_price(), wc_get_price_decimals() ),
			'price_display' => $this->format_price_plain( $product->get_price() ),
			'image'         => $image_url,
			'permalink'     => get_permalink( $product->get_id() ),
		);
	}

	/**
	 * AJAX: match product by amount.
	 */
	public function ajax_match_product() {
		check_ajax_referer( 'scf_payment_nonce', 'nonce' );

		$amount = isset( $_POST['amount'] ) ? floatval( wp_unslash( $_POST['amount'] ) ) : 0;

		if ( $amount <= 0 ) {
			wp_send_json_error( array( 'message' => __( '请输入有效金额', 'shop-custom-features' ) ), 400 );
		}

		$product = $this->find_product_by_amount( $amount );

		if ( ! $product ) {
			wp_send_json_error(
				array(
					'message' => __( '未找到该金额对应的商品', 'shop-custom-features' ),
				),
				404
			);
		}

		wp_send_json_success(
			array(
				'product' => $this->format_product_data( $product ),
			)
		);
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
				'min_amount'  => '0.01',
				'max_amount'  => '999999',
			),
			$atts,
			'shop_custom_payment'
		);

		$shop_name       = get_option( 'scf_payment_shop_name', get_bloginfo( 'name' ) );
		$currency_symbol = get_woocommerce_currency_symbol();
		$action_url      = add_query_arg( 'scf_custom_payment', '1', get_permalink() );
		$prefill_amount  = isset( $_GET['amount'] ) ? floatval( wp_unslash( $_GET['amount'] ) ) : 0;
		$prefill_product = isset( $_GET['product_id'] ) ? absint( wp_unslash( $_GET['product_id'] ) ) : 0;

		if ( $prefill_amount <= 0 && $prefill_product ) {
			$prefill_product_obj = wc_get_product( $prefill_product );
			if ( $prefill_product_obj ) {
				$prefill_amount = (float) $prefill_product_obj->get_price();
			}
		}

		$prefill_amount_display = $prefill_amount > 0 ? wc_format_decimal( $prefill_amount, wc_get_price_decimals() ) : '';

		ob_start();
		?>
		<div class="scf-custom-payment" data-min="<?php echo esc_attr( $atts['min_amount'] ); ?>" data-max="<?php echo esc_attr( $atts['max_amount'] ); ?>">
			<div class="scf-custom-payment__header">
				<h2 class="scf-custom-payment__title"><?php echo esc_html( $atts['title'] ); ?></h2>
				<p class="scf-custom-payment__payee">
					<?php
					printf(
						/* translators: %s: shop name */
						esc_html__( '付款给 %s', 'shop-custom-features' ),
						esc_html( $shop_name )
					);
					?>
				</p>
			</div>

			<div class="scf-custom-payment__methods">
				<h3><?php esc_html_e( '支付方式', 'shop-custom-features' ); ?> <span>Choose a payment method</span></h3>
				<div class="scf-custom-payment__method-list">
					<label class="scf-custom-payment__method is-active">
						<input type="radio" name="scf_payment_method_ui" value="wechat" checked>
						<span class="scf-custom-payment__method-icon scf-custom-payment__method-icon--wechat"><?php esc_html_e( '微信支付', 'shop-custom-features' ); ?></span>
					</label>
					<label class="scf-custom-payment__method">
						<input type="radio" name="scf_payment_method_ui" value="alipay">
						<span class="scf-custom-payment__method-icon scf-custom-payment__method-icon--alipay"><?php esc_html_e( '支付宝', 'shop-custom-features' ); ?></span>
					</label>
					<label class="scf-custom-payment__method">
						<input type="radio" name="scf_payment_method_ui" value="paypal">
						<span class="scf-custom-payment__method-icon scf-custom-payment__method-icon--paypal">PayPal</span>
					</label>
				</div>
			</div>

			<form class="scf-custom-payment__form" id="scf-payment-form" method="post" action="<?php echo esc_url( $action_url ); ?>">
				<?php wp_nonce_field( 'scf_custom_payment', 'scf_custom_payment_nonce' ); ?>
				<input type="hidden" name="scf_custom_payment_submit" value="1">
				<input type="hidden" name="scf_matched_product_id" id="scf_matched_product_id" value="">
				<input type="hidden" name="scf_payment_method" id="scf_payment_method" value="wechat">

				<div class="scf-custom-payment__amount-section">
					<h3><?php esc_html_e( '付款金额 (CNY)', 'shop-custom-features' ); ?> <span>Payment amount</span></h3>
					<div class="scf-custom-payment__amount-display">
						<span class="scf-custom-payment__currency"><?php echo esc_html( $currency_symbol ); ?></span>
						<input
							type="text"
							inputmode="decimal"
							id="scf_payment_amount"
							name="scf_payment_amount"
							value="<?php echo esc_attr( $prefill_amount_display ); ?>"
							placeholder="0.00"
							autocomplete="off"
							required
						>
					</div>
					<div class="scf-custom-payment__note-wrap">
						<label for="scf_payment_note"><?php esc_html_e( '备注', 'shop-custom-features' ); ?></label>
						<input type="text" id="scf_payment_note" name="scf_payment_note" maxlength="200" placeholder="<?php esc_attr_e( '选填', 'shop-custom-features' ); ?>">
					</div>
				</div>

				<div class="scf-custom-payment__status" id="scf-payment-status" hidden></div>

				<div class="scf-custom-payment__matched" id="scf-payment-matched" hidden>
					<h3><?php esc_html_e( '订单支付', 'shop-custom-features' ); ?></h3>
					<div class="scf-custom-payment__matched-card">
						<div class="scf-custom-payment__matched-image">
							<img id="scf-matched-image" src="" alt="">
						</div>
						<div class="scf-custom-payment__matched-info">
							<div class="scf-custom-payment__matched-name" id="scf-matched-name"></div>
							<div class="scf-custom-payment__matched-price" id="scf-matched-price"></div>
						</div>
					</div>
					<div class="scf-custom-payment__matched-total">
						<span><?php esc_html_e( '支付金额', 'shop-custom-features' ); ?></span>
						<strong id="scf-matched-total"></strong>
					</div>
				</div>

				<button type="submit" class="scf-custom-payment__submit" id="scf-payment-submit" disabled>
					<?php esc_html_e( 'PayNow 我要买单', 'shop-custom-features' ); ?>
				</button>
			</form>
		</div>
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

		$amount             = isset( $_POST['scf_payment_amount'] ) ? floatval( wp_unslash( $_POST['scf_payment_amount'] ) ) : 0;
		$note               = isset( $_POST['scf_payment_note'] ) ? sanitize_text_field( wp_unslash( $_POST['scf_payment_note'] ) ) : '';
		$matched_product_id = isset( $_POST['scf_matched_product_id'] ) ? absint( wp_unslash( $_POST['scf_matched_product_id'] ) ) : 0;
		$payment_method     = isset( $_POST['scf_payment_method'] ) ? sanitize_text_field( wp_unslash( $_POST['scf_payment_method'] ) ) : 'wechat';

		if ( $amount <= 0 ) {
			wc_add_notice( __( '请输入有效的支付金额。', 'shop-custom-features' ), 'error' );
			return;
		}

		$product = null;

		if ( $matched_product_id ) {
			$candidate = wc_get_product( $matched_product_id );
			if ( $candidate && (float) wc_format_decimal( $candidate->get_price(), wc_get_price_decimals() ) === (float) wc_format_decimal( $amount, wc_get_price_decimals() ) ) {
				$product = $candidate;
			}
		}

		if ( ! $product ) {
			$product = $this->find_product_by_amount( $amount );
		}

		WC()->cart->empty_cart();

		$cart_item_data = array();

		if ( $note ) {
			$cart_item_data['scf_payment_note'] = $note;
		}

		if ( $payment_method ) {
			$cart_item_data[ self::PAYMENT_METHOD_KEY ] = $payment_method;
			WC()->session->set( 'scf_preferred_payment_method', $payment_method );
		}

		if ( $product ) {
			$product_id   = $product->get_id();
			$variation_id = 0;
			$variation    = array();

			if ( $product->is_type( 'variation' ) ) {
				$variation_id = $product->get_id();
				$product_id   = $product->get_parent_id();
				$variation    = $product->get_variation_attributes();
			}

			$added = WC()->cart->add_to_cart( $product_id, 1, $variation_id, $variation, $cart_item_data );
		} else {
			$fallback_id = $this->get_payment_product_id();

			if ( ! $fallback_id ) {
				$fallback_id = SCF_Install::ensure_payment_product();
			}

			if ( ! $fallback_id ) {
				wc_add_notice( __( '未找到匹配商品，请联系管理员。', 'shop-custom-features' ), 'error' );
				return;
			}

			$cart_item_data[ self::CART_META_KEY ] = $amount;
			$added                                 = WC()->cart->add_to_cart( $fallback_id, 1, 0, array(), $cart_item_data );
		}

		if ( ! $added ) {
			wc_add_notice( __( '无法添加到购物车，请重试。', 'shop-custom-features' ), 'error' );
			return;
		}

		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	/**
	 * Save preferred payment method on order.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $data  Checkout data.
	 */
	public function save_payment_method_on_order( $order, $data ) {
		$method = WC()->session ? WC()->session->get( 'scf_preferred_payment_method' ) : '';

		if ( $method ) {
			$order->update_meta_data( '_scf_preferred_payment_method', $method );
			$order->save();
		}
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

		if ( isset( $values[ self::PAYMENT_METHOD_KEY ] ) ) {
			$cart_item[ self::PAYMENT_METHOD_KEY ] = $values[ self::PAYMENT_METHOD_KEY ];
		}

		return $cart_item;
	}

	/**
	 * Apply custom price to fallback cart item.
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
	 * @param string $name          Item name.
	 * @param array  $cart_item     Cart item.
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
		}

		if ( ! empty( $cart_item['scf_payment_note'] ) ) {
			$name .= '<br><small>' . esc_html( $cart_item['scf_payment_note'] ) . '</small>';
		}

		return $name;
	}

	/**
	 * Display custom amount in order item name.
	 *
	 * @param string        $name Order item name.
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
	 * @param WC_Order_Item_Product $item          Order item.
	 * @param string                $cart_item_key Cart item key.
	 * @param array                 $values        Cart values.
	 * @param WC_Order              $order         Order.
	 */
	public function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
		if ( isset( $values[ self::CART_META_KEY ] ) ) {
			$item->add_meta_data( '_scf_custom_amount', $values[ self::CART_META_KEY ], true );
		}

		if ( ! empty( $values['scf_payment_note'] ) ) {
			$item->add_meta_data( '_scf_payment_note', $values['scf_payment_note'], true );
		}

		if ( ! empty( $values[ self::PAYMENT_METHOD_KEY ] ) ) {
			$item->add_meta_data( '_scf_preferred_payment_method', $values[ self::PAYMENT_METHOD_KEY ], true );
		}
	}
}
