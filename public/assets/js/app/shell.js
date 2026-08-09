(function (window, document, api) {
	'use strict';

	var form = document.querySelector('[data-logout-form]');

	if ( ! form || ! api) {
		return;
	}

	var button = form.querySelector('button[type="submit"]');

	form.addEventListener('submit', function (event) {
		event.preventDefault();

		if (button) {
			button.disabled = true;
		}

		api.post(form.getAttribute('data-logout-url'), {}, form)
			.then(function () {
				window.location.assign(form.getAttribute('data-login-url'));
			})
			.catch(function () {
				if (button) {
					button.disabled = false;
				}

				window.alert('ログアウトに失敗しました。もう一度お試しください。');
			});
	});
}(window, document, window.InventoryApi));
