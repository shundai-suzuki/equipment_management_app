<?php

/**
 * 社員の認証規則を適用する。
 *
 * @package  app
 */
class Service_Auth
{
	const MAX_EMPLOYEE_ID = 2147483647;
	const MAX_EMPLOYEE_ID_LEN = 10;
	const MIN_FINGERPRINT_KEY_BYTES = 32;
	const DUMMY_PASSWORD_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';

	/**
	 * 認証時の取得に使用する社員モデル。
	 *
	 * @var Model_Table_Employee
	 */
	protected $model;

	/**
	 * 認証情報フィンガープリントだけに使用する秘密値。
	 *
	 * @var string
	 */
	protected $credential_fingerprint_key;

	/**
	 * @param  object|null  $model
	 * @param  string|null  $credential_fingerprint_key
	 */
	public function __construct($model = null, $credential_fingerprint_key = null)
	{
		if ($model !== null and ! is_object($model))
		{
			throw new \InvalidArgumentException('The authentication model must be an object.');
		}

		$this->model = $model ? $model : new Model_Table_Employee();
		$this->credential_fingerprint_key = $credential_fingerprint_key === null
			? \Config::get('employee_auth.credential_fingerprint_key')
			: $credential_fingerprint_key;

		if ( ! is_string($this->credential_fingerprint_key)
			or strlen($this->credential_fingerprint_key) < static::MIN_FINGERPRINT_KEY_BYTES)
		{
			throw new \RuntimeException('The credential fingerprint key is not configured.');
		}
	}

	/**
	 * 有効な社員を1件認証する。
	 *
	 * @param   int|string  $employee_number
	 * @param   mixed       $password
	 * @return  array|false
	 */
	public function authenticate($employee_number, $password)
	{
		$employee_id = $this->normalize_employee_id($employee_number);
		$employee = $employee_id === null
			? null
			: $this->model->read_for_authentication($employee_id);
		$password_hash = static::DUMMY_PASSWORD_HASH;

		if (is_array($employee)
			and isset($employee['password_hash'])
			and is_string($employee['password_hash']))
		{
			$password_hash = $employee['password_hash'];
		}

		$password_value = is_string($password) ? $password : '';
		$password_is_valid = password_verify($password_value, $password_hash);

		if ( ! $password_is_valid or ! $this->is_available_employee($employee))
		{
			return false;
		}

		return array(
			'user' => $this->safe_user($employee),
			'credential_fingerprint' => $this->create_credential_fingerprint($employee),
		);
	}

	/**
	 * 現在のセッションに保存された社員を検証する。
	 *
	 * @param   int|string  $employee_number
	 * @param   mixed       $credential_fingerprint
	 * @return  array|false
	 */
	public function validate_session($employee_number, $credential_fingerprint)
	{
		$employee_id = $this->normalize_employee_id($employee_number);

		if ($employee_id === null or ! is_string($credential_fingerprint))
		{
			return false;
		}

		$employee = $this->model->read_for_authentication($employee_id);

		if ( ! $this->is_available_employee($employee))
		{
			return false;
		}

		$current_fingerprint = $this->create_credential_fingerprint($employee);

		if ( ! hash_equals($current_fingerprint, $credential_fingerprint))
		{
			return false;
		}

		return $this->safe_user($employee);
	}

	/**
	 * 符号付きINT範囲の正の社員番号を正規化する。
	 *
	 * @param   mixed  $employee_number
	 * @return  int|null
	 */
	protected function normalize_employee_id($employee_number)
	{
		if (is_int($employee_number))
		{
			if ($employee_number < 1 or $employee_number > static::MAX_EMPLOYEE_ID)
			{
				return null;
			}

			return $employee_number;
		}

		if ( ! is_string($employee_number))
		{
			return null;
		}

		$employee_number = trim($employee_number);

		if ( ! preg_match('/\A[1-9][0-9]*\z/', $employee_number)
			or strlen($employee_number) > static::MAX_EMPLOYEE_ID_LEN)
		{
			return null;
		}

		$employee_id = (int) $employee_number;

		return $employee_id <= static::MAX_EMPLOYEE_ID ? $employee_id : null;
	}

	/**
	 * ログインと継続アクセスに必要なDB状態を確認する。
	 *
	 * @param   mixed  $employee
	 * @return  bool
	 */
	protected function is_available_employee($employee)
	{
		return is_array($employee)
			and isset(
				$employee['id'],
				$employee['employee_name'],
				$employee['role'],
				$employee['password_hash'],
				$employee['is_active']
			)
			and array_key_exists('deleted_at', $employee)
			and is_string($employee['password_hash'])
			and $employee['password_hash'] !== ''
			and (int) $employee['is_active'] === 1
			and $employee['deleted_at'] === null
			and in_array($employee['role'], array('EMPLOYEE', 'ADMIN'), true);
	}

	/**
	 * パスワードハッシュを公開せずにセッション用フィンガープリントを導出する。
	 *
	 * @param   array  $employee
	 * @return  string
	 */
	protected function create_credential_fingerprint(array $employee)
	{
		return hash_hmac(
			'sha256',
			(string) $employee['id'].'|'.$employee['password_hash'],
			$this->credential_fingerprint_key
		);
	}

	/**
	 * コントローラが使用できる社員項目だけを返す。
	 *
	 * @param   array  $employee
	 * @return  array
	 */
	protected function safe_user(array $employee)
	{
		return array(
			'id' => (int) $employee['id'],
			'employee_name' => $employee['employee_name'],
			'role' => $employee['role'],
		);
	}
}
