<?php

return array(
	'driver' => array(
		'Employee' => array(
			'credential_fingerprint_key' => \Config::get(
				'employee_auth.credential_fingerprint_key'
			),
		),
	),
	'verify_multiple_logins' => false,
);
