<?php

/**
 * 管理者用HTML画面。
 *
 * @package  app
 * @extends  Controller_Page_Base
 */
class Controller_Page_Admin extends Controller_Page_Base
{
	/** アクション実行前に管理者以外を拒否する。 */
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
