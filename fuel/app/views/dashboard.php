<?php
$loan_column_count = $is_admin ? 5 : 4;
?>
<section class="maw1440 m-0_auto">
	<header class="d-flex ai-center jc-space_between mb24">
		<h1 class="m0 fs30">ダッシュボード</h1>
	</header>
	<h2 class="mt26 mb14 fs20">操作メニュー</h2>
	<div class="d-grid gtc3 g18">
		<a class="d-grid gtc-1fr_auto ai-center p26_20 c-navy td-none ba-white bo1-border br8 c-green-hover bc-green-hover" href="<?php echo Uri::create('equipment'); ?>">
			<span class="d-flex ai-center g12"><span class="c-blue fs24" aria-hidden="true">▣</span><span class="fs18 fw700">備品一覧</span></span>
			<span aria-hidden="true">→</span>
		</a>
		<a class="d-grid gtc-1fr_auto ai-center p26_20 c-navy td-none ba-white bo1-border br8 c-green-hover bc-green-hover" href="<?php echo Uri::create('loans'); ?>">
			<span class="d-flex ai-center g12"><span class="c-blue fs24" aria-hidden="true">↔</span><span class="fs18 fw700">貸出一覧</span></span>
			<span aria-hidden="true">→</span>
		</a>
		<?php if ($is_admin): ?>
			<a class="d-grid gtc-1fr_auto ai-center p26_20 c-navy td-none ba-white bo1-border br8 c-green-hover bc-green-hover" href="<?php echo Uri::create('loans'); ?>">
				<span class="d-flex ai-center g12"><span class="c-blue fs24" aria-hidden="true">＋</span><span class="fs18 fw700">貸出登録</span></span>
				<span aria-hidden="true">→</span>
			</a>
			<a class="d-grid gtc-1fr_auto ai-center p26_20 c-navy td-none ba-white bo1-border br8 c-green-hover bc-green-hover" href="<?php echo Uri::create('equipment'); ?>">
				<span class="d-flex ai-center g12"><span class="c-green fs24" aria-hidden="true">◇</span><span class="fs18 fw700">備品登録</span></span>
				<span aria-hidden="true">→</span>
			</a>
			<a class="d-grid gtc-1fr_auto ai-center p26_20 c-navy td-none ba-white bo1-border br8 c-green-hover bc-green-hover" href="<?php echo Uri::create('admin/employees'); ?>">
				<span class="d-flex ai-center g12"><span class="c-green fs24" aria-hidden="true">●</span><span class="fs18 fw700">社員管理</span></span>
				<span aria-hidden="true">→</span>
			</a>
			<a class="d-grid gtc-1fr_auto ai-center p26_20 c-navy td-none ba-white bo1-border br8 c-green-hover bc-green-hover" href="<?php echo Uri::create('admin/departments'); ?>">
				<span class="d-flex ai-center g12"><span class="c-green fs24" aria-hidden="true">▤</span><span class="fs18 fw700">部署管理</span></span>
				<span aria-hidden="true">→</span>
			</a>
		<?php endif; ?>
	</div>
	<section class="mt36">
		<header class="d-flex ai-center jc-space_between mb14">
			<h2 class="m0 fs20">貸出中</h2>
			<a class="c-blue fw700 td-none" href="<?php echo Uri::create('loans'); ?>">貸出一覧を見る</a>
		</header>
		<div class="ba-white bo1-border br8 ov-hidden">
			<table>
				<thead>
					<tr>
						<th class="p13_14 ta-left va-middle bb1-border fs13 ba-table_head">貸出ID</th>
						<th class="p13_14 ta-left va-middle bb1-border fs13 ba-table_head">備品</th>
						<?php if ($is_admin): ?>
							<th class="p13_14 ta-left va-middle bb1-border fs13 ba-table_head">借用者</th>
						<?php endif; ?>
						<th class="p13_14 ta-left va-middle bb1-border fs13 ba-table_head">返却期限</th>
						<th class="p13_14 ta-left va-middle bb1-border fs13 ba-table_head">状態</th>
					</tr>
				</thead>
				<tbody>
					<?php if (empty($loans)): ?>
					<tr>
						<td class="p40 c-muted ta-center bb1-border" colspan="<?php echo $loan_column_count; ?>">貸出中の備品はありません。</td>
					</tr>
					<?php else: ?>
						<?php foreach ($loans as $loan): ?>
							<?php $is_overdue = $loan['loan_state'] === 'OVERDUE'; ?>
							<tr class="ba-row_hover-hover">
								<td class="p13_14 ta-left va-middle bb1-border"><?php echo e($loan['id']); ?></td>
								<td class="p13_14 ta-left va-middle bb1-border"><?php echo e($loan['equipment_name']); ?></td>
								<?php if ($is_admin): ?>
									<td class="p13_14 ta-left va-middle bb1-border"><?php echo e($loan['employee_name']); ?></td>
								<?php endif; ?>
								<td class="p13_14 ta-left va-middle bb1-border"><?php echo e(str_replace('-', '/', $loan['due_date'])); ?></td>
								<td class="p13_14 ta-left va-middle bb1-border">
									<span class="d-inline_block p4_9 fs13 fw700 br999 <?php echo $is_overdue ? 'c-red ba-red_light' : 'c-green ba-green_light'; ?>"><?php echo $is_overdue ? '返却超過' : '貸出中'; ?></span>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</section>
</section>
