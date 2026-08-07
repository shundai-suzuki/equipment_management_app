<?php

/**
 * Administrator HTML prototype pages.
 *
 * Authentication is added when the prototype is connected to the backend.
 *
 * @package  app
 * @extends  Controller_Page_Base
 */
class Controller_Page_Admin extends Controller_Page_Base
{
	public function action_employees()
	{
		$this->render_resource('employees', '社員管理');
	}

	public function action_departments()
	{
		$this->render_resource('departments', '部署管理');
	}
}
