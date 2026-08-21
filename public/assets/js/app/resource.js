(function (window, document, ko, api) {
	'use strict';

	const root = document.getElementById('resource-page');
	if ( ! root || ! ko || ! api) { 
		return; 
	}

	const form = root.querySelector('[data-resource-form]');
	const actionForm = root.querySelector('[data-resource-action]');
	const filters = Array.prototype.slice.call(root.querySelectorAll('[data-filter]'));
	const categoryFilter = root.querySelector('[data-category-filter]');
	let departments = [];
	const writeUrl = root.getAttribute('data-write-url');

	try { 
		departments = JSON.parse(root.getAttribute('data-departments')) || []; 
	}	catch (error) {}

	function value(row, key) {
		const item = row[key];
		if (key === 'department_id') {
			const department = departments.filter(function (candidate) {
				return String(candidate.id) === String(item);
			})[0];
			return department ? department.name : '部署ID ' + item;
		}
		if (key === 'loan_state') {
			return { ON_LOAN: '貸出中', OVERDUE: '返却超過', RETURNED: '返却済み' }[item] || '-';
		}
		if (key === 'is_active') {
			 return Number(item) === 1 ? '有効' : '無効'; }
		if (key === 'role') { 
			return item === 'ADMIN' ? '管理者' : '社員'; }
		if (key === 'loaned_at' || key === 'due_date') {
			return typeof item === 'string' ? item.replace(/-/g, '/') : '-';
		}
		return item === null || item === undefined || item === '' ? '-' : item;
	}

	function statusClass(row, key) {
		if ((key === 'loan_state' && row[key] === 'OVERDUE')
			|| (key === 'is_active' && Number(row[key]) !== 1)) {
			return 'c-red back-red_light';
		}
		if (key === 'loan_state' && row[key] === 'RETURNED') {
			return 'c-muted back-background';
		}
		return 'c-green back-green_light';
	}

	function parameters(page) {
		const result = { page: page };
		filters.forEach(function (input) {
			const key = input.getAttribute('data-filter');
			if (input.type === 'checkbox' ? input.checked : input.value !== '') {
				result[key] = input.type === 'checkbox' ? true : input.value;
			}
		});
		return result;
	}

	function setCategoryOptions(values) {
		const selected = categoryFilter.value;
		categoryFilter.innerHTML = '';
		['すべて'].concat(values || []).forEach(function (label, index) {
			const option = document.createElement('option');
			option.value = index ? label : '';
			option.text = label;
			categoryFilter.appendChild(option);
		});
		categoryFilter.value = selected;
	}

	function setFormValues(row) {
		Array.prototype.forEach.call(form.querySelectorAll('[data-field]'), function (input) {
			const item = row && row[input.name];
			input.value = (item === undefined) || (item === null) ? '' : item;
		});
	}

	function ResourceViewModel() {
		const self = this;
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
			return self.currentPage() + ' / ' + self.totalPages();
		});
		self.toggleLabel = function (row) {
			return Number(row.is_active) === 1 ? '無効化' : '有効化';
		};
		self.load = async function () {
			self.isLoading(true);
			self.errorMessage('');

			try {
				const body = await api.get(
					root.getAttribute('data-search-url'),
					parameters(self.currentPage())
				);
				const pagination = body.meta && body.meta.pagination;

				if ( ! Array.isArray(body.data) || ! pagination) {
					throw new api.ApiError(500, null);
				}

				self.rows(body.data);
				self.total(Number(pagination.total) || 0);
				self.totalPages(Number(pagination.total_pages) || 0);

				if (categoryFilter && body.meta.category_options) {
					setCategoryOptions(body.meta.category_options);
				}
			}
			catch (error) {
				if (api.isUnauthorized(error)) {
					window.location.assign(root.getAttribute('data-login-url'));
					return;
				}

				self.errorMessage(api.message(error, '一覧を取得できませんでした。'));
			}
			finally {
				self.isLoading(false);
			}
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
			const active = Number(row.is_active) === 1;
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

	const viewModel = new ResourceViewModel();
	let searchTimer;
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
