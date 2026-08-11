<?php
$has_actions = $resource === 'equipment' || $is_admin;
$column_count = count($columns) + ($has_actions ? 1 : 0);
?>
<section
	class="maw1440 m-0_auto"
	id="resource-page"
	data-resource="<?php echo e($resource); ?>"
	data-search-url="<?php echo e($search_url); ?>"
	data-write-url="<?php echo e($write_url); ?>"
	data-login-url="<?php echo e($login_url); ?>"
	data-create-label="<?php echo e($create_label); ?>"
	data-edit-label="<?php echo e($edit_label); ?>"
	data-departments="<?php echo e(json_encode($department_options)); ?>"
>
	<header class="d-flex ai-center jc-space_between mb24">
		<h1 class="m0 fs30"><?php echo e($title); ?></h1>
		<?php if ($is_admin): ?>
			<button
				class="p10_18 fw700 br6 c-white ba-green bo1-green ba-green_dark-hover"
				type="button"
				data-bind="click: openCreate, enable: ! isSubmitting()"
			><?php echo e($create_label); ?></button>
		<?php endif; ?>
	</header>

	<div
		hidden
		class="mb18 p13_16 c-red ba-red_light bo1-border br6"
		role="alert"
		data-bind="attr: { hidden: ! errorMessage() }, text: errorMessage"
	></div>

	<section class="mb18 p18 ba-white bo1-border br8">
		<div class="d-grid gtc4 g16 ai-end">
			<?php if ($resource === 'equipment'): ?>
				<label class="d-grid g8 fw600">
					<span>キーワード</span>
					<input type="search" data-filter="q" placeholder="備品名で検索">
				</label>
				<label class="d-grid g8 fw600">
					<span>カテゴリ</span>
					<select data-filter="category" data-category-filter><option value="">すべて</option></select>
				</label>
				<label class="d-grid g8 fw600">
					<span>管理部署</span>
					<select data-filter="department_id">
						<option value="">すべて</option>
						<?php foreach ($department_options as $department): ?>
							<option value="<?php echo e($department['id']); ?>"><?php echo e($department['name']); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="d-flex ai-center g8 fw600">
					<input class="w-auto p0" type="checkbox" data-filter="available_only">
					<span>利用可能のみ</span>
				</label>
			<?php elseif ($resource === 'loans'): ?>
				<label class="d-grid g8 fw600">
					<span>備品名</span>
					<input type="search" data-filter="q" placeholder="備品名で検索">
				</label>
				<label class="d-grid g8 fw600">
					<span>状態</span>
					<select data-filter="loan_state">
						<option value="">すべて</option>
						<option value="ON_LOAN">貸出中</option>
						<option value="OVERDUE">返却超過</option>
						<option value="RETURNED">返却済み</option>
					</select>
				</label>
			<?php elseif ($resource === 'employees'): ?>
				<label class="d-grid g8 fw600">
					<span>キーワード</span>
					<input type="search" data-filter="q" placeholder="社員番号・社員名で検索">
				</label>
				<label class="d-grid g8 fw600">
					<span>部署</span>
					<select data-filter="department_id">
						<option value="">すべて</option>
						<?php foreach ($department_options as $department): ?>
							<option value="<?php echo e($department['id']); ?>"><?php echo e($department['name']); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="d-grid g8 fw600">
					<span>権限</span>
					<select data-filter="role">
						<option value="">すべて</option>
						<option value="EMPLOYEE">社員</option>
						<option value="ADMIN">管理者</option>
					</select>
				</label>
				<label class="d-grid g8 fw600">
					<span>状態</span>
					<select data-filter="is_active">
						<option value="">すべて</option>
						<option value="1">有効</option>
						<option value="0">無効</option>
					</select>
				</label>
			<?php else: ?>
				<label class="d-grid g8 fw600">
					<span>キーワード</span>
					<input type="search" data-filter="q" placeholder="部署ID・部署名で検索">
				</label>
			<?php endif; ?>
		</div>
		<div class="d-flex jc-flex_end mt16">
			<button class="p10_18 fw700 br6 c-green ba-white bo1-green" type="button" data-bind="click: clearFilters">条件をクリア</button>
		</div>
	</section>

	<div class="ba-white bo1-border br8 ov-hidden">
		<table>
			<thead>
				<tr>
					<?php foreach ($columns as $column): ?>
						<th class="p13_14 ta-left va-middle bb1-border fs13 ba-table_head"><?php echo e($column[1]); ?></th>
					<?php endforeach; ?>
					<?php if ($has_actions): ?><th class="p13_14 ta-left va-middle bb1-border fs13 ba-table_head">操作</th><?php endif; ?>
				</tr>
			</thead>
			<tbody aria-live="polite">
				<tr hidden data-bind="attr: { hidden: ! (isLoading() && rows().length === 0) }">
					<td class="p40 c-muted ta-center bb1-border" colspan="<?php echo $column_count; ?>">読み込んでいます。</td>
				</tr>
				<!-- ko foreach: rows -->
				<tr class="ba-row_hover-hover">
					<?php foreach ($columns as $column): ?>
						<td class="p13_14 ta-left va-middle bb1-border">
							<?php if ($column[0] === 'loan_state' or $column[0] === 'is_active'): ?>
								<span class="d-inline_block p4_9 fs13 fw700 br999" data-bind="text: $root.value($data, '<?php echo e($column[0]); ?>'), css: $root.statusClass($data, '<?php echo e($column[0]); ?>')"></span>
							<?php else: ?>
								<span data-bind="text: $root.value($data, '<?php echo e($column[0]); ?>')"></span>
							<?php endif; ?>
						</td>
					<?php endforeach; ?>
					<?php if ($has_actions): ?>
						<td class="p13_14 ta-left va-middle bb1-border ws-nowrap">
							<?php if ($resource === 'equipment'): ?>
								<button class="mr6 p6_12 c-green fs13 fw700 ba-white bo1-green br6" type="button" data-bind="click: $root.openView, enable: ! $root.isSubmitting()">詳細</button>
								<?php if ($is_admin): ?>
									<button class="mr6 p6_12 c-green fs13 fw700 ba-white bo1-green br6" type="button" data-bind="click: $root.openEdit, enable: ! $root.isSubmitting()">編集</button>
									<button class="mr6 p6_12 c-red fs13 fw700 ba-white bo1-red br6" type="button" data-bind="click: $root.softDelete, enable: ! $root.isSubmitting()">削除</button>
								<?php endif; ?>
							<?php elseif ($resource === 'loans' and $is_admin): ?>
								<button class="mr6 p6_12 c-red fs13 fw700 ba-white bo1-red br6" type="button" data-bind="visible: loan_state !== 'RETURNED', click: $root.returnLoan, enable: ! $root.isSubmitting()">返却</button>
							<?php elseif ($resource === 'employees'): ?>
								<button class="mr6 p6_12 c-green fs13 fw700 ba-white bo1-green br6" type="button" data-bind="click: $root.openEdit, enable: ! $root.isSubmitting()">編集</button>
								<button class="mr6 p6_12 c-green fs13 fw700 ba-white bo1-green br6" type="button" data-bind="text: $root.toggleLabel($data), click: $root.toggle, enable: ! $root.isSubmitting()"></button>
								<button class="mr6 p6_12 c-green fs13 fw700 ba-white bo1-green br6" type="button" data-bind="click: $root.openPassword, enable: ! $root.isSubmitting()">パスワード再設定</button>
								<button class="mr6 p6_12 c-red fs13 fw700 ba-white bo1-red br6" type="button" data-bind="click: $root.softDelete, enable: ! $root.isSubmitting()">削除</button>
							<?php else: ?>
								<button class="mr6 p6_12 c-green fs13 fw700 ba-white bo1-green br6" type="button" data-bind="click: $root.openEdit, enable: ! $root.isSubmitting()">編集</button>
								<button class="mr6 p6_12 c-red fs13 fw700 ba-white bo1-red br6" type="button" data-bind="click: $root.softDelete, enable: ! $root.isSubmitting()">削除</button>
							<?php endif; ?>
						</td>
					<?php endif; ?>
				</tr>
				<!-- /ko -->
				<tr hidden data-bind="attr: { hidden: ! (! isLoading() && rows().length === 0) }">
					<td class="p40 c-muted ta-center bb1-border" colspan="<?php echo $column_count; ?>">該当するデータはありません。</td>
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

	<aside class="p-fixed t68 r0 zi10 w430 h-vh68 p26 oy-auto ba-white bl1-border tr100p" data-bind="css: { tr0: drawerOpen }, attr: { 'aria-hidden': drawerOpen() ? 'false' : 'true' }">
		<header class="p-sticky t0 d-flex ai-center jc-space_between mb24 p20_26 ba-white bb1-border">
			<h2 class="m0 fs20" data-bind="text: drawerTitle"></h2>
			<button class="p4_10 c-navy fs24 ba-transparent bo0" type="button" aria-label="閉じる" data-bind="click: closeDrawer, enable: ! isSubmitting()">×</button>
		</header>
		<form method="post" data-resource-form data-bind="submit: save">
			<?php echo \Form::csrf(); ?>
			<?php if ($resource === 'equipment'): ?>
				<label class="d-grid g8 fw600 mb22"><span>備品名</span><input data-field name="name" required maxlength="255" data-bind="disable: mode() === 'view'"></label>
				<label class="d-grid g8 fw600 mb22"><span>カテゴリ</span><input data-field name="category" required maxlength="20" data-bind="disable: mode() === 'view'"></label>
				<label class="d-grid g8 fw600 mb22"><span>管理部署</span><select data-field name="department_id" required data-bind="disable: mode() === 'view'">
					<option value="">選択してください</option>
					<?php foreach ($department_options as $department): ?><option value="<?php echo e($department['id']); ?>"><?php echo e($department['name']); ?></option><?php endforeach; ?>
				</select></label>
				<label class="d-grid g8 fw600 mb22"><span>総数</span><input data-field name="total_amount" type="number" min="1" required data-bind="disable: mode() === 'view'"></label>
				<label class="d-grid g8 fw600 mb22"><span>説明</span><textarea data-field name="description" rows="4" data-bind="disable: mode() === 'view'"></textarea></label>
			<?php elseif ($resource === 'loans'): ?>
				<label class="d-grid g8 fw600 mb22"><span>借用者の社員番号</span><input data-field name="employee_id" type="number" min="1" required></label>
				<label class="d-grid g8 fw600 mb22"><span>備品ID</span><input data-field name="equipment_id" type="number" min="1" required></label>
				<label class="d-grid g8 fw600 mb22"><span>返却期限</span><input data-field name="due_date" type="date" required></label>
			<?php elseif ($resource === 'employees'): ?>
				<div data-bind="visible: mode() !== 'password'">
					<label class="d-grid g8 fw600 mb22"><span>社員名</span><input data-field name="employee_name" maxlength="255" data-bind="attr: { required: mode() !== 'password' ? 'required' : null }"></label>
					<label class="d-grid g8 fw600 mb22"><span>部署</span><select data-field name="department_id" data-bind="attr: { required: mode() !== 'password' ? 'required' : null }"><option value="">選択してください</option><?php foreach ($department_options as $department): ?><option value="<?php echo e($department['id']); ?>"><?php echo e($department['name']); ?></option><?php endforeach; ?></select></label>
					<label class="d-grid g8 fw600 mb22"><span>権限</span><select data-field name="role" data-bind="attr: { required: mode() !== 'password' ? 'required' : null }"><option value="EMPLOYEE">社員</option><option value="ADMIN">管理者</option></select></label>
				</div>
				<div data-bind="visible: mode() === 'create' || mode() === 'password'">
					<label class="d-grid g8 fw600 mb22"><span>パスワード</span><input data-field name="password" type="password" minlength="12" autocomplete="new-password" data-bind="attr: { required: mode() !== 'edit' ? 'required' : null }"></label>
					<label class="d-grid g8 fw600 mb22"><span>パスワード（確認）</span><input data-field name="password_confirmation" type="password" minlength="12" autocomplete="new-password" data-bind="attr: { required: mode() !== 'edit' ? 'required' : null }"></label>
				</div>
				<label class="d-grid g8 fw600 mb22" data-bind="visible: mode() === 'password'"><span>管理者パスワード</span><input data-field name="admin_password" type="password" autocomplete="current-password" data-bind="attr: { required: mode() === 'password' ? 'required' : null }"></label>
			<?php else: ?>
				<label class="d-grid g8 fw600 mb22"><span>部署名</span><input data-field name="name" required maxlength="255"></label>
			<?php endif; ?>
			<div class="p-sticky b0 d-flex jc-flex_end g12 mt28 p18_26 ba-white bt1-border">
				<button class="p10_18 fw700 br6 c-green ba-white bo1-green" type="button" data-bind="click: closeDrawer, enable: ! isSubmitting()">閉じる</button>
				<button class="p10_18 fw700 br6 c-white ba-green bo1-green ba-green_dark-hover cu-default-disabled o04-disabled" type="submit" data-bind="visible: mode() !== 'view', enable: ! isSubmitting(), text: isSubmitting() ? '送信中…' : '保存する'"></button>
			</div>
		</form>
	</aside>

	<form method="post" data-resource-action>
		<?php echo \Form::csrf(); ?>
	</form>
</section>
