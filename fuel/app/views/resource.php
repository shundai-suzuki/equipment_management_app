<section
	class="maw1440 m-0_auto"
	id="resource-page"
	data-resource="<?php echo e($resource); ?>"
>
	<header class="d-flex ai-center jc-space_between mb24">
		<h1 class="m0 fs30" data-bind="text: title"></h1>
		<button
			class="p10_18 fw700 br6 c-white ba-green bo1-green ba-green_dark-hover"
			type="button"
			data-bind="text: createLabel, click: openCreate"
		></button>
	</header>

	<section class="mb18 p18 ba-white bo1-border br8">
		<div class="d-grid gtc4 g16 ai-end" data-bind="foreach: filters">
			<!-- ko if: type === 'checkbox' -->
			<label class="d-flex ai-center g8 fw600">
				<input class="w-auto p0" type="checkbox" data-bind="checked: value">
				<span data-bind="text: label"></span>
			</label>
			<!-- /ko -->
			<!-- ko if: type !== 'checkbox' -->
			<label class="d-grid g8 fw600">
				<span data-bind="text: label"></span>
				<!-- ko if: type === 'text' -->
				<input type="search" data-bind="textInput: value, attr: { placeholder: placeholder }">
				<!-- /ko -->
				<!-- ko if: type === 'select' -->
				<select data-bind="options: options, value: value"></select>
				<!-- /ko -->
			</label>
			<!-- /ko -->
		</div>
		<div class="d-flex jc-flex_end g12 mt16">
			<button class="p10_18 fw700 br6 c-green ba-white bo1-green" type="button" data-bind="click: clearFilters">条件をクリア</button>
		</div>
	</section>

	<div class="ba-white bo1-border br8 ov-hidden">
		<table>
			<thead>
				<tr>
					<!-- ko foreach: columns -->
					<th class="p13_14 ta-left va-middle bb1-border fs13 ba-table_head" data-bind="text: label"></th>
					<!-- /ko -->
					<th class="p13_14 ta-left va-middle bb1-border fs13 ba-table_head" data-bind="visible: actions.length > 0">操作</th>
				</tr>
			</thead>
			<tbody>
				<!-- ko foreach: pagedRows -->
				<tr class="ba-row_hover-hover">
					<!-- ko foreach: $root.columns -->
					<td class="p13_14 ta-left va-middle bb1-border" data-bind="text: $root.cellValue($parent, key)"></td>
					<!-- /ko -->
					<td class="p13_14 ta-left va-middle bb1-border ws-nowrap" data-bind="visible: $root.actions.length > 0">
						<!-- ko foreach: $root.actions -->
						<button
							class="mr6 p6_12 c-green fs13 fw700 ba-white bo1-green br6"
							type="button"
							data-bind="visible: $root.actionVisible($parent, $data), text: label, click: function () { $root.runAction($parent, $data); }"
						></button>
						<!-- /ko -->
					</td>
				</tr>
				<!-- /ko -->
				<tr data-bind="visible: pagedRows().length === 0">
					<td class="p40 c-muted ta-center bb1-border" data-bind="attr: { colspan: columns.length + (actions.length ? 1 : 0) }">
						該当するデータはありません。
					</td>
				</tr>
			</tbody>
		</table>
		<div class="d-flex ai-center jc-space_between p14_16">
			<span data-bind="text: totalLabel"></span>
			<div class="d-flex ai-center g12">
				<button class="p10_18 c-green fw700 ba-white bo1-green br6 cu-default-disabled o04-disabled" type="button" data-bind="click: previousPage, enable: currentPage() > 1">前へ</button>
				<span data-bind="text: pageLabel"></span>
				<button class="p10_18 c-green fw700 ba-white bo1-green br6 cu-default-disabled o04-disabled" type="button" data-bind="click: nextPage, enable: currentPage() < totalPages()">次へ</button>
			</div>
		</div>
	</div>

	<aside class="p-fixed t68 r0 zi10 w430 h-vh68 p26 oy-auto ba-white bl1-border tr100p" data-bind="css: { tr0: drawerOpen }">
		<header class="p-sticky t0 d-flex ai-center jc-space_between mb24 p20_26 ba-white bb1-border">
			<h2 class="m0 fs20" data-bind="text: drawerTitle"></h2>
			<button class="p4_10 c-navy fs24 ba-transparent bo0" type="button" aria-label="閉じる" data-bind="click: closeDrawer">×</button>
		</header>
		<form data-bind="submit: save">
			<div data-bind="foreach: formFields">
				<label class="d-grid g8 fw600 mb22" data-bind="visible: ! $data.secret || $root.editRow() === null">
					<span data-bind="text: label"></span>
					<!-- ko if: type === 'select' -->
					<select data-bind="options: options, value: $root.formValue(key)"></select>
					<!-- /ko -->
					<!-- ko if: type === 'textarea' -->
					<textarea rows="4" data-bind="value: $root.formValue(key)"></textarea>
					<!-- /ko -->
					<!-- ko if: type !== 'select' && type !== 'textarea' -->
					<input data-bind="value: $root.formValue(key), attr: { type: type }">
					<!-- /ko -->
				</label>
			</div>
			<div class="p-sticky b0 d-flex jc-flex_end g12 mt28 p18_26 ba-white bt1-border">
				<button class="p10_18 fw700 br6 c-green ba-white bo1-green" type="button" data-bind="click: closeDrawer">閉じる</button>
				<button class="p10_18 fw700 br6 c-white ba-green bo1-green ba-green_dark-hover" type="submit">保存する</button>
			</div>
		</form>
	</aside>
</section>
