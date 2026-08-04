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
		$id_column = $this->quoted_column('id', $db);
		$role_column = $this->quoted_column('role', $db);
		$is_active = $this->quoted_column('is_active', $db);
		$deleted_at = $this->quoted_column('deleted_at', $db);
		$sql = 'SELECT 1 AS employee_exists FROM '.$this->quoted_table($db)
			.' WHERE '.$id_column.' = :id'
			.' AND '.$is_active.' = 1 AND '.$deleted_at.' IS NULL';
		$parameters = array('id' => $id);

		if ($role !== null)
		{
			$sql .= ' AND '.$role_column.' = :role';
			$parameters['role'] = $role;
		}

		$result = \DB::query($sql, \DB::SELECT)
			->parameters($parameters)
			->execute($db);

		return (int) $result->get('employee_exists', 0) === 1;
	}
}
