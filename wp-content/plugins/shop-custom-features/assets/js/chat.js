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
	var MOBILE_QUERY = window.matchMedia('(max-width: 480px)');

	var els = {
		portal: null,
		backdrop: null,
		panel: null,
		toggle: null,
		closeBtn: null,
		form: null,
		messages: null,
		input: null,
		nameInput: null,
		emailInput: null,
	};

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

	function escapeHtml(text) {
		var div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	function renderMessage(msg) {
		var typeClass = msg.sender_type === 'admin' ? 'admin' : 'visitor';
		return (
			'<div class="scf-chat-message scf-chat-message--' + typeClass + '" data-id="' + msg.id + '">' +
				'<div class="scf-chat-message__text">' + escapeHtml(msg.message) + '</div>' +
				'<span class="scf-chat-message__meta">' + escapeHtml(msg.created_at) + '</span>' +
			'</div>'
		);
	}

	function isMobileLayout() {
		return MOBILE_QUERY.matches;
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

	function ensurePortal() {
		if (els.portal) {
			return els.portal;
		}

		els.portal = document.createElement('div');
		els.portal.id = 'scf-chat-portal';
		els.portal.setAttribute('aria-hidden', 'true');
		document.body.appendChild(els.portal);
		return els.portal;
	}

	function mountOpenLayer() {
		var host = ensurePortal();

		if (els.backdrop && els.backdrop.parentNode !== host) {
			host.appendChild(els.backdrop);
		}

		if (els.panel && els.panel.parentNode !== host) {
			host.appendChild(els.panel);
		}

		host.setAttribute('aria-hidden', 'false');
		state.portalActive = true;
	}

	function restoreLayerHome() {
		if (!els.portal) {
			return;
		}

		if (els.backdrop && els.backdrop.parentNode !== root) {
			root.insertBefore(els.backdrop, root.firstChild);
		}

		if (els.panel && els.panel.parentNode !== root) {
			if (els.toggle && els.toggle.nextSibling) {
				root.insertBefore(els.panel, els.toggle.nextSibling);
			} else {
				root.appendChild(els.panel);
			}
		}

		els.portal.setAttribute('aria-hidden', 'true');
		state.portalActive = false;
	}

	function clearPanelInlineStyles() {
		if (!els.panel) {
			return;
		}

		els.panel.classList.remove('is-keyboard-open', 'is-mobile');
		els.panel.style.position = '';
		els.panel.style.top = '';
		els.panel.style.left = '';
		els.panel.style.width = '';
		els.panel.style.height = '';
		els.panel.style.maxHeight = '';
		els.panel.style.bottom = '';
		els.panel.style.right = '';
		els.panel.style.transform = '';
	}

	function syncMobileViewport() {
		if (!els.panel || !state.open || !isMobileLayout()) {
			return;
		}

		els.panel.classList.add('is-mobile');

		var viewport = window.visualViewport;
		if (!viewport) {
			return;
		}

		var keyboardOpen = viewport.height < (window.innerHeight - 80);
		els.panel.classList.toggle('is-keyboard-open', keyboardOpen);

		els.panel.style.position = 'fixed';
		els.panel.style.top = viewport.offsetTop + 'px';
		els.panel.style.left = viewport.offsetLeft + 'px';
		els.panel.style.width = viewport.width + 'px';
		els.panel.style.height = viewport.height + 'px';
		els.panel.style.maxHeight = viewport.height + 'px';
		els.panel.style.bottom = 'auto';
		els.panel.style.right = 'auto';
		els.panel.style.transform = 'none';

		window.requestAnimationFrame(function () {
			scrollToBottom(els.messages, state.stickToBottom);
		});
	}

	function lockBodyScroll(locked) {
		if (!isMobileLayout()) {
			return;
		}

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
				syncMobileViewport();
			}
		};

		window.visualViewport.addEventListener('resize', handler);
		window.visualViewport.addEventListener('scroll', handler);
		window.addEventListener('resize', handler);
	}

	function bindMessagesScroll() {
		if (!els.messages) {
			return;
		}

		els.messages.addEventListener('scroll', function () {
			state.stickToBottom = isNearBottom(els.messages);
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

		if (isMobileLayout()) {
			window.scrollTo(0, state.bodyScrollY);
		}
	}

	function bindInputFocusHandlers() {
		var focusables = [els.input, els.nameInput, els.emailInput].filter(Boolean);

		focusables.forEach(function (el) {
			el.addEventListener('touchstart', function () {
				state.stickToBottom = true;
			}, { passive: true });

			el.addEventListener('focus', function () {
				state.stickToBottom = true;

				if (!isMobileLayout()) {
					return;
				}

				window.scrollTo(0, state.bodyScrollY);
				syncMobileViewport();
				window.setTimeout(syncMobileViewport, 60);
				window.setTimeout(syncMobileViewport, 180);
				window.setTimeout(syncMobileViewport, 360);
			});
		});
	}

	function bindPanelGuards() {
		if (!els.panel) {
			return;
		}

		['click', 'mousedown', 'touchstart'].forEach(function (eventName) {
			els.panel.addEventListener(eventName, function (event) {
				event.stopPropagation();
			});
		});
	}

	function setPanelOpen(isOpen) {
		state.open = isOpen;

		root.classList.toggle('is-active', isOpen);

		if (els.panel) {
			els.panel.classList.toggle('is-open', isOpen);
			els.panel.classList.toggle('is-mobile', isOpen && isMobileLayout());
		}

		if (els.backdrop) {
			els.backdrop.classList.toggle('is-visible', isOpen);
			els.backdrop.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
		}

		if (els.toggle) {
			els.toggle.classList.toggle('is-hidden', isOpen);
			els.toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
		}

		if (isOpen) {
			state.stickToBottom = true;
			mountOpenLayer();
			lockBodyScroll(isMobileLayout());

			if (isMobileLayout()) {
				syncMobileViewport();
				window.setTimeout(syncMobileViewport, 320);
			}

			fetchMessages(true);
			startPolling();
			return;
		}

		stopPolling();
		clearPanelInlineStyles();
		restoreLayerHome();
		lockBodyScroll(false);
	}

	function cacheElements() {
		els.backdrop = root.querySelector('.scf-chat-backdrop');
		els.toggle = root.querySelector('.scf-chat-toggle');
		els.panel = root.querySelector('.scf-chat-panel');
		els.closeBtn = root.querySelector('.scf-chat-close');
		els.form = root.querySelector('#scf-chat-form');
		els.messages = root.querySelector('#scf-chat-messages');
		els.input = root.querySelector('#scf-chat-input');
		els.nameInput = root.querySelector('#scf-chat-name');
		els.emailInput = root.querySelector('#scf-chat-email');
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

		cacheElements();
		bindMessagesScroll();
		bindInputFocusHandlers();
		bindPanelGuards();
		bindViewportListeners();

		els.toggle.addEventListener('click', function (event) {
			event.preventDefault();
			event.stopPropagation();
			if (!state.open) {
				setPanelOpen(true);
			}
		});

		els.closeBtn.addEventListener('click', function (event) {
			event.preventDefault();
			event.stopPropagation();
			setPanelOpen(false);
		});

		els.backdrop.addEventListener('click', function (event) {
			event.preventDefault();
			event.stopPropagation();
			setPanelOpen(false);
		});

		els.form.addEventListener('submit', function (event) {
			event.preventDefault();
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
		if (!els.messages) {
			return;
		}

		var wasNearBottom = isNearBottom(els.messages);
		var added = false;

		messages.forEach(function (msg) {
			if (msg.id <= state.lastId) {
				return;
			}
			if (els.messages.querySelector('[data-id="' + msg.id + '"]')) {
				return;
			}
			els.messages.insertAdjacentHTML('beforeend', renderMessage(msg));
			state.lastId = msg.id;
			added = true;
		});

		if (added && (forceScroll || (state.stickToBottom && wasNearBottom))) {
			scrollToBottom(els.messages, true);
		}
	}

	function sendMessage() {
		if (state.sending || !els.input) {
			return;
		}

		var message = els.input.value.trim();
		if (!message) {
			return;
		}

		state.name = els.nameInput ? els.nameInput.value.trim() : '';
		state.email = els.emailInput ? els.emailInput.value.trim() : '';
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
					els.input.value = '';
					appendMessages([data.data.message], true);
					syncMobileViewport();
					focusWithoutPageScroll(els.input);
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
