<?php

/**
 * 貸出・返却の業務規則を適用する。
 *
 * @package  app
 */
class Service_Table_Loan extends Service_BaseRegistration
{
	const PER_PAGE = 10;
	const MAX_LOAN_DAYS = 90;
	const MAX_KEYWORD_LENGTH = 255;
	const MAX_NOTE_LENGTH = 255;
	const FORBIDDEN_EXCEPTION_CODE = 403;
	const NOT_FOUND_EXCEPTION_CODE = 404;
	const CONFLICT_EXCEPTION_CODE = 409;
	const VALIDATION_EXCEPTION_CODE = 422;

	/**
	 * このサービスが登録するテーブル。
	 *
	 * @var string
	 */
	protected static $table_name = 'loans';

	/**
	 * 借用者と担当者の検証に使用する社員モデル。
	 *
	 * @var Model_Table_Employee
	 */
	protected $employee_model;

	/** @var Model_Table_Equipment 備品在庫の読取モデル。 */
	protected $equipment_model;

	/**
	 * @param  object|null  $model
	 * @param  object|null  $id_allocator
	 * @param  object|null  $employee_model
	 * @param  object|null  $equipment_model
	 */
	public function __construct($model = null, $id_allocator = null, $employee_model = null, $equipment_model = null)
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
	 * 実行者が参照できる貸出履歴を検索する。
	 *
	 * @param   int     $actor_id
	 * @param   int     $page
	 * @param   string  $keyword
	 * @param   array   $filters
	 * @return  array
	 */
	public function search_for_actor($actor_id, $page, $keyword = '', array $filters = array())
	{
		$this->assert_positive_id($actor_id, 'The employee ID');
		$this->assert_page($page);
		$keyword = $this->normalize_keyword($keyword);
		$this->assert_search_filters($filters);

		$actor = $this->employee_model->read_for_authentication($actor_id);

		if ($actor === null
			or $actor['is_active'] !== 1
			or $actor['deleted_at'] !== null)
		{
			throw new \RuntimeException(
				'The employee is not available.',
				static::FORBIDDEN_EXCEPTION_CODE
			);
		}

		$employee_scope = $actor['role'] === 'ADMIN' ? null : $actor_id;
		$today = $this->current_loan_date();
		$search_result = $this->model->search_loans(
			$page,
			static::PER_PAGE,
			$employee_scope,
			$keyword,
			$filters,
			$today
		);
		$search_result['rows'] = $this->add_loan_states(
			$search_result['rows'],
			$today
		);

		return $search_result;
	}

	/**
	 * 管理者を再確認してから貸出を1件取得する。
	 *
	 * @param   int  $actor_id
	 * @param   int  $id
	 * @return  array
	 */
	public function read_for_admin($actor_id, $id)
	{
		$this->assert_positive_id($actor_id, 'The administrator employee ID');
		$this->assert_positive_id($id, 'The loan ID');

		if ( ! $this->employee_model->is_active_admin($actor_id))
		{
			throw new \RuntimeException(
				'The actor is not an active administrator.',
				static::FORBIDDEN_EXCEPTION_CODE
			);
		}

		$loan = $this->model->read_loan($id);

		if ($loan === null)
		{
			throw new \RuntimeException(
				'The loan was not found.',
				static::NOT_FOUND_EXCEPTION_CODE
			);
		}

		return $this->add_loan_state($loan, $this->current_loan_date());
	}

	/**
	 * 有効な借用者と利用可能な備品に対して貸出を1件登録する。
	 *
	 * @param   int     $actor_id
	 * @param   int     $id
	 * @param   int     $employee_id
	 * @param   int     $equipment_id
	 * @param   string  $due_date
	 * @return  int
	 */
	public function create_for_admin($actor_id, $id, $employee_id, $equipment_id, $due_date)
	{
		$this->assert_new_id($id);
		$this->assert_positive_id($actor_id, 'The loan operator employee ID');
		$this->assert_positive_id($employee_id, 'The borrower employee ID');
		$this->assert_positive_id($equipment_id, 'The equipment ID');

		$loaned_at = $this->current_loan_date();
		$due_date = $this->normalize_due_date($due_date, $loaned_at);
		$this->assert_employee_states($employee_id, $actor_id);

		return $this->create_record(array(
			'employee_id' => $employee_id,
			'equipment_id' => $equipment_id,
			'due_date' => $due_date,
			'loaned_at' => $loaned_at,
			'loaned_by' => $actor_id,
			'returned_at' => null,
			'returned_by' => null,
			'note' => null,
		));
	}

	/**
	 * 履歴を削除せず、貸出中の1件を返却する。
	 *
	 * @param   int         $actor_id
	 * @param   int         $id
	 * @param   string|null $note
	 * @return  array
	 */
	public function return_for_admin($actor_id, $id, $note = null)
	{
		$this->assert_positive_id($actor_id, 'The return operator employee ID');
		$this->assert_positive_id($id, 'The loan ID');
		$note = $this->normalize_note($note);

		if ( ! $this->employee_model->is_active_admin($actor_id))
		{
			throw new \RuntimeException(
				'The return operator is not an active administrator.',
				static::FORBIDDEN_EXCEPTION_CODE
			);
		}

		return $this->model->transaction(
			function ($db) use ($actor_id, $id, $note)
			{
				$this->employee_model->lock_active_admin_count($db);

				if ( ! $this->employee_model->is_active_admin($actor_id, $db))
				{
					throw new \RuntimeException(
						'The return operator is not an active administrator.',
						static::FORBIDDEN_EXCEPTION_CODE
					);
				}

				$loan_before_lock = $this->model->read_loan($id, $db);

				if ($loan_before_lock === null)
				{
					throw new \RuntimeException(
						'The loan was not found.',
						static::NOT_FOUND_EXCEPTION_CODE
					);
				}

				$equipment_locked = $this->equipment_model->lock_for_return(
					$loan_before_lock['equipment_id'],
					$db
				);

				if ( ! $equipment_locked)
				{
					throw new \RuntimeException(
						'The loan equipment is not available.',
						static::CONFLICT_EXCEPTION_CODE
					);
				}

				$loan = $this->model->lock_for_return($id, $db);

				if ($loan === null)
				{
					throw new \RuntimeException(
						'The loan was not found.',
						static::NOT_FOUND_EXCEPTION_CODE
					);
				}

				if ($loan['equipment_id'] !== $loan_before_lock['equipment_id']
					or $loan['returned_at'] !== null)
				{
					throw new \RuntimeException(
						'The loan cannot be returned in its current state.',
						static::CONFLICT_EXCEPTION_CODE
					);
				}

				if ($this->model->mark_returned(
					$id,
					$this->current_loan_date(),
					$actor_id,
					$note,
					$db
				) !== 1)
				{
					throw new \RuntimeException(
						'The loan changed during the return operation.',
						static::CONFLICT_EXCEPTION_CODE
					);
				}

				$returned_loan = $this->model->read_loan($id, $db);

				if ($returned_loan === null)
				{
					throw new \RuntimeException(
						'The returned loan could not be read.',
						static::CONFLICT_EXCEPTION_CODE
					);
				}

				return $this->add_loan_state(
					$returned_loan,
					$this->current_loan_date()
				);
			}
		);
	}

	/** 
	 * 標準の貸出モデル。
	 * 
	 * @return Model_Table_Loan
	 */
	protected function new_model()
	{
		return new Model_Table_Loan();
	}

	/**
	 * ID採番中に関係者と在庫を再確認する。
	 *
	 * @param   array                $create_values
	 * @param   Database_Connection  $db
	 * @return  void
	 */
	protected function before_create(array $create_values, \Database_Connection $db)
	{
		$this->employee_model->lock_active_admin_count($db);

		if ( ! $this->employee_model->is_active_admin($create_values['loaned_by'], $db))
		{
			throw new \RuntimeException(
				'The loan operator is not an active administrator.',
				static::FORBIDDEN_EXCEPTION_CODE
			);
		}

		$borrower = $this->employee_model->read_for_update(
			$create_values['employee_id'],
			false,
			$db
		);

		if ($borrower === null or $borrower['is_active'] !== 1)
		{
			throw new \RuntimeException(
				'The selected borrower is not available.',
				static::VALIDATION_EXCEPTION_CODE
			);
		}

		$inventory = $this->equipment_model->lock_available(
			$create_values['equipment_id'],
			$db
		);

		if ($inventory === null or $inventory['available_amount'] < 1)
		{
			throw new \RuntimeException(
				'The selected equipment is not available.',
				static::CONFLICT_EXCEPTION_CODE
			);
		}
	}

	/**
	 * 採番前に有効な借用者行と管理者行を検証する。
	 *
	 * @param   int  $employee_id
	 * @param   int  $actor_id
	 * @return  void
	 */
	protected function assert_employee_states($employee_id, $actor_id)
	{
		if ( ! $this->employee_model->is_active($employee_id))
		{
			throw new \RuntimeException(
				'The selected borrower is not available.',
				static::VALIDATION_EXCEPTION_CODE
			);
		}

		if ( ! $this->employee_model->is_active_admin($actor_id))
		{
			throw new \RuntimeException(
				'The loan operator is not an active administrator.',
				static::FORBIDDEN_EXCEPTION_CODE
			);
		}
	}

	/** 
	 * Asia/Tokyo基準の現在業務日。
	 * 
	 * @return string 
	 */
	protected function current_loan_date()
	{
		return \Date::time('Asia/Tokyo')->format('%Y-%m-%d');
	}

	/**
	 * 貸出日から90日後までの返却期限を検証する。
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
			throw new \InvalidArgumentException(
				'The due date must be within 90 days of the loan date.'
			);
		}

		return $due_date;
	}

	/**
	 * 任意の返却メモを正規化する。
	 *
	 * @param   mixed  $note
	 * @return  string|null
	 */
	protected function normalize_note($note)
	{
		if ($note === null)
		{
			return null;
		}

		if ( ! is_string($note))
		{
			throw new \InvalidArgumentException('The return note must be a string.');
		}

		$note = trim($note);

		if (mb_strlen($note, 'UTF-8') > static::MAX_NOTE_LENGTH)
		{
			throw new \InvalidArgumentException('The return note is too long.');
		}

		return $note === '' ? null : $note;
	}

	/**
	 * 固定ページングを検証する。
	 *
	 * @param   mixed  $page
	 * @return  void
	 */
	protected function assert_page($page)
	{
		if ( ! is_int($page) or $page < 1)
		{
			throw new \InvalidArgumentException('The page must be a positive integer.');
		}
	}

	/**
	 * 備品名のキーワードを正規化する。
	 *
	 * @param   mixed  $keyword
	 * @return  string
	 */
	protected function normalize_keyword($keyword)
	{
		if ( ! is_string($keyword))
		{
			throw new \InvalidArgumentException('The keyword must be a string.');
		}

		$keyword = trim($keyword);

		if (mb_strlen($keyword, 'UTF-8') > static::MAX_KEYWORD_LENGTH)
		{
			throw new \InvalidArgumentException('The keyword is too long.');
		}

		return $keyword;
	}

	/**
	 * 仕様で定義した貸出検索条件だけを受け付ける。
	 *
	 * @param   array  $filters
	 * @return  void
	 */
	protected function assert_search_filters(array $filters)
	{
		$allowed = array('loan_id', 'equipment_id', 'active_only', 'loan_state');

		if (array_diff(array_keys($filters), $allowed))
		{
			throw new \InvalidArgumentException('The loan search filter is not allowed.');
		}

		foreach (array('loan_id', 'equipment_id') as $name)
		{
			if (isset($filters[$name]))
			{
				$this->assert_positive_id($filters[$name], $name);
			}
		}

		if (isset($filters['active_only']) and ! is_bool($filters['active_only']))
		{
			throw new \InvalidArgumentException('active_only must be a boolean.');
		}

		if (isset($filters['loan_state'])
			and ! in_array($filters['loan_state'], array('ON_LOAN', 'OVERDUE', 'RETURNED'), true))
		{
			throw new \InvalidArgumentException('The loan state is invalid.');
		}

		if (isset($filters['active_only'], $filters['loan_state'])
			and $filters['active_only']
			and $filters['loan_state'] === 'RETURNED')
		{
			throw new \InvalidArgumentException('The loan filters conflict.');
		}
	}

	/**
	 * 検索行へ算出した状態を追加する。
	 *
	 * @param   array   $rows
	 * @param   string  $today
	 * @return  array
	 */
	protected function add_loan_states(array $rows, $today)
	{
		foreach ($rows as $key => $row)
		{
			$rows[$key] = $this->add_loan_state($row, $today);
		}

		return $rows;
	}

	/**
	 * 状態列を保存せずに貸出状態を1件算出する。
	 *
	 * @param   array   $loan
	 * @param   string  $today
	 * @return  array
	 */
	protected function add_loan_state(array $loan, $today)
	{
		if ($loan['returned_at'] !== null)
		{
			$loan['loan_state'] = 'RETURNED';
		}
		elseif ($loan['due_date'] < $today)
		{
			$loan['loan_state'] = 'OVERDUE';
		}
		else
		{
			$loan['loan_state'] = 'ON_LOAN';
		}

		return $loan;
	}
}
