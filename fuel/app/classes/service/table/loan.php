<?php

/**
 * 貸出と返却の業務規則を適用する。
 */
class Service_Table_Loan extends Service_BaseCrud
{
	/** @var int 貸出期限の最大日数 */
	const MAX_LOAN_DAYS = 90;
	/** @var int 権限不足時の例外コード */
	const FORBIDDEN_EXCEPTION_CODE = 403;

	/** @var Model_Table_Employee 社員状態を確認するModel */
	protected $employee_model;
	/** @var Model_Table_Equipment 備品在庫を確認するModel */
	protected $equipment_model;

	/**
	 * 貸出、社員、備品のModelを初期化する。
	 *
	 * @param object|null $model           使用する貸出Model
	 * @param object|null $employee_model  使用する社員Model
	 * @param object|null $equipment_model 使用する備品Model
	 * @return void
	 */
	public function __construct($model = null, $employee_model = null, $equipment_model = null)
	{
		parent::__construct($model);
		$this->employee_model = $employee_model ?: new Model_Table_Employee();
		$this->equipment_model = $equipment_model ?: new Model_Table_Equipment();
	}

	/**
	 * 社員の権限に応じた貸出一覧を取得する。
	 *
	 * @param int    $actor_id 操作する社員のID
	 * @param int    $page     取得するページ番号
	 * @param string $keyword  検索キーワード
	 * @param array  $filters  検索条件
	 * @return array
	 */
	public function search_for_actor($actor_id, $page, $keyword = '', array $filters = array())
	{
		$this->assert_positive_id($actor_id);
		$this->assert_positive_id($page);
		$employee_id = null;

		if ( ! $this->employee_model->is_active_admin($actor_id))
		{
			if ( ! $this->employee_model->is_active($actor_id))
			{
				throw new \RuntimeException(
					'The employee is not available.',
					static::FORBIDDEN_EXCEPTION_CODE
				);
			}

			$employee_id = $actor_id;
		}

		$result = $this->model->search_loans(
			$page,
			static::PER_PAGE,
			$employee_id,
			is_string($keyword) ? trim($keyword) : '',
			$filters,
			$this->current_loan_date()
		);
		$result['rows'] = $this->add_loan_states(
			$result['rows'],
			$this->current_loan_date()
		);

		return $result;
	}

	/**
	 * 管理者が貸出を登録する。
	 *
	 * @param int   $actor_id     処理する管理者のID
	 * @param int   $employee_id  借用者のID
	 * @param int   $equipment_id 備品のID
	 * @param mixed $due_date     返却期限
	 * @return int
	 */
	public function create($actor_id, $employee_id, $equipment_id, $due_date)
	{
		$this->assert_positive_id($actor_id);
		$this->assert_positive_id($employee_id);
		$this->assert_positive_id($equipment_id);
		$loaned_at = $this->current_loan_date();
		$due_date = $this->due_date($due_date, $loaned_at);

		return $this->model->transaction(function ($db) use ($actor_id, $employee_id, $equipment_id, $loaned_at, $due_date)
		{
			$this->employee_model->count_active_admins();

			if ( ! $this->employee_model->is_active_admin($actor_id, $db))
			{
				throw new \RuntimeException(
					'The loan operator is not an active administrator.',
					static::FORBIDDEN_EXCEPTION_CODE
				);
			}

			$borrower = $this->employee_model->read($employee_id, false,	$db);

			if ($borrower === null or (int) $borrower['is_active'] !== 1)
			{
				throw new \InvalidArgumentException('The selected borrower is unavailable.');
			}

			$inventory = $this->equipment_model->lock_available($equipment_id, $db);

			if ($inventory === null or $inventory['available_amount'] < 1)
			{
				throw new \RuntimeException(
					'The selected equipment is unavailable.',
					static::CONFLICT_EXCEPTION_CODE
				);
			}

			return $this->model->create(array(
				'employee_id' => $employee_id,
				'equipment_id' => $equipment_id,
				'due_date' => $due_date,
				'loaned_at' => $loaned_at,
				'loaned_by' => $actor_id,
				'returned_at' => null,
				'returned_by' => null,
				'note' => null,
			), $db);
		});
	}

	/**
	 * 管理者が貸出を返却済みに変更する。
	 *
	 * @param int   $actor_id 処理する管理者のID
	 * @param int   $id       対象貸出のID
	 * @param mixed $note     返却メモ
	 * @return void
	 */
	public function return_loan($actor_id, $id, $note = null)
	{
		$this->assert_positive_id($actor_id);
		$this->assert_positive_id($id);
		$note = $this->optional_text($note);

		$this->model->transaction(function ($db) use ($actor_id, $id, $note)
		{
			$this->employee_model->count_active_admins();

			if ( ! $this->employee_model->is_active_admin($actor_id, $db))
			{
				throw new \RuntimeException(
					'The return operator is not an active administrator.',
					static::FORBIDDEN_EXCEPTION_CODE
				);
			}

			$loan = $this->model->read_loan($id, $db);

			if ($loan === null)
			{
				throw new \RuntimeException(
					'The loan was not found.',
					static::NOT_FOUND_EXCEPTION_CODE
				);
			}

			if ( ! $this->equipment_model->exists_for_return(
				$loan['equipment_id'],
				$db
			))
			{
				throw new \RuntimeException(
					'The loan equipment is unavailable.',
					static::CONFLICT_EXCEPTION_CODE
				);
			}

			if ($loan['returned_at'] !== null)
			{
				throw new \RuntimeException(
					'The loan cannot be returned.',
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
		});
	}

	/**
	 * このServiceで使用する貸出Modelを生成する。
	 *
	 * @return Model_Table_Loan
	 */
	protected function new_model()
	{
		return new Model_Table_Loan();
	}

	/**
	 * Asia/Tokyo基準の業務日を返す。
	 *
	 * @return string
	 */
	protected function current_loan_date()
	{
		return \Date::time('Asia/Tokyo')->format('%Y-%m-%d');
	}

	/**
	 * 貸出日から90日以内の返却期限を確認する。
	 *
	 * @param mixed  $due_date  返却期限
	 * @param string $loaned_at 貸出日
	 * @return string
	 */
	protected function due_date($due_date, $loaned_at)
	{
		if ( ! is_string($due_date))
		{
			throw new \InvalidArgumentException('The due date is invalid.');
		}

		$timezone = new \DateTimeZone('Asia/Tokyo');
		$date = \DateTimeImmutable::createFromFormat('!Y-m-d', $due_date, $timezone);
		$errors = \DateTimeImmutable::getLastErrors();

		if ($date === false
			or ($errors !== false and ($errors['warning_count'] > 0 or $errors['error_count'] > 0))
			or $date->format('Y-m-d') !== $due_date)
		{
			throw new \InvalidArgumentException('The due date is invalid.');
		}

		$first = new \DateTimeImmutable($loaned_at, $timezone);
		$last = $first->modify('+'.static::MAX_LOAN_DAYS.' days');

		if ($date < $first or $date > $last)
		{
			throw new \InvalidArgumentException('The due date is outside the allowed range.');
		}

		return $due_date;
	}

	/**
	 * 貸出一覧へ状態を追加する。
	 *
	 * @param array  $rows  貸出一覧
	 * @param string $today 判定基準日
	 * @return array
	 */
	protected function add_loan_states(array $rows, $today)
	{
		foreach ($rows as $key => $loan)
		{
			$rows[$key]['loan_state'] = $loan['returned_at'] !== null
				? 'RETURNED'
				: ($loan['due_date'] < $today ? 'OVERDUE' : 'ON_LOAN');
		}

		return $rows;
	}
}
