<?php

/**
 * Provides database operations for departments.
 *
 * @package  app
 */
class Model_Table_Department extends Model_BaseCrud
{
	/**
	 * Table operated by this Model.
	 *
	 * @var string
	 */
	protected static $table_name = 'departments';

	/**
	 * Department create values accepted for a new row.
	 *
	 * @var array
	 */
	protected static $create_columns = array('name');

	/**
	 * Columns returned by department CRUD reads. 
	 * 
	 * @var array 
	 */
	protected static $read_columns = array(
		'id',
		'name',
		'created_at',
		'updated_at',
		'deleted_at',
	);

	/** 
	 * Columns accepted by department updates.
	 * 
	 * @var array
	 */
	protected static $update_columns = array('name');

	/** 
	 * Department columns included in keyword searches.
	 * 
	 * @var array
	 */
	protected static $search_columns = array('name');

	/**
	 * Read a department by name, including soft-deleted rows.
	 *
	 * @param   string                    $name
	 * @param   Database_Connection|null  $db
	 * @return  array|null
	 */
	public function read_by_name($name, $db = null)
	{
		$db = $this->connection($db);
		$read_result = \DB::select('id', 'name', 'deleted_at')
			->from(static::$table_name)
			->where('name', '=', $name)
			->execute($db);

		if (count($read_result) === 0)
		{
			return null;
		}

		return array(
			'id' => (int) $read_result->get('id'),
			'name' => $read_result->get('name'),
			'deleted_at' => $read_result->get('deleted_at'),
		);
	}

	/**
	 * Check whether a department can be used by a new record.
	 *
	 * @param   int                       $id
	 * @param   Database_Connection|null  $db
	 * @return  bool
	 */
	public function is_active($id, $db = null)
	{
		$db = $this->connection($db);
		$read_result = \DB::select(
			array(\DB::expr('1'), 'is_active')
			)
			->from(static::$table_name)
			->where('id', '=', $id)
			->where('deleted_at', 'IS', null)
			->execute($db);

		return (int) $read_result->get('is_active', 0) === 1;
	}

	/**
	 * Check whether active employees or equipment still use a department.
	 *
	 * @param   int                       $id
	 * @param   Database_Connection|null  $db
	 * @return  bool
	 */
	public function has_active_references($id, $db = null)
	{
		$db = $this->connection($db);
		$employee_result = \DB::select(
			array(\DB::expr('1'), 'employee_exists')
		)
			->from('employees')
			->where('department_id', '=', $id)
			->where('deleted_at', 'IS', null)
			->limit(1)
			->execute($db);

		if ((int) $employee_result->get('employee_exists', 0) === 1)
		{
			return true;
		}

		$equipment_result = \DB::select(
			array(\DB::expr('1'), 'equipment_exists')
		)
			->from('equipments')
			->where('department_id', '=', $id)
			->where('deleted_at', 'IS', null)
			->limit(1)
			->execute($db);

		return (int) $equipment_result->get('equipment_exists', 0) === 1;
	}
}
