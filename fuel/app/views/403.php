<?php echo \View::forge('error', array(
	'status' => 403,
	'title' => 'アクセス権限がありません',
	'message' => 'この画面を表示する権限がありません。',
	'request_id' => '',
)); ?>
