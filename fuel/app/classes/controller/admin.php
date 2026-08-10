<?php

/**
 * 管理者専用JSON APIの共通コントローラ。
 */
abstract class Controller_Admin extends Controller_Base
{
	/**
	 * ADMIN権限を持たない認証済み社員を拒否する。
	 *
	 * @return void
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
