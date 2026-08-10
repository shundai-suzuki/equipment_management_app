<?php

/**
 * 社員の業務規則を適用する。
 */
class Service_Table_Employee extends Service_BaseCrud
{
	/** @var int 社員名の最大文字数 */
	const MAX_NAME_LENGTH = 30;
	/** @var int パスワードの最小バイト数 */
	const MIN_PASSWORD_BYTES = 12;
	/** @var int パスワードの最大バイト数 */
	const MAX_PASSWORD_BYTES = 72;

	/** @var Model_Table_Department 部署を確認するModel */
	protected $department_model;

	/**
	 * 社員と部署のModelを初期化する。
	 *
	 * @param object|null $model            使用する社員Model
	 * @param object|null $department_model 使用する部署Model
	 * @return void
	 */
	public function __construct($model = null, $department_model = null)
	{
		parent::__construct($model);
		$this->department_model = $department_model ?: new Model_Table_Department();
	}

	/**
	 * 社員を登録する。
	 *
	 * @param mixed $employee_name         社員名
	 * @param int   $department_id         所属部署ID
	 * @param mixed $role                  権限
	 * @param mixed $password              パスワード
	 * @param mixed $password_confirmation 確認用パスワード
	 * @return int
	 */
	public function create($employee_name, $department_id, $role, $password, $password_confirmation)
	{
		$this->assert_active_department($department_id);

		return $this->create_record(array(
			'employee_name' => $this->required_text(
				$employee_name,
				static::MAX_NAME_LENGTH
			),
			'department_id' => $department_id,
			'role' => $this->role($role),
			'password_hash' => $this->create_hash_password(
				$password,
				$password_confirmation
			),
			'is_active' => 1,
		));
	}

	/**
	 * 社員の基本情報を更新する。
	 *
	 * @param int   $id            対象社員のID
	 * @param mixed $employee_name 社員名
	 * @param int   $department_id 所属部署ID
	 * @param mixed $role          権限
	 * @return void
	 */
	public function update($id, $employee_name, $department_id, $role)
	{
		$this->assert_positive_id($id);
		$employee = $this->read($id);
		$this->assert_active_department($department_id);
		$role = $this->role($role);

		if ((int) $employee['department_id'] !== $department_id
			and $this->model->has_loan_history($id))
		{
			throw new \RuntimeException(
				'Employees with loan history cannot change departments.',
				static::CONFLICT_EXCEPTION_CODE
			);
		}

		if ((int) $employee['is_active'] === 1
			and $employee['role'] === 'ADMIN'
			and $role !== 'ADMIN')
		{
			$this->assert_not_last_admin($employee);
		}

		$this->model->update($id, array(
			'employee_name' => $this->required_text(
				$employee_name,
				static::MAX_NAME_LENGTH
			),
			'department_id' => $department_id,
			'role' => $role,
		));
	}

	/**
	 * 未返却貸出のない社員を論理削除する。
	 *
	 * @param int $id 対象社員のID
	 * @return void
	 */
	public function soft_delete($id)
	{
		$employee = $this->read($id);

		if ($this->model->has_active_loans($id))
		{
			throw new \RuntimeException(
				'Employees with active loans cannot be archived.',
				static::CONFLICT_EXCEPTION_CODE
			);
		}

		$this->assert_not_last_admin($employee);
		$this->model->soft_delete($id);
	}

	/**
	 * 社員を一時的に無効化する。
	 *
	 * @param int $id 対象社員のID
	 * @return void
	 */
	public function deactivate($id)
	{
		$employee = $this->read($id);

		if ((int) $employee['is_active'] === 0)
		{
			throw new \RuntimeException(
				'The employee is already inactive.',
				static::CONFLICT_EXCEPTION_CODE
			);
		}

		if ($this->model->has_active_loans($id))
		{
			throw new \RuntimeException(
				'Employees with active loans cannot be deactivated.',
				static::CONFLICT_EXCEPTION_CODE
			);
		}

		$this->assert_not_last_admin($employee);
		$this->model->update_active_state($id, 0);
	}

	/**
	 * 社員を再有効化する。
	 *
	 * @param int $id 対象社員のID
	 * @return void
	 */
	public function activate($id)
	{
		$employee = $this->read($id);

		if ((int) $employee['is_active'] === 1)
		{
			throw new \RuntimeException(
				'The employee is already active.',
				static::CONFLICT_EXCEPTION_CODE
			);
		}

		$this->assert_active_department((int) $employee['department_id']);
		$this->model->update_active_state($id, 1);
	}

	/**
	 * 論理削除済み社員を復元する。
	 *
	 * @param int $id 対象社員のID
	 * @return void
	 */
	public function restore($id)
	{
		$this->assert_positive_id($id);
		$employee = $this->model->read($id, true);

		if ($employee === null)
		{
			throw new \RuntimeException(
				'The employee was not found.',
				static::NOT_FOUND_EXCEPTION_CODE
			);
		}

		$this->assert_active_department((int) $employee['department_id']);
		$this->restore_record($id);
	}

	/**
	 * 認証済み社員本人のパスワードを変更する。
	 *
	 * @param int   $id                    対象社員のID
	 * @param mixed $current_password      現在のパスワード
	 * @param mixed $password              パスワード
	 * @param mixed $password_confirmation 確認用パスワード
	 * @return void
	 */
	public function change_own_password($id, $current_password, $password, $password_confirmation)
	{
		$this->assert_positive_id($id);
		$password_hash = $this->create_hash_password(
			$password,
			$password_confirmation
		);
		$employee = $this->model->read_for_authentication($id);

		if ( ! is_string($current_password)
			or ! $this->is_available_employee($employee)
			or ! password_verify($current_password, $employee['password_hash']))
		{
			throw new \RuntimeException(
				'The current password is invalid.',
				static::VALIDATION_EXCEPTION_CODE
			);
		}

		$this->model->update_password_hash($id, $password_hash);
	}

	/**
	 * 管理者パスワードを確認して社員パスワードを再設定する。
	 *
	 * @param int   $actor_id              操作する管理者のID
	 * @param int   $id                    対象社員のID
	 * @param mixed $admin_password        管理者確認用パスワード
	 * @param mixed $password              パスワード
	 * @param mixed $password_confirmation 確認用パスワード
	 * @return void
	 */
	public function reset_password($actor_id, $id, $admin_password, $password, $password_confirmation)
	{
		$this->assert_positive_id($actor_id);
		$this->read($id);
		$administrator = $this->model->read_for_authentication($actor_id);

		if ( ! is_string($admin_password)
			or ! $this->is_available_employee($administrator)
			or $administrator['role'] !== 'ADMIN'
			or ! password_verify($admin_password, $administrator['password_hash']))
		{
			throw new \RuntimeException(
				'The administrator password is invalid.',
				static::VALIDATION_EXCEPTION_CODE
			);
		}

		$this->model->update_password_hash(
			$id,
			$this->create_hash_password($password, $password_confirmation)
		);
	}

	/**
	 * このServiceで使用する社員Modelを生成する。
	 *
	 * @return Model_Table_Employee
	 */
	protected function new_model()
	{
		return new Model_Table_Employee();
	}

	/**
	 * 許可する社員権限を返す。
	 *
	 * @param mixed $role 権限
	 * @return string
	 */
	protected function role($role)
	{
		if ( ! in_array($role, array('EMPLOYEE', 'ADMIN'), true))
		{
			throw new \InvalidArgumentException('The employee role is invalid.');
		}

		return $role;
	}

	/**
	 * パスワードを確認してハッシュ化する。
	 *
	 * @param mixed $password     パスワード
	 * @param mixed $confirmation 確認用パスワード
	 * @return string
	 */
	protected function create_hash_password($password, $confirmation)
	{
		if ( ! is_string($password)
			or strlen($password) < static::MIN_PASSWORD_BYTES
			or strlen($password) > static::MAX_PASSWORD_BYTES
			or ! is_string($confirmation)
			or ! hash_equals($password, $confirmation))
		{
			throw new \InvalidArgumentException('The password is invalid.');
		}

		$password_hash = password_hash($password, PASSWORD_DEFAULT);

		if ($password_hash === false)
		{
			throw new \RuntimeException('Failed to hash the employee password.');
		}

		return $password_hash;
	}

	/**
	 * 指定部署が利用可能か確認する。
	 *
	 * @param int $department_id 所属部署ID
	 * @return void
	 */
	protected function assert_active_department($department_id)
	{
		$this->assert_positive_id($department_id);

		if ( ! $this->department_model->is_active($department_id))
		{
			throw new \InvalidArgumentException(
				'The selected department is not available.'
			);
		}
	}

	/**
	 * 有効な最後の管理者を保護する。
	 *
	 * @param array $employee 対象社員
	 * @return void
	 */
	protected function assert_not_last_admin(array $employee)
	{
		if ((int) $employee['is_active'] === 1
			and $employee['role'] === 'ADMIN'
			and $this->active_admin_count() <= 1)
		{
			throw new \RuntimeException(
				'The last active administrator cannot be changed.',
				static::CONFLICT_EXCEPTION_CODE
			);
		}
	}

	/**
	 * 有効な管理者数を取得する。
	 *
	 * @return int
	 */
	protected function active_admin_count()
	{
		return $this->model->transaction(function ($db)
		{
			return $this->model->lock_active_admin_count($db);
		});
	}

	/**
	 * パスワード更新可能な社員状態を確認する。
	 *
	 * @param mixed $employee 社員情報
	 * @return bool
	 */
	protected function is_available_employee($employee)
	{
		return is_array($employee)
			and isset($employee['password_hash'], $employee['is_active'])
			and $employee['deleted_at'] === null
			and (int) $employee['is_active'] === 1;
	}
}
