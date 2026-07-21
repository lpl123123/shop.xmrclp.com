<?php
/**
 * Plugin Name: Shop Custom Features
 * Description: 聊天功能、商家收款码绑定、自定义金额在线支付
 * Version: 1.2.0
 * Author: Shop
 * Text Domain: shop-custom-features
 * Requires Plugins: woocommerce
 *
 * @package ShopCustomFeatures
 */

defined( 'ABSPATH' ) || exit;

define( 'SCF_VERSION', '1.2.0' );
define( 'SCF_PLUGIN_FILE', __FILE__ );
define( 'SCF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SCF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once SCF_PLUGIN_DIR . 'includes/class-scf-install.php';
require_once SCF_PLUGIN_DIR . 'includes/class-scf-merchant.php';
require_once SCF_PLUGIN_DIR . 'includes/class-scf-chat.php';
require_once SCF_PLUGIN_DIR . 'includes/class-scf-custom-payment.php';

/**
 * Bootstrap plugin after WooCommerce loads.
 */
function scf_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action(
			'admin_notices',
			static function () {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Shop Custom Features 需要安装并启用 WooCommerce。', 'shop-custom-features' ) . '</p></div>';
			}
		);
		return;
	}

	SCF_Merchant::instance();
	SCF_Chat::instance();
	SCF_Custom_Payment::instance();
}
add_action( 'plugins_loaded', 'scf_init' );

register_activation_hook( __FILE__, array( 'SCF_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SCF_Install', 'deactivate' ) );
