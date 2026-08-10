<?php

/**
 * FuelPHP Authと社員認証を接続する。
 */
class Auth_Login_Employee extends \Auth_Login_Driver
{
	/** @var string 認証済み社員IDを保存するSessionキー */
	const SESSION_EMPLOYEE_ID = 'employee_auth.employee_id';
	
	/** @var string 認証情報フィンガープリントを保存するSessionキー */
	const SESSION_CREDENTIAL_FINGERPRINT = 'employee_auth.credential_fingerprint';

	/** @var array|null 認証情報を除いた現在の認証済み社員 */
	protected $user;

	/** @var Service_Auth|null 必要な場合だけ生成する認証Service */
	protected $service;

	/** @var string|null 検証後からSession生成までの間だけ保持するフィンガープリント */
	protected $pending_fingerprint;

	/** @var array 使用しないGroup・ACLドライバを除いた設定 */
	protected $config = array(
		'drivers' => array(),
		'additional_fields' => array(),
	);

	/**
	 * Sessionを現在の社員レコードと照合する。
	 *
	 * @return bool
	 */
	protected function perform_check()
	{
		$employee_id = \Session::get(static::SESSION_EMPLOYEE_ID);
		$credential_fingerprint = \Session::get(static::SESSION_CREDENTIAL_FINGERPRINT);

		if ($employee_id === null and $credential_fingerprint === null)
		{
			$this->user = null;
			return false;
		}

		$user = $this->get_service()
			->validate_session($employee_id, $credential_fingerprint);

		if ($user === false)
		{
			$this->invalidate_session();
			return false;
		}

		$this->user = $user;
		return true;
	}

	/**
	 * Sessionを変更せず、送信された認証情報を検証する。
	 *
	 * @param int|string $employee_number 認証する社員番号
	 * @param mixed      $password        認証するパスワード
	 * @return array|false
	 */
	public function validate_user($employee_number = '', $password = '')
	{
		$authentication = $this->get_service()
			->authenticate($employee_number, $password);

		if ($authentication === false)
		{
			$this->pending_fingerprint = null;
			return false;
		}

		$this->pending_fingerprint = $authentication['credential_fingerprint'];
		return $authentication['user'];
	}

	/**
	 * SimpleAuthの流れに従い、新しく生成したSessionでログインする。
	 *
	 * @param int|string $employee_number 認証する社員番号
	 * @param mixed      $password        認証するパスワード
	 * @return bool
	 */
	public function login($employee_number = '', $password = '')
	{
		$user = $this->validate_user($employee_number, $password);

		if ($user === false)
		{
			$this->user = null;
			$this->clear_session_values();
			return $this->check();
		}

		$fingerprint = $this->pending_fingerprint;
		$this->pending_fingerprint = null;

		\Session::destroy();
		\Session::read();
		\Session::set(static::SESSION_EMPLOYEE_ID, $user['id']);
		\Session::set(static::SESSION_CREDENTIAL_FINGERPRINT, $fingerprint);

		return $this->check();
	}

	/**
	 * サーバー側のSessionを破棄してログアウトする。
	 *
	 * @return bool
	 */
	public function logout()
	{
		$this->user = null;
		$this->pending_fingerprint = null;
		\Session::destroy();

		return true;
	}

	/**
	 * このドライバのIDと社員番号を返す。
	 *
	 * @return array|false
	 */
	public function get_user_id()
	{
		return $this->user === null
			? false
			: array($this->id, (int) $this->user['id']);
	}

	/**
	 * 社員権限にはGroupドライバを使用しない。
	 *
	 * @return array|false
	 */
	public function get_groups()
	{
		return $this->user === null ? false : array();
	}

	/**
	 * employeesテーブルにはメールアドレス列がない。
	 *
	 * @return false
	 */
	public function get_email()
	{
		return false;
	}

	/**
	 * 表示用の社員名を返す。
	 *
	 * @return string|false
	 */
	public function get_screen_name()
	{
		return $this->get('employee_name', false);
	}

	/**
	 * DBから再取得した現在の権限を返す。
	 *
	 * @return string|false
	 */
	public function get_role()
	{
		return $this->get('role', false);
	}

	/**
	 * 許可された現在の社員情報を1項目返す。
	 *
	 * @param string $field   取得する社員情報の項目名
	 * @param mixed  $default 項目を取得できない場合の既定値
	 * @return mixed
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
	 * 認証情報を確認する場合だけ認証Serviceを生成する。
	 *
	 * @return Service_Auth
	 */
	protected function get_service()
	{
		if ($this->service === null)
		{
			$this->service = new Service_Auth();
		}

		return $this->service;
	}

	/**
	 * 通常のログイン失敗後に認証用の値だけを削除する。
	 *
	 * @return void
	 */
	protected function clear_session_values()
	{
		\Session::delete(static::SESSION_EMPLOYEE_ID);
		\Session::delete(static::SESSION_CREDENTIAL_FINGERPRINT);
	}

	/**
	 * 古い、または無効な認証Sessionを破棄する。
	 *
	 * @return void
	 */
	protected function invalidate_session()
	{
		$this->user = null;
		$this->pending_fingerprint = null;
		\Session::destroy();
	}
}
