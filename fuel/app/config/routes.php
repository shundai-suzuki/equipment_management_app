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
	'api/admin/departments/:id/restore' => array(
		array('POST', new Route('table/department/restore/$1')),
	),
	'api/admin/departments/:id/archive' => array(
		array('POST', new Route('table/department/soft_delete/$1')),
	),
	'api/admin/departments/:id' => array(
		array('GET', new Route('table/department/read/$1')),
		array('POST', new Route('table/department/update/$1')),
	),
	'api/admin/departments' => array(
		array('GET', new Route('table/department/search')),
		array('POST', new Route('table/department/create')),
	),
	'api/admin/employees/:id/deactivate' => array(
		array('POST', new Route('table/employee/deactivate/$1')),
	),
	'api/admin/employees/:id/activate' => array(
		array('POST', new Route('table/employee/activate/$1')),
	),
	'api/admin/employees/:id/restore' => array(
		array('POST', new Route('table/employee/restore/$1')),
	),
	'api/admin/employees/:id/password' => array(
		array('POST', new Route('table/employee/password/$1')),
	),
	'api/admin/employees/:id/archive' => array(
		array('POST', new Route('table/employee/soft_delete/$1')),
	),
	'api/admin/employees/:id' => array(
		array('GET', new Route('table/employee/read/$1')),
		array('POST', new Route('table/employee/update/$1')),
	),
	'api/admin/employees' => array(
		array('GET', new Route('table/employee/search')),
		array('POST', new Route('table/employee/create')),
	),
	'api/admin/equipment/:id/archive' => array(
		array('POST', new Route('table/equipmentforadmin/soft_delete/$1')),
	),
	'api/admin/equipment/:id' => array(
		array('GET', new Route('table/equipmentforadmin/read/$1')),
		array('POST', new Route('table/equipmentforadmin/update/$1')),
	),
	'api/admin/equipment' => array(
		array('GET', new Route('table/equipmentforadmin/search')),
		array('POST', new Route('table/equipmentforadmin/create')),
	),
	'api/equipment/:id' => array(
		array('GET', new Route('table/equipment/read/$1')),
	),
	'api/equipment' => array(
		array('GET', new Route('table/equipment/search')),
	),
	'api/admin/loans/:id/return' => array(
		array('POST', new Route('adminloans/return/$1')),
	),
	'api/admin/loans' => array(
		array('POST', new Route('adminloans/create')),
	),
	'api/loans' => array(
		array('GET', new Route('table/loan/search')),
	),

	'hello(/:name)?' => array('welcome/hello', 'name' => 'hello'),
);
