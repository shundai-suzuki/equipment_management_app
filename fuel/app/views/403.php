<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="utf-8">
	<title>アクセス権限がありません</title>
	<?php echo Asset::css('bootstrap.css'); ?>
	<?php echo Asset::css('app.css'); ?>
</head>
<body class="ba-background">
	<main class="mih-vh68 d-flex ai-center jc-center p56">
		<?php echo View::forge('error', array(
			'status' => 403,
			'title' => 'アクセス権限がありません',
			'message' => 'この画面を表示する権限がありません。',
			'request_id' => '',
		)); ?>
	</main>
</body>
</html>