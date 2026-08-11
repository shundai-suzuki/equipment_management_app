<?php
$request_id = bin2hex(random_bytes(16));

try
{
	\Log::error('HTML server error. request_id='.$request_id);
}
catch (\Throwable $logging_exception)
{
}

echo \View::forge('error', array(
	'status' => 500,
	'title' => 'システムエラー',
	'message' => '処理に失敗しました。時間をおいて再度お試しください。',
	'request_id' => $request_id,
));
