<?php

/**
 * Provides database operations used by the ID allocation service.
 *
 * @package  app
 */
class Model_IdAllocator extends Model
{
	/**
	 * Database connection used for locking, allocation and insertion.
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
	 * @return  bool
	 */
	public function in_transaction()
	{
		return $this->db->in_transaction();
	}

	/**
	 * @param   string  $lock_name
	 * @param   int     $timeout_seconds
	 * @return  bool
	 */
	public function acquire_lock($lock_name, $timeout_seconds)
	{
		$result = \DB::query(
			'SELECT GET_LOCK(:lock_name, :lock_timeout) AS lock_acquired'
		)
			->parameters(
				array(
					'lock_name' => $lock_name,
					'lock_timeout' => $timeout_seconds,
			))
			->execute($this->db);

		return ((int) $result->get('lock_acquired', 0)) === 1;
	}

	/**
	 * @param   string  $lock_name
	 * @return  bool
	 */
	public function release_lock($lock_name)
	{
		$result = \DB::query(
			'SELECT RELEASE_LOCK(:lock_name) AS lock_released'
		)
			->param('lock_name', $lock_name)
			->execute($this->db);

		return ((int) $result->get('lock_released', 0)) === 1;
	}

	/**
	 * @return  bool
	 */
	public function start_transaction()
	{
		return $this->db->start_transaction();
	}

	/**
	 * @return  bool
	 */
	public function commit_transaction()
	{
		return $this->db->commit_transaction();
	}

	/**
	 * @return  bool
	 */
	public function rollback_transaction()
	{
		return $this->db->rollback_transaction();
	}

	/**
	 * Discard the database connection after transaction cleanup fails.
	 *
	 * @return  bool
	 */
	public function disconnect()
	{
		return $this->db->disconnect();
	}

	/**
	 * @param   string  $table
	 * @return  int|string
	 */
	public function get_max_id($table)
	{
		$table_identifier = $this->quoted_table($table);
		$id_identifier = $this->db->quote_identifier('id');
		$result = \DB::query(
			'SELECT COALESCE(MAX('.$id_identifier.'), 0) AS max_id FROM '.$table_identifier
		)->execute($this->db);

		return $result->get('max_id');
	}

	/**
	 * Execute an insert callback on this Model's transaction connection.
	 *
	 * @param   Closure  $operation
	 * @param   int      $id
	 * @return  void
	 */
	public function execute_insert(\Closure $operation, $id)
	{
		$operation($id, $this->db);
	}

	/**
	 * @param   string  $table
	 * @param   int     $id
	 * @return  bool
	 */
	public function id_exists($table, $id)
	{
		$table_identifier = $this->quoted_table($table);
		$id_identifier = $this->db->quote_identifier('id');
		$result = \DB::query(
			'SELECT 1 AS id_exists FROM '.$table_identifier
			.' WHERE '.$id_identifier.' = :id'
		)
			->param('id', $id)
			->execute($this->db);

		return (int) $result->get('id_exists', 0) === 1;
	}

	/**
	 * Quote the target table name for an SQL statement.
	 *
	 * @param   string  $table
	 * @return  string
	 */
	protected function quoted_table($table)
	{
		return $this->db->quote_identifier($this->db->table_prefix($table));
	}
}
