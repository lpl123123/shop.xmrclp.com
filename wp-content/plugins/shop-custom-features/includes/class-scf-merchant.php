<?php
/**
 * Merchant entity and product QR code binding.
 *
 * @package ShopCustomFeatures
 */

defined( 'ABSPATH' ) || exit;

/**
 * Merchant custom post type and product integration.
 */
class SCF_Merchant {

	/**
	 * Singleton instance.
	 *
	 * @var SCF_Merchant|null
	 */
	private static $instance = null;

	/**
	 * Post type slug.
	 */
	const POST_TYPE = 'shop_merchant';

	/**
	 * Get singleton instance.
	 *
	 * @return SCF_Merchant
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
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_merchant_meta' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_product_meta_box' ) );
		add_action( 'save_post_product', array( $this, 'save_product_merchant' ) );
		add_action( 'woocommerce_single_product_summary', array( $this, 'display_qr_code' ), 35 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue merchant frontend styles on product pages.
	 */
	public function enqueue_assets() {
		if ( ! is_product() ) {
			return;
		}

		wp_enqueue_style(
			'scf-merchant',
			SCF_PLUGIN_URL . 'assets/css/merchant.css',
			array(),
			SCF_VERSION
		);
	}

	/**
	 * Register merchant post type.
	 */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'               => __( '商家', 'shop-custom-features' ),
					'singular_name'      => __( '商家', 'shop-custom-features' ),
					'add_new'            => __( '添加商家', 'shop-custom-features' ),
					'add_new_item'       => __( '添加新商家', 'shop-custom-features' ),
					'edit_item'          => __( '编辑商家', 'shop-custom-features' ),
					'new_item'           => __( '新商家', 'shop-custom-features' ),
					'view_item'          => __( '查看商家', 'shop-custom-features' ),
					'search_items'       => __( '搜索商家', 'shop-custom-features' ),
					'not_found'          => __( '未找到商家', 'shop-custom-features' ),
					'not_found_in_trash' => __( '回收站中未找到商家', 'shop-custom-features' ),
					'menu_name'          => __( '商家管理', 'shop-custom-features' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'menu_icon'           => 'dashicons-store',
				'menu_position'       => 56,
				'supports'            => array( 'title', 'thumbnail' ),
				'capability_type'     => 'post',
				'has_archive'         => false,
				'rewrite'             => false,
			)
		);
	}

	/**
	 * Add merchant meta boxes.
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'scf_merchant_details',
			__( '商家信息', 'shop-custom-features' ),
			array( $this, 'render_merchant_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render merchant meta box.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function render_merchant_meta_box( $post ) {
		wp_nonce_field( 'scf_save_merchant', 'scf_merchant_nonce' );

		$contact = get_post_meta( $post->ID, '_scf_merchant_contact', true );
		$note    = get_post_meta( $post->ID, '_scf_merchant_note', true );
		?>
		<p>
			<label for="scf_merchant_contact"><strong><?php esc_html_e( '联系方式', 'shop-custom-features' ); ?></strong></label><br>
			<input type="text" id="scf_merchant_contact" name="scf_merchant_contact" value="<?php echo esc_attr( $contact ); ?>" class="widefat">
		</p>
		<p>
			<label for="scf_merchant_note"><strong><?php esc_html_e( '备注说明', 'shop-custom-features' ); ?></strong></label><br>
			<textarea id="scf_merchant_note" name="scf_merchant_note" class="widefat" rows="3"><?php echo esc_textarea( $note ); ?></textarea>
		</p>
		<p class="description">
			<?php esc_html_e( '请在右侧「特色图片」处上传商家实体收款码图片。', 'shop-custom-features' ); ?>
		</p>
		<?php
	}

	/**
	 * Save merchant meta.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_merchant_meta( $post_id ) {
		if ( ! isset( $_POST['scf_merchant_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['scf_merchant_nonce'] ) ), 'scf_save_merchant' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['scf_merchant_contact'] ) ) {
			update_post_meta( $post_id, '_scf_merchant_contact', sanitize_text_field( wp_unslash( $_POST['scf_merchant_contact'] ) ) );
		}

		if ( isset( $_POST['scf_merchant_note'] ) ) {
			update_post_meta( $post_id, '_scf_merchant_note', sanitize_textarea_field( wp_unslash( $_POST['scf_merchant_note'] ) ) );
		}
	}

	/**
	 * Add product merchant selector meta box.
	 */
	public function add_product_meta_box() {
		add_meta_box(
			'scf_product_merchant',
			__( '绑定商家收款码', 'shop-custom-features' ),
			array( $this, 'render_product_meta_box' ),
			'product',
			'side',
			'default'
		);
	}

	/**
	 * Render product merchant meta box.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function render_product_meta_box( $post ) {
		wp_nonce_field( 'scf_save_product_merchant', 'scf_product_merchant_nonce' );

		$selected = (int) get_post_meta( $post->ID, '_scf_merchant_id', true );
		$merchants = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		?>
		<p>
			<select name="scf_merchant_id" id="scf_merchant_id" class="widefat">
				<option value=""><?php esc_html_e( '— 不绑定 —', 'shop-custom-features' ); ?></option>
				<?php foreach ( $merchants as $merchant ) : ?>
					<option value="<?php echo esc_attr( $merchant->ID ); ?>" <?php selected( $selected, $merchant->ID ); ?>>
						<?php echo esc_html( $merchant->post_title ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="description">
			<?php esc_html_e( '选择商家后，商品详情页将展示该商家的收款码。', 'shop-custom-features' ); ?>
		</p>
		<?php
	}

	/**
	 * Save product merchant binding.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_product_merchant( $post_id ) {
		if ( ! isset( $_POST['scf_product_merchant_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['scf_product_merchant_nonce'] ) ), 'scf_save_product_merchant' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$merchant_id = isset( $_POST['scf_merchant_id'] ) ? absint( $_POST['scf_merchant_id'] ) : 0;

		if ( $merchant_id > 0 ) {
			update_post_meta( $post_id, '_scf_merchant_id', $merchant_id );
		} else {
			delete_post_meta( $post_id, '_scf_merchant_id' );
		}
	}

	/**
	 * Display merchant QR code on product page.
	 */
	public function display_qr_code() {
		global $product;

		if ( ! $product ) {
			return;
		}

		$merchant_id = (int) get_post_meta( $product->get_id(), '_scf_merchant_id', true );

		if ( ! $merchant_id ) {
			return;
		}

		$merchant = get_post( $merchant_id );

		if ( ! $merchant || 'publish' !== $merchant->post_status ) {
			return;
		}

		$qr_url  = get_the_post_thumbnail_url( $merchant_id, 'medium' );
		$contact = get_post_meta( $merchant_id, '_scf_merchant_contact', true );
		$note    = get_post_meta( $merchant_id, '_scf_merchant_note', true );

		if ( ! $qr_url ) {
			return;
		}

		?>
		<div class="scf-merchant-qrcode">
			<h4 class="scf-merchant-qrcode__title"><?php esc_html_e( '商家收款码', 'shop-custom-features' ); ?></h4>
			<p class="scf-merchant-qrcode__name">
				<strong><?php echo esc_html( $merchant->post_title ); ?></strong>
			</p>
			<div class="scf-merchant-qrcode__image">
				<img src="<?php echo esc_url( $qr_url ); ?>" alt="<?php echo esc_attr( $merchant->post_title ); ?>">
			</div>
			<?php if ( $contact ) : ?>
				<p class="scf-merchant-qrcode__contact"><?php echo esc_html( $contact ); ?></p>
			<?php endif; ?>
			<?php if ( $note ) : ?>
				<p class="scf-merchant-qrcode__note"><?php echo esc_html( $note ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Get merchant by product ID.
	 *
	 * @param int $product_id Product ID.
	 * @return WP_Post|null
	 */
	public static function get_merchant_for_product( $product_id ) {
		$merchant_id = (int) get_post_meta( $product_id, '_scf_merchant_id', true );

		if ( ! $merchant_id ) {
			return null;
		}

		$merchant = get_post( $merchant_id );

		if ( ! $merchant || 'publish' !== $merchant->post_status ) {
			return null;
		}

		return $merchant;
	}
}
