(function (window, document, ko, api) {
	'use strict';

	var root = document.getElementById('resource-page');

	if ( ! root || ! ko || ! api) {
		return;
	}

	var resource = root.getAttribute('data-resource');
	var isAdmin = root.getAttribute('data-is-admin') === '1';
	var searchUrl = root.getAttribute('data-search-url');
	var writeUrl = root.getAttribute('data-write-url');
	var departmentsUrl = root.getAttribute('data-departments-url');
	var loginUrl = root.getAttribute('data-login-url');
	var form = root.querySelector('[data-resource-form]');
	var departmentOptions = ko.observableArray([
		{ value: '', label: '選択してください' }
	]);

	function field(key, label, type, settings) {
		var result = {
			key: key,
			label: label,
			type: type || 'text',
			required: true,
			autocomplete: '',
			minimum: null,
			options: []
		};

		Object.keys(settings || {}).forEach(function (name) {
			result[name] = settings[name];
		});

		return result;
	}

	function filter(key, label, type, placeholder, options) {
		return {
			key: key,
			label: label,
			type: type,
			placeholder: placeholder || '',
			options: options || [],
			value: ko.observable(type === 'checkbox' ? false : '')
		};
	}

	function departmentFilter() {
		return filter('department_id', '部署', 'select', '', departmentOptions);
	}

	function definitions() {
		var categoryOptions = ko.observableArray([
			{ value: '', label: 'すべて' }
		]);
		var loanColumns = [
			{ key: 'id', label: '貸出ID' },
			{ key: 'equipmentId', label: '備品ID' },
			{ key: 'equipmentName', label: '備品名' }
		];

		if (isAdmin) {
			loanColumns.push({ key: 'employeeName', label: '借用者' });
		}

		loanColumns.push(
			{ key: 'loanedAt', label: '貸出日' },
			{ key: 'dueDate', label: '返却期限' },
			{ key: 'status', label: '状態' }
		);

		return {
			equipment: {
				title: '備品一覧',
				createLabel: '新規登録',
				columns: [
					{ key: 'id', label: '備品ID' },
					{ key: 'name', label: '備品名' },
					{ key: 'category', label: 'カテゴリ' },
					{ key: 'department', label: '管理部署' },
					{ key: 'totalAmount', label: '総数' },
					{ key: 'loanedAmount', label: '貸出中' },
					{ key: 'availableAmount', label: '利用可能' }
				],
				filters: [
					filter('q', 'キーワード', 'text', '備品ID・備品名で検索'),
					filter('category', 'カテゴリ', 'select', '', categoryOptions),
					departmentFilter(),
					filter('available_only', '利用可能のみ', 'checkbox')
				],
				fields: [
					field('name', '備品名'),
					field('category', 'カテゴリ'),
					field('department_id', '管理部署', 'select', { options: departmentOptions }),
					field('total_amount', '総数', 'number', { minimum: 1 }),
					field('description', '説明', 'textarea', { required: false })
				],
				actions: isAdmin
					? [{ type: 'detail' }, { type: 'edit' }, { type: 'archive' }]
					: [{ type: 'detail' }],
				categoryOptions: categoryOptions
			},
			loans: {
				title: '貸出一覧',
				createLabel: '貸出登録',
				columns: loanColumns,
				filters: [
					filter('q', '備品名', 'text', '備品名で検索'),
					filter('loan_state', '状態', 'select', '', [
						{ value: '', label: 'すべて' },
						{ value: 'ON_LOAN', label: '貸出中' },
						{ value: 'OVERDUE', label: '返却超過' },
						{ value: 'RETURNED', label: '返却済み' }
					])
				],
				fields: [
					field('employee_id', '借用者の社員番号', 'number', { minimum: 1 }),
					field('equipment_id', '備品ID', 'number', { minimum: 1 }),
					field('due_date', '返却期限', 'date')
				],
				actions: isAdmin ? [{ type: 'return' }] : []
			},
			employees: {
				title: '社員管理',
				createLabel: '社員登録',
				columns: [
					{ key: 'id', label: '社員番号' },
					{ key: 'employeeName', label: '社員名' },
					{ key: 'department', label: '部署' },
					{ key: 'role', label: '権限' },
					{ key: 'status', label: '状態' }
				],
				filters: [
					filter('q', 'キーワード', 'text', '社員番号・社員名で検索'),
					departmentFilter(),
					filter('role', '権限', 'select', '', [
						{ value: '', label: 'すべて' },
						{ value: 'EMPLOYEE', label: '社員' },
						{ value: 'ADMIN', label: '管理者' }
					]),
					filter('is_active', '状態', 'select', '', [
						{ value: '', label: 'すべて' },
						{ value: '1', label: '有効' },
						{ value: '0', label: '無効' }
					])
				],
				fields: [
					field('employee_name', '社員名'),
					field('department_id', '部署', 'select', { options: departmentOptions }),
					field('role', '権限', 'select', { options: [
						{ value: 'EMPLOYEE', label: '社員' },
						{ value: 'ADMIN', label: '管理者' }
					] }),
					field('password', 'パスワード', 'password', {
						modes: ['create', 'password'],
						autocomplete: 'new-password'
					}),
					field('password_confirmation', 'パスワード（確認）', 'password', {
						modes: ['create', 'password'],
						autocomplete: 'new-password'
					}),
					field('admin_password', '管理者パスワード', 'password', {
						modes: ['password'],
						autocomplete: 'current-password'
					})
				],
				actions: [
					{ type: 'edit' },
					{ type: 'toggleActive' },
					{ type: 'password' },
					{ type: 'archive' }
				]
			},
			departments: {
				title: '部署管理',
				createLabel: '部署登録',
				columns: [
					{ key: 'id', label: '部署ID' },
					{ key: 'name', label: '部署名' }
				],
				filters: [filter('q', 'キーワード', 'text', '部署ID・部署名で検索')],
				fields: [field('name', '部署名')],
				actions: [{ type: 'edit' }, { type: 'archive' }]
			}
		};
	}

	var definition = definitions()[resource];

	if ( ! definition) {
		return;
	}

	function toNumber(value) {
		var result = Number(value);
		return isFinite(result) ? result : 0;
	}

	function formatDate(value) {
		return typeof value === 'string' ? value.replace(/-/g, '/') : '';
	}

	function byteLength(value) {
		return unescape(encodeURIComponent(value)).length;
	}

	function ResourceViewModel() {
		var self = this;
		var requestSequence = 0;
		var searchTimer = null;
		var subscriptionsPaused = false;

		self.title = definition.title;
		self.createLabel = definition.createLabel;
		self.canCreate = isAdmin && writeUrl !== '';
		self.columns = definition.columns;
		self.filters = definition.filters;
		self.actions = definition.actions;
		self.rows = ko.observableArray([]);
		self.currentPage = ko.observable(1);
		self.total = ko.observable(0);
		self.totalPages = ko.observable(0);
		self.isLoading = ko.observable(false);
		self.isSubmitting = ko.observable(false);
		self.isBusy = ko.pureComputed(function () {
			return self.isLoading() || self.isSubmitting();
		});
		self.errorMessage = ko.observable('');
		self.successMessage = ko.observable('');
		self.formErrorMessage = ko.observable('');
		self.drawerOpen = ko.observable(false);
		self.mode = ko.observable('create');
		self.editRow = ko.observable(null);
		self.formValues = {};
		self.fieldErrors = {};

		definition.fields.forEach(function (item) {
			if ( ! self.formValues[item.key]) {
				self.formValues[item.key] = ko.observable('');
				self.fieldErrors[item.key] = ko.observable('');
			}
		});

		self.drawerTitle = ko.pureComputed(function () {
			if (self.mode() === 'view') {
				return '備品詳細';
			}
			if (self.mode() === 'password') {
				return 'パスワード再設定';
			}
			return self.mode() === 'edit'
				? definition.title.replace('一覧', '').replace('管理', '') + '編集'
				: definition.createLabel;
		});
		self.saveLabel = ko.pureComputed(function () {
			return self.isSubmitting() ? '送信中…' : '保存する';
		});
		self.viewOnly = ko.pureComputed(function () {
			return self.mode() === 'view';
		});
		self.activeFormFields = ko.pureComputed(function () {
			return definition.fields.filter(function (item) {
				if (self.mode() === 'password') {
					return item.modes && item.modes.indexOf('password') !== -1;
				}
				return ! item.modes || item.modes.indexOf(self.mode()) !== -1;
			});
		});
		self.totalLabel = ko.pureComputed(function () {
			return '全' + self.total() + '件';
		});
		self.pageLabel = ko.pureComputed(function () {
			return self.totalPages() === 0
				? '0 / 0'
				: self.currentPage() + ' / ' + self.totalPages();
		});

		self.formValue = function (key) {
			return self.formValues[key];
		};
		self.fieldError = function (key) {
			return self.fieldErrors[key] ? self.fieldErrors[key]() : '';
		};
		self.formDisabled = function () {
			return self.viewOnly() || self.isSubmitting();
		};
		self.cellValue = function (row, key) {
			if (key === 'department') {
				return departmentLabel(row.departmentId);
			}
			return Object.prototype.hasOwnProperty.call(row, key) ? row[key] : '-';
		};
		self.statusClass = function (row) {
			if (row.statusCode === 'OVERDUE' || row.statusCode === 'INACTIVE') {
				return 'c-red ba-red_light';
			}
			if (row.statusCode === 'RETURNED') {
				return 'c-muted ba-background';
			}
			return 'c-green ba-green_light';
		};
		self.actionVisible = function (row, action) {
			return action.type !== 'return' || row.statusCode !== 'RETURNED';
		};
		self.actionLabel = function (row, action) {
			var labels = {
				detail: '詳細',
				edit: '編集',
				archive: '削除',
				password: 'パスワード再設定',
				return: '返却'
			};
			if (action.type === 'toggleActive') {
				return row.isActive ? '無効化' : '有効化';
			}
			return labels[action.type] || '操作';
		};
		self.actionClass = function (action) {
			return action.type === 'archive' || action.type === 'return'
				? 'c-red bo1-red'
				: 'c-green bo1-green';
		};

		self.load = function () {
			var sequence = ++requestSequence;
			self.isLoading(true);
			self.errorMessage('');

			api.get(searchUrl, searchParameters())
				.then(function (body) {
					if (sequence !== requestSequence) {
						return;
					}
					var pagination = body.meta && body.meta.pagination;
					if ( ! Array.isArray(body.data) || ! pagination) {
						throw new api.ApiError(500, null);
					}
					self.rows(body.data.map(mapRow));
					self.total(toNumber(pagination.total));
					self.totalPages(toNumber(pagination.total_pages));
					updateCategoryOptions(body.meta);
					reflectSearch();
				})
				.catch(function (error) {
					if (sequence !== requestSequence) {
						return;
					}
					if (api.isUnauthorized(error)) {
						window.location.assign(loginUrl);
						return;
					}
					self.errorMessage(api.message(error, '一覧を取得できませんでした。'));
				})
				.then(function () {
					if (sequence === requestSequence) {
						self.isLoading(false);
					}
				});
		};

		self.clearFilters = function () {
			subscriptionsPaused = true;
			self.filters.forEach(function (item) {
				item.value(item.type === 'checkbox' ? false : '');
			});
			self.currentPage(1);
			subscriptionsPaused = false;
			self.load();
		};
		self.previousPage = function () {
			if (self.currentPage() > 1) {
				self.currentPage(self.currentPage() - 1);
				self.load();
			}
		};
		self.nextPage = function () {
			if (self.currentPage() < self.totalPages()) {
				self.currentPage(self.currentPage() + 1);
				self.load();
			}
		};
		self.openCreate = function () {
			openForm('create', null);
		};
		self.closeDrawer = function () {
			if ( ! self.isSubmitting()) {
				self.drawerOpen(false);
			}
		};
		self.runAction = function (row, action) {
			if (self.isSubmitting()) {
				return;
			}
			if (action.type === 'detail') {
				openForm('view', row);
			}
			else if (action.type === 'edit') {
				openForm('edit', row);
			}
			else if (action.type === 'password') {
				openForm('password', row);
			}
			else if (action.type === 'archive') {
				postAction(row, '/archive', 'このデータを削除しますか？', '削除しました。');
			}
			else if (action.type === 'toggleActive') {
				postAction(
					row,
					row.isActive ? '/deactivate' : '/activate',
					row.isActive ? 'この社員を無効化しますか？' : 'この社員を有効化しますか？',
					row.isActive ? '社員を無効化しました。' : '社員を有効化しました。'
				);
			}
			else if (action.type === 'return') {
				postAction(
					row,
					'/return',
					'この貸出を返却済みにしますか？',
					'返却を登録しました。',
					{ note: '' }
				);
			}
		};

		self.save = function () {
			if (self.viewOnly() || self.isSubmitting() || ! validate()) {
				return false;
			}

			var row = self.editRow();
			var payload = formPayload();
			var url = writeUrl;
			var creating = self.mode() === 'create';

			if (self.mode() === 'edit') {
				url += '/' + row.id;
			}
			else if (self.mode() === 'password') {
				url += '/' + row.id + '/password';
			}

			if (resource === 'loans'
				&& ! window.confirm('この内容で貸出を登録しますか？')) {
				return false;
			}
			if (resource === 'employees'
				&& self.mode() === 'edit'
				&& row.roleCode !== payload.role
				&& ! window.confirm('社員の権限を変更しますか？')) {
				return false;
			}

			self.isSubmitting(true);
			self.formErrorMessage('');

			api.post(url, payload, form)
				.then(function (body) {
					if (creating && toNumber(body.data && body.data.id) < 1) {
						throw new api.ApiError(500, null);
					}
					self.successMessage(creating ? '登録しました。' : '更新しました。');
					self.drawerOpen(false);
					self.load();
				})
				.catch(handleFormError)
				.then(function () {
					self.isSubmitting(false);
				});

			return false;
		};

		function departmentLabel(id) {
			var match = departmentOptions().filter(function (item) {
				return String(item.value) === String(id);
			})[0];
			return match ? match.label : '部署ID ' + id;
		}

		function mapRow(row) {
			if (resource === 'equipment') {
				return {
					id: toNumber(row.id),
					name: row.name || '',
					category: row.category || '',
					departmentId: toNumber(row.department_id),
					totalAmount: toNumber(row.total_amount),
					loanedAmount: toNumber(row.loaned_amount),
					availableAmount: toNumber(row.available_amount),
					description: row.description || ''
				};
			}
			if (resource === 'loans') {
				return {
					id: toNumber(row.id),
					employeeId: toNumber(row.employee_id),
					employeeName: row.employee_name || '-',
					equipmentId: toNumber(row.equipment_id),
					equipmentName: row.equipment_name || '-',
					loanedAt: formatDate(row.loaned_at),
					dueDate: formatDate(row.due_date),
					status: loanStateLabel(row.loan_state),
					statusCode: row.loan_state
				};
			}
			if (resource === 'employees') {
				return {
					id: toNumber(row.id),
					employeeName: row.employee_name || '',
					departmentId: toNumber(row.department_id),
					role: row.role === 'ADMIN' ? '管理者' : '社員',
					roleCode: row.role,
					isActive: toNumber(row.is_active) === 1,
					status: toNumber(row.is_active) === 1 ? '有効' : '無効',
					statusCode: toNumber(row.is_active) === 1 ? 'ACTIVE' : 'INACTIVE'
				};
			}
			return { id: toNumber(row.id), name: row.name || '' };
		}

		function loanStateLabel(state) {
			return {
				ON_LOAN: '貸出中',
				OVERDUE: '返却超過',
				RETURNED: '返却済み'
			}[state] || '-';
		}

		function searchParameters() {
			var parameters = { page: self.currentPage() };
			self.filters.forEach(function (item) {
				var value = item.value();
				if (item.type === 'checkbox') {
					if (value) {
						parameters[item.key] = true;
					}
				}
				else if (value !== '') {
					parameters[item.key] = value;
				}
			});
			return parameters;
		}

		function reflectSearch() {
			var parameters = searchParameters();
			var query = [];
			Object.keys(parameters).forEach(function (name) {
				if (name !== 'page' || toNumber(parameters[name]) !== 1) {
					query.push(
						encodeURIComponent(name) + '=' + encodeURIComponent(parameters[name])
					);
				}
			});
			window.history.replaceState(
				null,
				'',
				window.location.pathname + (query.length ? '?' + query.join('&') : '')
			);
		}

		function restoreSearch() {
			var parameters = new window.URLSearchParams(window.location.search);
			var page = parameters.get('page');
			if (page && /^[1-9][0-9]*$/.test(page)) {
				self.currentPage(toNumber(page));
			}
			self.filters.forEach(function (item) {
				var value = parameters.get(item.key);
				if (value !== null) {
					item.value(item.type === 'checkbox' ? value === 'true' : value);
				}
			});
		}

		function updateCategoryOptions(meta) {
			if (resource !== 'equipment' || ! Array.isArray(meta.category_options)) {
				return;
			}
			definition.categoryOptions([
				{ value: '', label: 'すべて' }
			].concat(meta.category_options.map(function (value) {
				return { value: value, label: value };
			})));
		}

		function clearForm() {
			Object.keys(self.formValues).forEach(function (key) {
				self.formValues[key]('');
				self.fieldErrors[key]('');
			});
			self.formErrorMessage('');
		}

		function openForm(mode, row) {
			clearForm();
			self.mode(mode);
			self.editRow(row);

			if (row) {
				if (resource === 'equipment') {
					self.formValues.name(row.name);
					self.formValues.category(row.category);
					self.formValues.department_id(String(row.departmentId));
					self.formValues.total_amount(String(row.totalAmount));
					self.formValues.description(row.description);
				}
				else if (resource === 'employees' && mode !== 'password') {
					self.formValues.employee_name(row.employeeName);
					self.formValues.department_id(String(row.departmentId));
					self.formValues.role(row.roleCode);
				}
				else if (resource === 'departments') {
					self.formValues.name(row.name);
				}
			}

			self.drawerOpen(true);
		}

		function formPayload() {
			var values = {};
			self.activeFormFields().forEach(function (item) {
				values[item.key] = self.formValues[item.key]();
			});
			if (self.mode() === 'create') {
				values.id = 0;
			}
			return values;
		}

		function validate() {
			var valid = true;
			clearErrors();

			self.activeFormFields().forEach(function (item) {
				var value = String(self.formValues[item.key]() || '').trim();
				var message = '';
				if (item.required && value === '') {
					message = item.label + 'を入力してください。';
				}
				else if (item.type === 'number'
					&& ( ! /^[0-9]+$/.test(value)
						|| toNumber(value) < toNumber(item.minimum || 0))) {
					message = item.label + 'を正しい数値で入力してください。';
				}
				else if (item.type === 'password'
					&& item.key !== 'admin_password'
					&& (byteLength(value) < 12 || byteLength(value) > 72)) {
					message = 'パスワードは12～72バイトで入力してください。';
				}
				if (message !== '') {
					self.fieldErrors[item.key](message);
					valid = false;
				}
			});

			if (self.formValues.password
				&& self.formValues.password_confirmation
				&& self.formValues.password() !== self.formValues.password_confirmation()) {
				self.fieldErrors.password_confirmation('確認用パスワードが一致しません。');
				valid = false;
			}

			if ( ! valid) {
				self.formErrorMessage('入力内容を確認してください。');
				focusInvalidField();
			}
			return valid;
		}

		function clearErrors() {
			Object.keys(self.fieldErrors).forEach(function (key) {
				self.fieldErrors[key]('');
			});
			self.formErrorMessage('');
		}

		function focusInvalidField() {
			window.setTimeout(function () {
				var invalid = form.querySelector('[aria-invalid="true"]');
				if (invalid) {
					invalid.focus();
				}
			}, 0);
		}

		function handleFormError(error) {
			if (api.isUnauthorized(error)) {
				window.location.assign(loginUrl);
				return;
			}
			if (error.fields && typeof error.fields === 'object') {
				Object.keys(error.fields).forEach(function (key) {
					if (self.fieldErrors[key]) {
						self.fieldErrors[key](String(error.fields[key]));
					}
				});
				focusInvalidField();
			}
			self.formErrorMessage(api.message(error, '保存できませんでした。'));
		}

		function postAction(row, suffix, confirmation, success, values) {
			if ( ! window.confirm(confirmation)) {
				return;
			}

			self.isSubmitting(true);
			self.errorMessage('');

			api.post(writeUrl + '/' + row.id + suffix, values || {}, form)
				.then(function () {
					self.successMessage(success);
					self.load();
				})
				.catch(function (error) {
					if (api.isUnauthorized(error)) {
						window.location.assign(loginUrl);
						return;
					}
					self.errorMessage(api.message(error, '操作を完了できませんでした。'));
				})
				.then(function () {
					self.isSubmitting(false);
				});
		}

		restoreSearch();

		self.filters.forEach(function (item) {
			item.value.subscribe(function () {
				if (subscriptionsPaused) {
					return;
				}
				window.clearTimeout(searchTimer);
				searchTimer = window.setTimeout(function () {
					self.currentPage(1);
					self.load();
				}, 300);
			});
		});
	}

	function loadDepartments() {
		if (resource !== 'equipment' && resource !== 'employees') {
			return window.Promise.resolve();
		}

		return api.allPages(departmentsUrl).then(function (rows) {
			departmentOptions([
				{ value: '', label: '選択してください' }
			].concat(rows.map(function (row) {
				return { value: String(row.id), label: row.name };
			})));
		});
	}

	var viewModel = new ResourceViewModel();
	ko.applyBindings(viewModel, root);

	var departmentLoadError = '';

	loadDepartments()
		.then(function () {
			return true;
		})
		.catch(function (error) {
			if (api.isUnauthorized(error)) {
				window.location.assign(loginUrl);
				return false;
			}
			departmentLoadError = api.message(
				error,
				'部署一覧を取得できませんでした。'
			);
			return true;
		})
		.then(function (shouldLoad) {
			if (shouldLoad) {
				viewModel.load();
				if (departmentLoadError !== '') {
					viewModel.errorMessage(departmentLoadError);
				}
			}
		});
}(window, document, window.ko, window.InventoryApi));