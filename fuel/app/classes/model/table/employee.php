<?php

/**
 * Provides database operations for employees.
 *
 * @package  app
 */
class Model_Table_Employee extends Model_BaseCrud
{
	/**
	 * Table operated by this Model.
	 *
	 * @var string
	 */
	protected static $table_name = 'employees';

	/**
	 * Employee values accepted for a new row.
	 *
	 * @var array
	 */
	protected static $insert_columns = array(
		'employee_name',
		'department_id',
		'role',
		'password_hash',
		'is_active',
	);

	/**
	 * Find only the employee fields required by authentication.
	 *
	 * Inactive and archived rows are returned so Service_Auth can perform the
	 * password check before applying the common authentication result.
	 *
	 * @param   int                       $id
	 * @param   Database_Connection|null  $db
	 * @return  array|null
	 */
	public function find_for_authentication($id, $db = null)
	{
		$db = $this->connection($db);
		$result = \DB::select(
			'id',
			'employee_name',
			'role',
			'password_hash',
			'is_active',
			'deleted_at'
		)
			->from(static::$table_name)
			->where('id', '=', $id)
			->execute($db);

		if (count($result) === 0)
		{
			return null;
		}

		return array(
			'id' => (int) $result->get('id'),
			'employee_name' => $result->get('employee_name'),
			'role' => $result->get('role'),
			'password_hash' => $result->get('password_hash'),
			'is_active' => (int) $result->get('is_active'),
			'deleted_at' => $result->get('deleted_at'),
		);
	}

	/**
	 * Check whether an employee is active and not archived.
	 *
	 * @param   int                       $id
	 * @param   Database_Connection|null  $db
	 * @return  bool
	 */
	public function is_active($id, $db = null)
	{
		return $this->has_active_role($id, null, $db);
	}

	/**
	 * Check whether an employee is an active administrator.
	 *
	 * @param   int                       $id
	 * @param   Database_Connection|null  $db
	 * @return  bool
	 */
	public function is_active_admin($id, $db = null)
	{
		return $this->has_active_role($id, 'ADMIN', $db);
	}

	/**
	 * Apply the common active conditions and an optional role condition.
	 *
	 * @param   int                       $id
	 * @param   string|null               $role
	 * @param   Database_Connection|null  $db
	 * @return  bool
	 */
	protected function has_active_role($id, $role, $db)
	{
		$db = $this->connection($db);
		$query = \DB::select(
			array(\DB::expr('1'), 'employee_exists')
		)
			->from(static::$table_name)
			->where('id', '=', $id)
			->where('is_active', '=', 1)
			->where('deleted_at', 'IS', null);

		if ($role !== null)
		{
			$query->where('role', '=', $role);
		}

		$result = $query->execute($db);

		return (int) $result->get('employee_exists', 0) === 1;
	}
}
