(function (window, document, ko) {
	'use strict';

	var root = document.getElementById('resource-page');

	if ( ! root || ! ko) {
		return;
	}

	var definitions = {
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
				{
					type: 'text',
					label: 'キーワード',
					placeholder: '備品ID・備品名で検索',
					matchFields: ['id', 'name']
				},
				{
					type: 'select',
					label: 'カテゴリ',
					key: 'category',
					options: ['すべて', '情報機器', '周辺機器', 'オフィス家具']
				},
				{
					type: 'select',
					label: '管理部署',
					key: 'department',
					options: ['すべて', '総務部', '情報システム部', '営業部']
				},
				{
					type: 'checkbox',
					label: '利用可能のみ',
					key: 'availableAmount'
				}
			],
			formFields: [
				{ key: 'name', label: '備品名', type: 'text' },
				{
					key: 'category',
					label: 'カテゴリ',
					type: 'select',
					options: ['情報機器', '周辺機器', 'オフィス家具']
				},
				{
					key: 'department',
					label: '管理部署',
					type: 'select',
					options: ['総務部', '情報システム部', '営業部']
				},
				{ key: 'totalAmount', label: '総数', type: 'number' },
				{ key: 'description', label: '説明', type: 'textarea' }
			],
			actions: [{ label: '編集', type: 'edit' }],
			defaults: { loanedAmount: '-', availableAmount: '-' },
			rows: [
				{ id: 101, name: 'ノートPC A', category: '情報機器', department: '総務部', totalAmount: 10, loanedAmount: 3, availableAmount: 7, description: '標準貸与用ノートPC' },
				{ id: 102, name: 'ノートPC B', category: '情報機器', department: '総務部', totalAmount: 8, loanedAmount: 2, availableAmount: 6, description: '' },
				{ id: 103, name: 'モニター 24インチ', category: '情報機器', department: '情報システム部', totalAmount: 15, loanedAmount: 6, availableAmount: 9, description: '' },
				{ id: 104, name: 'キーボード', category: '周辺機器', department: '総務部', totalAmount: 20, loanedAmount: 8, availableAmount: 12, description: '' },
				{ id: 105, name: '会議用スピーカー', category: 'オフィス家具', department: '営業部', totalAmount: 6, loanedAmount: 2, availableAmount: 4, description: '' }
			]
		},

		loans: {
			title: '貸出一覧',
			createLabel: '貸出登録',
			columns: [
				{ key: 'id', label: '貸出ID' },
				{ key: 'equipmentId', label: '備品ID' },
				{ key: 'equipment', label: '備品' },
				{ key: 'employee', label: '借用者' },
				{ key: 'loanedAt', label: '貸出日' },
				{ key: 'dueDate', label: '返却期限' },
				{ key: 'status', label: '状態' }
			],
			filters: [
				{
					type: 'text',
					label: 'キーワード',
					placeholder: '貸出ID・備品・借用者で検索',
					matchFields: ['id', 'equipment', 'employee']
				},
				{
					type: 'select',
					label: '状態',
					key: 'status',
					options: ['すべて', '貸出中', '返却超過', '返却済']
				}
			],
			formFields: [
				{
					key: 'employee',
					label: '借用者',
					type: 'select',
					options: ['佐藤 健一', '鈴木 花子', '高橋 一郎']
				},
				{
					key: 'equipment',
					label: '備品',
					type: 'select',
					options: ['ノートPC A', 'プロジェクター', '会議用マイク']
				},
				{ key: 'dueDate', label: '返却期限', type: 'date' }
			],
			actions: [{ label: '返却', type: 'return' }],
			defaults: { equipmentId: '-', loanedAt: '未保存', status: '貸出中' },
			rows: [
				{ id: 1001, equipmentId: 101, equipment: 'ノートPC A', employee: '山田 太郎', loanedAt: '2026/07/15', dueDate: '2026/07/29', status: '返却超過' },
				{ id: 1002, equipmentId: 205, equipment: 'プロジェクター', employee: '鈴木 花子', loanedAt: '2026/07/18', dueDate: '2026/08/12', status: '貸出中' },
				{ id: 1003, equipmentId: 303, equipment: '会議用マイク', employee: '佐藤 一郎', loanedAt: '2026/08/01', dueDate: '2026/08/20', status: '貸出中' },
				{ id: 1004, equipmentId: 614, equipment: 'キーボード', employee: '田中 美咲', loanedAt: '2026/07/03', dueDate: '2026/08/03', status: '返却済' }
			]
		},

		employees: {
			title: '社員管理',
			createLabel: '社員を登録',
			columns: [
				{ key: 'id', label: '社員番号' },
				{ key: 'employeeName', label: '表示名' },
				{ key: 'department', label: '部署' },
				{ key: 'role', label: '権限' },
				{ key: 'status', label: '有効状態' },
				{ key: 'updatedAt', label: '更新日時' }
			],
			filters: [
				{
					type: 'text',
					label: 'キーワード',
					placeholder: '社員番号・表示名で検索',
					matchFields: ['id', 'employeeName']
				},
				{
					type: 'select',
					label: '部署',
					key: 'department',
					options: ['すべて', '総務部', '情報システム部', '営業部']
				},
				{
					type: 'select',
					label: '権限',
					key: 'role',
					options: ['すべて', '社員', '管理者']
				},
				{
					type: 'select',
					label: '有効状態',
					key: 'status',
					options: ['すべて', '有効', '利用停止']
				}
			],
			formFields: [
				{ key: 'employeeName', label: '表示名', type: 'text' },
				{
					key: 'department',
					label: '部署',
					type: 'select',
					options: ['総務部', '情報システム部', '営業部']
				},
				{
					key: 'role',
					label: '権限',
					type: 'select',
					options: ['社員', '管理者']
				},
				{ key: 'password', label: 'パスワード', type: 'password', secret: true },
				{ key: 'passwordConfirmation', label: 'パスワード（確認）', type: 'password', secret: true }
			],
			actions: [{ label: '編集', type: 'edit' }],
			defaults: { status: '有効', updatedAt: '未保存' },
			rows: [
				{ id: 100101, employeeName: '社員 A', department: '総務部', role: '社員', status: '有効', updatedAt: '2026/08/07 10:15' },
				{ id: 100102, employeeName: '社員 B', department: '情報システム部', role: '管理者', status: '有効', updatedAt: '2026/08/06 16:42' },
				{ id: 100103, employeeName: '社員 C', department: '営業部', role: '社員', status: '利用停止', updatedAt: '2026/08/05 09:30' },
				{ id: 100104, employeeName: '社員 D', department: '経理部', role: '社員', status: '有効', updatedAt: '2026/08/04 14:05' }
			]
		},

		departments: {
			title: '部署管理',
			createLabel: '部署を登録',
			columns: [
				{ key: 'id', label: '部署ID' },
				{ key: 'name', label: '部署名' },
				{ key: 'updatedAt', label: '更新日時' }
			],
			filters: [
				{
					type: 'text',
					label: 'キーワード',
					placeholder: '部署ID・部署名で検索',
					matchFields: ['id', 'name']
				}
			],
			formFields: [
				{ key: 'name', label: '部署名', type: 'text' }
			],
			actions: [{ label: '編集', type: 'edit' }],
			defaults: { updatedAt: '未保存' },
			rows: [
				{ id: 1, name: '総務部', updatedAt: '2026/08/07 10:30' },
				{ id: 2, name: '人事部', updatedAt: '2026/08/06 16:45' },
				{ id: 3, name: '情報システム部', updatedAt: '2026/08/05 14:20' },
				{ id: 4, name: '営業部', updatedAt: '2026/08/04 11:15' },
				{ id: 5, name: '経理部', updatedAt: '2026/08/03 09:40' }
			]
		}
	};

	function ResourcePageViewModel(config) {
		var self = this;

		this.title = config.title;
		this.createLabel = config.createLabel;
		this.columns = config.columns;
		this.actions = config.actions;
		this.formFields = config.formFields;
		this.rows = ko.observableArray(copyRows(config.rows));
		this.filters = createFilters(config.filters);
		this.currentPage = ko.observable(1);
		this.perPage = 10;
		this.drawerOpen = ko.observable(false);
		this.editRow = ko.observable(null);
		this.form = createForm(config.formFields);

		this.filteredRows = ko.pureComputed(function () {
			return self.rows().filter(function (row) {
				return self.filters.every(function (filter) {
					return matchesFilter(row, filter);
				});
			});
		});

		this.totalPages = ko.pureComputed(function () {
			return Math.max(1, Math.ceil(self.filteredRows().length / self.perPage));
		});

		this.pagedRows = ko.pureComputed(function () {
			var page = Math.min(self.currentPage(), self.totalPages());
			var start = (page - 1) * self.perPage;

			return self.filteredRows().slice(start, start + self.perPage);
		});

		this.totalLabel = ko.pureComputed(function () {
			return '全 ' + self.filteredRows().length + ' 件';
		});

		this.pageLabel = ko.pureComputed(function () {
			return self.currentPage() + ' / ' + self.totalPages();
		});

		this.drawerTitle = ko.pureComputed(function () {
			return self.editRow() === null ? config.createLabel : config.title + '編集';
		});

		this.filters.forEach(function (filter) {
			filter.value.subscribe(function () {
				self.currentPage(1);
			});
		});

		this.clearFilters = function () {
			self.filters.forEach(function (filter) {
				filter.value(filter.defaultValue);
			});
		};

		this.previousPage = function () {
			if (self.currentPage() > 1) {
				self.currentPage(self.currentPage() - 1);
			}
		};

		this.nextPage = function () {
			if (self.currentPage() < self.totalPages()) {
				self.currentPage(self.currentPage() + 1);
			}
		};

		this.formValue = function (key) {
			return self.form[key];
		};

		this.openCreate = function () {
			self.editRow(null);
			resetForm(self.formFields, self.form);
			self.drawerOpen(true);
		};

		this.openEdit = function (row) {
			self.editRow(row);
			self.formFields.forEach(function (field) {
				self.form[field.key](field.secret ? '' : valueOrEmpty(row[field.key]));
			});
			self.drawerOpen(true);
		};

		this.closeDrawer = function () {
			resetForm(self.formFields, self.form);
			self.editRow(null);
			self.drawerOpen(false);
		};

		this.save = function () {
			var row = self.editRow();

			if (row === null) {
				row = copyObject(config.defaults || {});
				row.id = 0;
				self.formFields.forEach(function (field) {
					if ( ! field.secret) {
						row[field.key] = self.form[field.key]();
					}
				});
				self.rows.remove(function (item) {
					return item.id === 0;
				});
				self.rows.unshift(row);
			}
			else {
				self.formFields.forEach(function (field) {
					if ( ! field.secret) {
						row[field.key] = self.form[field.key]();
					}
				});
				self.rows.valueHasMutated();
			}

			self.closeDrawer();
			return false;
		};

		this.runAction = function (row, action) {
			if (action.type === 'edit') {
				self.openEdit(row);
			}

			if (action.type === 'return' && row.status !== '返却済') {
				row.status = '返却済';
				self.rows.valueHasMutated();
			}
		};

		this.actionVisible = function (row, action) {
			return action.type !== 'return' || row.status !== '返却済';
		};

		this.cellValue = function (row, key) {
			if (key === 'id' && row[key] === 0) {
				return '未採番';
			}

			return row[key] === null || typeof row[key] === 'undefined' || row[key] === ''
				? '-'
				: row[key];
		};
	}

	function createFilters(definitions) {
		return definitions.map(function (definition) {
			var defaultValue = definition.type === 'checkbox'
				? false
				: (definition.type === 'select' ? definition.options[0] : '');

			return {
				type: definition.type,
				label: definition.label,
				key: definition.key || '',
				placeholder: definition.placeholder || '',
				matchFields: definition.matchFields || [],
				options: definition.options || [],
				defaultValue: defaultValue,
				value: ko.observable(defaultValue)
			};
		});
	}

	function createForm(fields) {
		var form = {};

		fields.forEach(function (field) {
			form[field.key] = ko.observable(defaultFieldValue(field));
		});

		return form;
	}

	function resetForm(fields, form) {
		fields.forEach(function (field) {
			form[field.key](defaultFieldValue(field));
		});
	}

	function defaultFieldValue(field) {
		return field.type === 'select' && field.options.length > 0
			? field.options[0]
			: '';
	}

	function matchesFilter(row, filter) {
		var value = filter.value();

		if (filter.type === 'text') {
			value = String(value).toLowerCase().trim();

			if (value === '') {
				return true;
			}

			return filter.matchFields.some(function (key) {
				return String(row[key]).toLowerCase().indexOf(value) !== -1;
			});
		}

		if (filter.type === 'select') {
			return value === 'すべて' || String(row[filter.key]) === String(value);
		}

		if (filter.type === 'checkbox') {
			return ! value || Number(row[filter.key]) > 0;
		}

		return true;
	}

	function copyRows(rows) {
		return rows.map(function (row) {
			return copyObject(row);
		});
	}

	function copyObject(source) {
		var target = {};

		Object.keys(source).forEach(function (key) {
			target[key] = source[key];
		});

		return target;
	}

	function valueOrEmpty(value) {
		return value === null || typeof value === 'undefined' ? '' : value;
	}

	var resource = root.getAttribute('data-resource');
	var definition = definitions[resource];

	if (definition) {
		ko.applyBindings(new ResourcePageViewModel(definition), root);
	}
}(window, document, window.ko));
