<?php
/**
 * Plugin installation and database setup.
 *
 * @package ShopCustomFeatures
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles activation tasks.
 */
class SCF_Install {

	/**
	 * Run on plugin activation.
	 */
	public static function activate() {
		self::create_chat_table();
		self::ensure_payment_product();
		flush_rewrite_rules();
	}

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Create chat messages table.
	 */
	public static function create_chat_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'scf_chat_messages';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_id varchar(64) NOT NULL,
			sender_type varchar(20) NOT NULL DEFAULT 'visitor',
			sender_name varchar(191) NOT NULL DEFAULT '',
			sender_email varchar(191) NOT NULL DEFAULT '',
			message text NOT NULL,
			is_read tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY session_id (session_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create hidden product used for custom amount checkout.
	 *
	 * @return int Product ID.
	 */
	public static function ensure_payment_product() {
		$product_id = (int) get_option( 'scf_custom_payment_product_id', 0 );

		if ( $product_id && 'product' === get_post_type( $product_id ) ) {
			return $product_id;
		}

		$product_id = wp_insert_post(
			array(
				'post_title'   => __( '在线支付', 'shop-custom-features' ),
				'post_content' => '',
				'post_status'  => 'publish',
				'post_type'    => 'product',
			),
			true
		);

		if ( is_wp_error( $product_id ) ) {
			return 0;
		}

		wp_set_object_terms( $product_id, 'simple', 'product_type' );
		update_post_meta( $product_id, '_visibility', 'hidden' );
		update_post_meta( $product_id, '_virtual', 'yes' );
		update_post_meta( $product_id, '_sold_individually', 'yes' );
		update_post_meta( $product_id, '_regular_price', '0' );
		update_post_meta( $product_id, '_price', '0' );
		update_post_meta( $product_id, '_stock_status', 'instock' );
		update_post_meta( $product_id, '_manage_stock', 'no' );

		update_option( 'scf_custom_payment_product_id', $product_id );

		return $product_id;
	}
}
