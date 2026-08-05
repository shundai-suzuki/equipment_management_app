<?php

/**
 * Coordinates the registration steps shared by table Services.
 *
 * @package  app
 */
abstract class Service_BaseRegistration
{
	/**
	 * Physical table name declared by the child Service.
	 *
	 * @var string
	 */
	protected static $table_name = '';

	/**
	 * Table Model used for database operations.
	 *
	 * @var Model_BaseCrud
	 */
	protected $model;

	/**
	 * Service used to allocate application-managed IDs.
	 *
	 * @var Service_IdAllocator
	 */
	protected $id_allocator;

	/**
	 * @param  object|null  $model
	 * @param  object|null  $id_allocator
	 */
	public function __construct($model = null, $id_allocator = null)
	{
		if ($model !== null and ! is_object($model))
		{
			throw new \InvalidArgumentException('The table model must be an object.');
		}

		if ($id_allocator !== null and ! is_object($id_allocator))
		{
			throw new \InvalidArgumentException('The ID allocator service must be an object.');
		}

		$this->model = $model ? $model : $this->new_model();
		$this->id_allocator = $id_allocator ? $id_allocator : new Service_IdAllocator();
	}

	/**
	 * Create the default table Model for the child Service.
	 *
	 * @return  Model_BaseCrud
	 */
	abstract protected function new_model();

	/**
	 * Require the client-side sentinel used for a new row.
	 *
	 * @param   mixed  $id
	 * @return  void
	 */
	protected function assert_new_id($id)
	{
		if ($id !== 0)
		{
			throw new \InvalidArgumentException('A new record ID must be 0.');
		}
	}

	/**
	 * Require a positive integer foreign key or actor ID.
	 *
	 * @param   mixed   $id
	 * @param   string  $name
	 * @return  void
	 */
	protected function assert_positive_id($id, $name)
	{
		if ( ! is_int($id) or $id < 1)
		{
			throw new \InvalidArgumentException($name.' must be a positive integer.');
		}
	}

	/**
	 * Allocate an ID and insert through the child Model on the same connection.
	 *
	 * @param   array  $values
	 * @return  int
	 */
	protected function register(array $values)
	{
		if (static::$table_name === '')
		{
			throw new \LogicException('The registration table name is not configured.');
		}

		return $this->id_allocator->allocate(
			static::$table_name,
			function ($id, $db) use ($values)
			{
				$this->before_insert($values, $db);

				if ( ! $this->model->insert($id, $values, $db))
				{
					throw new \RuntimeException('Failed to insert the allocated record.');
				}
			}
		);
	}

	/**
	 * Let a child Service recheck table-specific rules inside the transaction.
	 *
	 * @param   array                $values
	 * @param   Database_Connection  $db
	 * @return  void
	 */
	protected function before_insert(array $values, \Database_Connection $db)
	{
	}
}
