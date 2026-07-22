(function ($) {
	'use strict';

	if (typeof scfChatAdmin === 'undefined') {
		return;
	}

	var state = {
		lastId: scfChatAdmin.lastId || 0,
		polling: null,
		sending: false,
	};

	function escapeHtml(text) {
		return $('<div>').text(text).html();
	}

	function canEditMessages() {
		return scfChatAdmin.sessionMode === 'edit';
	}

	function scrollThreadToBottom() {
		var $thread = $('#scf-admin-chat-thread');
		if ($thread.length) {
			$thread.scrollTop($thread[0].scrollHeight);
		}
	}

	function renderBubble(msg) {
		var typeClass = msg.sender_type === 'admin' ? 'admin' : 'visitor';
		var senderLabel = msg.sender_type === 'admin'
			? escapeHtml(msg.sender_name) + ' (' + escapeHtml(scfChatAdmin.labels.admin) + ')'
			: escapeHtml(msg.sender_name);

		var actionsHtml = '';

		if (canEditMessages()) {
			var editUrl = scfChatAdmin.editUrlBase
				.replace('__SESSION__', encodeURIComponent(scfChatAdmin.sessionId))
				.replace('__ID__', msg.id);
			var deleteUrl = scfChatAdmin.deleteUrlBase
				.replace('__ID__', msg.id)
				.replace('__SESSION__', encodeURIComponent(scfChatAdmin.sessionId))
				.replace('__NONCE__', scfChatAdmin.deleteNonces[msg.id] || '');

			actionsHtml =
				'<span class="scf-admin-chat-bubble__actions">' +
					'<a href="' + editUrl + '">' + escapeHtml(scfChatAdmin.labels.edit) + '</a>' +
					(deleteUrl.indexOf('__NONCE__') === -1
						? ' | <a href="' + deleteUrl + '" onclick="return confirm(\'' + escapeHtml(scfChatAdmin.labels.confirmDelete) + '\');">' + escapeHtml(scfChatAdmin.labels.delete) + '</a>'
						: '') +
				'</span>';
		}

		return (
			'<div class="scf-admin-chat-bubble scf-admin-chat-bubble--' + typeClass + '" data-id="' + msg.id + '">' +
				escapeHtml(msg.message) +
				'<div class="scf-admin-chat-bubble__meta">' +
					'<span>' + senderLabel + ' · ' + escapeHtml(msg.created_at) + '</span>' +
					actionsHtml +
				'</div>' +
			'</div>'
		);
	}

	function appendMessages(messages) {
		var $thread = $('#scf-admin-chat-thread');
		if (!$thread.length || !messages.length) {
			return;
		}

		var added = false;
		messages.forEach(function (msg) {
			if (msg.id <= state.lastId || $thread.find('[data-id="' + msg.id + '"]').length) {
				return;
			}
			$thread.append(renderBubble(msg));
			state.lastId = msg.id;
			added = true;
		});

		if (added) {
			scrollThreadToBottom();
		}
	}

	function fetchSessionMessages() {
		if (!scfChatAdmin.sessionId) {
			return;
		}

		$.post(scfChatAdmin.ajaxUrl, {
			action: 'scf_admin_fetch_session_messages',
			nonce: scfChatAdmin.nonce,
			session_id: scfChatAdmin.sessionId,
			after_id: state.lastId,
		}).done(function (response) {
			if (response.success && response.data.messages) {
				appendMessages(response.data.messages);
			}
		});
	}

	function sendReply() {
		if (state.sending) {
			return;
		}

		var $textarea = $('#scf-admin-reply-input');
		var message = $.trim($textarea.val());

		if (!message) {
			return;
		}

		state.sending = true;
		var $btn = $('#scf-admin-reply-btn').prop('disabled', true);

		$.post(scfChatAdmin.ajaxUrl, {
			action: 'scf_admin_send_reply',
			nonce: scfChatAdmin.nonce,
			session_id: scfChatAdmin.sessionId,
			message: message,
		}).done(function (response) {
			if (response.success && response.data.message) {
				$textarea.val('');
				if (response.data.delete_nonce && canEditMessages()) {
					scfChatAdmin.deleteNonces[response.data.message.id] = response.data.delete_nonce;
				}
				appendMessages([response.data.message]);
			}
		}).always(function () {
			state.sending = false;
			$btn.prop('disabled', false);
		});
	}

	function fetchSessions() {
		$.post(scfChatAdmin.ajaxUrl, {
			action: 'scf_admin_fetch_sessions',
			nonce: scfChatAdmin.nonce,
		}).done(function (response) {
			if (!response.success || !response.data.sessions) {
				return;
			}

			var $tbody = $('#scf-admin-sessions-tbody');
			if (!$tbody.length) {
				return;
			}

			response.data.sessions.forEach(function (session) {
				var $existing = $tbody.find('[data-session="' + session.session_id + '"]');
				if ($existing.length) {
					$existing.find('.scf-session-preview').text(session.preview);
					$existing.find('.scf-session-time').text(session.created_at);
				} else {
					$tbody.find('td[colspan]').closest('tr').remove();
					var viewUrl = scfChatAdmin.sessionUrlBase.replace('__SESSION__', encodeURIComponent(session.session_id));
					var editSessionUrl = scfChatAdmin.sessionEditUrlBase.replace('__SESSION__', encodeURIComponent(session.session_id));
					$tbody.prepend(
						'<tr class="scf-new-session" data-session="' + escapeHtml(session.session_id) + '">' +
							'<td class="scf-session-preview">' + escapeHtml(session.preview) + '</td>' +
							'<td>' + escapeHtml(session.sender_name) + '</td>' +
							'<td class="scf-session-time">' + escapeHtml(session.created_at) + '</td>' +
							'<td class="scf-session-actions">' +
								'<a href="' + viewUrl + '">' + escapeHtml(scfChatAdmin.labels.view) + '</a> | ' +
								'<a href="' + editSessionUrl + '">' + escapeHtml(scfChatAdmin.labels.editSession) + '</a>' +
							'</td>' +
						'</tr>'
					);
				}
			});
		});
	}

	function startPolling() {
		stopPolling();
		state.polling = setInterval(function () {
			if (scfChatAdmin.sessionId) {
				fetchSessionMessages();
			} else {
				fetchSessions();
			}
		}, 3000);
	}

	function stopPolling() {
		if (state.polling) {
			clearInterval(state.polling);
			state.polling = null;
		}
	}

	$(function () {
		if (scfChatAdmin.sessionId) {
			scrollThreadToBottom();

			$('#scf-admin-reply-form').on('submit', function (e) {
				e.preventDefault();
				sendReply();
			});
		}

		startPolling();

		$(document).on('visibilitychange', function () {
			if (document.hidden) {
				stopPolling();
			} else {
				startPolling();
				if (scfChatAdmin.sessionId) {
					fetchSessionMessages();
				} else {
					fetchSessions();
				}
			}
		});
	});
})(jQuery);
