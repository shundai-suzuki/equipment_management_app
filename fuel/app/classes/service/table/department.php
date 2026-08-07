<?php

/**
 * Applies department registration rules and delegates persistence.
 *
 * @package  app
 */
class Service_Table_Department extends Service_BaseRegistration
{
	const MAX_NAME_LENGTH = 255;
	const CONFLICT_EXCEPTION_CODE = 409;

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
		$department = $this->model->find_by_name($name);

		if ($department !== null)
		{
			return $this->restore_or_reject($department, $name);
		}

		try
		{
			return $this->register(array('name' => $name));
		}
		catch (\Database_Exception $exception)
		{
			return $this->handle_insert_exception($name, $exception);
		}
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
	 * @param   array   $department
	 * @param   string  $name
	 * @return  int
	 */
	protected function restore_or_reject(array $department, $name)
	{
		if ($department['deleted_at'] === null)
		{
			throw new \RuntimeException(
				'A department with the same name already exists.',
				static::CONFLICT_EXCEPTION_CODE
			);
		}

		$id = (int) $department['id'];

		if ($this->model->restore($id))
		{
			return $id;
		}

		$current = $this->model->find_by_name($name);

		if ($current !== null
			and (int) $current['id'] === $id
			and $current['deleted_at'] === null)
		{
			return $id;
		}

		throw new \RuntimeException(
			'Failed to restore the archived department.',
			static::CONFLICT_EXCEPTION_CODE
		);
	}

	/**
	 * Convert a duplicate department name into a conflict.
	 *
	 * @param   string              $name
	 * @param   Database_Exception  $exception
	 * @return  int
	 */
	protected function handle_insert_exception($name, \Database_Exception $exception)
	{
		if ((int) $exception->getCode() !== 1062)
		{
			throw $exception;
		}

		$department = $this->model->find_by_name($name);

		if ($department === null)
		{
			throw $exception;
		}

		return $this->restore_or_reject($department, $name);
	}
}
