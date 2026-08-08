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
				$employee_before_update = $this->model->read_before_update($id, false, $db);

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

				if ($employee_before_update['role'] === 'ADMIN'
					and $role !== 'ADMIN'
					and $this->model->lock_active_admin_count($db) <= 1)
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
	 * @param   string  $reason
	 * @return  array
	 */
	public function soft_delete_for_admin($actor_id, $id, $reason)
	{
		$this->assert_positive_id($id, 'The employee ID');
		$this->assert_soft_delete_reason($reason);

		return $this->model->transaction(
			function ($db) use ($actor_id, $id)
			{
				$this->assert_admin_actor($actor_id, $db);
				$employee_before_delete = $this->model->read_before_update($id, false, $db);

				if ($employee_before_delete === null)
				{
					throw new RuntimeException(
						'The employee was not found.',
						static::NOT_FOUND_EXCEPTION_CODE
					);
				}

				if ($employee_before_delete['role'] === 'ADMIN'
					and $this->model->lock_active_admin_count($db) <= 1)
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
	 * Require a department that is not archived.
	 *
	 * @param   int                       $department_id
	 * @param   Database_Connection|null  $db
	 * @return  void
	 */
	protected function assert_active_department($department_id, $db = null)
	{
		if ( ! $this->department_model->is_active($department_id, $db))
		{
			throw new \InvalidArgumentException('The selected department is not available.');
		}
	}
}
