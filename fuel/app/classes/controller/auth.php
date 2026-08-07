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
	 * File-backed login attempt limiter created only when required.
	 *
	 * @var Security_LoginRateLimit|null
	 */
	protected $login_rate_limit;

	/**
	 * Authenticate an employee and return only safe employee fields.
	 *
	 * @return  Response
	 */
	public function post_login()
	{
		$employee_number = \Input::post('employee_number', '');
		$password = \Input::post('password', '');
		$ip_address = \Input::server('REMOTE_ADDR');

		try
		{
			$login_rate_limit = $this->get_login_rate_limit();

			if ($login_rate_limit->is_blocked($employee_number, $ip_address))
			{
				return $this->login_rate_limited_response();
			}
		}
		catch (Security_LoginRateLimitException $exception)
		{
			return $this->login_rate_limit_unavailable_response();
		}

		if ( ! \Auth::login($employee_number, $password))
		{
			try
			{
				$blocked = $login_rate_limit->record_failure(
					$employee_number,
					$ip_address
				);
			}
			catch (Security_LoginRateLimitException $exception)
			{
				return $this->login_rate_limit_unavailable_response();
			}

			if ($blocked)
			{
				return $this->login_rate_limited_response();
			}

			return $this->json_error(
				'AUTHENTICATION_FAILED',
				'社員番号またはパスワードが正しくありません。',
				401
			);
		}

		try
		{
			$login_rate_limit->record_success($employee_number);
		}
		catch (Security_LoginRateLimitException $exception)
		{
			\Auth::logout();
			return $this->login_rate_limit_unavailable_response();
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

	/**
	 * Create the local rate limiter only for login requests.
	 *
	 * @return  Security_LoginRateLimit
	 */
	protected function get_login_rate_limit()
	{
		if ($this->login_rate_limit === null)
		{
			$this->login_rate_limit = new Security_LoginRateLimit();
		}

		return $this->login_rate_limit;
	}

	/**
	 * Return the same response for an account or IP block.
	 *
	 * @return  Response
	 */
	protected function login_rate_limited_response()
	{
		return $this->json_error(
			'LOGIN_RATE_LIMITED',
			'ログインできません。時間をおいて再度お試しください。',
			429
		);
	}

	/**
	 * Fail closed without exposing state-file details.
	 *
	 * @return  Response
	 */
	protected function login_rate_limit_unavailable_response()
	{
		try
		{
			\Log::error(
				'SECURITY_ALERT Login rate-limit state is unavailable. request_id='
				.$this->request_id
			);
		}
		catch (\Throwable $logging_exception)
		{
			// Keep the client response closed even when fallback logging fails.
		}

		return $this->json_error(
			'LOGIN_RATE_LIMIT_UNAVAILABLE',
			'処理に失敗しました。時間をおいて再度お試しください。',
			503
		);
	}
}
