<?php

/**
 * Applies loan registration rules and delegates persistence.
 *
 * @package  app
 */
class Service_Table_Loan extends Service_BaseRegistration
{
	const MAX_LOAN_DAYS = 90;
	const FORBIDDEN_EXCEPTION_CODE = 403;
	const VALIDATION_EXCEPTION_CODE = 422;
	const CONFLICT_EXCEPTION_CODE = 409;

	/**
	 * Table registered by this Service.
	 *
	 * @var string
	 */
	protected static $table_name = 'loans';

	/**
	 * Employee Model used to validate the borrower and operator.
	 *
	 * @var Model_Table_Employee
	 */
	protected $employee_model;

	/**
	 * Equipment Model used to lock and validate inventory.
	 *
	 * @var Model_Table_Equipment
	 */
	protected $equipment_model;

	/**
	 * @param  object|null  $model
	 * @param  object|null  $id_allocator
	 * @param  object|null  $employee_model
	 * @param  object|null  $equipment_model
	 */
	public function __construct(
		$model = null,
		$id_allocator = null,
		$employee_model = null,
		$equipment_model = null
	)
	{
		parent::__construct($model, $id_allocator);

		if ($employee_model !== null and ! is_object($employee_model))
		{
			throw new \InvalidArgumentException('The employee model must be an object.');
		}

		if ($equipment_model !== null and ! is_object($equipment_model))
		{
			throw new \InvalidArgumentException('The equipment model must be an object.');
		}

		$this->employee_model = $employee_model ?: new Model_Table_Employee();
		$this->equipment_model = $equipment_model ?: new Model_Table_Equipment();
	}

	/**
	 * Register a loan after validating the actor, borrower and inventory.
	 *
	 * @param   int     $id
	 * @param   int     $employee_id
	 * @param   int     $equipment_id
	 * @param   string  $due_date
	 * @param   int     $loaned_by
	 * @return  int
	 */
	public function create($id, $employee_id, $equipment_id, $due_date, $loaned_by)
	{
		$this->assert_new_id($id);
		$this->assert_positive_id($employee_id, 'The borrower employee ID');
		$this->assert_positive_id($equipment_id, 'The equipment ID');
		$this->assert_positive_id($loaned_by, 'The loan operator employee ID');
		$loaned_at = $this->current_loan_date();
		$due_date = $this->normalize_due_date($due_date, $loaned_at);
		$this->assert_employee_states($employee_id, $loaned_by);

		return $this->register(array(
			'employee_id' => $employee_id,
			'equipment_id' => $equipment_id,
			'due_date' => $due_date,
			'loaned_at' => $loaned_at,
			'loaned_by' => $loaned_by,
			'returned_at' => null,
			'returned_by' => null,
			'note' => null,
		));
	}

	/**
	 * Create the loan Model used by this Service.
	 *
	 * @return  Model_Table_Loan
	 */
	protected function new_model()
	{
		return new Model_Table_Loan();
	}

	/**
	 * Recheck participants and inventory inside the allocation transaction.
	 *
	 * @param   array                $values
	 * @param   Database_Connection  $db
	 * @return  void
	 */
	protected function before_insert(array $values, \Database_Connection $db)
	{
		$this->assert_employee_states($values['employee_id'], $values['loaned_by'], $db);
		$inventory = $this->equipment_model->lock_available($values['equipment_id'], $db);

		if ($inventory === null or $inventory['available_amount'] < 1)
		{
			throw new \RuntimeException(
				'The selected equipment is not available.',
				static::CONFLICT_EXCEPTION_CODE
			);
		}
	}

	/**
	 * Validate active borrower and administrator records.
	 *
	 * @param   int                       $employee_id
	 * @param   int                       $loaned_by
	 * @param   Database_Connection|null  $db
	 * @return  void
	 */
	protected function assert_employee_states($employee_id, $loaned_by, $db = null)
	{
		if ( ! $this->employee_model->is_active($employee_id, $db))
		{
			throw new \RuntimeException(
				'The selected borrower is not available.',
				static::VALIDATION_EXCEPTION_CODE
			);
		}

		if ( ! $this->employee_model->is_active_admin($loaned_by, $db))
		{
			throw new \RuntimeException(
				'The loan operator is not an active administrator.',
				static::FORBIDDEN_EXCEPTION_CODE
			);
		}
	}

	/**
	 * Return the current business date in Asia/Tokyo.
	 *
	 * @return  string
	 */
	protected function current_loan_date()
	{
		$timezone = new \DateTimeZone('Asia/Tokyo');

		return (new \DateTimeImmutable('now', $timezone))->format('Y-m-d');
	}

	/**
	 * Validate a due date between the loan date and 90 days later.
	 *
	 * @param   mixed   $due_date
	 * @param   string  $loaned_at
	 * @return  string
	 */
	protected function normalize_due_date($due_date, $loaned_at)
	{
		if ( ! is_string($due_date))
		{
			throw new \InvalidArgumentException('The due date must be a string.');
		}

		$timezone = new \DateTimeZone('Asia/Tokyo');
		$date = \DateTimeImmutable::createFromFormat('!Y-m-d', $due_date, $timezone);
		$errors = \DateTimeImmutable::getLastErrors();

		if ($date === false
			or ($errors !== false and ($errors['warning_count'] > 0 or $errors['error_count'] > 0))
			or $date->format('Y-m-d') !== $due_date)
		{
			throw new \InvalidArgumentException('The due date must use YYYY-MM-DD.');
		}

		$start = new \DateTimeImmutable($loaned_at, $timezone);
		$last_due_date = $start->modify('+'.static::MAX_LOAN_DAYS.' days');

		if ($date < $start or $date > $last_due_date)
		{
			throw new \InvalidArgumentException('The due date must be within 90 days of the loan date.');
		}

		return $due_date;
	}
}
