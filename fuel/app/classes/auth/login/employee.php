<?php

/**
 * Connects FuelPHP Auth to employee authentication.
 *
 * @package  app
 */
class Auth_Login_Employee extends \Auth_Login_Driver
{
	const SESSION_EMPLOYEE_ID = 'employee_auth.employee_id';
	const SESSION_CREDENTIAL_FINGERPRINT = 'employee_auth.credential_fingerprint';

	/**
	 * Current authenticated employee without credential data.
	 *
	 * @var array|null
	 */
	protected $user;

	/**
	 * Authentication Service created only when required.
	 *
	 * @var Service_Auth|null
	 */
	protected $service;

	/**
	 * Fingerprint held only between validation and Session creation.
	 *
	 * @var string|null
	 */
	protected $pending_fingerprint;

	/**
	 * Keep the base driver free of unused Group and ACL drivers.
	 *
	 * @var array
	 */
	protected $config = array(
		'drivers' => array(),
		'additional_fields' => array(),
	);

	/**
	 * Check the Session against the current employee record.
	 *
	 * @return  bool
	 */
	protected function perform_check()
	{
		$employee_id = \Session::get(static::SESSION_EMPLOYEE_ID);
		$credential_fingerprint = \Session::get(
			static::SESSION_CREDENTIAL_FINGERPRINT
		);

		if ($employee_id === null and $credential_fingerprint === null)
		{
			$this->user = null;
			return false;
		}

		$user = $this->get_service()
			->validate_session(
				$employee_id,
				$credential_fingerprint
			);

		if ($user === false)
		{
			$this->invalidate_session();
			return false;
		}

		$this->user = $user;
		return true;
	}

	/**
	 * Validate submitted credentials without changing the Session.
	 *
	 * @param   int|string  $employee_number
	 * @param   mixed       $password
	 * @return  array|false
	 */
	public function validate_user($employee_number = '', $password = '')
	{
		$authentication = $this->get_service()->authenticate(
			$employee_number,
			$password
		);

		if ($authentication === false)
		{
			$this->pending_fingerprint = null;
			return false;
		}

		$this->pending_fingerprint = $authentication['credential_fingerprint'];
		return $authentication['user'];
	}

	/**
	 * Login with a newly created Session, following SimpleAuth's flow.
	 *
	 * @param   int|string  $employee_number
	 * @param   mixed       $password
	 * @return  bool
	 */
	public function login($employee_number = '', $password = '')
	{
		$user = $this->validate_user($employee_number, $password);

		if ($user === false)
		{
			$this->user = null;
			$this->clear_session_values();
			\Auth::_unregister_verified($this);
			return false;
		}

		$this->user = $user;
		$fingerprint = $this->pending_fingerprint;
		$this->pending_fingerprint = null;

		\Session::destroy();
		\Session::read();
		\Session::set(static::SESSION_EMPLOYEE_ID, $this->user['id']);
		\Session::set(static::SESSION_CREDENTIAL_FINGERPRINT, $fingerprint);
		\Auth::_register_verified($this);

		return true;
	}

	/**
	 * Logout by destroying the server-side Session.
	 *
	 * @return  bool
	 */
	public function logout()
	{
		$this->user = null;
		$this->pending_fingerprint = null;
		\Session::destroy();

		return true;
	}

	/**
	 * Return this driver's ID and the employee number.
	 *
	 * @return  array|false
	 */
	public function get_user_id()
	{
		return $this->user === null
			? false
			: array($this->id, (int) $this->user['id']);
	}

	/**
	 * Group drivers are intentionally not used for employee roles.
	 *
	 * @return  array|false
	 */
	public function get_groups()
	{
		return $this->user === null ? false : array();
	}

	/**
	 * The employees table has no email column.
	 *
	 * @return  false
	 */
	public function get_email()
	{
		return false;
	}

	/**
	 * Return the employee name for display.
	 *
	 * @return  string|false
	 */
	public function get_screen_name()
	{
		return $this->get('employee_name', false);
	}

	/**
	 * Return the current role reloaded from the DB.
	 *
	 * @return  string|false
	 */
	public function get_role()
	{
		return $this->get('role', false);
	}

	/**
	 * Return one allowed current-user field.
	 *
	 * @param   string  $field
	 * @param   mixed   $default
	 * @return  mixed
	 */
	public function get($field, $default = null)
	{
		if ($this->user !== null
			and in_array($field, array('id', 'employee_name', 'role'), true))
		{
			return $this->user[$field];
		}

		return $default;
	}

	/**
	 * Create the authentication Service only when credentials must be checked.
	 *
	 * @return  Service_Auth
	 */
	protected function get_service()
	{
		if ($this->service === null)
		{
			$this->service = new Service_Auth(
				null,
				$this->get_config('credential_fingerprint_key')
			);
		}

		return $this->service;
	}

	/**
	 * Remove only authentication values after an ordinary login failure.
	 *
	 * @return  void
	 */
	protected function clear_session_values()
	{
		\Session::delete(static::SESSION_EMPLOYEE_ID);
		\Session::delete(static::SESSION_CREDENTIAL_FINGERPRINT);
	}

	/**
	 * Destroy a stale or invalid authenticated Session.
	 *
	 * @return  void
	 */
	protected function invalidate_session()
	{
		$this->user = null;
		$this->pending_fingerprint = null;
		\Session::destroy();
	}
}
