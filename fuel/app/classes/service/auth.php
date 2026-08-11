<?php

/**
 * 社員の認証規則を適用する。
 */
class Service_Auth
{
	/** @var int 許可する社員IDの最大値 */
	const MAX_EMPLOYEE_ID = 2147483647;
	/** @var string 社員番号列挙を防ぐダミーパスワードハッシュ */
	const DUMMY_PASSWORD_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';

	/** @var Model_Table_Employee 認証時の取得に使用する社員モデル */
	protected $model;

	/**
	 * 認証に使用するModelを初期化する。
	 *
	 * @param object|null $model 使用する操作対象Model
	 * @return void
	 */
	public function __construct()
	{
		$this->model = new Model_Table_Employee();
	}

	/**
	 * 有効な社員を1件認証する。
	 *
	 * @param  int|string $employee_number 社員番号
	 * @param  mixed      $password        パスワード
	 * @return array|false
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
	 * @param  int|string $employee_number        社員番号
	 * @param  mixed      $credential_fingerprint 認証情報のフィンガープリント
	 * @return array|false
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
	 * @param  mixed $employee_number 社員番号
	 * @return int|null
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

		if ( ! preg_match('/\A[1-9][0-9]*\z/', $employee_number))
		{
			return null;
		}

		$employee_id = (int) $employee_number;
		return $employee_id <= static::MAX_EMPLOYEE_ID ? $employee_id : null;
	}

	/**
	 * ログインと継続アクセスに必要なDB状態を確認する。
	 *
	 * @param  mixed $employee 対象の社員情報
	 * @return bool
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
	 * @param  array $employee 対象の社員情報
	 * @return string
	 */
	protected function create_credential_fingerprint(array $employee)
	{
		return hash('sha256',	(string) $employee['id'].'|'.$employee['password_hash']);
	}

	/**
	 * コントローラが使用できる社員項目だけを返す。
	 *
	 * @param  array $employee 対象の社員情報
	 * @return array
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
