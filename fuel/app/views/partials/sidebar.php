<?php
$items = array(
	'dashboard' => array('ダッシュボード', 'dashboard'),
	'equipment' => array('備品一覧', 'equipment'),
	'loans' => array('貸出一覧', 'loans'),
	'employees' => array('社員管理', 'admin/employees'),
	'departments' => array('部署管理', 'admin/departments'),
);
$link_class = 'd-block mb8 p13_16 c-navy fw600 td-none br6 c-blue-hover ba-blue_light-hover';
?>
<nav class="p24_14 ba-white br1-border" aria-label="メインメニュー">
	<?php foreach ($items as $key => $item): ?>
		<a
			class="<?php echo $link_class; ?><?php echo $active_page === $key ? ' c-blue ba-blue_light' : ''; ?>"
			href="<?php echo Uri::create($item[1]); ?>"
		>
			<?php echo e($item[0]); ?>
		</a>
	<?php endforeach; ?>
	<a
		class="<?php echo $link_class; ?><?php echo $active_page === 'password' ? ' c-blue ba-blue_light' : ''; ?>"
		href="<?php echo Uri::create('account/password'); ?>"
	>
		パスワード変更
	</a>
</nav>
