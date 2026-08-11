<?php echo \View::forge('error', array(
	'status' => 400,
	'title' => 'リクエスト形式が正しくありません',
	'message' => '入力内容を確認して、もう一度お試しください。',
	'request_id' => '',
)); ?>
