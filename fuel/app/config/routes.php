<?php
return array(
	'_root_' => 'page/site/login',
	'_404_' => 'welcome/404',

	'login' => array(
		array('GET', new Route('page/site/login')),
		array('POST', new Route('page/site/login_submit')),
	),
	'logout' => array(
		array('POST', new Route('page/site/logout'))
	),
	'account/password' => array(
		array('GET', new Route('page/site/password')),
		array('POST', new Route('page/site/password_submit')),
	),
	'dashboard' => array(
		array('GET', new Route('page/site/dashboard'))
	),
	'equipment' => array(
		array('GET', new Route('page/site/equipment'))
	),
	'loans' => array(
		array('GET', new Route('page/site/loans'))
	),
	'admin/employees' => array(
		array('GET', new Route('page/admin/employees'))
	),
	'admin/departments' => array(
		array('GET', new Route('page/admin/departments'))
	),

	'admin/:resource/create' => array(
		array('POST', new Route('page/admin/mutate/$1/create')),
	),
	'admin/:resource/:id/:operation' => array(
		array('POST', new Route('page/admin/mutate/$1/$3/$2')),
	),

	'api/admin/departments' => array(
		array('GET', new Route('table/department/search')),
	),
	'api/admin/employees' => array(
		array('GET', new Route('table/employee/search')),
	),
	'api/equipment' => array(
		array('GET', new Route('table/equipment/search')),
	),
	'api/loans' => array(
		array('GET', new Route('table/loan/search')),
	),
);
