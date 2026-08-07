<?php

$request_id = bin2hex(random_bytes(16));

header('Content-Type: application/json; charset=utf-8');

echo \Format::forge(
	array(
		'error' => array(
			'code' => 'BAD_REQUEST',
			'message' => 'リクエスト形式が正しくありません。',
		),
		'request_id' => $request_id,
	)
)->to_json();
