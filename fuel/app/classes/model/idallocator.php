<?php

/**
 * ID採番サービスが使用するデータベース操作を提供する。
 *
 * @package  app
 */
class Model_IdAllocator extends Model
{
	/**
	 * ロック、採番、登録に使用するデータベース接続。
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
			'SELECT GET_LOCK(:lock_name, :lock_timeout) AS lock_acquired',
			\DB::SELECT
		)
			->parameters(
				array(
					'lock_name' => $lock_name,
					'lock_timeout' => $timeout_seconds,
				)
			)
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
			'SELECT RELEASE_LOCK(:lock_name) AS lock_released',
			\DB::SELECT
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
	 * トランザクション終了処理に失敗した場合はデータベース接続を破棄する。
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
		$id_identifier = $this->db->quote_identifier('id');
		$result = \DB::select(
			array(
				\DB::expr('COALESCE(MAX('.$id_identifier.'), 0)'),
				'max_id',
			)
		)
			->from($table)
			->execute($this->db);

		return $result->get('max_id');
	}

	/**
	 * このモデルのトランザクション接続で登録コールバックを実行する。
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
		$result = \DB::select(
			array(\DB::expr('1'), 'id_exists')
		)
			->from($table)
			->where('id', '=', $id)
			->execute($this->db);

		return (int) $result->get('id_exists', 0) === 1;
	}
}
