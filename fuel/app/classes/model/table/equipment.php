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
	 * Equipment create values accepted for a new row.
	 *
	 * @var array
	 */
	protected static $create_columns = array(
		'name',
		'department_id',
		'category',
		'total_amount',
		'description',
	);

	/**
	 * Equipment columns returned by CRUD reads. 
	 * 
	 * @var array 
	 */
	protected static $read_columns = array(
		'id',
		'name',
		'department_id',
		'category',
		'total_amount',
		'description',
		'created_at',
		'updated_at',
		'deleted_at',
	);

	/** 
	 * Columns accepted by equipment updates. 
	 * 
	 * @var array
	 */
	protected static $update_columns = array(
		'name',
		'department_id',
		'category',
		'total_amount',
		'description',
	);

	/** 
	 * Equipment columns included in keyword searches. 
	 * 
	 * @var array 
	 */
	protected static $search_columns = array('name');

	/**
	 * Exact-match equipment search filters. 
	 * 
	 * @var array
	 */
	protected static $filter_columns = array(
		'department_id' => 'department_id',
		'category' => 'category',
	);

	/** 
	 * Equipment columns returned as integers. 
	 * 
	 * @var array
	 */
	protected static $integer_columns = array(
		'id',
		'department_id',
		'total_amount',
		'loaned_amount',
		'available_amount',
	);

	/**
	 * Read equipment by its unique department and name pair.
	 *
	 * @param   int                       $department_id
	 * @param   string                    $name
	 * @param   Database_Connection|null  $db
	 * @return  array|null
	 */
	public function read_by_department_and_name($department_id, $name, $db = null)
	{
		$db = $this->connection($db);
		$read_result = \DB::select('id', 'department_id', 'name', 'deleted_at')
			->from(static::$table_name)
			->where('department_id', '=', $department_id)
			->where('name', '=', $name)
			->execute($db);

		if (count($read_result) === 0)
		{
			return null;
		}

		return array(
			'id' => (int) $read_result->get('id'),
			'department_id' => (int) $read_result->get('department_id'),
			'name' => $read_result->get('name'),
			'deleted_at' => $read_result->get('deleted_at'),
		);
	}

	/**
	 * Return active equipment categories for the list filter.
	 *
	 * @return  array
	 */
	public function read_category_options()
	{
		$read_rows = \DB::select('category')
			->distinct()
			->from(static::$table_name)
			->where('deleted_at', 'IS', null)
			->order_by('category', 'ASC')
			->execute($this->db)
			->as_array();
		return array_column($read_rows, 'category');
	}

	/**
	 * Search equipment with calculated lending quantities.
	 *
	 * @param   int     $page
	 * @param   int     $per_page
	 * @param   string  $keyword
	 * @param   array   $filters
	 * @return  array
	 */
	public function search($page, $per_page, $keyword = '', array $filters = array())
	{
		$search_offset = ($page - 1) * $per_page;
		$search_count_query = $this->equipment_query(
			array(array(\DB::expr('COUNT(*)'), 'total'))
		);
		$this->apply_equipment_conditions(
			$search_count_query,
			$keyword,
			$filters
		);
		$search_total = (int) $search_count_query
			->execute($this->db)
			->get('total', 0);

		$search_query = $this->equipment_query(
			$this->equipment_select_columns()
		);
		$this->apply_equipment_conditions($search_query, $keyword, $filters);
		$search_rows = $search_query
			->order_by('equipments.updated_at', 'DESC')
			->order_by('equipments.id', 'DESC')
			->limit($per_page)
			->offset($search_offset)
			->execute($this->db)
			->as_array();

		return array(
			'rows' => $this->format_rows($search_rows),
			'total' => $search_total,
		);
	}

	/**
	 * Build the fixed active-loan aggregate join.
	 *
	 * @param   array  $columns
	 * @return  Database_Query_Builder_Select
	 */
	protected function equipment_query(array $columns)
	{
		$active_loans = \DB::select(
			'equipment_id',
			array(\DB::expr('COUNT(*)'), 'loaned_amount')
		)
			->from('loans')
			->where('returned_at', 'IS', null)
			->group_by('equipment_id');

		return \DB::select_array($columns)
			->from('equipments')
			->join(array($active_loans, 'active_loans'), 'LEFT')
			->on('equipments.id', '=', 'active_loans.equipment_id');
	}

	/**
	 * Return fixed equipment columns and calculated quantities.
	 *
	 * @return  array
	 */
	protected function equipment_select_columns()
	{
		$loaned_amount = 'COALESCE(active_loans.loaned_amount, 0)';

		return array(
			array('equipments.id', 'id'),
			array('equipments.name', 'name'),
			array('equipments.department_id', 'department_id'),
			array('equipments.category', 'category'),
			array('equipments.total_amount', 'total_amount'),
			array(\DB::expr($loaned_amount), 'loaned_amount'),
			array(
				\DB::expr('equipments.total_amount - '.$loaned_amount),
				'available_amount'
			),
			array('equipments.description', 'description'),
			array('equipments.created_at', 'created_at'),
			array('equipments.updated_at', 'updated_at'),
			array('equipments.deleted_at', 'deleted_at'),
		);
	}

	/**
	 * Apply active-row, keyword and exact equipment filters.
	 *
	 * @param   Database_Query_Builder_Select  $query
	 * @param   string                         $keyword
	 * @param   array                          $filters
	 * @return  void
	 */
	protected function apply_equipment_conditions($query, $keyword, array $filters)
	{
		$allowed_filters = array('department_id', 'category', 'available_only');

		if (array_diff(array_keys($filters), $allowed_filters))
		{
			throw new \InvalidArgumentException(
				'The equipment search filter is not allowed.'
			);
		}

		$query->where('equipments.deleted_at', 'IS', null);

		if ($keyword !== '')
		{
			$query->and_where_open();

			if (preg_match('/\A[1-9][0-9]*\z/', $keyword) === 1)
			{
				$query->where('equipments.id', '=', (int) $keyword)
					->or_where('equipments.name', 'LIKE', '%'.$keyword.'%');
			}
			else
			{
				$query->where('equipments.name', 'LIKE', '%'.$keyword.'%');
			}

			$query->and_where_close();
		}

		if (isset($filters['department_id']))
		{
			$query->where(
				'equipments.department_id',
				'=',
				$filters['department_id']
			);
		}

		if (isset($filters['category']))
		{
			$query->where('equipments.category', '=', $filters['category']);
		}

		if (isset($filters['available_only']))
		{
			if ( ! is_bool($filters['available_only']))
			{
				throw new \InvalidArgumentException(
					'available_only must be a boolean.'
				);
			}

			if ($filters['available_only'])
			{
				$query->where(
					\DB::expr(
						'equipments.total_amount'
						.' - COALESCE(active_loans.loaned_amount, 0)'
					),
					'>',
					0
				);
			}
		}
	}

	/**
	 * Count unreturned loans for an equipment row.
	 *
	 * @param   int                       $id
	 * @param   Database_Connection|null  $db
	 * @return  int
	 */
	public function count_active_loans($id, $db = null)
	{
		$db = $this->connection($db);
		$read_count_result = \DB::select(array(\DB::expr('COUNT(*)'), 'total'))
			->from('loans')
			->where('equipment_id', '=', $id)
			->where('returned_at', 'IS', null)
			->execute($db);

		return (int) $read_count_result->get('total', 0);
	}

	/**
	 * Check whether an equipment row has lending history.
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
			->where('equipment_id', '=', $id)
			->limit(1)
			->execute($db);

		return (int) $read_result->get('loan_exists', 0) === 1;
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

		$read_equipment_result = \DB::query(
			'SELECT '.$id_column.', '.$total_amount
			.' FROM '.$this->quoted_table($db)
			.' WHERE '.$id_column.' = :id AND '.$deleted_at.' IS NULL FOR UPDATE',
			\DB::SELECT
		)
			->param('id', $id)
			->execute($db);

		if (count($read_equipment_result) === 0)
		{
			return null;
		}

		$read_loan_count = \DB::select(
			array(\DB::expr('COUNT(*)'), 'loaned_amount')
		)
			->from('loans')
			->where('equipment_id', '=', $id)
			->where('returned_at', 'IS', null)
			->execute($db);

		$total = (int) $read_equipment_result->get('total_amount');
		$loaned_amount = (int) $read_loan_count->get('loaned_amount', 0);
		$available = $total - $loaned_amount;

		if ($available < 0)
		{
			throw new \RuntimeException('The equipment inventory is inconsistent.');
		}

		return array(
			'id' => (int) $read_equipment_result->get('id'),
			'total_amount' => $total,
			'loaned_amount' => $loaned_amount,
			'available_amount' => $available,
		);
	}

	/**
	 * Lock the equipment row required by a return operation.
	 *
	 * @param   int                  $id
	 * @param   Database_Connection  $db
	 * @return  bool
	 */
	public function lock_for_return($id, \Database_Connection $db)
	{
		$id_column = $this->quoted_column('id', $db);
		$read_result = \DB::query(
			'SELECT '.$id_column
			.' FROM '.$this->quoted_table($db)
			.' WHERE '.$id_column.' = :id FOR UPDATE',
			\DB::SELECT
		)
			->param('id', $id)
			->execute($db);

		return count($read_result) === 1;
	}
}
