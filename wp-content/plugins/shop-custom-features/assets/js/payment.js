(function () {
	'use strict';

	if (typeof scfPayment === 'undefined') {
		return;
	}

	var root = document.querySelector('.scf-custom-payment');
	if (!root) {
		return;
	}

	var amountInput = document.getElementById('scf_payment_amount');
	var matchedBox = document.getElementById('scf-payment-matched');
	var statusBox = document.getElementById('scf-payment-status');
	var submitBtn = document.getElementById('scf-payment-submit');
	var productIdInput = document.getElementById('scf_matched_product_id');
	var paymentMethodInput = document.getElementById('scf_payment_method');
	var matchedImage = document.getElementById('scf-matched-image');
	var matchedName = document.getElementById('scf-matched-name');
	var matchedPrice = document.getElementById('scf-matched-price');
	var matchedTotal = document.getElementById('scf-matched-total');
	var form = document.getElementById('scf-payment-form');

	var matchTimer = null;
	var matchRequest = null;
	var currentProduct = null;

	function parseAmount(value) {
		var cleaned = String(value).replace(/[^\d.]/g, '');
		var amount = parseFloat(cleaned);
		return isNaN(amount) ? 0 : amount;
	}

	function setStatus(message, type) {
		if (!statusBox) {
			return;
		}

		if (!message) {
			statusBox.hidden = true;
			statusBox.textContent = '';
			statusBox.className = 'scf-custom-payment__status';
			return;
		}

		statusBox.hidden = false;
		statusBox.textContent = message;
		statusBox.className = 'scf-custom-payment__status scf-custom-payment__status--' + (type || 'info');
	}

	function clearMatch() {
		currentProduct = null;
		if (productIdInput) {
			productIdInput.value = '';
		}
		if (matchedBox) {
			matchedBox.hidden = true;
		}
		if (submitBtn) {
			submitBtn.disabled = true;
			submitBtn.textContent = scfPayment.labels.payNow;
		}
	}

	function showMatch(product) {
		currentProduct = product;

		if (productIdInput) {
			productIdInput.value = product.id;
		}
		if (matchedImage) {
			matchedImage.src = product.image;
			matchedImage.alt = product.name;
		}
		if (matchedName) {
			matchedName.textContent = product.name;
		}
		if (matchedPrice) {
			matchedPrice.textContent = product.price_html;
		}
		if (matchedTotal) {
			matchedTotal.textContent = product.price_html;
		}
		if (matchedBox) {
			matchedBox.hidden = false;
		}
		if (submitBtn) {
			submitBtn.disabled = false;
			submitBtn.textContent = scfPayment.labels.payAmount + ' (' + product.price_html + ')';
		}

		setStatus(scfPayment.labels.matched, 'success');
	}

	function matchProduct(amount) {
		if (matchRequest) {
			matchRequest.abort();
		}

		if (amount < scfPayment.minAmount || amount > scfPayment.maxAmount) {
			clearMatch();
			setStatus(scfPayment.labels.invalid, 'error');
			return;
		}

		setStatus(scfPayment.labels.matching, 'loading');
		clearMatch();

		var body = new FormData();
		body.append('action', 'scf_match_product_by_amount');
		body.append('nonce', scfPayment.nonce);
		body.append('amount', String(amount));

		matchRequest = new AbortController();

		fetch(scfPayment.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body,
			signal: matchRequest.signal,
		})
			.then(function (res) { return res.json(); })
			.then(function (data) {
				matchRequest = null;
				if (data.success && data.data.product) {
					showMatch(data.data.product);
				} else {
					setStatus(data.data && data.data.message ? data.data.message : scfPayment.labels.notFound, 'error');
				}
			})
			.catch(function (err) {
				if (err.name !== 'AbortError') {
					matchRequest = null;
					setStatus(scfPayment.labels.notFound, 'error');
				}
			});
	}

	function scheduleMatch() {
		if (!amountInput) {
			return;
		}

		clearTimeout(matchTimer);
		matchTimer = setTimeout(function () {
			var amount = parseAmount(amountInput.value);
			if (amount > 0) {
				matchProduct(amount);
			} else {
				clearMatch();
				setStatus('', '');
			}
		}, 500);
	}

	if (amountInput) {
		amountInput.addEventListener('input', scheduleMatch);
		amountInput.addEventListener('blur', function () {
			var amount = parseAmount(amountInput.value);
			if (amount > 0) {
				amountInput.value = amount.toFixed(2);
			}
		});

		if (parseAmount(amountInput.value) > 0) {
			scheduleMatch();
		}
	}

	document.querySelectorAll('.scf-custom-payment__method').forEach(function (label) {
		label.addEventListener('click', function () {
			document.querySelectorAll('.scf-custom-payment__method').forEach(function (item) {
				item.classList.remove('is-active');
			});
			label.classList.add('is-active');
			var input = label.querySelector('input[type="radio"]');
			if (input && paymentMethodInput) {
				paymentMethodInput.value = input.value;
			}
		});
	});

	if (form) {
		form.addEventListener('submit', function (e) {
			if (!currentProduct || !productIdInput || !productIdInput.value) {
				e.preventDefault();
				setStatus(scfPayment.labels.notFound, 'error');
			}
		});
	}
})();
