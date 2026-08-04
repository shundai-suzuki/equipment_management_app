<?php

/**
 * Provides database operations for equipment inventory.
 *
 * @package  app
 */
class Model_Table_Equipment extends Model_BaseCrud
{
	/**
	 * Table operated by this Model.
	 *
	 * @var string
	 */
	protected static $table_name = 'equipments';

	/**
	 * Equipment values accepted for a new row.
	 *
	 * @var array
	 */
	protected static $insert_columns = array(
		'name',
		'department_id',
		'category',
		'total_amount',
		'description',
	);

	/**
	 * Find equipment by its unique department and name pair.
	 *
	 * @param   int                       $department_id
	 * @param   string                    $name
	 * @param   Database_Connection|null  $db
	 * @return  array|null
	 */
	public function find_by_department_and_name($department_id, $name, $db = null)
	{
		$db = $this->connection($db);
		$id = $this->quoted_column('id', $db);
		$department = $this->quoted_column('department_id', $db);
		$name_column = $this->quoted_column('name', $db);
		$deleted_at = $this->quoted_column('deleted_at', $db);
		$result = \DB::query(
			'SELECT '.$id.', '.$department.', '.$name_column.', '.$deleted_at
			.' FROM '.$this->quoted_table($db)
			.' WHERE '.$department.' = :department_id'
			.' AND '.$name_column.' = :name LIMIT 1',
			\DB::SELECT
		)
			->parameters(array('department_id' => $department_id, 'name' => $name))
			->execute($db);

		if (count($result) === 0)
		{
			return null;
		}

		return array(
			'id' => (int) $result->get('id'),
			'department_id' => (int) $result->get('department_id'),
			'name' => $result->get('name'),
			'deleted_at' => $result->get('deleted_at'),
		);
	}

	/**
	 * Restore archived equipment without changing its ID.
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

	/**
	 * Lock active equipment and calculate its current available amount.
	 *
	 * @param   int                  $id
	 * @param   Database_Connection  $db
	 * @return  array|null
	 */
	public function lock_available($id, \Database_Connection $db)
	{
		$id_column = $this->quoted_column('id', $db);
		$total_amount = $this->quoted_column('total_amount', $db);
		$deleted_at = $this->quoted_column('deleted_at', $db);
		$result = \DB::query(
			'SELECT '.$id_column.', '.$total_amount
			.' FROM '.$this->quoted_table($db)
			.' WHERE '.$id_column.' = :id AND '.$deleted_at.' IS NULL FOR UPDATE',
			\DB::SELECT
		)
			->param('id', $id)
			->execute($db);

		if (count($result) === 0)
		{
			return null;
		}

		$loan_table = $db->quote_identifier($db->table_prefix('loans'));
		$equipment_id = $db->quote_identifier('equipment_id');
		$returned_at = $db->quote_identifier('returned_at');
		$loaned = \DB::query(
			'SELECT COUNT(*) AS loaned_amount FROM '.$loan_table
			.' WHERE '.$equipment_id.' = :equipment_id AND '.$returned_at.' IS NULL',
			\DB::SELECT
		)
			->param('equipment_id', $id)
			->execute($db);

		$total = (int) $result->get('total_amount');
		$loaned_amount = (int) $loaned->get('loaned_amount', 0);
		$available = $total - $loaned_amount;

		if ($available < 0)
		{
			throw new \RuntimeException('The equipment inventory is inconsistent.');
		}

		return array(
			'id' => (int) $result->get('id'),
			'total_amount' => $total,
			'loaned_amount' => $loaned_amount,
			'available_amount' => $available,
		);
	}
}
