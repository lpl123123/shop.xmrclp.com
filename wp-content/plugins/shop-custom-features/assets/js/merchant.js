(function () {
	'use strict';

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

	document.querySelectorAll('.scf-qrcode-trigger').forEach(function (trigger) {
		var button = trigger.querySelector('.scf-qrcode-button');
		var saveBtn = trigger.querySelector('.scf-qrcode-save');

		if (button) {
			button.addEventListener('click', function (event) {
				if (window.matchMedia('(hover: none)').matches) {
					event.preventDefault();
					trigger.classList.toggle('is-open');
					button.setAttribute('aria-expanded', trigger.classList.contains('is-open') ? 'true' : 'false');
				}
			});
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

		document.addEventListener('click', function (event) {
			if (!trigger.contains(event.target)) {
				trigger.classList.remove('is-open');
				if (button) {
					button.setAttribute('aria-expanded', 'false');
				}
			}
		});
	});
})();
