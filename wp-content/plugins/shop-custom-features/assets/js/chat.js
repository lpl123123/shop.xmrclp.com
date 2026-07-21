(function () {
	'use strict';

	if (typeof scfChat === 'undefined') {
		return;
	}

	var root = document.getElementById('scf-chat-root');
	if (!root) {
		return;
	}

	var state = {
		open: false,
		lastId: 0,
		polling: null,
		sending: false,
		name: '',
		email: '',
	};

	function escapeHtml(text) {
		var div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	function renderMessage(msg) {
		var typeClass = msg.sender_type === 'admin' ? 'admin' : 'visitor';
		return (
			'<div class="scf-chat-message scf-chat-message--' + typeClass + '" data-id="' + msg.id + '">' +
				escapeHtml(msg.message) +
				'<span class="scf-chat-message__meta">' + escapeHtml(msg.created_at) + '</span>' +
			'</div>'
		);
	}

	function scrollToBottom(container) {
		container.scrollTop = container.scrollHeight;
	}

	function buildUI() {
		root.innerHTML =
			'<button type="button" class="scf-chat-toggle" aria-label="打开聊天">💬</button>' +
			'<div class="scf-chat-panel" role="dialog" aria-label="' + escapeHtml(scfChat.title) + '">' +
				'<div class="scf-chat-header">' +
					'<h3>' + escapeHtml(scfChat.title) + '</h3>' +
					'<button type="button" class="scf-chat-close" aria-label="关闭">&times;</button>' +
				'</div>' +
				'<div class="scf-chat-messages" id="scf-chat-messages">' +
					'<div class="scf-chat-welcome">' + escapeHtml(scfChat.welcome) + '</div>' +
				'</div>' +
				'<form class="scf-chat-form" id="scf-chat-form">' +
					'<div class="scf-chat-form__intro">' +
						'<input type="text" id="scf-chat-name" placeholder="您的姓名" maxlength="50">' +
						'<input type="email" id="scf-chat-email" placeholder="邮箱（可选）" maxlength="100">' +
					'</div>' +
					'<div class="scf-chat-form__row">' +
						'<textarea id="scf-chat-input" placeholder="' + escapeHtml(scfChat.placeholder) + '" rows="1" maxlength="2000"></textarea>' +
						'<button type="submit">发送</button>' +
					'</div>' +
				'</form>' +
			'</div>';

		var toggle = root.querySelector('.scf-chat-toggle');
		var panel = root.querySelector('.scf-chat-panel');
		var closeBtn = root.querySelector('.scf-chat-close');
		var form = root.querySelector('#scf-chat-form');

		toggle.addEventListener('click', function () {
			state.open = !state.open;
			panel.classList.toggle('is-open', state.open);
			if (state.open) {
				fetchMessages();
				startPolling();
			} else {
				stopPolling();
			}
		});

		closeBtn.addEventListener('click', function () {
			state.open = false;
			panel.classList.remove('is-open');
			stopPolling();
		});

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			sendMessage();
		});
	}

	function fetchMessages() {
		var body = new FormData();
		body.append('action', 'scf_fetch_chat_messages');
		body.append('nonce', scfChat.nonce);
		body.append('session_id', scfChat.sessionId);
		body.append('after_id', String(state.lastId));

		fetch(scfChat.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body,
		})
			.then(function (res) { return res.json(); })
			.then(function (data) {
				if (!data.success || !data.data.messages) {
					return;
				}
				appendMessages(data.data.messages);
			})
			.catch(function () {});
	}

	function appendMessages(messages) {
		var container = document.getElementById('scf-chat-messages');
		if (!container) {
			return;
		}

		messages.forEach(function (msg) {
			if (msg.id <= state.lastId) {
				return;
			}
			if (container.querySelector('[data-id="' + msg.id + '"]')) {
				return;
			}
			container.insertAdjacentHTML('beforeend', renderMessage(msg));
			state.lastId = msg.id;
		});

		scrollToBottom(container);
	}

	function sendMessage() {
		if (state.sending) {
			return;
		}

		var input = document.getElementById('scf-chat-input');
		var nameInput = document.getElementById('scf-chat-name');
		var emailInput = document.getElementById('scf-chat-email');

		if (!input) {
			return;
		}

		var message = input.value.trim();
		if (!message) {
			return;
		}

		state.name = nameInput ? nameInput.value.trim() : '';
		state.email = emailInput ? emailInput.value.trim() : '';
		state.sending = true;

		var body = new FormData();
		body.append('action', 'scf_send_chat_message');
		body.append('nonce', scfChat.nonce);
		body.append('session_id', scfChat.sessionId);
		body.append('message', message);
		body.append('name', state.name);
		body.append('email', state.email);

		fetch(scfChat.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body,
		})
			.then(function (res) { return res.json(); })
			.then(function (data) {
				state.sending = false;
				if (data.success && data.data.message) {
					input.value = '';
					appendMessages([data.data.message]);
				}
			})
			.catch(function () {
				state.sending = false;
			});
	}

	function startPolling() {
		stopPolling();
		state.polling = setInterval(fetchMessages, 4000);
	}

	function stopPolling() {
		if (state.polling) {
			clearInterval(state.polling);
			state.polling = null;
		}
	}

	buildUI();
})();
