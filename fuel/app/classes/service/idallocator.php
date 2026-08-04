<?php

/**
 * Calculates and allocates positive IDs for the business tables.
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
	 * Tables whose IDs may be allocated by this service.
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
	 * Model used for every database operation.
	 *
	 * @var Model_IdAllocator
	 */
	protected $model;

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
	 * Allocate an ID and execute the Model insert operation in one transaction.
	 *
	 * The operation receives the allocated ID and the database connection. The
	 * callback must delegate the insert to a Model and must not commit or roll
	 * back the transaction itself.
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
			$this->acquire_lock($lock_name);

			try
			{
				$id = $this->allocate_once($table, $operation, $retry_count);
			}
			finally
			{
				$this->release_lock($lock_name);
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
	 * Execute one allocation attempt inside a transaction.
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
			$id = $this->next_id($this->model->max_id($table));

			if ( ! $this->execute_insert($table, $id, $operation, $retry_count))
			{
				return null;
			}

			$this->commit_allocation($table, $id);

			return $id;
		}
		finally
		{
			if ($this->model->in_transaction())
			{
				$this->rollback_transaction();
			}
		}
	}

	/**
	 * Execute the insert and report whether the attempt may be committed.
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
	 * Handle a database exception raised by the insert operation.
	 * Except for exception 1062, simply pass it up the chain
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
	 * Validate and commit the completed ID allocation.
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

		if ( ! $this->model->commit_transaction())
		{
			throw new \RuntimeException('Failed to commit the ID allocation transaction.');
		}

		if ($this->model->in_transaction())
		{
			$this->rollback_transaction();
			throw new \LogicException('The ID allocation operation left a nested transaction open.');
		}
	}

	/**
	 * Acquire the named ID allocation lock.
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
	 * Ensure that the target table supports ID allocation.
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
	 * Calculate the next positive ID from the current maximum ID.
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
	 * Roll back the active ID allocation transaction.
	 *
	 * @return  void
	 * @throws  RuntimeException
	 */
	protected function rollback_transaction()
	{
		if ( ! $this->model->rollback_transaction())
		{
			throw new \RuntimeException('Failed to roll back the ID allocation transaction.');
		}
	}

	/**
	 * Release the named ID allocation lock.
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
