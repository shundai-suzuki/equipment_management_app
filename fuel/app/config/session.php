<?php

return array(
	// Authの初期化時にサーバ側Sessionを開始する。
	'auto_initialize' => true,
	'driver' => 'file',

	// 接続元IPの変化では無効化せず、User-Agentの一致を確認する。
	'match_ip' => false,
	'match_ua' => true,

	// POST変数と任意HTTPヘッダからのSession ID受渡しを無効化する。
	'cookie_domain' => '',
	'cookie_path' => '/',
	'cookie_http_only' => true,
	'encrypt_cookie' => true,
	'enable_cookie' => true,
	'post_cookie_name' => '',
	'http_header_name' => '',

	// 30分間操作がなければ失効し、5分ごとにIDを更新する。
	'expire_on_close' => false,
	'expiration_time' => 1800,
	'rotation_time' => 300,

	'native_emulation' => false,

	'file' => array(
		'cookie_name' => \Fuel::$env === \Fuel::PRODUCTION
			? '__Host-inventory_sid'
			: 'inventory_sid',
		'path' => '/var/cache/fuel',
		'gc_probability' => 5,
	),
);