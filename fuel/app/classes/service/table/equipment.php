<?php

/**
 * 備品の業務規則を適用する。
 */
class Service_Table_Equipment extends Service_BaseCrud
{
	/** @var int 備品名の最大文字数 */
	const MAX_NAME_LENGTH = 255;
	/** @var int カテゴリの最大文字数 */
	const MAX_CATEGORY_LENGTH = 20;
	/** @var int 説明の最大文字数 */
	const MAX_DESCRIPTION_LENGTH = 255;

	/** @var Model_Table_Department 部署を確認するModel */
	protected $department_model;

	/**
	 * 備品と部署のModelを初期化する。
	 *
	 * @param object|null $model            使用する備品Model
	 * @param object|null $department_model 使用する部署Model
	 * @return void
	 */
	public function __construct($model = null, $department_model = null)
	{
		parent::__construct($model);
		$this->department_model = $department_model ?: new Model_Table_Department();
	}

	/**
	 * 備品を登録するか、同じ削除済み備品を復元する。
	 *
	 * @param mixed $name          備品名
	 * @param int   $department_id 管理部署ID
	 * @param mixed $category      カテゴリ
	 * @param int   $total_amount  総数
	 * @param mixed $description   説明
	 * @return int
	 */
	public function create($name, $department_id, $category, $total_amount, $description = null)
	{
		$values = $this->values(
			$name,
			$department_id,
			$category,
			$total_amount,
			$description
		);
		$this->assert_active_department($department_id);
		$equipment = $this->model->read_by_department_and_name(
			$department_id,
			$values['name']
		);

		if ($equipment === null)
		{
			return $this->create_record($values);
		}

		if ($equipment['deleted_at'] === null)
		{
			throw new \RuntimeException(
				'An equipment record with the same name already exists.',
				static::CONFLICT_EXCEPTION_CODE
			);
		}

		$this->restore_record((int) $equipment['id']);
		return (int) $equipment['id'];
	}

	/**
	 * 備品一覧とカテゴリ候補を取得する。
	 *
	 * @param int    $page    取得するページ番号
	 * @param string $keyword 検索キーワード
	 * @param array  $filters 検索条件
	 * @return array
	 */
	public function search($page, $keyword = '', array $filters = array())
	{
		$result = parent::search($page, $keyword, $filters);
		$result['category_options'] = $this->model->read_category_options();

		return $result;
	}

	/**
	 * 備品の管理情報を更新する。
	 *
	 * @param int   $id            対象備品のID
	 * @param mixed $name          備品名
	 * @param int   $department_id 管理部署ID
	 * @param mixed $category      カテゴリ
	 * @param int   $total_amount  総数
	 * @param mixed $description   説明
	 * @return void
	 */
	public function update($id, $name, $department_id, $category, $total_amount, $description = null)
	{
		$this->assert_positive_id($id);
		$equipment = $this->read($id);
		$values = $this->values(
			$name,
			$department_id,
			$category,
			$total_amount,
			$description
		);
		$this->assert_active_department($department_id);

		if ((int) $equipment['department_id'] !== $department_id
			and $this->model->has_loan_history($id))
		{
			throw new \RuntimeException(
				'Equipment with loan history cannot change departments.',
				static::CONFLICT_EXCEPTION_CODE
			);
		}

		if ($total_amount < $this->model->count_active_loans($id))
		{
			throw new \InvalidArgumentException('The total amount is too small.');
		}

		$this->model->update($id, $values);
	}

	/**
	 * 未返却貸出のない備品を論理削除する。
	 *
	 * @param int $id 対象備品のID
	 * @return void
	 */
	public function soft_delete($id)
	{
		$this->read($id);

		if ($this->model->count_active_loans($id) > 0)
		{
			throw new \RuntimeException(
				'Equipment with an active loan cannot be archived.',
				static::CONFLICT_EXCEPTION_CODE
			);
		}

		$this->model->soft_delete($id);
	}

	/**
	 * このServiceで使用する備品Modelを生成する。
	 *
	 * @return Model_Table_Equipment
	 */
	protected function new_model()
	{
		return new Model_Table_Equipment();
	}

	/**
	 * 備品の登録・更新値を作成する。
	 *
	 * @param mixed $name          備品名
	 * @param int   $department_id 管理部署ID
	 * @param mixed $category      カテゴリ
	 * @param int   $total_amount  総数
	 * @param mixed $description   説明
	 * @return array
	 */
	protected function values($name, $department_id, $category, $total_amount, $description)
	{
		$this->assert_positive_id($department_id);

		if ( ! is_int($total_amount) or $total_amount < 1)
		{
			throw new \InvalidArgumentException('The total amount is invalid.');
		}

		return array(
			'name' => $this->required_text($name, static::MAX_NAME_LENGTH),
			'department_id' => $department_id,
			'category' => $this->required_text($category, static::MAX_CATEGORY_LENGTH),
			'total_amount' => $total_amount,
			'description' => $this->optional_text(
				$description,
				static::MAX_DESCRIPTION_LENGTH
			),
		);
	}

	/**
	 * 指定部署が利用可能か確認する。
	 *
	 * @param int $department_id 管理部署ID
	 * @return void
	 */
	protected function assert_active_department($department_id)
	{
		if ( ! $this->department_model->is_active($department_id))
		{
			throw new \InvalidArgumentException(
				'The selected department is not available.'
			);
		}
	}
}
