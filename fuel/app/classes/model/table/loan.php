<?php

/**
 * Provides database operations for loans and returns.
 *
 * @package  app
 */
class Model_Table_Loan extends Model_BaseCrud
{
	/**
	 * Table operated by this Model.
	 *
	 * @var string
	 */
	protected static $table_name = 'loans';

	/**
	 * Columns inserted for a new loan.
	 *
	 * @var array
	 */
	protected static $create_columns = array(
		'employee_id',
		'equipment_id',
		'due_date',
		'loaned_at',
		'loaned_by',
		'returned_at',
		'returned_by',
		'note',
	);

	/** 
	 * Loan columns safe to return or lock. 
	 * 
	 * @var array 
	 */
	protected static $read_columns = array(
		'id',
		'employee_id',
		'equipment_id',
		'due_date',
		'loaned_at',
		'loaned_by',
		'returned_at',
		'returned_by',
		'note',
		'created_at',
		'updated_at',
	);

	/** 
	 * Database integer columns. 
	 * 
	 * @var array 
	 */
	protected static $integer_columns = array(
		'id',
		'employee_id',
		'equipment_id',
		'loaned_by',
		'returned_by',
	);

	/**
	 * Search loans without applying the common deleted_at condition.
	 *
	 * @param   int       $page
	 * @param   int       $per_page
	 * @param   int|null  $employee_id
	 * @param   string    $keyword
	 * @param   array     $filters
	 * @param   string    $today
	 * @return  array
	 */
	public function search_loans($page, $per_page, $employee_id, $keyword, array $filters, $today)
	{
		$search_offset = ($page - 1) * $per_page;
		$search_count_query = $this->loan_query(
			array(array(\DB::expr('COUNT(*)'), 'total'))
		);
		$this->apply_loan_conditions(
			$search_count_query,
			$employee_id,
			$keyword,
			$filters,
			$today
		);
		$search_total = (int) $search_count_query
			->execute($this->db)
			->get('total', 0);

		$search_query = $this->loan_query($this->loan_select_columns());
		$this->apply_loan_conditions(
			$search_query,
			$employee_id,
			$keyword,
			$filters,
			$today
		);
		$search_rows = $search_query
			->order_by('loans.loaned_at', 'DESC')
			->order_by('loans.id', 'DESC')
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
	 * Read one loan including borrower and equipment names.
	 *
	 * @param   int                       $id
	 * @param   Database_Connection|null  $db
	 * @return  array|null
	 */
	public function read_loan($id, $db = null)
	{
		$db = $this->connection($db);
		$read_rows = $this->loan_query($this->loan_select_columns())
			->where('loans.id', '=', $id)
			->execute($db)
			->as_array();

		return empty($read_rows) ? null : $this->format_row($read_rows[0]);
	}

	/**
	 * Lock one loan before changing it to returned.
	 *
	 * @param   int                  $id
	 * @param   Database_Connection  $db
	 * @return  array|null
	 */
	public function lock_for_return($id, \Database_Connection $db)
	{
		$columns = array();

		foreach (static::$read_columns as $column)
		{
			$columns[] = $this->quoted_column($column, $db);
		}

		$read_rows = \DB::query(
			'SELECT '.implode(', ', $columns)
			.' FROM '.$this->quoted_table($db)
			.' WHERE '.$this->quoted_column('id', $db).' = :id FOR UPDATE',
			\DB::SELECT
		)
			->param('id', $id)
			->execute($db)
			->as_array();

		return empty($read_rows) ? null : $this->format_row($read_rows[0]);
	}

	/**
	 * Set the return fields only while the loan is still active.
	 *
	 * @param   int                  $id
	 * @param   string               $returned_at
	 * @param   int                  $returned_by
	 * @param   string|null          $note
	 * @param   Database_Connection  $db
	 * @return  int
	 */
	public function mark_returned($id, $returned_at, $returned_by, $note, \Database_Connection $db)
	{
		return (int) \DB::update(static::$table_name)
			->set(array(
				'returned_at' => $returned_at,
				'returned_by' => $returned_by,
				'note' => $note,
				'updated_at' => \DB::expr('CURRENT_TIMESTAMP'),
			))
			->where('id', '=', $id)
			->where('returned_at', 'IS', null)
			->execute($db);
	}

	/**
	 * Build the fixed joins shared by loan reads.
	 *
	 * @param   array  $columns
	 * @return  Database_Query_Builder_Select
	 */
	protected function loan_query(array $columns)
	{
		return \DB::select_array($columns)
			->from('loans')
			->join('employees', 'INNER')
			->on('loans.employee_id', '=', 'employees.id')
			->join('equipments', 'INNER')
			->on('loans.equipment_id', '=', 'equipments.id');
	}

	/**
	 * Return the fixed columns exposed by the loan API.
	 *
	 * @return  array
	 */
	protected function loan_select_columns()
	{
		return array(
			array('loans.id', 'id'),
			array('loans.employee_id', 'employee_id'),
			array('employees.employee_name', 'employee_name'),
			array('loans.equipment_id', 'equipment_id'),
			array('equipments.name', 'equipment_name'),
			array('loans.due_date', 'due_date'),
			array('loans.loaned_at', 'loaned_at'),
			array('loans.loaned_by', 'loaned_by'),
			array('loans.returned_at', 'returned_at'),
			array('loans.returned_by', 'returned_by'),
			array('loans.note', 'note'),
			array('loans.created_at', 'created_at'),
			array('loans.updated_at', 'updated_at'),
		);
	}

	/**
	 * Apply ownership, keyword and state filters to a loan query.
	 *
	 * @param   Database_Query_Builder_Select  $query
	 * @param   int|null                       $employee_id
	 * @param   string                         $keyword
	 * @param   array                          $filters
	 * @param   string                         $today
	 * @return  void
	 */
	protected function apply_loan_conditions($query, $employee_id, $keyword, array $filters, $today)
	{
		if ($employee_id !== null)
		{
			$query->where('loans.employee_id', '=', $employee_id);
		}

		if ($keyword !== '')
		{
			$query->where('equipments.name', 'LIKE', '%'.$keyword.'%');
		}

		if (isset($filters['loan_id']))
		{
			$query->where('loans.id', '=', $filters['loan_id']);
		}

		if (isset($filters['equipment_id']))
		{
			$query->where('loans.equipment_id', '=', $filters['equipment_id']);
		}

		if (isset($filters['active_only']) and $filters['active_only'])
		{
			$query->where('loans.returned_at', 'IS', null);
		}

		if (isset($filters['loan_state']))
		{
			switch ($filters['loan_state'])
			{
				case 'ON_LOAN':
					$query->where('loans.returned_at', 'IS', null)
						->where('loans.due_date', '>=', $today);
					break;
				case 'OVERDUE':
					$query->where('loans.returned_at', 'IS', null)
						->where('loans.due_date', '<', $today);
					break;
				case 'RETURNED':
					$query->where('loans.returned_at', 'IS NOT', null);
					break;
			}
		}
	}
}
