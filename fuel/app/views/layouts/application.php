<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="utf-8">
	<title><?php echo e($title); ?></title>
	<?php echo Asset::css('app.css'); ?>
</head>
<body>
	<?php echo View::forge('partials/header', array('guest' => false)); ?>
	<div class="mih-vh68 d-grid gtc230">
		<?php echo View::forge(
			'partials/sidebar',
			array('active_page' => $active_page)
		); ?>
		<main class="miw0 p30_36_48">
			<?php echo $content; ?>
		</main>
	</div>
	<?php echo Asset::js('knockout-3.5.3.js'); ?>
	<?php echo Asset::js($page_script); ?>
</body>
</html>
