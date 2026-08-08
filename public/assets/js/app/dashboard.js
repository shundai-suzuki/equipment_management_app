(function (window, document, ko) {
	'use strict';

	var root = document.querySelector('[data-page="dashboard"]');

	if ( ! root || ! ko) {
		return;
	}

	function DashboardViewModel() {
		this.loans = ko.observableArray([
			{
				id: 1205,
				equipment: 'ノートPC',
				employee: '佐藤 健一',
				dueDate: '2026/08/06',
				status: '返却超過',
				statusClass: 'c-red ba-red_light'
			},
			{
				id: 1204,
				equipment: 'プロジェクター',
				employee: '鈴木 花子',
				dueDate: '2026/08/10',
				status: '貸出中',
				statusClass: ''
			},
			{
				id: 1203,
				equipment: '会議用マイク',
				employee: '高橋 一郎',
				dueDate: '2026/08/15',
				status: '貸出中',
				statusClass: ''
			}
		]);
	}

	ko.applyBindings(new DashboardViewModel(), root);
}(window, document, window.ko));
