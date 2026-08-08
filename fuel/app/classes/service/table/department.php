<?php

/**
 * Applies department registration rules and delegates persistence.
 *
 * @package  app
 */
class Service_Table_Department extends Service_BaseCrud
{
	const MAX_NAME_LENGTH = 255;

	/**
	 * Table registered by this Service.
	 *
	 * @var string
	 */
	protected static $table_name = 'departments';

	/**
	 * Create a department or restore an archived department with the same name.
	 *
	 * @param   int     $id
	 * @param   string  $name
	 * @return  int
	 */
	public function create($id, $name)
	{
		$this->assert_new_id($id);
		$name = $this->normalize_name($name);
		$read_department = $this->model->read_by_name($name);

		if ($read_department !== null)
		{
			return $this->restore_or_reject($read_department);
		}

		try
		{
			return $this->create_record(array('name' => $name));
		}
		catch (\Database_Exception $exception)
		{
			return $this->handle_create_exception($name, $exception);
		}
	}

	/**
	 * Create a department after rechecking the administrator.
	 *
	 * @param   int     $actor_id
	 * @param   int     $id
	 * @param   string  $name
	 * @return  int
	 */
	public function create_for_admin($actor_id, $id, $name)
	{
		$this->assert_admin_actor($actor_id);

		return $this->create($id, $name);
	}

	/**
	 * Update a department name.
	 *
	 * @param   int     $actor_id
	 * @param   int     $id
	 * @param   string  $name
	 * @return  array
	 */
	public function update_for_admin($actor_id, $id, $name)
	{
		$this->assert_positive_id($id, 'The department ID');
		$name = $this->normalize_name($name);

		return $this->model->transaction(
			function ($db) use ($actor_id, $id, $name)
			{
				$this->assert_admin_actor($actor_id, $db);
				$department_before_update = $this->model->read_for_update($id, false, $db);

				if ($department_before_update === null)
				{
					throw new \RuntimeException(
						'The department was not found.',
						static::NOT_FOUND_EXCEPTION_CODE
					);
				}

				$read_duplicate = $this->model->read_by_name($name, $db);

				if ($read_duplicate !== null and (int) $read_duplicate['id'] !== $id)
				{
					throw new \RuntimeException(
						'A department with the same name already exists.',
						static::CONFLICT_EXCEPTION_CODE
					);
				}

				return $this->update_and_read_record($id, array('name' => $name), $db);
			}
		);
	}

	/**
	 * Soft-delete an unused department.
	 *
	 * @param   int     $actor_id
	 * @param   int     $id
	 * @param   string  $reason
	 * @return  array
	 */
	public function soft_delete_for_admin($actor_id, $id, $reason)
	{
		$this->assert_positive_id($id, 'The department ID');
		$this->assert_soft_delete_reason($reason);

		return $this->model->transaction(
			function ($db) use ($actor_id, $id)
			{
				$this->assert_admin_actor($actor_id, $db);

				$department_before_delete = $this->model->read_for_update($id, true, $db);

				if ($department_before_delete === null)
				{
					throw new \RuntimeException(
						'The department was not found.',
						static::NOT_FOUND_EXCEPTION_CODE
					);
				}

				if ($department_before_delete['deleted_at'] !== null)
				{
					throw new \RuntimeException(
						'The department is already archived.',
						static::CONFLICT_EXCEPTION_CODE
					);
				}

				if ($this->model->has_active_references($id, $db))
				{
					throw new \RuntimeException(
						'The department is still referenced.',
						static::CONFLICT_EXCEPTION_CODE
					);
				}

				return $this->soft_delete_and_read_record($id, $db);
			}
		);
	}

	/**
	 * Restore one archived department.
	 *
	 * @param   int  $actor_id
	 * @param   int  $id
	 * @return  array
	 */
	public function restore_for_admin($actor_id, $id)
	{
		$this->assert_positive_id($id, 'The department ID');

		return $this->model->transaction(
			function ($db) use ($actor_id, $id)
			{
				$this->assert_admin_actor($actor_id, $db);
				$department_before_restore = $this->model->read_for_update(
					$id,
					true,
					$db
				);

				if ($department_before_restore === null)
				{
					throw new \RuntimeException(
						'The department was not found.',
						static::NOT_FOUND_EXCEPTION_CODE
					);
				}

				if ($department_before_restore['deleted_at'] === null)
				{
					throw new \RuntimeException(
						'The department is not archived.',
						static::CONFLICT_EXCEPTION_CODE
					);
				}

				return $this->restore_and_read_record($id, $db);
			}
		);
	}

	/**
	 * Create the department Model used by this Service.
	 *
	 * @return  Model_Table_Department
	 */
	protected function new_model()
	{
		return new Model_Table_Department();
	}

	/**
	 * Normalize and validate a department name.
	 *
	 * @param   mixed  $name
	 * @return  string
	 */
	protected function normalize_name($name)
	{
		if ( ! is_string($name))
		{
			throw new \InvalidArgumentException('The department name must be a string.');
		}

		$name = trim($name);

		if ($name === '')
		{
			throw new \InvalidArgumentException('The department name is required.');
		}

		if (mb_strlen($name, 'UTF-8') > static::MAX_NAME_LENGTH)
		{
			throw new \InvalidArgumentException('The department name must not exceed 255 characters.');
		}

		return $name;
	}

	/**
	 * Restore an archived match or reject an active duplicate.
	 *
	 * @param   array  $read_department
	 * @return  int
	 */
	protected function restore_or_reject(array $read_department)
	{
		if ($read_department['deleted_at'] === null)
		{
			throw new \RuntimeException(
				'A department with the same name already exists.',
				static::CONFLICT_EXCEPTION_CODE
			);
		}

		$id = (int) $read_department['id'];

		return $this->model->transaction(
			function ($db) use ($id)
			{
				$department_before_restore = $this->model->read_for_update(
					$id,
					true,
					$db
				);

				if ($department_before_restore === null)
				{
					throw new \RuntimeException(
						'The archived department could not be read.',
						static::CONFLICT_EXCEPTION_CODE
					);
				}

				if ($department_before_restore['deleted_at'] === null)
				{
					return $id;
				}

				$this->restore_and_read_record($id, $db);

				return $id;
			}
		);
	}

	/**
	 * Convert a duplicate department name into a conflict.
	 *
	 * @param   string              $name
	 * @param   Database_Exception  $exception
	 * @return  int
	 */
	protected function handle_create_exception($name, \Database_Exception $exception)
	{
		if ((int) $exception->getCode() !== 1062)
		{
			throw $exception;
		}

		$read_department = $this->model->read_by_name($name);

		if ($read_department === null)
		{
			throw $exception;
		}

		return $this->restore_or_reject($read_department);
	}
}
