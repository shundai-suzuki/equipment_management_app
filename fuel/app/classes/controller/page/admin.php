<?php

/**
 * Administrator HTML pages.
 *
 * @package  app
 * @extends  Controller_Page_Base
 */
class Controller_Page_Admin extends Controller_Page_Base
{
	/** Reject non-administrators before an action runs. */
	public function before()
	{
		parent::before();

		if ( ! $this->is_admin)
		{
			throw new \HttpNoAccessException();
		}
	}

	public function action_employees()
	{
		$this->render_resource('employees', '社員管理');
	}

	public function action_departments()
	{
		$this->render_resource('departments', '部署管理');
	}
}
