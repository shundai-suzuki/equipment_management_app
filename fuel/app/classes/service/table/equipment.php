<?php

/**
 * Applies equipment registration rules and delegates persistence.
 *
 * @package  app
 */
class Service_Table_Equipment extends Service_BaseRegistration
{
	const MAX_NAME_LENGTH = 255;
	const MAX_CATEGORY_LENGTH = 20;
	const MAX_DESCRIPTION_LENGTH = 255;
	const MAX_TOTAL_AMOUNT = 2147483647;
	const CONFLICT_EXCEPTION_CODE = 409;

	/**
	 * Table registered by this Service.
	 *
	 * @var string
	 */
	protected static $table_name = 'equipments';

	/**
	 * Department Model used to validate the managing department.
	 *
	 * @var Model_Table_Department
	 */
	protected $department_model;

	/**
	 * @param  object|null  $model
	 * @param  object|null  $id_allocator
	 * @param  object|null  $department_model
	 */
	public function __construct($model = null, $id_allocator = null, $department_model = null)
	{
		parent::__construct($model, $id_allocator);

		if ($department_model !== null and ! is_object($department_model))
		{
			throw new \InvalidArgumentException('The department model must be an object.');
		}

		$this->department_model = $department_model ? $department_model : new Model_Table_Department();
	}

	/**
	 * Register equipment or restore an archived matching inventory row.
	 *
	 * @param   int          $id
	 * @param   string       $name
	 * @param   int          $department_id
	 * @param   string       $category
	 * @param   int          $total_amount
	 * @param   string|null  $description
	 * @return  int
	 */
	public function create($id, $name, $department_id, $category, $total_amount, $description = null)
	{
		$this->assert_new_id($id);
		$this->assert_positive_id($department_id, 'The department ID');

		$name = $this->normalize_required_text($name, 'The equipment name', static::MAX_NAME_LENGTH);
		$category = $this->normalize_required_text($category, 'The equipment category', static::MAX_CATEGORY_LENGTH);
		$total_amount = $this->normalize_total_amount($total_amount);
		$description = $this->normalize_description($description);
		
		$this->assert_active_department($department_id);
		$equipment = $this->model->find_by_department_and_name($department_id, $name);

		if ($equipment !== null)
		{
			return $this->restore_or_reject($equipment, $department_id, $name);
		}

		$values = array(
			'name' => $name,
			'department_id' => $department_id,
			'category' => $category,
			'total_amount' => $total_amount,
			'description' => $description,
		);

		try
		{
			return $this->register($values);
		}
		catch (\Database_Exception $exception)
		{
			return $this->handle_insert_exception($department_id, $name, $exception);
		}
	}

	/**
	 * Create the equipment Model used by this Service.
	 *
	 * @return  Model_Table_Equipment
	 */
	protected function new_model()
	{
		return new Model_Table_Equipment();
	}

	/**
	 * Recheck the department on the allocator transaction connection.
	 *
	 * @param   array                $values
	 * @param   Database_Connection  $db
	 * @return  void
	 */
	protected function before_insert(array $values, \Database_Connection $db)
	{
		$this->assert_active_department($values['department_id'], $db);
	}

	/**
	 * Normalize a required text field with a table-specific limit.
	 *
	 * @param   mixed   $value
	 * @param   string  $name
	 * @param   int     $max_length
	 * @return  string
	 */
	protected function normalize_required_text($value, $name, $max_length)
	{
		if ( ! is_string($value))
		{
			throw new \InvalidArgumentException($name.' must be a string.');
		}

		$value = trim($value);

		if ($value === '' or mb_strlen($value, 'UTF-8') > $max_length)
		{
			throw new \InvalidArgumentException($name.' has an invalid length.');
		}

		return $value;
	}

	/**
	 * Normalize the optional equipment description.
	 *
	 * @param   mixed  $description
	 * @return  string|null
	 */
	protected function normalize_description($description)
	{
		if ($description === null)
		{
			return null;
		}

		if ( ! is_string($description))
		{
			throw new \InvalidArgumentException('The equipment description must be a string or null.');
		}

		$description = trim($description);

		if ($description === '')
		{
			return null;
		}

		if (mb_strlen($description, 'UTF-8') > static::MAX_DESCRIPTION_LENGTH)
		{
			throw new \InvalidArgumentException('The equipment description must not exceed 255 characters.');
		}

		return $description;
	}

	/**
	 * Require a positive signed INT equipment total.
	 *
	 * @param   mixed  $total_amount
	 * @return  int
	 */
	protected function normalize_total_amount($total_amount)
	{
		if ( ! is_int($total_amount)
			or $total_amount < 1
			or $total_amount > static::MAX_TOTAL_AMOUNT)
		{
			throw new \InvalidArgumentException('The equipment total amount is invalid.');
		}

		return $total_amount;
	}

	/**
	 * Require a department that is not archived.
	 *
	 * @param   int                       $department_id
	 * @param   Database_Connection|null  $db
	 * @return  void
	 */
	protected function assert_active_department($department_id, $db = null)
	{
		if ( ! $this->department_model->is_active($department_id, $db))
		{
			throw new \InvalidArgumentException('The selected department is not available.');
		}
	}

	/**
	 * Restore an archived match or reject an active duplicate.
	 *
	 * @param   array   $equipment
	 * @param   int     $department_id
	 * @param   string  $name
	 * @return  int
	 */
	protected function restore_or_reject(array $equipment, $department_id, $name)
	{
		if ($equipment['deleted_at'] === null)
		{
			throw new \RuntimeException(
				'Equipment with the same department and name already exists.',
				static::CONFLICT_EXCEPTION_CODE
			);
		}

		$id = (int) $equipment['id'];

		if ($this->model->restore($id))
		{
			return $id;
		}

		$current = $this->model->find_by_department_and_name($department_id, $name);

		if ($current !== null
			and (int) $current['id'] === $id
			and $current['deleted_at'] === null)
		{
			return $id;
		}

		throw new \RuntimeException(
			'Failed to restore the archived equipment.',
			static::CONFLICT_EXCEPTION_CODE
		);
	}

	/**
	 * Convert a duplicate department and name pair into a conflict.
	 *
	 * @param   int                 $department_id
	 * @param   string              $name
	 * @param   Database_Exception  $exception
	 * @return  int
	 */
	protected function handle_insert_exception($department_id, $name, \Database_Exception $exception)
	{
		if ((int) $exception->getCode() !== 1062)
		{
			throw $exception;
		}

		$equipment = $this->model->find_by_department_and_name($department_id, $name);

		if ($equipment === null)
		{
			throw $exception;
		}

		return $this->restore_or_reject($equipment, $department_id, $name);
	}
}
