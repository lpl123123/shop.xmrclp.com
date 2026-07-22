(function () {
	'use strict';

	var HIDE_DELAY = 200;
	var VIEWPORT_PADDING = 12;
	var GAP = 10;

	function saveImage(url, filename, button) {
		if (!url) {
			return;
		}

		if (button) {
			button.disabled = true;
			button.dataset.originalText = button.textContent;
			if (typeof scfMerchant !== 'undefined' && scfMerchant.labels.saving) {
				button.textContent = scfMerchant.labels.saving;
			}
		}

		fetch(url, { mode: 'cors' })
			.then(function (response) {
				if (!response.ok) {
					throw new Error('fetch failed');
				}
				return response.blob();
			})
			.then(function (blob) {
				var objectUrl = URL.createObjectURL(blob);
				var link = document.createElement('a');
				link.href = objectUrl;
				link.download = filename || 'merchant-qrcode.png';
				document.body.appendChild(link);
				link.click();
				document.body.removeChild(link);
				URL.revokeObjectURL(objectUrl);
			})
			.catch(function () {
				window.open(url, '_blank');
			})
			.finally(function () {
				if (button) {
					button.disabled = false;
					if (button.dataset.originalText) {
						button.textContent = button.dataset.originalText;
					}
				}
			});
	}

	function isTouchDevice() {
		return window.matchMedia('(hover: none)').matches;
	}

	function positionPopup(trigger) {
		var popup = trigger.querySelector('.scf-qrcode-popup');
		var button = trigger.querySelector('.scf-qrcode-button');

		if (!popup || !button) {
			return;
		}

		popup.classList.add('is-positioned');
		popup.style.display = 'block';

		var buttonBox = button.getBoundingClientRect();
		var popupWidth = popup.offsetWidth;
		var popupHeight = popup.offsetHeight;
		var top = buttonBox.top - popupHeight - GAP;
		var showBelow = top < VIEWPORT_PADDING;

		popup.classList.toggle('is-below', showBelow);

		if (showBelow) {
			top = buttonBox.bottom + GAP;
		}

		if (showBelow) {
			var maxHeight = window.innerHeight - top - VIEWPORT_PADDING;
			popup.style.maxHeight = Math.max(180, maxHeight) + 'px';
		} else {
			if (top < VIEWPORT_PADDING) {
				top = VIEWPORT_PADDING;
			}
			var maxHeightAbove = buttonBox.top - GAP - VIEWPORT_PADDING;
			popup.style.maxHeight = Math.max(180, maxHeightAbove) + 'px';
		}

		var left = buttonBox.left + (buttonBox.width / 2) - (popupWidth / 2);
		left = Math.max(
			VIEWPORT_PADDING,
			Math.min(left, window.innerWidth - popupWidth - VIEWPORT_PADDING)
		);

		popup.style.position = 'fixed';
		popup.style.top = top + 'px';
		popup.style.left = left + 'px';
		popup.style.bottom = 'auto';
		popup.style.right = 'auto';
		popup.style.transform = 'none';
		popup.style.setProperty('--scf-arrow-left', ((buttonBox.left + buttonBox.width / 2) - left) + 'px');

		// Reposition after max-height applied in case height changed.
		window.requestAnimationFrame(function () {
			popupHeight = popup.offsetHeight;
			top = showBelow ? buttonBox.bottom + GAP : buttonBox.top - popupHeight - GAP;

			if (!showBelow && top < VIEWPORT_PADDING) {
				top = VIEWPORT_PADDING;
			}

			popup.style.top = top + 'px';
		});
	}

	function bindTrigger(trigger) {
		var button = trigger.querySelector('.scf-qrcode-button');
		var popup = trigger.querySelector('.scf-qrcode-popup');
		var saveBtn = trigger.querySelector('.scf-qrcode-save');
		var hideTimer = null;
		var touchDevice = isTouchDevice();

		function showPopup() {
			window.clearTimeout(hideTimer);
			trigger.classList.add('is-hover');
			if (button) {
				button.setAttribute('aria-expanded', 'true');
			}
			if (popup) {
				popup.setAttribute('aria-hidden', 'false');
				positionPopup(trigger);
			}
		}

		function hidePopup() {
			hideTimer = window.setTimeout(function () {
				trigger.classList.remove('is-hover');
				if (!touchDevice) {
					trigger.classList.remove('is-open');
				}
				if (button) {
					button.setAttribute('aria-expanded', 'false');
				}
				if (popup) {
					popup.setAttribute('aria-hidden', 'true');
					popup.classList.remove('is-positioned', 'is-below');
					popup.style.display = '';
					popup.style.maxHeight = '';
				}
			}, HIDE_DELAY);
		}

		function togglePopup(event) {
			event.preventDefault();
			event.stopPropagation();
			var willOpen = !trigger.classList.contains('is-open');
			trigger.classList.toggle('is-open', willOpen);
			if (willOpen) {
				showPopup();
			} else {
				window.clearTimeout(hideTimer);
				trigger.classList.remove('is-hover');
				if (button) {
					button.setAttribute('aria-expanded', 'false');
				}
				if (popup) {
					popup.setAttribute('aria-hidden', 'true');
					popup.classList.remove('is-positioned', 'is-below');
					popup.style.display = '';
					popup.style.maxHeight = '';
				}
			}
		}

		if (touchDevice) {
			if (button) {
				button.addEventListener('click', togglePopup);
			}
		} else {
			trigger.addEventListener('mouseenter', showPopup);
			trigger.addEventListener('mouseleave', hidePopup);

			if (popup) {
				popup.addEventListener('mouseenter', showPopup);
				popup.addEventListener('mouseleave', hidePopup);
			}
		}

		if (saveBtn) {
			saveBtn.addEventListener('click', function (event) {
				event.preventDefault();
				event.stopPropagation();
				saveImage(
					saveBtn.getAttribute('data-image-url'),
					saveBtn.getAttribute('data-filename'),
					saveBtn
				);
			});
		}

		window.addEventListener('resize', function () {
			if (trigger.classList.contains('is-hover') || trigger.classList.contains('is-open')) {
				positionPopup(trigger);
			}
		});

		window.addEventListener('scroll', function () {
			if (trigger.classList.contains('is-hover') || trigger.classList.contains('is-open')) {
				positionPopup(trigger);
			}
		}, true);

		document.addEventListener('click', function (event) {
			if (!trigger.contains(event.target)) {
				trigger.classList.remove('is-open', 'is-hover');
				if (button) {
					button.setAttribute('aria-expanded', 'false');
				}
				if (popup) {
					popup.setAttribute('aria-hidden', 'true');
					popup.classList.remove('is-positioned', 'is-below');
					popup.style.display = '';
					popup.style.maxHeight = '';
				}
			}
		});
	}

	document.querySelectorAll('.scf-qrcode-trigger').forEach(bindTrigger);
})();
