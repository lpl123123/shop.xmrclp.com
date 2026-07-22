(function () {
	'use strict';

	if (typeof scfChat === 'undefined') {
		return;
	}

	var root = document.getElementById('scf-chat-root');
	if (!root) {
		return;
	}

	var chatIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/></svg>';
	var NEAR_BOTTOM_THRESHOLD = 80;
	var KEYBOARD_HEIGHT_THRESHOLD = 120;

	var state = {
		open: false,
		lastId: 0,
		polling: null,
		sending: false,
		stickToBottom: true,
		inputFocused: false,
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

	function getMessagesContainer() {
		return document.getElementById('scf-chat-messages');
	}

	function isNearBottom(container) {
		if (!container) {
			return true;
		}

		return (container.scrollHeight - container.scrollTop - container.clientHeight) <= NEAR_BOTTOM_THRESHOLD;
	}

	function scrollToBottom(container, force) {
		if (!container) {
			return;
		}

		if (force || state.stickToBottom) {
			container.scrollTop = container.scrollHeight;
		}
	}

	function isMobileLayout() {
		return window.matchMedia('(max-width: 480px)').matches;
	}

	function isKeyboardOpen() {
		if (!window.visualViewport) {
			return state.inputFocused;
		}

		return (window.innerHeight - window.visualViewport.height) > KEYBOARD_HEIGHT_THRESHOLD;
	}

	function resetPanelViewportStyles() {
		var panel = root.querySelector('.scf-chat-panel');
		if (!panel) {
			return;
		}

		panel.classList.remove('is-keyboard-open');
		panel.style.top = '';
		panel.style.height = '';
		panel.style.maxHeight = '';
		panel.style.bottom = '';
	}

	function adjustPanelForViewport() {
		var panel = root.querySelector('.scf-chat-panel');
		if (!panel || !panel.classList.contains('is-open')) {
			return;
		}

		if (!isMobileLayout()) {
			resetPanelViewportStyles();
			return;
		}

		if (!state.inputFocused && !isKeyboardOpen()) {
			resetPanelViewportStyles();
			return;
		}

		var viewport = window.visualViewport;
		if (!viewport) {
			return;
		}

		var gap = 8;
		var availableHeight = Math.max(220, viewport.height - gap * 2);
		var offsetTop = Math.max(viewport.offsetTop + gap, gap);

		panel.classList.add('is-keyboard-open');
		panel.style.position = 'fixed';
		panel.style.top = offsetTop + 'px';
		panel.style.height = availableHeight + 'px';
		panel.style.maxHeight = availableHeight + 'px';
		panel.style.bottom = 'auto';
		panel.style.left = '0';
		panel.style.right = '0';

		if (state.stickToBottom) {
			window.requestAnimationFrame(function () {
				scrollToBottom(getMessagesContainer(), true);
			});
		}
	}

	function lockBodyScroll(locked) {
		document.documentElement.classList.toggle('scf-chat-body-lock', locked);
		document.body.classList.toggle('scf-chat-body-lock', locked);
	}

	function bindViewportListeners() {
		if (!window.visualViewport) {
			return;
		}

		window.visualViewport.addEventListener('resize', adjustPanelForViewport);
	}

	function bindMessagesScroll(container) {
		if (!container) {
			return;
		}

		container.addEventListener('scroll', function () {
			state.stickToBottom = isNearBottom(container);
		}, { passive: true });
	}

	function bindInputFocusHandlers() {
		var focusables = root.querySelectorAll('#scf-chat-input, #scf-chat-name, #scf-chat-email');
		focusables.forEach(function (el) {
			el.addEventListener('focus', function () {
				state.inputFocused = true;
				window.setTimeout(adjustPanelForViewport, 80);
				window.setTimeout(adjustPanelForViewport, 320);
			});
			el.addEventListener('blur', function () {
				state.inputFocused = false;
				window.setTimeout(function () {
					if (!isKeyboardOpen()) {
						resetPanelViewportStyles();
					}
				}, 120);
			});
		});
	}

	function setPanelOpen(isOpen) {
		state.open = isOpen;
		var panel = root.querySelector('.scf-chat-panel');
		var toggle = root.querySelector('.scf-chat-toggle');
		var backdrop = root.querySelector('.scf-chat-backdrop');

		root.classList.toggle('is-active', isOpen);

		if (panel) {
			panel.classList.toggle('is-open', isOpen);
		}

		if (backdrop) {
			backdrop.classList.toggle('is-visible', isOpen);
			backdrop.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
		}

		if (toggle) {
			toggle.classList.toggle('is-hidden', isOpen);
			toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
		}

		if (isOpen) {
			state.stickToBottom = true;
			lockBodyScroll(isMobileLayout());
			fetchMessages(true);
			startPolling();
		} else {
			state.inputFocused = false;
			stopPolling();
			resetPanelViewportStyles();
			lockBodyScroll(false);
		}
	}

	function buildUI() {
		root.innerHTML =
			'<div class="scf-chat-backdrop" aria-hidden="true"></div>' +
			'<button type="button" class="scf-chat-toggle" aria-label="打开聊天" aria-expanded="false">' + chatIcon + '</button>' +
			'<div class="scf-chat-panel" role="dialog" aria-modal="true" aria-label="' + escapeHtml(scfChat.title) + '">' +
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
		var closeBtn = root.querySelector('.scf-chat-close');
		var backdrop = root.querySelector('.scf-chat-backdrop');
		var form = root.querySelector('#scf-chat-form');
		var messagesContainer = getMessagesContainer();

		bindMessagesScroll(messagesContainer);
		bindInputFocusHandlers();
		bindViewportListeners();

		function openChat(event) {
			if (event) {
				event.preventDefault();
				event.stopPropagation();
			}

			if (!state.open) {
				setPanelOpen(true);
			}
		}

		function closeChat(event) {
			if (event) {
				event.preventDefault();
				event.stopPropagation();
			}

			setPanelOpen(false);
		}

		toggle.addEventListener('click', openChat);
		closeBtn.addEventListener('click', closeChat);
		backdrop.addEventListener('click', closeChat);

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			sendMessage();
		});
	}

	function fetchMessages(forceScroll) {
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
				appendMessages(data.data.messages, forceScroll);
			})
			.catch(function () {});
	}

	function appendMessages(messages, forceScroll) {
		var container = getMessagesContainer();
		if (!container) {
			return;
		}

		var wasNearBottom = isNearBottom(container);
		var added = false;

		messages.forEach(function (msg) {
			if (msg.id <= state.lastId) {
				return;
			}
			if (container.querySelector('[data-id="' + msg.id + '"]')) {
				return;
			}
			container.insertAdjacentHTML('beforeend', renderMessage(msg));
			state.lastId = msg.id;
			added = true;
		});

		if (added && (forceScroll || (state.stickToBottom && wasNearBottom))) {
			scrollToBottom(container, true);
		}
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
		state.stickToBottom = true;

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
					appendMessages([data.data.message], true);
					adjustPanelForViewport();
				}
			})
			.catch(function () {
				state.sending = false;
			});
	}

	function startPolling() {
		stopPolling();
		state.polling = setInterval(function () {
			fetchMessages(false);
		}, 3000);
	}

	function stopPolling() {
		if (state.polling) {
			clearInterval(state.polling);
			state.polling = null;
		}
	}

	buildUI();
})();
