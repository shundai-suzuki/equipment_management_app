(function (window, document, ko, api) {
	'use strict';
	var root = document.getElementById('resource-page');
	if ( ! root || ! ko || ! api) { return; }

	var form = root.querySelector('[data-resource-form]');
	var actionForm = root.querySelector('[data-resource-action]');
	var filters = Array.prototype.slice.call(root.querySelectorAll('[data-filter]'));
	var categoryFilter = root.querySelector('[data-category-filter]');
	var departments = [];
	var writeUrl = root.getAttribute('data-write-url');

	try { departments = JSON.parse(root.getAttribute('data-departments')) || []; }
	catch (error) {}

	function value(row, key) {
		var item = row[key];
		var department;
		if (key === 'department_id') {
			department = departments.filter(function (candidate) {
				return String(candidate.id) === String(item);
			})[0];
			return department ? department.name : '部署ID ' + item;
		}
		if (key === 'loan_state') {
			return { ON_LOAN: '貸出中', OVERDUE: '返却超過', RETURNED: '返却済み' }[item] || '-';
		}
		if (key === 'is_active') { return Number(item) === 1 ? '有効' : '無効'; }
		if (key === 'role') { return item === 'ADMIN' ? '管理者' : '社員'; }
		if (key === 'loaned_at' || key === 'due_date') {
			return typeof item === 'string' ? item.replace(/-/g, '/') : '-';
		}
		return item === null || item === undefined || item === '' ? '-' : item;
	}

	function statusClass(row, key) {
		if ((key === 'loan_state' && row[key] === 'OVERDUE')
			|| (key === 'is_active' && Number(row[key]) !== 1)) {
			return 'c-red ba-red_light';
		}
		if (key === 'loan_state' && row[key] === 'RETURNED') {
			return 'c-muted ba-background';
		}
		return 'c-green ba-green_light';
	}

	function parameters(page) {
		var result = { page: page };
		filters.forEach(function (input) {
			var key = input.getAttribute('data-filter');
			if (input.type === 'checkbox' ? input.checked : input.value !== '') {
				result[key] = input.type === 'checkbox' ? true : input.value;
			}
		});
		return result;
	}

	function setCategoryOptions(values) {
		var selected = categoryFilter.value;
		categoryFilter.innerHTML = '';
		['すべて'].concat(values || []).forEach(function (label, index) {
			var option = document.createElement('option');
			option.value = index ? label : '';
			option.text = label;
			categoryFilter.appendChild(option);
		});
		categoryFilter.value = selected;
	}

	function setFormValues(row) {
		Array.prototype.forEach.call(form.querySelectorAll('[data-field]'), function (input) {
			var item = row && row[input.name];
			input.value = item === undefined || item === null ? '' : item;
		});
	}

	function ResourceViewModel() {
		var self = this;
		self.rows = ko.observableArray([]);
		self.currentPage = ko.observable(1);
		self.total = ko.observable(0);
		self.totalPages = ko.observable(0);
		self.isLoading = ko.observable(false);
		self.isSubmitting = ko.observable(false);
		self.errorMessage = ko.observable('');
		self.drawerOpen = ko.observable(false);
		self.mode = ko.observable('create');
		self.drawerTitle = ko.observable('');
		self.value = value;
		self.statusClass = statusClass;
		self.totalLabel = ko.pureComputed(function () {
			return '全' + self.total() + '件';
		});
		self.pageLabel = ko.pureComputed(function () {
			return self.totalPages() ? self.currentPage() + ' / ' + self.totalPages() : '0 / 0';
		});
		self.toggleLabel = function (row) {
			return Number(row.is_active) === 1 ? '無効化' : '有効化';
		};
		self.load = function () {
			self.isLoading(true);
			self.errorMessage('');
			api.get(root.getAttribute('data-search-url'), parameters(self.currentPage()))
				.then(function (body) {
					var pagination = body.meta && body.meta.pagination;
					if ( ! Array.isArray(body.data) || ! pagination) {
						throw new api.ApiError(500, null);
					}
					self.rows(body.data);
					self.total(Number(pagination.total) || 0);
					self.totalPages(Number(pagination.total_pages) || 0);
					if (categoryFilter && body.meta.category_options) {
						setCategoryOptions(body.meta.category_options);
					}
				})
				.catch(function (error) {
					if (api.isUnauthorized(error)) {
						window.location.assign(root.getAttribute('data-login-url'));
						return;
					}
					self.errorMessage(api.message(error, '一覧を取得できませんでした。'));
				})
				.then(function () { self.isLoading(false); });
		};
		self.clearFilters = function () {
			filters.forEach(function (input) {
				input[input.type === 'checkbox' ? 'checked' : 'value'] = input.type === 'checkbox' ? false : '';
			});
			self.currentPage(1);
			self.load();
		};
		self.previousPage = function () {
			if (self.currentPage() > 1) { self.currentPage(self.currentPage() - 1); self.load(); }
		};
		self.nextPage = function () {
			if (self.currentPage() < self.totalPages()) { self.currentPage(self.currentPage() + 1); self.load(); }
		};
		self.openCreate = function () { self.open('create'); };
		self.openView = function (row) { self.open('view', row); };
		self.openEdit = function (row) { self.open('edit', row); };
		self.openPassword = function (row) { self.open('password', row); };
		self.open = function (mode, row) {
			setFormValues(row);
			self.mode(mode);
			self.drawerTitle(mode === 'view' ? '備品詳細' : mode === 'password'
				? 'パスワード再設定' : root.getAttribute('data-' + (mode === 'edit' ? 'edit' : 'create') + '-label'));
			form.action = mode === 'create' ? writeUrl + '/create'
				: writeUrl + '/' + row.id + '/' + (mode === 'password' ? 'password' : 'update');
			self.drawerOpen(true);
		};
		self.closeDrawer = function () {
			if ( ! self.isSubmitting()) { self.drawerOpen(false); }
		};
		self.save = function () { self.isSubmitting(true); return true; };
		self.softDelete = function (row) {
			self.submitAction(row, 'soft_delete', 'このデータを削除しますか？');
		};
		self.toggle = function (row) {
			var active = Number(row.is_active) === 1;
			self.submitAction(row, active ? 'deactivate' : 'activate',
				active ? 'この社員を無効化しますか？' : 'この社員を有効化しますか？');
		};
		self.returnLoan = function (row) {
			self.submitAction(row, 'return', 'この貸出を返却済みにしますか？');
		};
		self.submitAction = function (row, operation, message) {
			if (window.confirm(message)) {
				self.isSubmitting(true);
				actionForm.action = writeUrl + '/' + row.id + '/' + operation;
				actionForm.submit();
			}
		};
	}

	var viewModel = new ResourceViewModel();
	var searchTimer;
	ko.applyBindings(viewModel, root);
	filters.forEach(function (input) {
		input.addEventListener(input.type === 'checkbox' ? 'change' : 'input', function () {
			window.clearTimeout(searchTimer);
			searchTimer = window.setTimeout(function () {
				viewModel.currentPage(1);
				viewModel.load();
			}, 300);
		});
	});
	viewModel.load();
}(window, document, window.ko, window.InventoryApi));
