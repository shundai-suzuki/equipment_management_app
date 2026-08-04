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
			$lock_acquired = false;
			$transaction_started = false;

			try
			{
				if ( ! $this->model->acquire_lock($lock_name, static::LOCK_TIMEOUT_SECONDS))
				{
					throw new \RuntimeException(
						'Failed to acquire the ID allocation lock.',
						static::CONFLICT_EXCEPTION_CODE
					);
				}
				$lock_acquired = true;

				if ( ! $this->model->start_transaction())
				{
					throw new \RuntimeException('Failed to start the ID allocation transaction.');
				}
				$transaction_started = true;

				$id = $this->next_id($this->model->max_id($table));

				try
				{
					$this->model->execute_insert($operation, $id);
				}
				catch (\Database_Exception $e)
				{
					if ((int) $e->getCode() !== 1062)
					{
						throw $e;
					}

					$this->rollback_transaction();
					$transaction_started = false;

					if ( ! $this->model->id_exists($table, $id))
					{
						throw $e;
					}

					if ($retry_count >= static::MAX_RETRIES)
					{
						throw new \RuntimeException(
							'ID allocation conflicted after the maximum retries.',
							static::CONFLICT_EXCEPTION_CODE,
							$e
						);
					}

					continue;
				}

				if ( ! $this->model->in_transaction())
				{
					$transaction_started = false;
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
					$transaction_started = false;
					throw new \LogicException('The ID allocation operation left a nested transaction open.');
				}

				$transaction_started = false;

				return $id;
			}
			finally
			{
				try
				{
					if ($transaction_started)
					{
						$this->rollback_transaction();
					}
				}
				finally
				{
					if ($lock_acquired)
					{
						$this->release_lock($lock_name);
					}
				}
			}
		}

		throw new \RuntimeException(
			'ID allocation conflicted after the maximum retries.',
			static::CONFLICT_EXCEPTION_CODE
		);
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
