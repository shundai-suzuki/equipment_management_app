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
		$result = \DB::select('id', 'department_id', 'name', 'deleted_at')
			->from(static::$table_name)
			->where('department_id', '=', $department_id)
			->where('name', '=', $name)
			->limit(1)
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

		$loaned = \DB::select(
			array(\DB::expr('COUNT(*)'), 'loaned_amount')
		)
			->from('loans')
			->where('equipment_id', '=', $id)
			->where('returned_at', 'IS', null)
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
