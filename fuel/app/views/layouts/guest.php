<?php
// before login layout
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="utf-8">
	<title><?php echo e($title); ?></title>
	<?php echo Asset::css('app.css'); ?>
</head>
<body class="ba-background">
	<?php echo View::forge('partials/header', array('guest' => true)); ?>
	<main class="mih-vh68 d-flex ai-center jc-center p56">
		<?php echo $content; ?>
	</main>
	<?php echo Asset::js('knockout-3.5.3.js'); ?>
	<?php echo Asset::js($page_script); ?>
</body>
</html>
