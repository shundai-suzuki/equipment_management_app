<?php

/**
 * 社員のログイン・ログアウト用JSONエンドポイント。
 *
 * @package  app
 * @extends  Controller_Base
 */
class Controller_Auth extends Controller_Base
{
	/**
	 * 既存のセッションがなくてもログイン処理を実行できるようにする。
	 *
	 * @var bool
	 */
	protected $authentication_required = false;

	/**
	 * 必要な場合だけ生成するファイル保存型ログイン試行制限。
	 *
	 * @var Security_LoginRateLimit|null
	 */
	protected $login_rate_limit;

	/**
	 * 社員を認証し、安全な社員項目だけを返す。
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
	 * 認証済みセッションを破棄し、JSON結果を返す。
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
	 * ログインリクエストの場合だけローカル試行制限を生成する。
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
	 * アカウント制限とIP制限に同じレスポンスを返す。
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
	 * 状態ファイルの詳細を公開せず、安全側に処理を失敗させる。
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
			// 代替ログ出力に失敗しても、クライアントへの情報公開を防ぐ。
		}

		return $this->json_error(
			'LOGIN_RATE_LIMIT_UNAVAILABLE',
			'処理に失敗しました。時間をおいて再度お試しください。',
			503
		);
	}
}
