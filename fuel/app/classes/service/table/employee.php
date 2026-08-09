<?php

/**
 * Applies employee registration rules and delegates persistence.
 *
 * @package  app
 */
class Service_Table_Employee extends Service_BaseCrud
{
	const MAX_NAME_LENGTH = 30;
	const MIN_PASSWORD_BYTES = 12;
	const MAX_PASSWORD_BYTES = 72;

	/**
	 * Table registered by this Service.
	 *
	 * @var string
	 */
	protected static $table_name = 'employees';

	/**
	 * Department Model used to validate the selected department.
	 *
	 * @var Model_Table_Department
	 */
	protected $department_model;

	/**
	 * @param  object|null  $model
	 * @param  object|null  $id_allocator
	 * @param  object|null  $department_model
	 */
	public function __construct($model = null, $id_allocator = null, $department_model = null)
	{
		parent::__construct($model, $id_allocator);

		if ($department_model !== null and ! is_object($department_model))
		{
			throw new \InvalidArgumentException('The department model must be an object.');
		}

		$this->department_model = $department_model ? $department_model : new Model_Table_Department();
	}

	/**
	 * Register an employee with an application-managed employee number.
	 *
	 * @param   int     $id
	 * @param   string  $employee_name
	 * @param   int     $department_id
	 * @param   string  $role
	 * @param   string  $password
	 * @param   string  $password_confirmation
	 * @return  int
	 */
	public function create($id, $employee_name, $department_id, $role, $password, $password_confirmation)
	{
		$this->assert_new_id($id);
		$this->assert_positive_id($department_id, 'The department ID');

		$employee_name = $this->normalize_name($employee_name);
		$role = $this->normalize_role($role);

		$password_hash = $this->create_hash_password($password, $password_confirmation);
		return $this->create_record(array(
			'employee_name' => $employee_name,
			'department_id' => $department_id,
			'role' => $role,
			'password_hash' => $password_hash,
			'is_active' => 1,
		));
	}

	/**
	 * Create an employee after rechecking the administrator.
	 *
	 * @param   int     $actor_id
	 * @param   int     $id
	 * @param   string  $employee_name
	 * @param   int     $department_id
	 * @param   string  $role
	 * @param   string  $password
	 * @param   string  $password_confirmation
	 * @return  int
	 */
	public function create_for_admin($actor_id,	$id, $employee_name, $department_id, $role, $password, $password_confirmation)
	{
		$this->assert_admin_actor($actor_id);

		return $this->create(
			$id,
			$employee_name,
			$department_id,
			$role,
			$password,
			$password_confirmation
		);
	}

	/**
	 * Update the editable employee fields.
	 *
	 * @param   int     $actor_id
	 * @param   int     $id
	 * @param   string  $employee_name
	 * @param   int     $department_id
	 * @param   string  $role
	 * @return  array
	 */
	public function update_for_admin($actor_id, $id, $employee_name, $department_id, $role)
	{
		$this->assert_positive_id($id, 'The employee ID');
		$this->assert_positive_id($department_id, 'The department ID');
		$employee_name = $this->normalize_name($employee_name);
		$role = $this->normalize_role($role);

		return $this->model->transaction(
			function ($db) use ($actor_id, $id, $employee_name, $department_id, $role)
			{
				$this->assert_admin_actor($actor_id, $db);
				$active_admin_count = $this->model->lock_active_admin_count($db);
				$employee_before_update = $this->model->read_for_update($id, false, $db);

				if ($employee_before_update === null)
				{
					throw new RuntimeException(
						'The employee was not found.',
						static::NOT_FOUND_EXCEPTION_CODE
					);
				}

				$this->assert_active_department($department_id, $db);

				if ((int) $employee_before_update['department_id'] !== $department_id
					and $this->model->has_loan_history($id, $db))
				{
					throw new RuntimeException(
						'An employee with lending history cannot change departments.',
						static::CONFLICT_EXCEPTION_CODE
					);
				}

				if ((int) $employee_before_update['is_active'] === 1
					and $employee_before_update['role'] === 'ADMIN'
					and $role !== 'ADMIN'
					and $active_admin_count <= 1)
				{
					throw new RuntimeException(
						'The last active administrator cannot lose administrator access.',
						static::CONFLICT_EXCEPTION_CODE
					);
				}

				return $this->update_and_read_record(
					$id,
					array(
						'employee_name' => $employee_name,
						'department_id' => $department_id,
						'role' => $role,
					),
					$db
				);
			}
		);
	}

	/**
	 * Soft-delete an employee who has no active loan.
	 *
	 * @param   int     $actor_id
	 * @param   int     $id
	 * @return  array
	 */
	public function soft_delete_for_admin($actor_id, $id)
	{
		$this->assert_positive_id($id, 'The employee ID');

		return $this->model->transaction(
			function ($db) use ($actor_id, $id)
			{
				$this->assert_admin_actor($actor_id, $db);
				$active_admin_count = $this->model->lock_active_admin_count($db);
				$employee_before_delete = $this->model->read_for_update($id, true, $db);

				if ($employee_before_delete === null)
				{
					throw new RuntimeException(
						'The employee was not found.',
						static::NOT_FOUND_EXCEPTION_CODE
					);
				}

				if ($employee_before_delete['deleted_at'] !== null)
				{
					throw new RuntimeException(
						'The employee is already archived.',
						static::CONFLICT_EXCEPTION_CODE
					);
				}

				if ((int) $employee_before_delete['is_active'] === 1
					and $employee_before_delete['role'] === 'ADMIN'
					and $active_admin_count <= 1)
				{
					throw new RuntimeException(
						'The last active administrator cannot be archived.',
						static::CONFLICT_EXCEPTION_CODE
					);
				}

				if ($this->model->has_active_loans($id, $db))
				{
					throw new RuntimeException(
						'An employee with an active loan cannot be archived.',
						static::CONFLICT_EXCEPTION_CODE
					);
				}

				return $this->soft_delete_and_read_record($id, $db);
			}
		);
	}

	/**
	 * Temporarily disable one employee account.
	 *
	 * @param   int  $actor_id
	 * @param   int  $id
	 * @return  array
	 */
	public function deactivate_for_admin($actor_id, $id)
	{
		$this->assert_positive_id($id, 'The employee ID');

		return $this->model->transaction(
			function ($db) use ($actor_id, $id)
			{
				$this->assert_admin_actor($actor_id, $db);
				$active_admin_count = $this->model->lock_active_admin_count($db);
				$employee_before_deactivate = $this->model->read_for_update(
					$id,
					false,
					$db
				);

				if ($employee_before_deactivate === null)
				{
					throw new \RuntimeException(
						'The employee was not found.',
						static::NOT_FOUND_EXCEPTION_CODE
					);
				}

				if ((int) $employee_before_deactivate['is_active'] === 0)
				{
					throw new \RuntimeException(
						'The employee is already inactive.',
						static::CONFLICT_EXCEPTION_CODE
					);
				}

				if ($employee_before_deactivate['role'] === 'ADMIN'
					and $active_admin_count <= 1)
				{
					throw new \RuntimeException(
						'The last active administrator cannot be deactivated.',
						static::CONFLICT_EXCEPTION_CODE
					);
				}

				if ($this->model->has_active_loans($id, $db))
				{
					throw new \RuntimeException(
						'An employee with an active loan cannot be deactivated.',
						static::CONFLICT_EXCEPTION_CODE
					);
				}

				return $this->update_active_state_and_read($id, 0, $db);
			}
		);
	}

	/**
	 * Re-enable one inactive employee account.
	 *
	 * @param   int  $actor_id
	 * @param   int  $id
	 * @return  array
	 */
	public function activate_for_admin($actor_id, $id)
	{
		$this->assert_positive_id($id, 'The employee ID');

		return $this->model->transaction(
			function ($db) use ($actor_id, $id)
			{
				$this->assert_admin_actor($actor_id, $db);
				$employee_before_activate = $this->model->read_for_update(
					$id,
					false,
					$db
				);

				if ($employee_before_activate === null)
				{
					throw new \RuntimeException(
						'The employee was not found.',
						static::NOT_FOUND_EXCEPTION_CODE
					);
				}

				if ((int) $employee_before_activate['is_active'] === 1)
				{
					throw new \RuntimeException(
						'The employee is already active.',
						static::CONFLICT_EXCEPTION_CODE
					);
				}

				$this->assert_active_department(
					(int) $employee_before_activate['department_id'],
					$db
				);

				return $this->update_active_state_and_read($id, 1, $db);
			}
		);
	}

	/**
	 * Restore one archived employee without reactivating the account.
	 *
	 * @param   int  $actor_id
	 * @param   int  $id
	 * @return  array
	 */
	public function restore_for_admin($actor_id, $id)
	{
		$this->assert_positive_id($id, 'The employee ID');

		return $this->model->transaction(
			function ($db) use ($actor_id, $id)
			{
				$this->assert_admin_actor($actor_id, $db);
				$employee_before_restore = $this->model->read_for_update(
					$id,
					true,
					$db
				);

				if ($employee_before_restore === null)
				{
					throw new \RuntimeException(
						'The employee was not found.',
						static::NOT_FOUND_EXCEPTION_CODE
					);
				}

				if ($employee_before_restore['deleted_at'] === null)
				{
					throw new \RuntimeException(
						'The employee is not archived.',
						static::CONFLICT_EXCEPTION_CODE
					);
				}

				$this->assert_active_department(
					(int) $employee_before_restore['department_id'],
					$db
				);

				return $this->restore_and_read_record($id, $db);
			}
		);
	}

	/**
	 * Reset one employee password after verifying the administrator password.
	 *
	 * @param   int    $actor_id
	 * @param   int    $id
	 * @param   mixed  $admin_password
	 * @param   mixed  $password
	 * @param   mixed  $password_confirmation
	 * @return  array
	 */
	public function reset_password_for_admin($actor_id, $id, $admin_password,	$password, $password_confirmation)
	{
		$this->assert_positive_id($id, 'The employee ID');

		if ( ! is_string($admin_password)
		or $admin_password === ''
		or strlen($admin_password) > static::MAX_PASSWORD_BYTES)
		{
			throw new \InvalidArgumentException('The administrator password is invalid.');
		}

		$password_hash = $this->create_hash_password(
			$password,
			$password_confirmation
		);

		return $this->model->transaction(
			function ($db) use ($actor_id, $id, $admin_password, $password_hash)
			{
				$this->assert_admin_actor($actor_id, $db);
				$this->model->lock_active_admin_count($db);
				$administrator_is_locked = $this->model->read_for_update(
					$actor_id,
					false,
					$db
				);
				$administrator = $this->model->read_for_authentication(
					$actor_id,
					$db
				);

				if ($administrator_is_locked === null
					or $administrator === null
					or $administrator['role'] !== 'ADMIN'
					or (int) $administrator['is_active'] !== 1
					or $administrator['deleted_at'] !== null
					or ! password_verify($admin_password,	$administrator['password_hash']))
				{
					throw new \RuntimeException(
						'The administrator password is invalid.',
						static::FORBIDDEN_EXCEPTION_CODE
					);
				}

				if ($this->model->read_for_update($id, false, $db) === null)
				{
					throw new \RuntimeException(
						'The employee was not found.',
						static::NOT_FOUND_EXCEPTION_CODE
					);
				}

				if ($this->model->update_password_hash($id,	$password_hash,	$db) !== 1)
				{
					throw new \RuntimeException(
						'The employee password changed during the update.',
						static::CONFLICT_EXCEPTION_CODE
					);
				}

				return $this->read_required_record($id, $db);
			}
		);
	}

	/**
	 * Change an already locked account state and return the safe employee row.
	 *
	 * @param   int                  $id
	 * @param   int                  $is_active
	 * @param   Database_Connection  $db
	 * @return  array
	 */
	protected function update_active_state_and_read($id, $is_active, \Database_Connection $db)
	{
		if ($this->model->update_active_state($id, $is_active, $db) !== 1)
		{
			throw new \RuntimeException(
				'The employee state changed during the update.',
				static::CONFLICT_EXCEPTION_CODE
			);
		}

		return $this->read_required_record($id, $db);
	}

	/**
	 * Create the employee Model used by this Service.
	 *
	 * @return  Model_Table_Employee
	 */
	protected function new_model()
	{
		return new Model_Table_Employee();
	}

	/**
	 * Recheck the department on the allocator transaction connection.
	 *
	 * @param   array                $create_values
	 * @param   Database_Connection  $db
	 * @return  void
	 */
	protected function before_create(array $create_values, \Database_Connection $db)
	{
		$this->assert_active_department($create_values['department_id'], $db);
	}

	/**
	 * Normalize and validate an employee name.
	 *
	 * @param   mixed  $name
	 * @return  string
	 */
	protected function normalize_name($name)
	{
		if ( ! is_string($name))
		{
			throw new \InvalidArgumentException('The employee name must be a string.');
		}

		$name = trim($name);

		if ($name === '' or mb_strlen($name, 'UTF-8') > static::MAX_NAME_LENGTH)
		{
			throw new \InvalidArgumentException('The employee name must be between 1 and 30 characters.');
		}

		return $name;
	}

	/**
	 * Accept only the roles stored by the employees table.
	 *
	 * @param   mixed  $role
	 * @return  string
	 */
	protected function normalize_role($role)
	{
		if ( ! is_string($role))
		{
			throw new \InvalidArgumentException('The employee role must be a string.');
		}

		$role = trim($role);

		if ( ! in_array($role, array('EMPLOYEE', 'ADMIN'), true))
		{
			throw new \InvalidArgumentException('The employee role is invalid.');
		}

		return $role;
	}

	/**
	 * Validate matching passwords and return only their hash.
	 *
	 * @param   mixed  $password
	 * @param   mixed  $confirmation
	 * @return  string
	 */
	protected function create_hash_password($password, $confirmation)
	{
		if ( ! is_string($password) or ! is_string($confirmation))
		{
			throw new \InvalidArgumentException('The password and confirmation must be strings.');
		}

		$length = strlen($password);

		if ($length < static::MIN_PASSWORD_BYTES or $length > static::MAX_PASSWORD_BYTES)
		{
			throw new \InvalidArgumentException('The password must be between 12 and 72 bytes.');
		}

		if ( ! hash_equals($password, $confirmation))
		{
			throw new \InvalidArgumentException('The password confirmation does not match.');
		}

		$hash = password_hash($password, PASSWORD_DEFAULT);

		if ($hash === false)
		{
			throw new \RuntimeException('Failed to hash the employee password.');
		}

		return $hash;
	}

	/**
	 * Require and lock a department during transactional writes.
	 *
	 * @param   int                       $department_id
	 * @param   Database_Connection|null  $db
	 * @return  void
	 */
	protected function assert_active_department($department_id, $db = null)
	{
		if ($db instanceof \Database_Connection)
		{
			if ($this->department_model->read_for_update($department_id, false, $db) !== null)
			{
				return;
			}
		}
		elseif ($this->department_model->is_active($department_id))
		{
			return;
		}

		throw new \InvalidArgumentException(
			'The selected department is not available.'
		);
	}
}
