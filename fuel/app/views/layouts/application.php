<?php
// ログイン後のレイアウト
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="utf-8">
	<title><?php echo e($title); ?></title>
	<?php echo \Asset::css('bootstrap.css'); ?>
	<?php echo \Asset::css('app.css'); ?>
</head>
<body>
	<?php echo \View::forge('partials/header', array('guest' => false, 'is_admin' => $is_admin)); ?>
	<div class="minh-68 d-grid gtc230">
		<?php echo \View::forge('partials/sidebar', array('active_page' => $active_page, 'is_admin' => $is_admin)); ?>
		<main class="pt30 pr36 pb48 pl36">
			<?php if ($error !== ''): ?><p class="mb18 pt13 pr16 pb13 pl16 c-red back-red_light bo1-border br6" role="alert"><?php echo e($error); ?></p><?php endif; ?>
			<?php if ($notice !== ''): ?><p class="mb18 pt13 pr16 pb13 pl16 c-green back-green_light bo1-border br6" role="status"><?php echo e($notice); ?></p><?php endif; ?>
			<?php echo $content; ?>
		</main>
	</div>
	<?php if ($page_script !== ''): ?>
		<?php echo \Asset::js('knockout-3.5.3.js'); ?>
		<?php echo \Asset::js('app/api.js'); ?>
		<?php echo \Asset::js($page_script); ?>
	<?php endif; ?>
</body>
</html>
