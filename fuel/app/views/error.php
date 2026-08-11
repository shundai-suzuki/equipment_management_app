<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="utf-8">
	<title><?php echo e($title); ?></title>
	<?php echo \Asset::css('bootstrap.css'); ?>
	<?php echo \Asset::css('app.css'); ?>
</head>
<body class="back-background">
	<main class="mih-vh68 d-flex ai-center jc-center p56">
		<section class="w720 p54 ta-center back-surface bo1-border br10">
			<p class="m0 mb12 fs72 fw800"><?php echo e($status); ?></p>
			<h1 class="m0 fs30"><?php echo e($title); ?></h1>
			<p><?php echo e($message); ?></p>
			<?php if ($request_id !== ''): ?>
				<p class="c-muted ff-monospace">追跡ID: <?php echo e($request_id); ?></p>
			<?php endif; ?>
		</section>
	</main>
</body>
</html>
