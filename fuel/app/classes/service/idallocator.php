<?php

/**
 * 業務テーブル用の正のIDを計算して採番する。
 *
 * @package  app
 */
class Service_IdAllocator
{
	const LOCK_TIMEOUT_SECONDS = 5;
	const MAX_RETRIES = 3;
	const MAX_ID = 2147483647;
	const CONFLICT_EXCEPTION_CODE = 409;

	/**
	 * このサービスでIDを採番できるテーブル。
	 *
	 * @var array
	 */
	protected static $allowed_tables = array(
		'departments',
		'employees',
		'equipments',
		'loans',
	);

	/**
	 * すべてのデータベース操作に使用するモデル。
	 *
	 * @var Model_IdAllocator
	 */
	protected $model;

	/**
	 * 現在の試行でデータベース接続を破棄したかどうか。
	 *
	 * @var bool
	 */
	protected $connection_discarded = false;

	/**
	 * @param  Model_IdAllocator|null  $model
	 */
	public function __construct($model = null)
	{
		if ($model !== null and ! is_object($model))
		{
			throw new \InvalidArgumentException('The ID allocator model must be an object.');
		}

		$this->model = $model === null ? new Model_IdAllocator() : $model;
	}

	/**
	 * 1つのトランザクションでIDを採番し、モデルの登録処理を実行する。
	 *
	 * 処理は採番済みIDとデータベース接続を受け取る。
	 * コールバックは登録をモデルへ委譲し、自身でトランザクションをコミットまたは
	 * ロールバックしてはならない。
	 *
	 * @param   string   $table
	 * @param   Closure  $operation
	 * @return  int
	 * @throws  InvalidArgumentException
	 * @throws  LogicException
	 * @throws  OverflowException
	 * @throws  RuntimeException
	 */
	public function allocate($table, \Closure $operation)
	{
		$this->assert_allowed_table($table);

		if ($this->model->in_transaction())
		{
			throw new \LogicException('ID allocation cannot start inside an active transaction.');
		}

		$lock_name = 'id_alloc:'.$table;

		for ($retry_count = 0; $retry_count <= static::MAX_RETRIES; $retry_count++)
		{
			$this->connection_discarded = false;
			$this->acquire_lock($lock_name);

			try
			{
				$id = $this->allocate_once($table, $operation, $retry_count);
			}
			finally
			{
				if ( ! $this->connection_discarded)
				{
					$this->release_lock($lock_name);
				}
			}

			if ($id !== null)
			{
				return $id;
			}
		}

		throw new \RuntimeException(
			'ID allocation conflicted after the maximum retries.',
			static::CONFLICT_EXCEPTION_CODE
		);
	}

	/**
	 * トランザクション内で採番を1回試行する。
	 *
	 * @param   string   $table
	 * @param   Closure  $operation
	 * @param   int      $retry_count
	 * @return  int|null
	 */
	protected function allocate_once($table, \Closure $operation, $retry_count)
	{
		if ( ! $this->model->start_transaction())
		{
			throw new \RuntimeException('Failed to start the ID allocation transaction.');
		}

		try
		{
			$id = $this->next_id($this->model->get_max_id($table));

			if ( ! $this->execute_insert($table, $id, $operation, $retry_count))
			{
				return null;
			}

			$this->commit_allocation($table, $id);

			return $id;
		}
		finally
		{
			if ( ! $this->connection_discarded and $this->model->in_transaction())
			{
				$this->rollback_transaction();
			}
		}
	}

	/**
	 * 登録を実行し、試行をコミットできるか返す。
	 *
	 * @param   string   $table
	 * @param   int      $id
	 * @param   Closure  $operation
	 * @param   int      $retry_count
	 * @return  bool
	 */
	protected function execute_insert($table, $id, \Closure $operation, $retry_count)
	{
		try
		{
			$this->model->execute_insert($operation, $id);
		}
		catch (\Database_Exception $e)
		{
			return $this->handle_insert_exception($table, $id, $retry_count, $e);
		}

		return true;
	}

	/**
	 * 登録処理で発生したデータベース例外を処理する。
	 * 例外コード1062以外はそのまま上位へ渡す。
	 *
	 * @param   string              $table
	 * @param   int                 $id
	 * @param   int                 $retry_count
	 * @param   Database_Exception  $exception
	 * @return  bool
	 */
	protected function handle_insert_exception($table, $id, $retry_count, \Database_Exception $exception)
	{
		if ((int) $exception->getCode() !== 1062)
		{
			throw $exception;
		}

		$this->rollback_transaction();

		if ( ! $this->model->id_exists($table, $id))
		{
			throw $exception;
		}

		if ($retry_count >= static::MAX_RETRIES)
		{
			throw new \RuntimeException(
				'ID allocation conflicted after the maximum retries.',
				static::CONFLICT_EXCEPTION_CODE,
				$exception
			);
		}

		return false;
	}

	/**
	 * 完了したID採番を検証してコミットする。
	 *
	 * @param   string  $table
	 * @param   int     $id
	 * @return  void
	 */
	protected function commit_allocation($table, $id)
	{
		if ( ! $this->model->in_transaction())
		{
			throw new \LogicException('The ID allocation operation ended its transaction.');
		}

		if ( ! $this->model->id_exists($table, $id))
		{
			throw new \RuntimeException('The ID allocation operation did not insert the allocated ID.');
		}

		$this->commit_transaction();

		if ($this->model->in_transaction())
		{
			$this->rollback_transaction();
			throw new \LogicException('The ID allocation operation left a nested transaction open.');
		}
	}

	/**
	 * 名前付きID採番ロックを取得する。
	 *
	 * @param   string  $lock_name
	 * @return  void
	 */
	protected function acquire_lock($lock_name)
	{
		if ( ! $this->model->acquire_lock($lock_name, static::LOCK_TIMEOUT_SECONDS))
		{
			throw new \RuntimeException(
				'Failed to acquire the ID allocation lock.',
				static::CONFLICT_EXCEPTION_CODE
			);
		}
	}

	/**
	 * 対象テーブルがID採番に対応していることを確認する。
	 *
	 * @param   string  $table
	 * @return  void
	 * @throws  InvalidArgumentException
	 */
	protected function assert_allowed_table($table)
	{
		if ( ! is_string($table) or ! in_array($table, static::$allowed_tables, true))
		{
			throw new \InvalidArgumentException('Unsupported ID allocation table.');
		}
	}

	/**
	 * 現在の最大IDから次の正のIDを計算する。
	 *
	 * @param   int|string  $max_id
	 * @return  int
	 * @throws  OverflowException
	 * @throws  RuntimeException
	 */
	protected function next_id($max_id)
	{
		if ( ! is_int($max_id)
			and ( ! is_string($max_id) or ! ctype_digit($max_id)))
		{
			throw new \RuntimeException('The database returned an invalid maximum ID.');
		}

		$max_id = (int) $max_id;

		if ($max_id < 0)
		{
			throw new \RuntimeException('The database returned a negative maximum ID.');
		}

		if ($max_id >= static::MAX_ID)
		{
			throw new \OverflowException('The ID allocation limit has been reached.');
		}

		return $max_id + 1;
	}

	/**
	 * 実行中のID採番トランザクションをロールバックする。
	 *
	 * @return  void
	 * @throws  RuntimeException
	 */
	protected function rollback_transaction()
	{
		try
		{
			$rolled_back = $this->model->rollback_transaction();
		}
		catch (\Throwable $exception)
		{
			$this->discard_connection();
			throw $exception;
		}

		if ( ! $rolled_back)
		{
			$this->discard_connection();
			throw new \RuntimeException('Failed to roll back the ID allocation transaction.');
		}
	}

	/**
	 * 実行中のトランザクションをコミットし、結果が不確実な場合は接続を破棄する。
	 *
	 * @return  void
	 * @throws  RuntimeException
	 */
	protected function commit_transaction()
	{
		try
		{
			$committed = $this->model->commit_transaction();
		}
		catch (\Throwable $exception)
		{
			$this->discard_connection();
			throw $exception;
		}

		if ( ! $committed)
		{
			$this->discard_connection();
			throw new \RuntimeException('Failed to commit the ID allocation transaction.');
		}
	}

	/**
	 * トランザクションを安全に終了できなかった接続を破棄する。
	 *
	 * @return  void
	 * @throws  RuntimeException
	 */
	protected function discard_connection()
	{
		$this->connection_discarded = true;

		if ( ! $this->model->disconnect())
		{
			throw new \RuntimeException('Failed to discard the ID allocation database connection.');
		}
	}

	/**
	 * 名前付きID採番ロックを解放する。
	 *
	 * @param   string  $lock_name
	 * @return  void
	 * @throws  RuntimeException
	 */
	protected function release_lock($lock_name)
	{
		if ( ! $this->model->release_lock($lock_name))
		{
			throw new \RuntimeException('Failed to release the ID allocation lock.');
		}
	}
}
