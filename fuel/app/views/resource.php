<section
	class="maw1440 m-0_auto"
	id="resource-page"
	data-resource="<?php echo e($resource); ?>"
	data-is-admin="<?php echo $is_admin ? '1' : '0'; ?>"
	data-search-url="<?php echo e($search_url); ?>"
	data-write-url="<?php echo e($write_url); ?>"
	data-departments-url="<?php echo e($departments_url); ?>"
	data-login-url="<?php echo e($login_url); ?>"
>
	<header class="d-flex ai-center jc-space_between mb24">
		<h1 class="m0 fs30" data-bind="text: title"></h1>
		<?php if ($is_admin): ?>
			<button
				class="p10_18 fw700 br6 c-white ba-green bo1-green ba-green_dark-hover"
				type="button"
				data-bind="visible: canCreate, text: createLabel, click: openCreate, enable: ! isBusy()"
			></button>
		<?php endif; ?>
	</header>

	<div
		hidden
		class="mb18 p13_16 c-red ba-red_light bo1-border br6"
		role="alert"
		data-bind="attr: { hidden: ! errorMessage() }, text: errorMessage"
	></div>
	<div
		hidden
		class="mb18 p13_16 c-green ba-green_light bo1-border br6"
		role="status"
		data-bind="attr: { hidden: ! successMessage() }, text: successMessage"
	></div>

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
				<select
					data-bind="options: options, optionsText: 'label', optionsValue: 'value', value: value, valueAllowUnset: true"
				></select>
				<!-- /ko -->
			</label>
			<!-- /ko -->
		</div>
		<div class="d-flex jc-flex_end g12 mt16">
			<button class="p10_18 fw700 br6 c-green ba-white bo1-green" type="button" data-bind="click: clearFilters, enable: ! isLoading()">条件をクリア</button>
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
			<tbody aria-live="polite">
				<tr hidden data-bind="attr: { hidden: ! (isLoading() && rows().length === 0) }">
					<td class="p40 c-muted ta-center bb1-border" data-bind="attr: { colspan: columns.length + (actions.length ? 1 : 0) }">読み込んでいます。</td>
				</tr>
				<!-- ko foreach: rows -->
				<tr class="ba-row_hover-hover">
					<!-- ko foreach: $root.columns -->
					<td class="p13_14 ta-left va-middle bb1-border">
						<!-- ko if: key === 'status' -->
						<span
							class="d-inline_block p4_9 fs13 fw700 br999"
							data-bind="text: $root.cellValue($parent, key), css: $root.statusClass($parent)"
						></span>
						<!-- /ko -->
						<!-- ko if: key !== 'status' -->
						<span data-bind="text: $root.cellValue($parent, key)"></span>
						<!-- /ko -->
					</td>
					<!-- /ko -->
					<td class="p13_14 ta-left va-middle bb1-border ws-nowrap" data-bind="visible: $root.actions.length > 0">
						<!-- ko foreach: $root.actions -->
						<button
							class="mr6 p6_12 fs13 fw700 ba-white br6"
							type="button"
							data-bind="visible: $root.actionVisible($parent, $data), text: $root.actionLabel($parent, $data), css: $root.actionClass($data), click: function () { $root.runAction($parent, $data); }, enable: ! $root.isBusy()"
						></button>
						<!-- /ko -->
					</td>
				</tr>
				<!-- /ko -->
				<tr hidden data-bind="attr: { hidden: ! (! isLoading() && rows().length === 0) }">
					<td class="p40 c-muted ta-center bb1-border" data-bind="attr: { colspan: columns.length + (actions.length ? 1 : 0) }">
						該当するデータはありません。
					</td>
				</tr>
			</tbody>
		</table>
		<div class="d-flex ai-center jc-space_between p14_16">
			<span data-bind="text: totalLabel"></span>
			<div class="d-flex ai-center g12">
				<button class="p10_18 c-green fw700 ba-white bo1-green br6 cu-default-disabled o04-disabled" type="button" data-bind="click: previousPage, enable: currentPage() > 1 && ! isLoading()">前へ</button>
				<span data-bind="text: pageLabel"></span>
				<button class="p10_18 c-green fw700 ba-white bo1-green br6 cu-default-disabled o04-disabled" type="button" data-bind="click: nextPage, enable: currentPage() < totalPages() && ! isLoading()">次へ</button>
			</div>
		</div>
	</div>

	<aside
		class="p-fixed t68 r0 zi10 w430 h-vh68 p26 oy-auto ba-white bl1-border tr100p"
		data-bind="css: { tr0: drawerOpen }, attr: { 'aria-hidden': drawerOpen() ? 'false' : 'true' }"
	>
		<header class="p-sticky t0 d-flex ai-center jc-space_between mb24 p20_26 ba-white bb1-border">
			<h2 class="m0 fs20" data-bind="text: drawerTitle"></h2>
			<button class="p4_10 c-navy fs24 ba-transparent bo0" type="button" aria-label="閉じる" data-bind="click: closeDrawer, enable: ! isSubmitting()">×</button>
		</header>
		<div
			hidden
			class="mb22 p13_16 c-red ba-red_light bo1-border br6"
			role="alert"
			data-bind="attr: { hidden: ! formErrorMessage() }, text: formErrorMessage"
		></div>
		<form data-resource-form data-bind="submit: save">
			<?php echo Form::csrf(); ?>
			<div data-bind="foreach: activeFormFields">
				<label class="d-grid g8 fw600 mb22">
					<span data-bind="text: label"></span>
					<!-- ko if: type === 'select' -->
					<select
						data-bind="options: options, optionsText: 'label', optionsValue: 'value', value: $root.formValue(key), valueAllowUnset: true, enable: ! $root.formDisabled($data), attr: { 'aria-invalid': $root.fieldError(key) ? 'true' : 'false' }"
					></select>
					<!-- /ko -->
					<!-- ko if: type === 'textarea' -->
					<textarea
						rows="4"
						data-bind="value: $root.formValue(key), enable: ! $root.formDisabled($data), attr: { 'aria-invalid': $root.fieldError(key) ? 'true' : 'false' }"
					></textarea>
					<!-- /ko -->
					<!-- ko if: type !== 'select' && type !== 'textarea' -->
					<input
						data-bind="value: $root.formValue(key), enable: ! $root.formDisabled($data), attr: { type: type, autocomplete: autocomplete || 'off', min: minimum || null, 'aria-invalid': $root.fieldError(key) ? 'true' : 'false' }"
					>
					<!-- /ko -->
					<span class="c-red fs13" data-bind="visible: $root.fieldError(key), text: $root.fieldError(key)"></span>
				</label>
			</div>
			<div class="p-sticky b0 d-flex jc-flex_end g12 mt28 p18_26 ba-white bt1-border">
				<button class="p10_18 fw700 br6 c-green ba-white bo1-green" type="button" data-bind="click: closeDrawer, enable: ! isSubmitting()">閉じる</button>
				<button
					class="p10_18 fw700 br6 c-white ba-green bo1-green ba-green_dark-hover cu-default-disabled o04-disabled"
					type="submit"
					data-bind="visible: ! viewOnly(), enable: ! isSubmitting(), text: saveLabel"
				></button>
			</div>
		</form>
	</aside>
</section>
