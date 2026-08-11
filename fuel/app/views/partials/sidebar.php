<?php
// 共通サイドバー
$items = array(
	'dashboard' => array('ダッシュボード', 'dashboard'),
	'equipment' => array('備品一覧', 'equipment'),
	'loans' => array('貸出一覧', 'loans'),
);

if ($is_admin)
{
	$items['employees'] = array('社員管理', 'admin/employees');
	$items['departments'] = array('部署管理', 'admin/departments');
}

$link_class = 'd-block mb8 pt13 pr16 pb13 pl16 c-navy fw600 td-none br6 c-blue-hover back-blue_light-hover';
$active_class = $link_class.' c-blue ba-blue_light';
?>
<nav class="pt24 pr14 pb24 pl14 back-white br1-border" aria-label="メインメニュー">
	<?php foreach ($items as $key => $item): ?>
		<a
			class="<?php echo $active_page === $key ? $active_class : $link_class; ?>"
			href="<?php echo Uri::create($item[1]); ?>"
		>
			<?php echo e($item[0]); ?>
		</a>
	<?php endforeach; ?>
	<a
		class="<?php echo $active_page === 'password' ? $active_class : $link_class; ?>"
		href="<?php echo Uri::create('account/password'); ?>"
	>
		パスワード変更
	</a>
</nav>
