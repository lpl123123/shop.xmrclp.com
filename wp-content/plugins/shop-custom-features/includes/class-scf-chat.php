<?php
/**
 * Chat functionality with editable messages.
 *
 * @package ShopCustomFeatures
 */

defined( 'ABSPATH' ) || exit;

/**
 * Frontend chat widget and admin message management.
 */
class SCF_Chat {

	/**
	 * Singleton instance.
	 *
	 * @var SCF_Chat|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return SCF_Chat
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
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_scf_update_chat_message', array( $this, 'handle_update_message' ) );
		add_action( 'admin_post_scf_delete_chat_message', array( $this, 'handle_delete_message' ) );
		add_action( 'admin_post_scf_reply_chat_message', array( $this, 'handle_admin_reply' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_chat_widget' ) );

		add_action( 'wp_ajax_scf_send_chat_message', array( $this, 'ajax_send_message' ) );
		add_action( 'wp_ajax_nopriv_scf_send_chat_message', array( $this, 'ajax_send_message' ) );
		add_action( 'wp_ajax_scf_fetch_chat_messages', array( $this, 'ajax_fetch_messages' ) );
		add_action( 'wp_ajax_nopriv_scf_fetch_chat_messages', array( $this, 'ajax_fetch_messages' ) );
	}

	/**
	 * Get chat messages table name.
	 *
	 * @return string
	 */
	private function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'scf_chat_messages';
	}

	/**
	 * Register admin menu pages.
	 */
	public function register_admin_menu() {
		add_menu_page(
			__( '在线聊天', 'shop-custom-features' ),
			__( '在线聊天', 'shop-custom-features' ),
			'manage_options',
			'scf-chat',
			array( $this, 'render_messages_page' ),
			'dashicons-format-chat',
			57
		);

		add_submenu_page(
			'scf-chat',
			__( '聊天消息', 'shop-custom-features' ),
			__( '聊天消息', 'shop-custom-features' ),
			'manage_options',
			'scf-chat',
			array( $this, 'render_messages_page' )
		);

		add_submenu_page(
			'scf-chat',
			__( '聊天设置', 'shop-custom-features' ),
			__( '聊天设置', 'shop-custom-features' ),
			'manage_options',
			'scf-chat-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings() {
		register_setting( 'scf_chat_settings', 'scf_chat_enabled', array( 'type' => 'boolean', 'default' => true ) );
		register_setting( 'scf_chat_settings', 'scf_chat_title', array( 'type' => 'string', 'default' => __( '在线客服', 'shop-custom-features' ) ) );
		register_setting( 'scf_chat_settings', 'scf_chat_welcome', array( 'type' => 'string', 'default' => __( '您好！有什么可以帮您的吗？', 'shop-custom-features' ) ) );
		register_setting( 'scf_chat_settings', 'scf_chat_placeholder', array( 'type' => 'string', 'default' => __( '请输入消息...', 'shop-custom-features' ) ) );
	}

	/**
	 * Enqueue frontend assets.
	 */
	public function enqueue_assets() {
		if ( ! $this->is_chat_enabled() || is_admin() ) {
			return;
		}

		wp_enqueue_style(
			'scf-chat',
			SCF_PLUGIN_URL . 'assets/css/chat.css',
			array(),
			SCF_VERSION
		);

		wp_enqueue_script(
			'scf-chat',
			SCF_PLUGIN_URL . 'assets/js/chat.js',
			array(),
			SCF_VERSION,
			true
		);

		wp_localize_script(
			'scf-chat',
			'scfChat',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'scf_chat_nonce' ),
				'title'       => get_option( 'scf_chat_title', __( '在线客服', 'shop-custom-features' ) ),
				'welcome'     => get_option( 'scf_chat_welcome', __( '您好！有什么可以帮您的吗？', 'shop-custom-features' ) ),
				'placeholder' => get_option( 'scf_chat_placeholder', __( '请输入消息...', 'shop-custom-features' ) ),
				'sessionId'   => $this->get_session_id(),
			)
		);
	}

	/**
	 * Check if chat is enabled.
	 *
	 * @return bool
	 */
	private function is_chat_enabled() {
		return (bool) get_option( 'scf_chat_enabled', true );
	}

	/**
	 * Get or create visitor session ID.
	 *
	 * @return string
	 */
	private function get_session_id() {
		if ( isset( $_COOKIE['scf_chat_session'] ) ) {
			$session = sanitize_text_field( wp_unslash( $_COOKIE['scf_chat_session'] ) );
			if ( preg_match( '/^[a-f0-9]{32}$/', $session ) ) {
				return $session;
			}
		}

		$session = md5( uniqid( 'scf_', true ) );

		if ( ! headers_sent() ) {
			setcookie( 'scf_chat_session', $session, time() + MONTH_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
		}

		return $session;
	}

	/**
	 * Render chat widget in footer.
	 */
	public function render_chat_widget() {
		if ( ! $this->is_chat_enabled() || is_admin() ) {
			return;
		}
		?>
		<div id="scf-chat-root" aria-live="polite"></div>
		<?php
	}

	/**
	 * AJAX: send chat message.
	 */
	public function ajax_send_message() {
		check_ajax_referer( 'scf_chat_nonce', 'nonce' );

		$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
		$message    = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$name       = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		if ( ! preg_match( '/^[a-f0-9]{32}$/', $session_id ) ) {
			wp_send_json_error( array( 'message' => __( '会话无效', 'shop-custom-features' ) ), 400 );
		}

		if ( '' === trim( $message ) ) {
			wp_send_json_error( array( 'message' => __( '消息不能为空', 'shop-custom-features' ) ), 400 );
		}

		if ( '' === $name ) {
			$name = __( '访客', 'shop-custom-features' );
		}

		global $wpdb;

		$inserted = $wpdb->insert(
			$this->get_table_name(),
			array(
				'session_id'  => $session_id,
				'sender_type' => 'visitor',
				'sender_name' => $name,
				'sender_email'=> $email,
				'message'     => $message,
				'is_read'     => 0,
				'created_at'  => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( ! $inserted ) {
			wp_send_json_error( array( 'message' => __( '发送失败，请重试', 'shop-custom-features' ) ), 500 );
		}

		wp_send_json_success(
			array(
				'id'      => (int) $wpdb->insert_id,
				'message' => $this->format_message_row( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $this->get_table_name() . ' WHERE id = %d', $wpdb->insert_id ) ) ),
			)
		);
	}

	/**
	 * AJAX: fetch chat messages for session.
	 */
	public function ajax_fetch_messages() {
		check_ajax_referer( 'scf_chat_nonce', 'nonce' );

		$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
		$after_id   = isset( $_POST['after_id'] ) ? absint( $_POST['after_id'] ) : 0;

		if ( ! preg_match( '/^[a-f0-9]{32}$/', $session_id ) ) {
			wp_send_json_error( array( 'message' => __( '会话无效', 'shop-custom-features' ) ), 400 );
		}

		global $wpdb;
		$table = $this->get_table_name();

		if ( $after_id > 0 ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE session_id = %s AND id > %d ORDER BY id ASC",
					$session_id,
					$after_id
				)
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE session_id = %s ORDER BY id ASC",
					$session_id
				)
			);
		}

		$messages = array_map( array( $this, 'format_message_row' ), $rows ? $rows : array() );

		wp_send_json_success( array( 'messages' => $messages ) );
	}

	/**
	 * Format message row for JSON output.
	 *
	 * @param object|null $row Database row.
	 * @return array|null
	 */
	private function format_message_row( $row ) {
		if ( ! $row ) {
			return null;
		}

		return array(
			'id'          => (int) $row->id,
			'sender_type' => $row->sender_type,
			'sender_name' => $row->sender_name,
			'message'     => $row->message,
			'created_at'  => mysql2date( 'Y-m-d H:i', $row->created_at ),
		);
	}

	/**
	 * Render admin messages list page.
	 */
	public function render_messages_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;
		$table = $this->get_table_name();

		$session_filter = isset( $_GET['session_id'] ) ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) : '';
		$edit_id        = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;

		if ( $edit_id ) {
			$this->render_edit_message_page( $edit_id );
			return;
		}

		if ( $session_filter ) {
			$messages = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE session_id = %s ORDER BY id ASC",
					$session_filter
				)
			);
		} else {
			$messages = $wpdb->get_results(
				"SELECT m.* FROM {$table} m
				INNER JOIN (
					SELECT session_id, MAX(id) AS max_id
					FROM {$table}
					GROUP BY session_id
				) latest ON m.id = latest.max_id
				ORDER BY m.created_at DESC
				LIMIT 100"
			);
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( '聊天消息管理', 'shop-custom-features' ); ?></h1>

			<?php if ( $session_filter ) : ?>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=scf-chat' ) ); ?>">&larr; <?php esc_html_e( '返回会话列表', 'shop-custom-features' ); ?></a>
				</p>

				<h2><?php esc_html_e( '会话详情', 'shop-custom-features' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:20px;">
					<?php wp_nonce_field( 'scf_admin_reply', 'scf_admin_reply_nonce' ); ?>
					<input type="hidden" name="action" value="scf_reply_chat_message">
					<input type="hidden" name="session_id" value="<?php echo esc_attr( $session_filter ); ?>">
					<p>
						<label for="scf_admin_reply"><strong><?php esc_html_e( '回复访客', 'shop-custom-features' ); ?></strong></label><br>
						<textarea id="scf_admin_reply" name="message" rows="3" class="large-text" required></textarea>
					</p>
					<?php submit_button( __( '发送回复', 'shop-custom-features' ) ); ?>
				</form>

				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( '时间', 'shop-custom-features' ); ?></th>
							<th><?php esc_html_e( '发送者', 'shop-custom-features' ); ?></th>
							<th><?php esc_html_e( '消息内容', 'shop-custom-features' ); ?></th>
							<th><?php esc_html_e( '操作', 'shop-custom-features' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $messages ) ) : ?>
							<tr><td colspan="4"><?php esc_html_e( '暂无消息', 'shop-custom-features' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $messages as $message ) : ?>
								<tr>
									<td><?php echo esc_html( mysql2date( 'Y-m-d H:i', $message->created_at ) ); ?></td>
									<td>
										<?php echo esc_html( $message->sender_name ); ?>
										<?php if ( 'admin' === $message->sender_type ) : ?>
											<span class="description">(<?php esc_html_e( '客服', 'shop-custom-features' ); ?>)</span>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( $message->message ); ?></td>
									<td>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=scf-chat&session_id=' . rawurlencode( $session_filter ) . '&edit=' . $message->id ) ); ?>"><?php esc_html_e( '编辑', 'shop-custom-features' ); ?></a>
										|
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=scf_delete_chat_message&id=' . $message->id . '&session_id=' . rawurlencode( $session_filter ) ), 'scf_delete_message_' . $message->id ) ); ?>" onclick="return confirm('<?php echo esc_js( __( '确定删除此消息？', 'shop-custom-features' ) ); ?>');"><?php esc_html_e( '删除', 'shop-custom-features' ); ?></a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p class="description"><?php esc_html_e( '点击会话查看详情，可在后台编辑或回复聊天内容。', 'shop-custom-features' ); ?></p>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( '最近消息', 'shop-custom-features' ); ?></th>
							<th><?php esc_html_e( '访客', 'shop-custom-features' ); ?></th>
							<th><?php esc_html_e( '时间', 'shop-custom-features' ); ?></th>
							<th><?php esc_html_e( '操作', 'shop-custom-features' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $messages ) ) : ?>
							<tr><td colspan="4"><?php esc_html_e( '暂无聊天记录', 'shop-custom-features' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $messages as $message ) : ?>
								<tr>
									<td><?php echo esc_html( wp_trim_words( $message->message, 12, '...' ) ); ?></td>
									<td><?php echo esc_html( $message->sender_name ); ?></td>
									<td><?php echo esc_html( mysql2date( 'Y-m-d H:i', $message->created_at ) ); ?></td>
									<td>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=scf-chat&session_id=' . rawurlencode( $message->session_id ) ) ); ?>"><?php esc_html_e( '查看会话', 'shop-custom-features' ); ?></a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render message edit page.
	 *
	 * @param int $message_id Message ID.
	 */
	private function render_edit_message_page( $message_id ) {
		global $wpdb;
		$table = $this->get_table_name();

		$message = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $message_id ) );

		if ( ! $message ) {
			echo '<div class="wrap"><p>' . esc_html__( '消息不存在', 'shop-custom-features' ) . '</p></div>';
			return;
		}

		$session_id = isset( $_GET['session_id'] ) ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) : $message->session_id;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( '编辑聊天消息', 'shop-custom-features' ); ?></h1>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=scf-chat&session_id=' . rawurlencode( $session_id ) ) ); ?>">&larr; <?php esc_html_e( '返回会话', 'shop-custom-features' ); ?></a>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'scf_update_message_' . $message_id, 'scf_update_message_nonce' ); ?>
				<input type="hidden" name="action" value="scf_update_chat_message">
				<input type="hidden" name="message_id" value="<?php echo esc_attr( $message_id ); ?>">
				<input type="hidden" name="session_id" value="<?php echo esc_attr( $session_id ); ?>">
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( '发送者', 'shop-custom-features' ); ?></th>
						<td><?php echo esc_html( $message->sender_name ); ?> (<?php echo esc_html( $message->sender_type ); ?>)</td>
					</tr>
					<tr>
						<th><label for="scf_message_content"><?php esc_html_e( '消息内容', 'shop-custom-features' ); ?></label></th>
						<td>
							<textarea id="scf_message_content" name="message" rows="6" class="large-text" required><?php echo esc_textarea( $message->message ); ?></textarea>
						</td>
					</tr>
				</table>
				<?php submit_button( __( '保存修改', 'shop-custom-features' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render chat settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( '聊天设置', 'shop-custom-features' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'scf_chat_settings' );
				?>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( '启用聊天', 'shop-custom-features' ); ?></th>
						<td>
							<input type="hidden" name="scf_chat_enabled" value="0">
							<label>
								<input type="checkbox" name="scf_chat_enabled" value="1" <?php checked( get_option( 'scf_chat_enabled', true ) ); ?>>
								<?php esc_html_e( '在前台显示聊天窗口', 'shop-custom-features' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><label for="scf_chat_title"><?php esc_html_e( '窗口标题', 'shop-custom-features' ); ?></label></th>
						<td><input type="text" id="scf_chat_title" name="scf_chat_title" value="<?php echo esc_attr( get_option( 'scf_chat_title', __( '在线客服', 'shop-custom-features' ) ) ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th><label for="scf_chat_welcome"><?php esc_html_e( '欢迎语', 'shop-custom-features' ); ?></label></th>
						<td><textarea id="scf_chat_welcome" name="scf_chat_welcome" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'scf_chat_welcome', __( '您好！有什么可以帮您的吗？', 'shop-custom-features' ) ) ); ?></textarea></td>
					</tr>
					<tr>
						<th><label for="scf_chat_placeholder"><?php esc_html_e( '输入框提示', 'shop-custom-features' ); ?></label></th>
						<td><input type="text" id="scf_chat_placeholder" name="scf_chat_placeholder" value="<?php echo esc_attr( get_option( 'scf_chat_placeholder', __( '请输入消息...', 'shop-custom-features' ) ) ); ?>" class="regular-text"></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle admin message update.
	 */
	public function handle_update_message() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '权限不足', 'shop-custom-features' ) );
		}

		$message_id = isset( $_POST['message_id'] ) ? absint( $_POST['message_id'] ) : 0;
		$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
		$content    = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		check_admin_referer( 'scf_update_message_' . $message_id, 'scf_update_message_nonce' );

		global $wpdb;

		$wpdb->update(
			$this->get_table_name(),
			array(
				'message'    => $content,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $message_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		wp_safe_redirect( admin_url( 'admin.php?page=scf-chat&session_id=' . rawurlencode( $session_id ) . '&updated=1' ) );
		exit;
	}

	/**
	 * Handle admin message delete.
	 */
	public function handle_delete_message() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '权限不足', 'shop-custom-features' ) );
		}

		$message_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$session_id = isset( $_GET['session_id'] ) ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) : '';

		check_admin_referer( 'scf_delete_message_' . $message_id );

		global $wpdb;
		$wpdb->delete( $this->get_table_name(), array( 'id' => $message_id ), array( '%d' ) );

		wp_safe_redirect( admin_url( 'admin.php?page=scf-chat&session_id=' . rawurlencode( $session_id ) . '&deleted=1' ) );
		exit;
	}

	/**
	 * Handle admin reply to visitor.
	 */
	public function handle_admin_reply() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '权限不足', 'shop-custom-features' ) );
		}

		check_admin_referer( 'scf_admin_reply', 'scf_admin_reply_nonce' );

		$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
		$message    = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		if ( ! preg_match( '/^[a-f0-9]{32}$/', $session_id ) || '' === trim( $message ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=scf-chat' ) );
			exit;
		}

		$user = wp_get_current_user();

		global $wpdb;
		$wpdb->insert(
			$this->get_table_name(),
			array(
				'session_id'  => $session_id,
				'sender_type' => 'admin',
				'sender_name' => $user->display_name ? $user->display_name : __( '客服', 'shop-custom-features' ),
				'sender_email'=> $user->user_email,
				'message'     => $message,
				'is_read'     => 1,
				'created_at'  => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		wp_safe_redirect( admin_url( 'admin.php?page=scf-chat&session_id=' . rawurlencode( $session_id ) . '&replied=1' ) );
		exit;
	}
}
