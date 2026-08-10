(function (window, document, ko, api) {
	'use strict';

	var root = document.querySelector('[data-page="dashboard"]');

	if ( ! root || ! ko || ! api) {
		return;
	}

	function formatDate(value) {
		return typeof value === 'string' ? value.replace(/-/g, '/') : '';
	}

	function loanRow(row) {
		var overdue = row.loan_state === 'OVERDUE';

		return {
			id: row.id,
			equipment: row.equipment_name || '-',
			employee: row.employee_name || '-',
			dueDate: formatDate(row.due_date),
			status: overdue ? '返却超過' : '貸出中',
			statusClass: overdue ? 'c-red ba-red_light' : ''
		};
	}

	function DashboardViewModel() {
		var self = this;
		var loansUrl = root.getAttribute('data-loans-url');
		var loginUrl = root.getAttribute('data-login-url');

		self.loans = ko.observableArray([]);
		self.isLoading = ko.observable(false);
		self.errorMessage = ko.observable('');
		self.showEmpty = ko.pureComputed(function () {
			return ! self.isLoading()
				&& self.errorMessage() === ''
				&& self.loans().length === 0;
		});

		self.loadLoans = function () {
			self.isLoading(true);
			self.errorMessage('');

			api.get(loansUrl, { page: 1, active_only: true })
				.then(function (body) {
					if ( ! Array.isArray(body.data)) {
						throw new api.ApiError(500, null);
					}
					self.loans(body.data.slice(0, 6).map(loanRow));
				})
				.catch(function (error) {
					if (api.isUnauthorized(error)) {
						window.location.assign(loginUrl);
						return;
					}
					self.errorMessage(api.message(error, '貸出情報を取得できませんでした。'));
				})
				.then(function () {
					self.isLoading(false);
				});
		};
	}

	var viewModel = new DashboardViewModel();

	ko.applyBindings(viewModel, root);
	viewModel.loadLoans();
}(window, document, window.ko, window.InventoryApi));