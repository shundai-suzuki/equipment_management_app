<?php

/**
 * Authenticated account-management API Controller.
 *
 * @package  app
 */
class Controller_Account extends Controller_Base
{
	/**
	 * Employee Service used for the authenticated account.
	 *
	 * @var Service_Table_Employee
	 */
	protected $service;

	/** Initialize the Service after authentication. */
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
	 * Change only the authenticated employee's password.
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
