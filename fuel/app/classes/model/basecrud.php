<?php

/**
 * Provides the database operations shared by table Models.
 *
 * @package  app
 */
abstract class Model_BaseCrud extends \Model
{
	/**
	 * Physical table name declared by the child Model.
	 *
	 * @var string
	 */
	protected static $table_name = '';

	/**
	 * Columns accepted when the child Model creates a row.
	 *
	 * @var array
	 */
	protected static $create_columns = array();

	/**
	 * Columns returned by common read operations.
	 *
	 * @var array
	 */
	protected static $read_columns = array();

	/**
	 * Columns accepted by the common update operation.
	 *
	 * @var array
	 */
	protected static $update_columns = array();

	/**
	 * Text columns included in keyword searches.
	 *
	 * @var array
	 */
	protected static $search_columns = array();

	/**
	 * Request filter names mapped to fixed database columns.
	 *
	 * @var array
	 */
	protected static $filter_columns = array();

	/**
	 * Read columns converted from database strings to integers.
	 *
	 * @var array
	 */
	protected static $integer_columns = array('id');

	/**
	 * Default database connection for reads and standalone updates.
	 *
	 * @var Database_Connection
	 */
	protected $db;

	/**
	 * @param  Database_Connection|string|null  $db
	 */
	public function __construct($db = null)
	{
		$this->db = $db instanceof \Database_Connection
			? $db
			: \Database_Connection::instance($db);
	}

	/**
	 * Create a row with an allocated ID on the supplied transaction connection.
	 *
	 * @param   int                  $id
	 * @param   array                $create_values
	 * @param   Database_Connection  $db
	 * @return  bool
	 */
	public function create($id, array $create_values, \Database_Connection $db)
	{
		if ( ! is_int($id) or $id < 1)
		{
			throw new \InvalidArgumentException('The allocated ID must be a positive integer.');
		}

		$this->assert_identifier(static::$table_name);
		$this->assert_values(
			$create_values,
			static::$create_columns,
			'create'
		);

		$create_values_with_id = array('id' => $id);

		foreach (static::$create_columns as $column)
		{
			$create_values_with_id[$column] = $create_values[$column];
		}

		$create_values_with_id['created_at'] = \DB::expr('CURRENT_TIMESTAMP');
		$create_values_with_id['updated_at'] = \DB::expr('CURRENT_TIMESTAMP');

		$create_result = \DB::insert(static::$table_name)
			->set($create_values_with_id)
			->execute($db);

		return isset($create_result[1]) and (int) $create_result[1] === 1;
	}

	/**
	 * Read one row using only the child Model's allowed read columns.
	 *
	 * @param   int                       $id
	 * @param   bool                      $include_soft_deleted
	 * @param   Database_Connection|null  $db
	 * @return  array|null
	 */
	public function read($id, $include_soft_deleted = false, $db = null)
	{
		$db = $this->connection($db);
		$this->assert_crud_configuration();

		$read_query = \DB::select_array(static::$read_columns)
			->from(static::$table_name)
			->where('id', '=', $id);

		if ( ! $include_soft_deleted)
		{
			$read_query->where('deleted_at', 'IS', null);
		}

		$read_rows = $read_query->execute($db)
			->as_array();

		return empty($read_rows) ? null : $this->format_row($read_rows[0]);
	}

	/**
	 * Search active rows with fixed filters and pagination.
	 *
	 * @param   int     $page
	 * @param   int     $per_page
	 * @param   string  $keyword
	 * @param   array   $filters
	 * @return  array
	 */
	public function search($page, $per_page, $keyword = '', array $filters = array())
	{
		$this->assert_crud_configuration();
		$search_offset = ($page - 1) * $per_page;

		$search_count_query = \DB::select(
			array(\DB::expr('COUNT(*)'), 'total')
		)
			->from(static::$table_name);
		$this->apply_search_conditions($search_count_query, $keyword, $filters);
		$search_count_result = $search_count_query->execute($this->db);
		$search_total = (int) $search_count_result->get('total', 0);

		$search_query = \DB::select_array(static::$read_columns)
			->from(static::$table_name);
		$this->apply_search_conditions($search_query, $keyword, $filters);
		$search_rows = $search_query
			->order_by('updated_at', 'DESC')
			->order_by('id', 'DESC')
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
	 * Update exactly the columns allowed by the child Model.
	 *
	 * @param   int                       $id
	 * @param   array                     $update_values
	 * @param   Database_Connection|null  $db
	 * @return  int
	 */
	public function update($id, array $update_values, $db = null)
	{
		$db = $this->connection($db);
		$this->assert_crud_configuration();
		$this->assert_values(
			$update_values,
			static::$update_columns,
			'update'
		);
		$update_values['updated_at'] = \DB::expr('CURRENT_TIMESTAMP');

		return (int) \DB::update(static::$table_name)
			->set($update_values)
			->where('id', '=', $id)
			->where('deleted_at', 'IS', null)
			->execute($db);
	}

	/**
	 * Soft-delete one active row.
	 *
	 * @param   int                       $id
	 * @param   Database_Connection|null  $db
	 * @return  int
	 */
	public function soft_delete($id, $db = null)
	{
		$db = $this->connection($db);
		$this->assert_crud_configuration();

		return (int) \DB::update(static::$table_name)
			->set(array(
				'deleted_at' => \DB::expr('CURRENT_TIMESTAMP'),
				'updated_at' => \DB::expr('CURRENT_TIMESTAMP'),
			))
			->where('id', '=', $id)
			->where('deleted_at', 'IS', null)
			->execute($db);
	}

	/**
	 * Restore one archived row.
	 *
	 * @param   int                       $id
	 * @param   Database_Connection|null  $db
	 * @return  int
	 */
	public function restore($id, $db = null)
	{
		$db = $this->connection($db);
		$this->assert_crud_configuration();

		return (int) \DB::update(static::$table_name)
			->set(array(
				'deleted_at' => null,
				'updated_at' => \DB::expr('CURRENT_TIMESTAMP'),
			))
			->where('id', '=', $id)
			->where('deleted_at', 'IS NOT', null)
			->execute($db);
	}

	/**
	 * Read one row before a table-specific business update.
	 *
	 * @param   int                  $id
	 * @param   bool                 $include_soft_deleted
	 * @param   Database_Connection  $db
	 * @return  array|null
	 */
	public function read_for_update($id, $include_soft_deleted, \Database_Connection $db)
	{
		$this->assert_crud_configuration();
		$read_query = 
			'SELECT '.implode(', ', $columns)
			.' FROM '.$this->quoted_table($db)
			.' WHERE '.$this->quoted_column('id', $db).' = :id';

		if ( ! $include_soft_deleted)
		{
			$sql .= ' AND '.$this->quoted_column('deleted_at', $db).' IS NULL';
		}

		$read_rows = \DB::query($sql.' FOR UPDATE', \DB::SELECT)
			->param('id', $id)
			->execute($db)
			->as_array();

		return empty($read_rows) ? null : $this->format_row($read_rows[0]);
	}

	/**
	 * Run a table-specific business update in one transaction.
	 *
	 * @param   Closure  $operation
	 * @return  mixed
	 */
	public function transaction(\Closure $operation)
	{
		if ( ! $this->db->start_transaction())
		{
			throw new \RuntimeException('Failed to start the CRUD transaction.');
		}

		try
		{
			$operation_result = $operation($this->db);

			if ( ! $this->db->commit_transaction())
			{
				throw new \RuntimeException('Failed to commit the CRUD transaction.');
			}

			return $operation_result;
		}
		catch (\Throwable $exception)
		{
			if ($this->db->in_transaction()
				and ! $this->db->rollback_transaction())
			{
				throw new \RuntimeException(
					'Failed to rollback the CRUD transaction.',
					0,
					$exception
				);
			}

			throw $exception;
		}
	}

	/**
	 * Apply 1.active-row, 2.search ID & name by keyword, 3.exact-match (Department, permissions, category).
	 *
	 * @param   Database_Query_Builder_Where  $search_query
	 * @param   string                        $keyword
	 * @param   array                         $filters
	 * @return  void
	 */
	protected function apply_search_conditions($search_query, $keyword, array $filters)
	{
		$search_query->where('deleted_at', 'IS', null);

		if ($keyword !== '')
		{
			$search_query->and_where_open();
			$has_condition = false;

			if (preg_match('/\A[1-9][0-9]*\z/', $keyword) === 1)
			{
				$search_query->where('id', '=', (int) $keyword);
				$has_condition = true;
			}

			foreach (static::$search_columns as $column)
			{
				$this->assert_identifier($column);

				if ($has_condition)
				{
					$search_query->or_where($column, 'LIKE', '%'.$keyword.'%');
				}
				else
				{
					$search_query->where($column, 'LIKE', '%'.$keyword.'%');
					$has_condition = true;
				}
			}

			$search_query->and_where_close();
		}

		foreach ($filters as $name => $value)
		{
			if ( ! array_key_exists($name, static::$filter_columns))
			{
				throw new \InvalidArgumentException('The search filter is not allowed.');
			}

			$column = static::$filter_columns[$name];
			$this->assert_identifier($column);
			$search_query->where($column, '=', $value);
		}
	}

	/**
	 * Convert database scalar types for a list of rows.
	 *
	 * @param   array  $read_rows
	 * @return  array
	 */
	protected function format_rows(array $read_rows)
	{
		foreach ($read_rows as $key => $read_row)
		{
			$read_rows[$key] = $this->format_row($read_row);
		}

		return $read_rows;
	}

	/**
	 * Convert integer columns returned as database strings.
	 *
	 * @param   array  $read_row
	 * @return  array
	 */
	protected function format_row(array $read_row)
	{
		foreach (static::$integer_columns as $column)
		{
			if (array_key_exists($column, $read_row) and $read_row[$column] !== null)
			{
				$read_row[$column] = (int) $read_row[$column];
			}
		}

		return $read_row;
	}

	/**
	 * Validate the fixed CRUD metadata declared by a child Model.
	 *
	 * @return  void
	 */
	protected function assert_crud_configuration()
	{
		$this->assert_identifier(static::$table_name);

		if (empty(static::$read_columns))
		{
			throw new \LogicException('The Model read columns are not configured.');
		}

		foreach (static::$read_columns as $column)
		{
			$this->assert_identifier($column);
		}
	}

	/**
	 * Require exactly the columns declared for a CRUD operation.
	 *
	 * @param   array   $values
	 * @param   array   $allowed_columns
	 * @param   string  $operation
	 * @return  void
	 */
	protected function assert_values(array $values,	array $allowed_columns, $operation)
	{
		foreach ($allowed_columns as $column)
		{
			$this->assert_identifier($column);
		}

		$keys = array_keys($values);

		if (array_diff($keys, $allowed_columns)
			or array_diff($allowed_columns, $keys))
		{
			throw new \InvalidArgumentException(
				'The '.$operation.' values do not match the allowed columns.'
			);
		}
	}

	/**
	 * Use a supplied transaction connection when one is available.
	 *
	 * @param   Database_Connection|null  $db
	 * @return  Database_Connection
	 */
	protected function connection($db = null)
	{
		return $db ?: $this->db;
	}

	/**
	 * Quote the child Model's fixed table name.
	 *
	 * @param   Database_Connection  $db
	 * @return  string
	 */
	protected function quoted_table(\Database_Connection $db)
	{
		$this->assert_identifier(static::$table_name);

		return $db->quote_identifier($db->table_prefix(static::$table_name));
	}

	/**
	 * Quote a fixed column name declared by a Model.
	 *
	 * @param   string               $column
	 * @param   Database_Connection  $db
	 * @return  string
	 */
	protected function quoted_column($column, \Database_Connection $db)
	{
		$this->assert_identifier($column);

		return $db->quote_identifier($column);
	}

	/**
	 * Ensure table and column identifiers come from safe class definitions.
	 *
	 * @param   string  $identifier
	 * @return  void
	 */
	protected function assert_identifier($identifier)
	{
		if ( ! is_string($identifier)
			or preg_match('/\A[a-z][a-z0-9_]*\z/', $identifier) !== 1)
		{
			throw new \LogicException('The Model contains an invalid database identifier.');
		}
	}
}
