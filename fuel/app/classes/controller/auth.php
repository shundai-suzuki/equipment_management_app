<?php

/**
 * JSON endpoints for employee login and logout.
 *
 * @package  app
 * @extends  Controller_Base
 */
class Controller_Auth extends Controller_Base
{
	/**
	 * Allow the login action to run without an existing Session.
	 *
	 * @var bool
	 */
	protected $authentication_required = false;

	/**
	 * Authenticate an employee and return only safe employee fields.
	 *
	 * @return  Response
	 */
	public function post_login()
	{
		$employee_number = \Input::post('employee_number', '');
		$password = \Input::post('password', '');

		if ( ! \Auth::login($employee_number, $password))
		{
			return $this->json_error(
				'AUTHENTICATION_FAILED',
				'社員番号またはパスワードが正しくありません。',
				401
			);
		}

		$employee = $this->current_employee();

		if ($employee === null)
		{
			\Auth::logout();

			return $this->json_error(
				'INTERNAL_ERROR',
				'処理に失敗しました。時間をおいて再度お試しください。',
				500
			);
		}

		return $this->json_success(
			array('employee' => $employee)
		);
	}

	/**
	 * Destroy the authenticated Session and return a JSON result.
	 *
	 * @return  Response
	 */
	public function post_logout()
	{
		if ( ! \Auth::check())
		{
			return $this->json_error(
				'AUTHENTICATION_REQUIRED',
				'ログインが必要です。',
				401
			);
		}

		\Auth::logout();

		return $this->json_success(
			array('logged_out' => true)
		);
	}
}
