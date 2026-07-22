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
		add_filter( 'wp_viewport_meta_tag', array( $this, 'filter_viewport_meta' ) );

		add_action( 'wp_ajax_scf_send_chat_message', array( $this, 'ajax_send_message' ) );
		add_action( 'wp_ajax_nopriv_scf_send_chat_message', array( $this, 'ajax_send_message' ) );
		add_action( 'wp_ajax_scf_fetch_chat_messages', array( $this, 'ajax_fetch_messages' ) );
		add_action( 'wp_ajax_nopriv_scf_fetch_chat_messages', array( $this, 'ajax_fetch_messages' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_scf_admin_fetch_session_messages', array( $this, 'ajax_admin_fetch_session_messages' ) );
		add_action( 'wp_ajax_scf_admin_send_reply', array( $this, 'ajax_admin_send_reply' ) );
		add_action( 'wp_ajax_scf_admin_fetch_sessions', array( $this, 'ajax_admin_fetch_sessions' ) );
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
	 * Improve mobile keyboard behavior by resizing the layout viewport.
	 *
	 * @param string $viewport Meta tag content attribute.
	 * @return string
	 */
	public function filter_viewport_meta( $viewport ) {
		if ( ! $this->is_chat_enabled() || is_admin() ) {
			return $viewport;
		}

		if ( false !== strpos( $viewport, 'interactive-widget' ) ) {
			return $viewport;
		}

		return $viewport . ', interactive-widget=resizes-content';
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
	 * Enqueue admin chat management assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'toplevel_page_scf-chat' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'scf-chat-admin',
			SCF_PLUGIN_URL . 'assets/css/chat-admin.css',
			array(),
			SCF_VERSION
		);

		wp_enqueue_script(
			'scf-chat-admin',
			SCF_PLUGIN_URL . 'assets/js/chat-admin.js',
			array( 'jquery' ),
			SCF_VERSION,
			true
		);

		$session_filter = isset( $_GET['session_id'] ) ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) : '';
		$session_mode   = ( isset( $_GET['mode'] ) && 'edit' === sanitize_text_field( wp_unslash( $_GET['mode'] ) ) ) ? 'edit' : 'view';
		$last_id        = 0;
		$delete_nonces  = array();

		if ( $session_filter && ! isset( $_GET['edit'] ) ) {
			global $wpdb;
			$table = $this->get_table_name();
			$last_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT MAX(id) FROM {$table} WHERE session_id = %s",
					$session_filter
				)
			);

			if ( 'edit' === $session_mode ) {
				$session_messages = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT id FROM {$table} WHERE session_id = %s",
						$session_filter
					)
				);

				foreach ( $session_messages ? $session_messages : array() as $message ) {
					$delete_nonces[ $message->id ] = wp_create_nonce( 'scf_delete_message_' . $message->id );
				}
			}
		}

		$session_query_args = array(
			'page'       => 'scf-chat',
			'session_id' => '__SESSION__',
		);

		wp_localize_script(
			'scf-chat-admin',
			'scfChatAdmin',
			array(
				'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
				'nonce'              => wp_create_nonce( 'scf_chat_admin_nonce' ),
				'sessionId'          => $session_filter,
				'sessionMode'        => $session_mode,
				'lastId'             => $last_id,
				'sessionUrlBase'     => add_query_arg( $session_query_args, admin_url( 'admin.php' ) ),
				'sessionEditUrlBase' => add_query_arg( array_merge( $session_query_args, array( 'mode' => 'edit' ) ), admin_url( 'admin.php' ) ),
				'editUrlBase'        => add_query_arg(
					array_merge(
						$session_query_args,
						array(
							'mode' => 'edit',
							'edit' => '__ID__',
						)
					),
					admin_url( 'admin.php' )
				),
				'deleteUrlBase'      => admin_url( 'admin-post.php?action=scf_delete_chat_message&id=__ID__&session_id=__SESSION__&mode=edit&_wpnonce=__NONCE__' ),
				'deleteNonces'       => $delete_nonces,
				'labels'             => array(
					'admin'         => __( '客服', 'shop-custom-features' ),
					'edit'          => __( '编辑', 'shop-custom-features' ),
					'delete'        => __( '删除', 'shop-custom-features' ),
					'view'          => __( '查看会话', 'shop-custom-features' ),
					'editSession'   => __( '编辑会话', 'shop-custom-features' ),
					'confirmDelete' => __( '确定删除此消息？', 'shop-custom-features' ),
				),
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
	 * AJAX: admin fetch session messages with polling support.
	 */
	public function ajax_admin_fetch_session_messages() {
		check_ajax_referer( 'scf_chat_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '权限不足', 'shop-custom-features' ) ), 403 );
		}

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
	 * AJAX: admin send reply without page reload.
	 */
	public function ajax_admin_send_reply() {
		check_ajax_referer( 'scf_chat_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '权限不足', 'shop-custom-features' ) ), 403 );
		}

		$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
		$message    = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		if ( ! preg_match( '/^[a-f0-9]{32}$/', $session_id ) || '' === trim( $message ) ) {
			wp_send_json_error( array( 'message' => __( '参数无效', 'shop-custom-features' ) ), 400 );
		}

		$user = wp_get_current_user();

		global $wpdb;

		$inserted = $wpdb->insert(
			$this->get_table_name(),
			array(
				'session_id'   => $session_id,
				'sender_type'  => 'admin',
				'sender_name'  => $user->display_name ? $user->display_name : __( '客服', 'shop-custom-features' ),
				'sender_email' => $user->user_email,
				'message'      => $message,
				'is_read'      => 1,
				'created_at'   => current_time( 'mysql' ),
				'updated_at'   => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( ! $inserted ) {
			wp_send_json_error( array( 'message' => __( '发送失败', 'shop-custom-features' ) ), 500 );
		}

		wp_send_json_success(
			array(
				'message'      => $this->format_message_row(
					$wpdb->get_row(
						$wpdb->prepare(
							'SELECT * FROM ' . $this->get_table_name() . ' WHERE id = %d',
							$wpdb->insert_id
						)
					)
				),
				'delete_nonce' => wp_create_nonce( 'scf_delete_message_' . $wpdb->insert_id ),
			)
		);
	}

	/**
	 * AJAX: admin fetch latest sessions for list page.
	 */
	public function ajax_admin_fetch_sessions() {
		check_ajax_referer( 'scf_chat_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '权限不足', 'shop-custom-features' ) ), 403 );
		}

		global $wpdb;
		$table = $this->get_table_name();

		$rows = $wpdb->get_results(
			"SELECT m.* FROM {$table} m
			INNER JOIN (
				SELECT session_id, MAX(id) AS max_id
				FROM {$table}
				GROUP BY session_id
			) latest ON m.id = latest.max_id
			ORDER BY m.created_at DESC
			LIMIT 100"
		);

		$sessions = array();

		foreach ( $rows ? $rows : array() as $row ) {
			$sessions[] = array(
				'session_id'  => $row->session_id,
				'sender_name' => $row->sender_name,
				'preview'     => wp_trim_words( $row->message, 12, '...' ),
				'created_at'  => mysql2date( 'Y-m-d H:i', $row->created_at ),
			);
		}

		wp_send_json_success( array( 'sessions' => $sessions ) );
	}

	/**
	 * Parse datetime-local input into MySQL datetime.
	 *
	 * @param string $datetime Local datetime string.
	 * @return string|null
	 */
	private function parse_datetime_input( $datetime ) {
		$datetime = trim( $datetime );

		if ( '' === $datetime ) {
			return null;
		}

		$timestamp = strtotime( $datetime );

		if ( ! $timestamp ) {
			return null;
		}

		return wp_date( 'Y-m-d H:i:s', $timestamp );
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
		$session_mode   = ( isset( $_GET['mode'] ) && 'edit' === sanitize_text_field( wp_unslash( $_GET['mode'] ) ) ) ? 'edit' : 'view';
		$edit_id        = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
		$can_edit_msgs  = ( 'edit' === $session_mode );

		if ( $edit_id ) {
			if ( 'edit' !== $session_mode ) {
				wp_safe_redirect(
					admin_url(
						'admin.php?page=scf-chat&session_id=' . rawurlencode(
							isset( $_GET['session_id'] ) ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) : ''
						) . '&mode=edit&edit=' . $edit_id
					)
				);
				exit;
			}

			$this->render_edit_message_page( $edit_id, $session_mode );
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
				<div class="scf-admin-chat-wrap">
					<p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=scf-chat' ) ); ?>">&larr; <?php esc_html_e( '返回会话列表', 'shop-custom-features' ); ?></a>
						<span class="scf-admin-chat-status">
							<span class="scf-admin-chat-status__dot"></span>
							<?php esc_html_e( '实时同步中', 'shop-custom-features' ); ?>
						</span>
					</p>

					<h2>
						<?php echo $can_edit_msgs ? esc_html__( '编辑会话', 'shop-custom-features' ) : esc_html__( '查看会话', 'shop-custom-features' ); ?>
					</h2>

					<?php if ( $can_edit_msgs ) : ?>
						<p class="description"><?php esc_html_e( '可编辑或删除会话中的消息，也可回复访客。', 'shop-custom-features' ); ?></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( '只读查看会话消息，可回复访客。如需编辑或删除消息，请使用「编辑会话」。', 'shop-custom-features' ); ?></p>
					<?php endif; ?>

					<div id="scf-admin-chat-thread" class="scf-admin-chat-thread" data-mode="<?php echo esc_attr( $session_mode ); ?>">
						<?php if ( empty( $messages ) ) : ?>
							<p class="description"><?php esc_html_e( '暂无消息', 'shop-custom-features' ); ?></p>
						<?php else : ?>
							<?php foreach ( $messages as $message ) : ?>
								<div class="scf-admin-chat-bubble scf-admin-chat-bubble--<?php echo 'admin' === $message->sender_type ? 'admin' : 'visitor'; ?>" data-id="<?php echo esc_attr( $message->id ); ?>">
									<?php echo esc_html( $message->message ); ?>
									<div class="scf-admin-chat-bubble__meta">
										<span>
											<?php echo esc_html( $message->sender_name ); ?>
											<?php if ( 'admin' === $message->sender_type ) : ?>
												(<?php esc_html_e( '客服', 'shop-custom-features' ); ?>)
											<?php endif; ?>
											· <?php echo esc_html( mysql2date( 'Y-m-d H:i', $message->created_at ) ); ?>
										</span>
										<?php if ( $can_edit_msgs ) : ?>
											<span class="scf-admin-chat-bubble__actions">
												<a href="<?php echo esc_url( admin_url( 'admin.php?page=scf-chat&session_id=' . rawurlencode( $session_filter ) . '&mode=edit&edit=' . $message->id ) ); ?>"><?php esc_html_e( '编辑', 'shop-custom-features' ); ?></a>
												|
												<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=scf_delete_chat_message&id=' . $message->id . '&session_id=' . rawurlencode( $session_filter ) . '&mode=edit' ), 'scf_delete_message_' . $message->id ) ); ?>" onclick="return confirm('<?php echo esc_js( __( '确定删除此消息？', 'shop-custom-features' ) ); ?>');"><?php esc_html_e( '删除', 'shop-custom-features' ); ?></a>
											</span>
										<?php endif; ?>
									</div>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>

					<div class="scf-admin-chat-reply">
						<form id="scf-admin-reply-form">
							<label for="scf-admin-reply-input"><strong><?php esc_html_e( '回复访客', 'shop-custom-features' ); ?></strong></label>
							<textarea id="scf-admin-reply-input" rows="3" class="large-text" placeholder="<?php esc_attr_e( '输入回复内容...', 'shop-custom-features' ); ?>" required></textarea>
							<button type="submit" class="button button-primary" id="scf-admin-reply-btn"><?php esc_html_e( '发送回复', 'shop-custom-features' ); ?></button>
						</form>
					</div>
				</div>
			<?php else : ?>
				<p class="description">
					<?php esc_html_e( '查看会话用于只读浏览和回复；编辑会话可修改或删除消息。', 'shop-custom-features' ); ?>
					<span class="scf-admin-chat-status">
						<span class="scf-admin-chat-status__dot"></span>
						<?php esc_html_e( '实时同步中', 'shop-custom-features' ); ?>
					</span>
				</p>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( '最近消息', 'shop-custom-features' ); ?></th>
							<th><?php esc_html_e( '访客', 'shop-custom-features' ); ?></th>
							<th><?php esc_html_e( '时间', 'shop-custom-features' ); ?></th>
							<th><?php esc_html_e( '操作', 'shop-custom-features' ); ?></th>
						</tr>
					</thead>
					<tbody id="scf-admin-sessions-tbody">
						<?php if ( empty( $messages ) ) : ?>
							<tr><td colspan="4"><?php esc_html_e( '暂无聊天记录', 'shop-custom-features' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $messages as $message ) : ?>
								<tr data-session="<?php echo esc_attr( $message->session_id ); ?>">
									<td class="scf-session-preview"><?php echo esc_html( wp_trim_words( $message->message, 12, '...' ) ); ?></td>
									<td><?php echo esc_html( $message->sender_name ); ?></td>
									<td class="scf-session-time"><?php echo esc_html( mysql2date( 'Y-m-d H:i', $message->created_at ) ); ?></td>
									<td class="scf-session-actions">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=scf-chat&session_id=' . rawurlencode( $message->session_id ) ) ); ?>"><?php esc_html_e( '查看会话', 'shop-custom-features' ); ?></a>
										|
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=scf-chat&session_id=' . rawurlencode( $message->session_id ) . '&mode=edit' ) ); ?>"><?php esc_html_e( '编辑会话', 'shop-custom-features' ); ?></a>
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
	 * @param int    $message_id   Message ID.
	 * @param string $session_mode Session mode.
	 */
	private function render_edit_message_page( $message_id, $session_mode = 'edit' ) {
		global $wpdb;
		$table = $this->get_table_name();

		$message = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $message_id ) );

		if ( ! $message ) {
			echo '<div class="wrap"><p>' . esc_html__( '消息不存在', 'shop-custom-features' ) . '</p></div>';
			return;
		}

		$session_id = isset( $_GET['session_id'] ) ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) : $message->session_id;
		$back_url   = admin_url( 'admin.php?page=scf-chat&session_id=' . rawurlencode( $session_id ) . '&mode=edit' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( '编辑聊天消息', 'shop-custom-features' ); ?></h1>
			<p>
				<a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( '返回编辑会话', 'shop-custom-features' ); ?></a>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'scf_update_message_' . $message_id, 'scf_update_message_nonce' ); ?>
				<input type="hidden" name="action" value="scf_update_chat_message">
				<input type="hidden" name="message_id" value="<?php echo esc_attr( $message_id ); ?>">
				<input type="hidden" name="session_id" value="<?php echo esc_attr( $session_id ); ?>">
				<input type="hidden" name="session_mode" value="edit">
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( '发送者', 'shop-custom-features' ); ?></th>
						<td><?php echo esc_html( $message->sender_name ); ?> (<?php echo esc_html( $message->sender_type ); ?>)</td>
					</tr>
					<tr>
						<th><label for="scf_message_created_at"><?php esc_html_e( '消息时间', 'shop-custom-features' ); ?></label></th>
						<td>
							<input
								type="datetime-local"
								id="scf_message_created_at"
								name="created_at"
								value="<?php echo esc_attr( mysql2date( 'Y-m-d\TH:i', $message->created_at ) ); ?>"
								class="regular-text"
								required
							>
							<p class="description"><?php esc_html_e( '可修改消息显示的时间。', 'shop-custom-features' ); ?></p>
						</td>
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
		$created_at = isset( $_POST['created_at'] ) ? $this->parse_datetime_input( sanitize_text_field( wp_unslash( $_POST['created_at'] ) ) ) : null;

		check_admin_referer( 'scf_update_message_' . $message_id, 'scf_update_message_nonce' );

		global $wpdb;

		$update_data = array(
			'message'    => $content,
			'updated_at' => current_time( 'mysql' ),
		);
		$update_format = array( '%s', '%s' );

		if ( $created_at ) {
			$update_data['created_at'] = $created_at;
			$update_format[]           = '%s';
		}

		$wpdb->update(
			$this->get_table_name(),
			$update_data,
			array( 'id' => $message_id ),
			$update_format,
			array( '%d' )
		);

		$redirect_url = admin_url( 'admin.php?page=scf-chat&session_id=' . rawurlencode( $session_id ) . '&mode=edit&updated=1' );

		wp_safe_redirect( $redirect_url );
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
		$mode       = isset( $_GET['mode'] ) ? sanitize_text_field( wp_unslash( $_GET['mode'] ) ) : 'view';

		check_admin_referer( 'scf_delete_message_' . $message_id );

		global $wpdb;
		$wpdb->delete( $this->get_table_name(), array( 'id' => $message_id ), array( '%d' ) );

		$redirect_url = admin_url( 'admin.php?page=scf-chat&session_id=' . rawurlencode( $session_id ) );
		if ( 'edit' === $mode ) {
			$redirect_url = add_query_arg( 'mode', 'edit', $redirect_url );
		}
		$redirect_url = add_query_arg( 'deleted', '1', $redirect_url );

		wp_safe_redirect( $redirect_url );
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
