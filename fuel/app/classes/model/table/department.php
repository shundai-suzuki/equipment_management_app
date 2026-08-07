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
	 * Department values accepted for a new row.
	 *
	 * @var array
	 */
	protected static $insert_columns = array('name');

	/**
	 * Find a department by name, including archived rows.
	 *
	 * @param   string                    $name
	 * @param   Database_Connection|null  $db
	 * @return  array|null
	 */
	public function find_by_name($name, $db = null)
	{
		$db = $this->connection($db);
		$result = \DB::select('id', 'name', 'deleted_at')
			->from(static::$table_name)
			->where('name', '=', $name)
			->limit(1)
			->execute($db);

		if (count($result) === 0)
		{
			return null;
		}

		return array(
			'id' => (int) $result->get('id'),
			'name' => $result->get('name'),
			'deleted_at' => $result->get('deleted_at'),
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
		$result = \DB::select(
			array(\DB::expr('1'), 'is_active')
			)
			->from(static::$table_name)
			->where('id', '=', $id)
			->where('deleted_at', 'IS', null)
			->execute($db);

		return (int) $result->get('is_active', 0) === 1;
	}

	/**
	 * Restore an archived department without changing its ID.
	 *
	 * @param   int                       $id
	 * @param   Database_Connection|null  $db
	 * @return  bool
	 */
	public function restore($id, $db = null)
	{
		$db = $this->connection($db);
		$result = \DB::update(static::$table_name)
			->set(
				array(
					'deleted_at' => null,
					'updated_at' => \DB::expr('CURRENT_TIMESTAMP'),
				)
		)
			->where('id', '=', $id)
			->where('deleted_at', 'IS NOT', null)
			->execute($db);

		return (int) $result === 1;
	}
}
