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
		$id = $this->quoted_column('id', $db);
		$name_column = $this->quoted_column('name', $db);
		$deleted_at = $this->quoted_column('deleted_at', $db);
		$result = \DB::query(
			'SELECT '.$id.', '.$name_column.', '.$deleted_at
			.' FROM '.$this->quoted_table($db)
			.' WHERE '.$name_column.' = :name LIMIT 1',
			\DB::SELECT
		)
			->param('name', $name)
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
		$id_column = $this->quoted_column('id', $db);
		$deleted_at = $this->quoted_column('deleted_at', $db);
		$result = \DB::query(
			'SELECT 1 AS is_active FROM '.$this->quoted_table($db)
			.' WHERE '.$id_column.' = :id AND '.$deleted_at.' IS NULL',
			\DB::SELECT
		)
			->param('id', $id)
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
		$id_column = $this->quoted_column('id', $db);
		$updated_at = $this->quoted_column('updated_at', $db);
		$deleted_at = $this->quoted_column('deleted_at', $db);
		$result = \DB::query(
			'UPDATE '.$this->quoted_table($db)
			.' SET '.$deleted_at.' = NULL, '.$updated_at.' = CURRENT_TIMESTAMP'
			.' WHERE '.$id_column.' = :id AND '.$deleted_at.' IS NOT NULL',
			\DB::UPDATE
		)
			->param('id', $id)
			->execute($db);

		return (int) $result === 1;
	}
}
