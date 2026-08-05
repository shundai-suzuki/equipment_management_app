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
