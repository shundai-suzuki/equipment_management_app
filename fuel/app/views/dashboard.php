<section class="maw1440 m-0_auto" data-page="dashboard">
	<header class="d-flex ai-center jc-space_between mb24">
		<h1 class="m0 fs30">ダッシュボード</h1>
	</header>
	<h2 class="mt26 mb14 fs20">操作メニュー</h2>
	<div class="d-grid gtc4 g18">
		<a class="p26_20 c-navy fs18 fw700 ta-center td-none ba-white bo1-border br8 c-green-hover bc-green-hover" href="<?php echo Uri::create('equipment'); ?>">備品一覧</a>
		<a class="p26_20 c-navy fs18 fw700 ta-center td-none ba-white bo1-border br8 c-green-hover bc-green-hover" href="<?php echo Uri::create('loans'); ?>">貸出一覧</a>
		<a class="p26_20 c-navy fs18 fw700 ta-center td-none ba-white bo1-border br8 c-green-hover bc-green-hover" href="<?php echo Uri::create('admin/employees'); ?>">社員管理</a>
		<a class="p26_20 c-navy fs18 fw700 ta-center td-none ba-white bo1-border br8 c-green-hover bc-green-hover" href="<?php echo Uri::create('admin/departments'); ?>">部署管理</a>
	</div>
	<section class="mt36">
		<h2 class="mt26 mb14 fs20">貸出中</h2>
		<div class="ba-white bo1-border br8 ov-hidden">
			<table>
				<thead>
					<tr>
						<th class="p13_14 ta-left va-middle bb1-border fs13 ba-table_head">貸出ID</th>
						<th class="p13_14 ta-left va-middle bb1-border fs13 ba-table_head">備品</th>
						<th class="p13_14 ta-left va-middle bb1-border fs13 ba-table_head">借用者</th>
						<th class="p13_14 ta-left va-middle bb1-border fs13 ba-table_head">返却期限</th>
						<th class="p13_14 ta-left va-middle bb1-border fs13 ba-table_head">状態</th>
					</tr>
				</thead>
				<tbody data-bind="foreach: loans">
					<tr class="ba-row_hover-hover">
						<td class="p13_14 ta-left va-middle bb1-border" data-bind="text: id"></td>
						<td class="p13_14 ta-left va-middle bb1-border" data-bind="text: equipment"></td>
						<td class="p13_14 ta-left va-middle bb1-border" data-bind="text: employee"></td>
						<td class="p13_14 ta-left va-middle bb1-border" data-bind="text: dueDate"></td>
						<td class="p13_14 ta-left va-middle bb1-border"><span class="d-inline_block p4_9 c-green fs13 fw700 ba-green_light br999" data-bind="text: status, css: statusClass"></span></td>
					</tr>
				</tbody>
			</table>
		</div>
	</section>
</section>
