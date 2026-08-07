<?php
return array(
	'_root_'  => 'welcome/index',  // The default route
	'_404_'   => 'welcome/404',    // The main 404 route
	'login' => array(
		array('GET', new Route('page/site/login')),
		array('POST', new Route('auth/login')),
	),
	'logout' => array(
		array('POST', new Route('auth/logout')),
	),
	'account/password' => array(
		array('GET', new Route('page/site/password')),
	),
	'dashboard' => array(
		array('GET', new Route('page/site/dashboard')),
	),
	'equipment' => array(
		array('GET', new Route('page/site/equipment')),
	),
	'loans' => array(
		array('GET', new Route('page/site/loans')),
	),
	'admin/employees' => array(
		array('GET', new Route('page/admin/employees')),
	),
	'admin/departments' => array(
		array('GET', new Route('page/admin/departments')),
	),

	'hello(/:name)?' => array('welcome/hello', 'name' => 'hello'),
);
