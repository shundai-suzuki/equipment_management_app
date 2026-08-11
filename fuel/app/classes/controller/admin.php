<?php

/**
 * 管理者専用JSON APIの共通コントローラ。
 */
abstract class Controller_Admin extends Controller_Crud
{
	/**
	 * ADMIN権限を持たない認証済み社員を拒否する。
	 *
	 * @return void
	 */
	protected function authorize()
	{
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
