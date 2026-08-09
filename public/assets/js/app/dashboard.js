(function (window, document, ko) {
	'use strict';

	var root = document.querySelector('[data-page="dashboard"]');

	if ( ! root || ! ko) {
		return;
	}

	function formatDate(date) {
		return typeof date === 'string' 
			? date.replace(/-/g, '/') 
			: '';
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

			window.fetch(loansUrl + '?page=1&active_only=true', {
				method: 'GET',
				credentials: 'same-origin',
				headers: { Accept: 'application/json' }
			})
				.then(function (response) {
					if ( ! response.ok) {
						throw new Error('request_failed');
					}

					return response.json();
				})
				.then(function (body) {
					if ( ! body || ! Array.isArray(body.data)) {
						throw new Error('invalid_response');
					}

					self.loans(body.data.slice(0, 6).map(loanRow));
				})
				.catch(function () {
					self.loans([]);
					self.errorMessage('貸出情報を取得できませんでした。');
				})
				.then(function () {
					self.isLoading(false);
				});
		};
	}

	var viewModel = new DashboardViewModel();

	ko.applyBindings(viewModel, root);
	viewModel.loadLoans();
}(window, document, window.ko));
