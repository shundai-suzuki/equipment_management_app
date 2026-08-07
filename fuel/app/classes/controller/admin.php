<?php

/**
 * Common Controller for administrator-only JSON APIs.
 *
 * @package  app
 * @extends  Controller_Base
 */
abstract class Controller_Admin extends Controller_Base
{
	/**
	 * Reject authenticated employees who do not have the ADMIN role.
	 *
	 * @return  void
	 */
	public function before()
	{
		parent::before();

		if ($this->before_response instanceof \Response)
		{
			return;
		}

		$employee = $this->current_employee();

		if ($employee === null or $employee['role'] !== 'ADMIN')
		{
			$this->before_response = $this->json_error(
				'AUTHORIZATION_FAILED',
				'この操作を行う権限がありません。',
				403
			);
		}
	}
}
