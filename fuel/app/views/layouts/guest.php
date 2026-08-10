<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="utf-8">
	<title><?php echo e($title); ?></title>
	<?php echo \Asset::css('bootstrap.css'); ?>
	<?php echo \Asset::css('app.css'); ?>
</head>
<body class="ba-background">
	<?php echo \View::forge('partials/header', array('guest' => true)); ?>
	<main class="mih-vh68 d-flex ai-center jc-center p56">
		<div>
			<?php if ($error !== ''): ?><p class="mb18 p13_16 c-red ba-red_light bo1-border br6" role="alert"><?php echo e($error); ?></p><?php endif; ?>
			<?php echo $content; ?>
		</div>
	</main>
</body>
</html>
