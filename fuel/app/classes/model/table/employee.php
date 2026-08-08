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
	 * Employee create values accepted for a new row.
	 *
	 * @var array
	 */
	protected static $create_columns = array(
		'employee_name',
		'department_id',
		'role',
		'password_hash',
		'is_active',
	);

	/** 
	 * Safe employee columns returned by CRUD reads. 
	 * 
	 * @var array
	 */
	protected static $read_columns = array(
		'id',
		'employee_name',
		'department_id',
		'role',
		'is_active',
		'created_at',
		'updated_at',
		'deleted_at',
	);

	/** 
	 * Columns accepted by normal employee updates.
	 * 
	 * @var array
	 */
	protected static $update_columns = array(
		'employee_name',
		'department_id',
		'role',
	);

	/** Employee columns included in keyword searches. 
	 * 
	 * @var array
	*/
	protected static $search_columns = array('employee_name');

	/** 
	 * Exact-match employee search filters. 
	 * 
	 * @var array 
	 */
	protected static $filter_columns = array(
		'department_id' => 'department_id',
		'role' => 'role',
		'is_active' => 'is_active',
	);

	/** 
	 * Employee columns returned as integers. 
	 * 
	 * @var array 
	 */
	protected static $integer_columns = array('id', 'department_id', 'is_active');

	/**
	 * Read only the employee fields required by authentication.
	 *
	 * Inactive and archived rows are returned so Service_Auth can perform the
	 * password check before applying the common authentication result.
	 *
	 * @param   int                       $id
	 * @param   Database_Connection|null  $db
	 * @return  array|null
	 */
	public function read_for_authentication($id, $db = null)
	{
		$db = $this->connection($db);
		$read_result = \DB::select(
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

		if (count($read_result) === 0)
		{
			return null;
		}

		return array(
			'id' => (int) $read_result->get('id'),
			'employee_name' => $read_result->get('employee_name'),
			'role' => $read_result->get('role'),
			'password_hash' => $read_result->get('password_hash'),
			'is_active' => (int) $read_result->get('is_active'),
			'deleted_at' => $read_result->get('deleted_at'),
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
	 * Lock all active administrators and return their count.
	 *
	 * @param   Database_Connection  $db
	 * @return  int
	 */
	public function lock_active_admin_count(\Database_Connection $db)
	{
		$id = $this->quoted_column('id', $db);
		$role = $this->quoted_column('role', $db);
		$is_active = $this->quoted_column('is_active', $db);
		$deleted_at = $this->quoted_column('deleted_at', $db);
		$read_admin_rows = \DB::query(
			'SELECT '.$id.' FROM '.$this->quoted_table($db)
			.' WHERE '.$role.' = :role'
			.' AND '.$is_active.' = 1'
			.' AND '.$deleted_at.' IS NULL FOR UPDATE',
			\DB::SELECT
		)
			->param('role', 'ADMIN')
			->execute($db);

		return count($read_admin_rows);
	}

	/**
	 * Check whether an employee has any lending history.
	 *
	 * @param   int                       $id
	 * @param   Database_Connection|null  $db
	 * @return  bool
	 */
	public function has_loan_history($id, $db = null)
	{
		$db = $this->connection($db);
		$read_result = \DB::select(
			array(\DB::expr('1'), 'loan_exists')
		)
			->from('loans')
			->where('employee_id', '=', $id)
			->limit(1)
			->execute($db);

		return (int) $read_result->get('loan_exists', 0) === 1;
	}

	/**
	 * Check whether an employee currently has unreturned equipment.
	 *
	 * @param   int                       $id
	 * @param   Database_Connection|null  $db
	 * @return  bool
	 */
	public function has_active_loans($id, $db = null)
	{
		$db = $this->connection($db);
		$read_result = \DB::select(
			array(\DB::expr('1'), 'active_loan_exists')
		)
			->from('loans')
			->where('employee_id', '=', $id)
			->where('returned_at', 'IS', null)
			->limit(1)
			->execute($db);

		return (int) $read_result->get('active_loan_exists', 0) === 1;
	}

	/**
	 * Soft-delete an employee and make the account inactive atomically.
	 *
	 * @param   int                       $id
	 * @param   Database_Connection|null  $db
	 * @return  int
	 */
	public function soft_delete($id, $db = null)
	{
		$db = $this->connection($db);

		return (int) \DB::update(static::$table_name)
			->set(array(
				'is_active' => 0,
				'deleted_at' => \DB::expr('CURRENT_TIMESTAMP'),
				'updated_at' => \DB::expr('CURRENT_TIMESTAMP'),
			))
			->where('id', '=', $id)
			->where('deleted_at', 'IS', null)
			->execute($db);
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
		$read_query = \DB::select(
			array(\DB::expr('1'), 'employee_exists')
		)
			->from(static::$table_name)
			->where('id', '=', $id)
			->where('is_active', '=', 1)
			->where('deleted_at', 'IS', null);

		if ($role !== null)
		{
			$read_query->where('role', '=', $role);
		}

		$read_result = $read_query->execute($db);

		return (int) $read_result->get('employee_exists', 0) === 1;
	}
}
