<?php

/**
 * 認証済みアカウント管理APIのコントローラ。
 *
 * @package  app
 */
class Controller_Account extends Controller_Base
{
	/**
	 * 認証済みアカウントに使用する社員サービス。
	 *
	 * @var Service_Table_Employee
	 */
	protected $service;

	/** 認証後にサービスを初期化する。 */
	public function before()
	{
		parent::before();

		if ($this->before_response instanceof \Response)
		{
			return;
		}

		$this->service = new Service_Table_Employee();
	}

	/**
	 * 認証済み社員本人のパスワードだけを変更する。
	 *
	 * @return  Response
	 */
	public function post_password()
	{
		return $this->execute_api(
			function ()
			{
				$employee_id = $this->employee_id();
				$password = \Input::post('password');

				$this->service->change_own_password(
					$employee_id,
					\Input::post('current_password'),
					$password,
					\Input::post('password_confirmation')
				);

				if ( ! \Auth::login($employee_id, $password))
				{
					\Auth::logout();

					throw new \RuntimeException(
						'Failed to regenerate the authenticated Session.'
					);
				}

				return $this->json_success(
					array('password_changed' => true)
				);
			}
		);
	}
}
