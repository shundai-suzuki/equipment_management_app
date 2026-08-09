<?php

/**
 * 備品登録規則を適用し、永続化を委譲する。
 *
 * @package  app
 */
class Service_Table_Equipment extends Service_BaseCrud
{
	const MAX_NAME_LENGTH = 255;
	const MAX_CATEGORY_LENGTH = 20;
	const MAX_DESCRIPTION_LENGTH = 255;
	const MAX_TOTAL_AMOUNT = 2147483647;

	/**
	 * このサービスが登録するテーブル。
	 *
	 * @var string
	 */
	protected static $table_name = 'equipments';

	/**
	 * 管理部署の検証に使用する部署モデル。
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
	 * 備品を登録するか、一致する論理削除済み在庫行を復元する。
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
		$read_equipment = $this->model->read_by_department_and_name($department_id, $name);

		if ($read_equipment !== null)
		{
			return $this->restore_or_reject($read_equipment);
		}

		$create_values = array(
			'name' => $name,
			'department_id' => $department_id,
			'category' => $category,
			'total_amount' => $total_amount,
			'description' => $description,
		);

		try
		{
			return $this->create_record($create_values);
		}
		catch (\Database_Exception $exception)
		{
			return $this->handle_create_exception($department_id, $name, $exception);
		}
	}

	/**
	 * 管理者を再確認してから備品を登録する。
	 *
	 * @param   int          $actor_id
	 * @param   int          $id
	 * @param   string       $name
	 * @param   int          $department_id
	 * @param   string       $category
	 * @param   int          $total_amount
	 * @param   string|null  $description
	 * @return  int
	 */
	public function create_for_admin($actor_id,	$id, $name,	$department_id,	$category, $total_amount,	$description = null)
	{
		$this->assert_admin_actor($actor_id);

		return $this->create(
			$id,
			$name,
			$department_id,
			$category,
			$total_amount,
			$description
		);
	}

	/**
	 * 備品一覧と有効なカテゴリ候補を返す。
	 *
	 * @param   int     $page
	 * @param   string  $keyword
	 * @param   array   $filters
	 * @return  array
	 */
	public function search($page, $keyword = '', array $filters = array())
	{
		$search_result = parent::search(
			$page,
			$keyword,
			$filters
		);
		$search_result['category_options'] = $this->model->read_category_options();

		return $search_result;
	}

	/**
	 * 更新可能な備品項目を更新する。
	 *
	 * @param   int          $actor_id
	 * @param   int          $id
	 * @param   string       $name
	 * @param   int          $department_id
	 * @param   string       $category
	 * @param   int          $total_amount
	 * @param   string|null  $description
	 * @return  array
	 */
	public function update_for_admin($actor_id,	$id, $name,	$department_id,	$category, $total_amount,	$description = null)
	{
		$this->assert_positive_id($id, 'The equipment ID');
		$this->assert_positive_id($department_id, 'The department ID');

		$name = $this->normalize_required_text($name, 'The equipment name', static::MAX_NAME_LENGTH);
		$category = $this->normalize_required_text($category, 'The equipment category', static::MAX_CATEGORY_LENGTH);
		$total_amount = $this->normalize_total_amount($total_amount);
		$description = $this->normalize_description($description);

		return $this->model->transaction(
			function ($db) use ($actor_id, $id,	$name, $department_id, $category,	$total_amount, $description)
			{
				$this->assert_admin_actor($actor_id, $db);
				$equipment_before_update = $this->model->read_for_update($id, false, $db);

				if ($equipment_before_update === null)
				{
					throw new RuntimeException(
						'The equipment was not found.',
						static::NOT_FOUND_EXCEPTION_CODE
					);
				}

				$this->assert_active_department($department_id, $db);

				if ((int) $equipment_before_update['department_id'] !== $department_id
					and $this->model->has_loan_history($id, $db))
				{
					throw new RuntimeException(
						'Equipment with lending history cannot change departments.',
						static::CONFLICT_EXCEPTION_CODE
					);
				}

				$read_duplicate = $this->model->read_by_department_and_name(
					$department_id,
					$name,
					$db
				);

				if ($read_duplicate !== null and (int) $read_duplicate['id'] !== $id)
				{
					throw new RuntimeException(
						'Equipment with the same department and name already exists.',
						static::CONFLICT_EXCEPTION_CODE
					);
				}

				if ($total_amount < $this->model->count_active_loans($id, $db))
				{
					throw new RuntimeException(
						'The total amount cannot be less than the active loan count.',
						static::VALIDATION_EXCEPTION_CODE
					);
				}

				return $this->update_and_read_record(
					$id,
					array(
						'name' => $name,
						'department_id' => $department_id,
						'category' => $category,
						'total_amount' => $total_amount,
						'description' => $description,
					),
					$db
				);
			}
		);
	}

	/**
	 * 貸出中データがない備品を論理削除する。
	 *
	 * @param   int     $actor_id
	 * @param   int     $id
	 * @return  array
	 */
	public function soft_delete_for_admin($actor_id, $id)
	{
		$this->assert_positive_id($id, 'The equipment ID');

		return $this->model->transaction(
			function ($db) use ($actor_id, $id)
			{
				$this->assert_admin_actor($actor_id, $db);

				$equipment_before_delete = $this->model->read_for_update($id, true, $db);

				if ($equipment_before_delete === null)
				{
					throw new RuntimeException(
						'The equipment was not found.',
						static::NOT_FOUND_EXCEPTION_CODE
					);
				}

				if ($equipment_before_delete['deleted_at'] !== null)
				{
					throw new RuntimeException(
						'The equipment is already archived.',
						static::CONFLICT_EXCEPTION_CODE
					);
				}

				if ($this->model->count_active_loans($id, $db) > 0)
				{
					throw new RuntimeException(
						'Equipment with an active loan cannot be archived.',
						static::CONFLICT_EXCEPTION_CODE
					);
				}

				return $this->soft_delete_and_read_record($id, $db);
			}
		);
	}

	/**
	 * このサービスで使用する備品モデルを生成する。
	 *
	 * @return  Model_Table_Equipment
	 */
	protected function new_model()
	{
		return new Model_Table_Equipment();
	}

	/**
	 * 採番用トランザクション接続上で部署を再確認する。
	 *
	 * @param   array                $create_values
	 * @param   Database_Connection  $db
	 * @return  void
	 */
	protected function before_create(array $create_values, \Database_Connection $db)
	{
		$this->assert_active_department($create_values['department_id'], $db);
	}

	/**
	 * テーブル固有の上限で必須テキスト項目を正規化する。
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
	 * 任意の備品説明を正規化する。
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
	 * 備品総数が符号付きINT範囲の正の整数であることを必須とする。
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
	 * トランザクション更新中に部署を必須としてロックする。
	 *
	 * @param   int                       $department_id
	 * @param   Database_Connection|null  $db
	 * @return  void
	 */
	protected function assert_active_department($department_id, $db = null)
	{
		if ($db instanceof \Database_Connection)
		{
			if ($this->department_model->read_for_update($department_id, false,	$db) !== null)
			{
				return;
			}
		}
		elseif ($this->department_model->is_active($department_id))
		{
			return;
		}

		throw new \InvalidArgumentException(
			'The selected department is not available.'
		);
	}

	/**
	 * 一致する論理削除済み行を復元するか、有効な重複行を拒否する。
	 *
	 * @param   array  $read_equipment
	 * @return  int
	 */
	protected function restore_or_reject(array $read_equipment)
	{
		if ($read_equipment['deleted_at'] === null)
		{
			throw new \RuntimeException(
				'Equipment with the same department and name already exists.',
				static::CONFLICT_EXCEPTION_CODE
			);
		}

		$id = (int) $read_equipment['id'];

		return $this->model->transaction(
			function ($db) use ($id)
			{
				$equipment_before_restore = $this->model->read_for_update(
					$id,
					true,
					$db
				);

				if ($equipment_before_restore === null)
				{
					throw new \RuntimeException(
						'The archived equipment could not be read.',
						static::CONFLICT_EXCEPTION_CODE
					);
				}

				if ($equipment_before_restore['deleted_at'] === null)
				{
					return $id;
				}

				$this->assert_active_department(
					(int) $equipment_before_restore['department_id'],
					$db
				);
				$this->restore_and_read_record($id, $db);

				return $id;
			}
		);
	}

	/**
	 * 部署・名称の組み合わせの重複を競合へ変換する。
	 *
	 * @param   int                 $department_id
	 * @param   string              $name
	 * @param   Database_Exception  $exception
	 * @return  int
	 */
	protected function handle_create_exception($department_id, $name, \Database_Exception $exception)
	{
		if ((int) $exception->getCode() !== 1062)
		{
			throw $exception;
		}

		$read_equipment = $this->model->read_by_department_and_name($department_id, $name);

		if ($read_equipment === null)
		{
			throw $exception;
		}

		return $this->restore_or_reject($read_equipment);
	}
}
