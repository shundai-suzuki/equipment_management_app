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
	 * Columns accepted when the child Model inserts a row.
	 *
	 * @var array
	 */
	protected static $insert_columns = array();

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
	 * Insert a row with an allocated ID on the supplied transaction connection.
	 *
	 * @param   int                  $id
	 * @param   array                $values
	 * @param   Database_Connection  $db
	 * @return  bool
	 */
	public function insert($id, array $values, \Database_Connection $db)
	{
		if ( ! is_int($id) or $id < 1)
		{
			throw new \InvalidArgumentException('The allocated ID must be a positive integer.');
		}

		$this->assert_insert_values($values);

		$columns = array($this->quoted_column('id', $db));
		$placeholders = array(':id');
		$parameters = array('id' => $id);

		foreach (static::$insert_columns as $column)
		{
			$parameter = 'value_'.$column;
			$columns[] = $this->quoted_column($column, $db);
			$placeholders[] = ':'.$parameter;
			$parameters[$parameter] = $values[$column];
		}

		$columns[] = $this->quoted_column('created_at', $db);
		$columns[] = $this->quoted_column('updated_at', $db);
		$placeholders[] = 'CURRENT_TIMESTAMP';
		$placeholders[] = 'CURRENT_TIMESTAMP';

		$result = \DB::query(
			'INSERT INTO '.$this->quoted_table($db)
			.' ('.implode(', ', $columns).')'
			.' VALUES ('.implode(', ', $placeholders).')',
			\DB::INSERT
		)
			->parameters($parameters)
			->execute($db);

		return isset($result[1]) and (int) $result[1] === 1;
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
	 * Require exactly the insert columns declared by the child Model.
	 *
	 * @param   array  $values
	 * @return  void
	 */
	protected function assert_insert_values(array $values)
	{
		foreach (static::$insert_columns as $column)
		{
			$this->assert_identifier($column);
		}

		$keys = array_keys($values);

		if (array_diff($keys, static::$insert_columns)
			or array_diff(static::$insert_columns, $keys))
		{
			throw new \InvalidArgumentException('The insert values do not match the allowed columns.');
		}
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
