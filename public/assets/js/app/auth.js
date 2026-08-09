(function (window, document, ko, api) {
	'use strict';

	var root = document.querySelector('[data-page]');

	if ( ! root || ! ko || ! api) {
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

	function ErrorState(fieldNames) {
		var self = this;

		self.errorMessage = ko.observable('');
		self.successMessage = ko.observable('');
		self.fieldErrors = {};

		fieldNames.forEach(function (name) {
			self.fieldErrors[name] = ko.observable('');
		});

		self.fieldError = function (name) {
			return self.fieldErrors[name] ? self.fieldErrors[name]() : '';
		};

		self.clearMessages = function () {
			self.errorMessage('');
			self.successMessage('');

			Object.keys(self.fieldErrors).forEach(function (name) {
				self.fieldErrors[name]('');
			});
		};

		self.applyError = function (error, fallback) {
			self.errorMessage(api.message(error, fallback));

			if (error instanceof api.ApiError && error.fields) {
				Object.keys(error.fields).forEach(function (name) {
					var messages = error.fields[name];
					var message = Array.isArray(messages) ? messages[0] : messages;

					if (self.fieldErrors[name] && typeof message === 'string') {
						self.fieldErrors[name](message);
					}
				});
			}

			focusFirstError();
		};
	}

	function focusFirstError() {
		window.setTimeout(function () {
			var input = root.querySelector('[aria-invalid="true"]');

			if (input) {
				input.focus();
			}
		}, 0);
	}

	function LoginViewModel() {
		var self = this;
		var form = root.querySelector('[data-auth-form]');

		PasswordVisibility.call(self);
		ErrorState.call(self, ['employee_number', 'password']);

		self.employeeNumber = ko.observable('');
		self.password = ko.observable('');
		self.isSubmitting = ko.observable(false);

		self.submit = function () {
			self.clearMessages();

			if ( ! /^[1-9][0-9]*$/.test(self.employeeNumber().trim())) {
				self.fieldErrors.employee_number('社員番号を正の整数で入力してください。');
			}

			if (self.password() === '') {
				self.fieldErrors.password('パスワードを入力してください。');
			}

			if (self.fieldError('employee_number') || self.fieldError('password')) {
				focusFirstError();
				return false;
			}

			self.isSubmitting(true);

			api.post(
				root.getAttribute('data-submit-url'),
				{
					employee_number: self.employeeNumber().trim(),
					password: self.password()
				},
				form
			)
				.then(function () {
					window.location.assign(root.getAttribute('data-redirect-url'));
				})
				.catch(function (error) {
					self.password('');
					self.applyError(
						error,
						'ログインに失敗しました。時間をおいて再度お試しください。'
					);
				})
				.then(function () {
					self.isSubmitting(false);
				});

			return false;
		};
	}

	function PasswordViewModel() {
		var self = this;
		var form = root.querySelector('[data-auth-form]');

		PasswordVisibility.call(self);
		ErrorState.call(
			self,
			['current_password', 'password', 'password_confirmation']
		);

		self.currentPassword = ko.observable('');
		self.password = ko.observable('');
		self.passwordConfirmation = ko.observable('');
		self.isSubmitting = ko.observable(false);
		self.fields = [
			{
				key: 'current_password',
				label: '現在のパスワード',
				value: self.currentPassword,
				autocomplete: 'current-password'
			},
			{
				key: 'password',
				label: '新しいパスワード',
				value: self.password,
				autocomplete: 'new-password'
			},
			{
				key: 'password_confirmation',
				label: '新しいパスワード（確認）',
				value: self.passwordConfirmation,
				autocomplete: 'new-password'
			}
		];

		self.submit = function () {
			self.clearMessages();

			if (self.currentPassword() === '') {
				self.fieldErrors.current_password('現在のパスワードを入力してください。');
			}

			if (self.password() === '') {
				self.fieldErrors.password('新しいパスワードを入力してください。');
			}
			else if (new window.Blob([self.password()]).size < 12
				|| new window.Blob([self.password()]).size > 72) {
				self.fieldErrors.password('12バイト以上72バイト以下で入力してください。');
			}

			if (self.passwordConfirmation() !== self.password()) {
				self.fieldErrors.password_confirmation('確認用パスワードが一致しません。');
			}

			if (Object.keys(self.fieldErrors).some(function (name) {
				return self.fieldErrors[name]() !== '';
			})) {
				focusFirstError();
				return false;
			}

			self.isSubmitting(true);

			api.post(
				root.getAttribute('data-submit-url'),
				{
					current_password: self.currentPassword(),
					password: self.password(),
					password_confirmation: self.passwordConfirmation()
				},
				form
			)
				.then(function () {
					self.currentPassword('');
					self.password('');
					self.passwordConfirmation('');
					self.successMessage('パスワードを変更しました。');
				})
				.catch(function (error) {
					if (api.isUnauthorized(error)) {
						window.location.assign(root.getAttribute('data-login-url'));
						return;
					}

					self.applyError(error, 'パスワードを変更できませんでした。');
				})
				.then(function () {
					self.isSubmitting(false);
				});

			return false;
		};
	}

	var page = root.getAttribute('data-page');
	var viewModel = page === 'login'
		? new LoginViewModel()
		: new PasswordViewModel();

	ko.applyBindings(viewModel, root);
}(window, document, window.ko, window.InventoryApi));
