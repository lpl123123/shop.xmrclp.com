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

	var state = {
		open: false,
		lastId: 0,
		polling: null,
		sending: false,
		stickToBottom: true,
		name: '',
		email: '',
		bodyScrollY: 0,
		portalActive: false,
	};

	var portal = null;
	var panelHome = { panel: null, backdrop: null };

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

	function getPanel() {
		return root.querySelector('.scf-chat-panel');
	}

	function getBackdrop() {
		return root.querySelector('.scf-chat-backdrop');
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

	function ensurePortal() {
		if (portal) {
			return portal;
		}

		portal = document.createElement('div');
		portal.id = 'scf-chat-portal';
		portal.setAttribute('aria-hidden', 'true');
		document.body.appendChild(portal);
		return portal;
	}

	function rememberPanelHome() {
		var panel = getPanel();
		var backdrop = getBackdrop();

		if (panel && !panelHome.panel) {
			panelHome.panel = panel;
		}

		if (backdrop && !panelHome.backdrop) {
			panelHome.backdrop = backdrop;
		}
	}

	function activateMobilePortal(active) {
		if (!isMobileLayout()) {
			deactivateMobilePortal();
			return;
		}

		rememberPanelHome();

		var panel = getPanel();
		var backdrop = getBackdrop();
		var host = ensurePortal();

		if (active) {
			if (backdrop && backdrop.parentNode !== host) {
				host.appendChild(backdrop);
			}
			if (panel && panel.parentNode !== host) {
				host.appendChild(panel);
			}
			host.setAttribute('aria-hidden', 'false');
			state.portalActive = true;
			return;
		}

		deactivateMobilePortal();
	}

	function deactivateMobilePortal() {
		if (!portal) {
			return;
		}

		var panel = getPanel();
		var backdrop = getBackdrop();

		if (backdrop && backdrop.parentNode !== root) {
			root.insertBefore(backdrop, root.firstChild);
		}

		if (panel && panel.parentNode !== root) {
			var toggle = root.querySelector('.scf-chat-toggle');
			if (toggle && toggle.nextSibling) {
				root.insertBefore(panel, toggle.nextSibling);
			} else {
				root.appendChild(panel);
			}
		}

		portal.setAttribute('aria-hidden', 'true');
		state.portalActive = false;
	}

	function resetPanelViewportStyles() {
		var panel = getPanel();
		if (!panel) {
			return;
		}

		panel.classList.remove('is-keyboard-open');
		panel.style.position = '';
		panel.style.top = '';
		panel.style.left = '';
		panel.style.width = '';
		panel.style.height = '';
		panel.style.maxHeight = '';
		panel.style.bottom = '';
		panel.style.right = '';
		panel.style.transform = '';
	}

	function syncPanelToViewport() {
		var panel = getPanel();
		if (!panel || !panel.classList.contains('is-open') || !isMobileLayout()) {
			return;
		}

		var viewport = window.visualViewport;
		if (!viewport) {
			panel.classList.add('is-keyboard-open');
			return;
		}

		var keyboardOpen = viewport.height < (window.innerHeight - 80);

		panel.classList.toggle('is-keyboard-open', keyboardOpen);
		panel.style.position = 'fixed';
		panel.style.top = viewport.offsetTop + 'px';
		panel.style.left = viewport.offsetLeft + 'px';
		panel.style.width = viewport.width + 'px';
		panel.style.height = viewport.height + 'px';
		panel.style.maxHeight = viewport.height + 'px';
		panel.style.bottom = 'auto';
		panel.style.right = 'auto';
		panel.style.transform = 'none';

		window.requestAnimationFrame(function () {
			scrollToBottom(getMessagesContainer(), state.stickToBottom);
		});
	}

	function lockBodyScroll(locked) {
		if (locked) {
			state.bodyScrollY = window.scrollY || window.pageYOffset || 0;
			document.documentElement.classList.add('scf-chat-body-lock');
			document.body.classList.add('scf-chat-body-lock');
			document.body.style.position = 'fixed';
			document.body.style.top = '-' + state.bodyScrollY + 'px';
			document.body.style.left = '0';
			document.body.style.right = '0';
			document.body.style.width = '100%';
			return;
		}

		document.documentElement.classList.remove('scf-chat-body-lock');
		document.body.classList.remove('scf-chat-body-lock');
		document.body.style.position = '';
		document.body.style.top = '';
		document.body.style.left = '';
		document.body.style.right = '';
		document.body.style.width = '';
		window.scrollTo(0, state.bodyScrollY);
	}

	function bindViewportListeners() {
		if (!window.visualViewport) {
			return;
		}

		var handler = function () {
			if (state.open && isMobileLayout()) {
				syncPanelToViewport();
			}
		};

		window.visualViewport.addEventListener('resize', handler);
		window.visualViewport.addEventListener('scroll', handler);
	}

	function bindMessagesScroll(container) {
		if (!container) {
			return;
		}

		container.addEventListener('scroll', function () {
			state.stickToBottom = isNearBottom(container);
		}, { passive: true });
	}

	function focusWithoutPageScroll(el) {
		if (!el) {
			return;
		}

		try {
			el.focus({ preventScroll: true });
		} catch (err) {
			el.focus();
		}

		window.scrollTo(0, state.bodyScrollY);
	}

	function bindInputFocusHandlers() {
		var focusables = root.querySelectorAll('#scf-chat-input, #scf-chat-name, #scf-chat-email');
		focusables.forEach(function (el) {
			el.addEventListener('touchstart', function () {
				state.stickToBottom = true;
			}, { passive: true });

			el.addEventListener('focus', function () {
				state.stickToBottom = true;
				window.scrollTo(0, state.bodyScrollY);
				syncPanelToViewport();
				window.setTimeout(syncPanelToViewport, 50);
				window.setTimeout(syncPanelToViewport, 150);
				window.setTimeout(syncPanelToViewport, 350);
			});
		});
	}

	function setPanelOpen(isOpen) {
		state.open = isOpen;
		var panel = getPanel();
		var toggle = root.querySelector('.scf-chat-toggle');
		var backdrop = getBackdrop();

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
			activateMobilePortal(true);
			syncPanelToViewport();
			window.setTimeout(syncPanelToViewport, 320);
			fetchMessages(true);
			startPolling();
			return;
		}

		stopPolling();
		resetPanelViewportStyles();
		deactivateMobilePortal();
		lockBodyScroll(false);
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
						'<input type="text" id="scf-chat-name" placeholder="您的姓名" maxlength="50" enterkeyhint="next">' +
						'<input type="email" id="scf-chat-email" placeholder="邮箱（可选）" maxlength="100" enterkeyhint="next">' +
					'</div>' +
					'<div class="scf-chat-form__row">' +
						'<textarea id="scf-chat-input" placeholder="' + escapeHtml(scfChat.placeholder) + '" rows="1" maxlength="2000" enterkeyhint="send"></textarea>' +
						'<button type="submit">发送</button>' +
					'</div>' +
				'</form>' +
			'</div>';

		rememberPanelHome();

		var toggle = root.querySelector('.scf-chat-toggle');
		var closeBtn = root.querySelector('.scf-chat-close');
		var backdrop = getBackdrop();
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
					syncPanelToViewport();
					focusWithoutPageScroll(input);
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
