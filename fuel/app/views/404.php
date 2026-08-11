<?php echo \View::forge('error', array(
	'status' => 404,
	'title' => 'ページが見つかりません',
	'message' => '指定されたページは存在しないか、移動した可能性があります。',
	'request_id' => '',
)); ?>
