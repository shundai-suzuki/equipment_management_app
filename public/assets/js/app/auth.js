(function (window, document, ko) {
	'use strict';

	var root = document.querySelector('[data-page]');

	if ( ! root || ! ko) {
		return;
	}

	function PasswordVisibility() {
		this.passwordVisible = ko.observable(false);
		this.passwordType = ko.pureComputed(function () {
			return this.passwordVisible() ? 'text' : 'password';
		}, this);
		this.passwordToggleLabel = ko.pureComputed(function () {
			return this.passwordVisible() ? '非表示' : '表示';
		}, this);
		this.togglePassword = function () {
			this.passwordVisible( ! this.passwordVisible());
		};
	}

	function LoginViewModel() {
		PasswordVisibility.call(this);
		this.employeeNumber = ko.observable('');
		this.password = ko.observable('');
		this.submit = function () {
		};
	}

	function PasswordViewModel() {
		PasswordVisibility.call(this);
		this.fields = [
			{ label: '現在のパスワード', value: ko.observable('') },
			{ label: '新しいパスワード', value: ko.observable('') },
			{ label: '新しいパスワード（確認）', value: ko.observable('') }
		];
		this.submit = function () {
		};
	}

	var page = root.getAttribute('data-page');
	var viewModel = page === 'login'
		? new LoginViewModel()
		: new PasswordViewModel();

	ko.applyBindings(viewModel, root);
}(window, document, window.ko));
