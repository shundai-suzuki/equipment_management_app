<?php

return array(
	'auto_initialize' => true,
	'driver' => 'file',
	'match_ip' => false,
	'match_ua' => true,
	'cookie_domain' => '',
	'cookie_path' => '/',
	'cookie_http_only' => true,

	'expiration_time' => 1800,
	'expire_on_close' => false,
	'rotation_time' => 300,

	'post_cookie_name' => '',
	'http_header_name' => '',
	'enable_cookie' => true,
	'native_emulation' => false,

	'file' => array(
		'cookie_name' => 'fuelcookie',
		'path' => '/var/cache/fuel',
		'gc_probability' => 5,
	),
);