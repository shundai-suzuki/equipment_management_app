<?php

/**
 * Applies employee registration rules and delegates persistence.
 *
 * @package  app
 */
class Service_Table_Employee extends Service_BaseRegistration
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

		$this->department_model = $department_model ?: new Model_Table_Department();
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
		$password_hash = $this->hash_password($password, $password_confirmation);
		$this->assert_active_department($department_id);

		return $this->register(array(
			'employee_name' => $employee_name,
			'department_id' => $department_id,
			'role' => $role,
			'password_hash' => $password_hash,
			'is_active' => 1,
		));
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
	 * @param   array                $values
	 * @param   Database_Connection  $db
	 * @return  void
	 */
	protected function before_insert(array $values, \Database_Connection $db)
	{
		$this->assert_active_department($values['department_id'], $db);
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
	protected function hash_password($password, $confirmation)
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
