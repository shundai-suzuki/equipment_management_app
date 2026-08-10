<?php

/**
 * 備品在庫のデータベース操作を提供する。
 */
class Model_Table_Equipment extends Model_BaseCrud
{
	/** @var string このモデルが操作するテーブル */
	protected static $table_name = 'equipments';

	/** @var array 備品の新規行で受け付ける登録値 */
	protected static $create_columns = array(
		'name',
		'department_id',
		'category',
		'total_amount',
		'description',
	);

	/** @var array 備品CRUDの読取処理が返す列 */
	protected static $read_columns = array(
		'id',
		'name',
		'department_id',
		'category',
		'total_amount',
		'description',
		'created_at',
		'updated_at',
		'deleted_at',
	);

	/** @var array 備品更新で受け付ける列 */
	protected static $update_columns = array(
		'name',
		'department_id',
		'category',
		'total_amount',
		'description',
	);

	/** @var array キーワード検索の対象となる備品列 */
	protected static $search_columns = array('name');

	/** @var array 備品検索で完全一致させる条件 */
	protected static $filter_columns = array(
		'department_id' => 'department_id',
		'category' => 'category',
	);

	/** @var array 整数として返す備品列 */
	protected static $integer_columns = array(
		'id',
		'department_id',
		'total_amount',
		'loaned_amount',
		'available_amount',
	);

	/**
	 * 一意な部署・名称の組み合わせで備品を取得する。
	 *
	 * @param  int                      $department_id 部署ID
	 * @param  string                   $name          対象の名前
	 * @param  Database_Connection|null $db            使用するDB接続
	 * @return array|null
	 */
	public function read_by_department_and_name($department_id, $name, $db = null)
	{
		$db = $this->connection($db);
		$read_result = \DB::select('id', 'department_id', 'name', 'deleted_at')
			->from(static::$table_name)
			->where('department_id', '=', $department_id)
			->where('name', '=', $name)
			->execute($db);

		if (count($read_result) === 0)
		{
			return null;
		}

		return array(
			'id' => (int) $read_result->get('id'),
			'department_id' => (int) $read_result->get('department_id'),
			'name' => $read_result->get('name'),
			'deleted_at' => $read_result->get('deleted_at'),
		);
	}

	/**
	 * 一覧検索用に有効な備品カテゴリを返す。
	 *
	 * @return array
	 */
	public function read_category_options()
	{
		$read_rows = \DB::select('category')
			->distinct()
			->from(static::$table_name)
			->where('deleted_at', 'IS', null)
			->order_by('category', 'ASC')
			->execute($this->db)
			->as_array();
		return array_column($read_rows, 'category');
	}

	/**
	 * 算出した貸出数量を含めて備品を検索する。
	 *
	 * @param  int    $page     取得するページ番号
	 * @param  int    $per_page 1ページ当たりの表示件数
	 * @param  string $keyword  検索キーワード
	 * @param  array  $filters  検索条件
	 * @return array
	 */
	public function search($page, $per_page, $keyword = '', array $filters = array())
	{
		$search_offset = ($page - 1) * $per_page;
		$search_count_query = $this->equipment_query(
			array(array(\DB::expr('COUNT(*)'), 'total'))
		);
		$this->apply_equipment_conditions(
			$search_count_query,
			$keyword,
			$filters
		);
		$search_total = (int) $search_count_query
			->execute($this->db)
			->get('total', 0);

		$search_query = $this->equipment_query(
			$this->equipment_select_columns()
		);
		$this->apply_equipment_conditions($search_query, $keyword, $filters);
		$search_rows = $search_query
			->order_by('equipments.updated_at', 'DESC')
			->order_by('equipments.id', 'DESC')
			->limit($per_page)
			->offset($search_offset)
			->execute($this->db)
			->as_array();

		return array(
			'rows' => $this->format_rows($search_rows),
			'total' => $search_total,
		);
	}

	/**
	 * 貸出中件数を集計する固定結合を生成する。
	 *
	 * @param  array $columns 取得する列
	 * @return Database_Query_Builder_Select
	 */
	protected function equipment_query(array $columns)
	{
		$active_loans = \DB::select(
			'equipment_id',
			array(\DB::expr('COUNT(*)'), 'loaned_amount')
		)
			->from('loans')
			->where('returned_at', 'IS', null)
			->group_by('equipment_id');

		return \DB::select_array($columns)
			->from('equipments')
			->join(array($active_loans, 'active_loans'), 'LEFT')
			->on('equipments.id', '=', 'active_loans.equipment_id');
	}

	/**
	 * 固定の備品列と算出数量を返す。
	 *
	 * @return array
	 */
	protected function equipment_select_columns()
	{
		$loaned_amount = 'COALESCE(active_loans.loaned_amount, 0)';

		return array(
			array('equipments.id', 'id'),
			array('equipments.name', 'name'),
			array('equipments.department_id', 'department_id'),
			array('equipments.category', 'category'),
			array('equipments.total_amount', 'total_amount'),
			array(\DB::expr($loaned_amount), 'loaned_amount'),
			array(
				\DB::expr('equipments.total_amount - '.$loaned_amount),
				'available_amount'
			),
			array('equipments.description', 'description'),
			array('equipments.created_at', 'created_at'),
			array('equipments.updated_at', 'updated_at'),
			array('equipments.deleted_at', 'deleted_at'),
		);
	}

	/**
	 * 有効行、キーワード、備品の完全一致条件を適用する。
	 *
	 * @param  Database_Query_Builder_Select $query   検索クエリ
	 * @param  string                        $keyword 検索キーワード
	 * @param  array                         $filters 検索条件
	 * @return void
	 */
	protected function apply_equipment_conditions($query, $keyword, array $filters)
	{
		$allowed_filters = array('department_id', 'category', 'available_only');

		if (array_diff(array_keys($filters), $allowed_filters))
		{
			throw new \InvalidArgumentException(
				'The equipment search filter is not allowed.'
			);
		}

		$query->where('equipments.deleted_at', 'IS', null);

		if ($keyword !== '')
		{
			$query->and_where_open();

			if (preg_match('/\A[1-9][0-9]*\z/', $keyword) === 1)
			{
				$query->where('equipments.id', '=', (int) $keyword)
					->or_where('equipments.name', 'LIKE', '%'.$keyword.'%');
			}
			else
			{
				$query->where('equipments.name', 'LIKE', '%'.$keyword.'%');
			}

			$query->and_where_close();
		}

		if (isset($filters['department_id']))
		{
			$query->where(
				'equipments.department_id',
				'=',
				$filters['department_id']
			);
		}

		if (isset($filters['category']))
		{
			$query->where('equipments.category', '=', $filters['category']);
		}

		if (isset($filters['available_only']))
		{
			if ( ! is_bool($filters['available_only']))
			{
				throw new \InvalidArgumentException(
					'available_only must be a boolean.'
				);
			}

			if ($filters['available_only'])
			{
				$query->where(
					\DB::expr(
						'equipments.total_amount'
						.' - COALESCE(active_loans.loaned_amount, 0)'
					),
					'>',
					0
				);
			}
		}
	}

	/**
	 * 備品1件の未返却貸出数を数える。
	 *
	 * @param  int                      $id 対象レコードのID
	 * @param  Database_Connection|null $db 使用するDB接続
	 * @return int
	 */
	public function count_active_loans($id, $db = null)
	{
		$db = $this->connection($db);
		$read_count_result = \DB::select(array(\DB::expr('COUNT(*)'), 'total'))
			->from('loans')
			->where('equipment_id', '=', $id)
			->where('returned_at', 'IS', null)
			->execute($db);

		return (int) $read_count_result->get('total', 0);
	}

	/**
	 * 備品1件に貸出履歴があるか確認する。
	 *
	 * @param  int                      $id 対象レコードのID
	 * @param  Database_Connection|null $db 使用するDB接続
	 * @return bool
	 */
	public function has_loan_history($id, $db = null)
	{
		$db = $this->connection($db);
		$read_result = \DB::select(
			array(\DB::expr('1'), 'loan_exists')
		)
			->from('loans')
			->where('equipment_id', '=', $id)
			->limit(1)
			->execute($db);

		return (int) $read_result->get('loan_exists', 0) === 1;
	}

	/**
	 * 有効な備品をロックし、現在の利用可能数を算出する。
	 *
	 * @param  int                 $id 対象レコードのID
	 * @param  Database_Connection $db 使用するDB接続
	 * @return array|null
	 */
	public function lock_available($id, \Database_Connection $db)
	{
		$id_column = $this->quoted_column('id', $db);
		$total_amount = $this->quoted_column('total_amount', $db);
		$deleted_at = $this->quoted_column('deleted_at', $db);

		$read_equipment_result = \DB::query(
			'SELECT '.$id_column.', '.$total_amount
			.' FROM '.$this->quoted_table($db)
			.' WHERE '.$id_column.' = :id AND '.$deleted_at.' IS NULL FOR UPDATE',
			\DB::SELECT
		)
			->param('id', $id)
			->execute($db);

		if (count($read_equipment_result) === 0)
		{
			return null;
		}

		$read_loan_count = \DB::select(
			array(\DB::expr('COUNT(*)'), 'loaned_amount')
		)
			->from('loans')
			->where('equipment_id', '=', $id)
			->where('returned_at', 'IS', null)
			->execute($db);

		$total = (int) $read_equipment_result->get('total_amount');
		$loaned_amount = (int) $read_loan_count->get('loaned_amount', 0);
		$available = $total - $loaned_amount;

		if ($available < 0)
		{
			throw new \RuntimeException('The equipment inventory is inconsistent.');
		}

		return array(
			'id' => (int) $read_equipment_result->get('id'),
			'total_amount' => $total,
			'loaned_amount' => $loaned_amount,
			'available_amount' => $available,
		);
	}

	/**
	 * 返却処理に必要な備品行をロックする。
	 *
	 * @param  int                 $id 対象レコードのID
	 * @param  Database_Connection $db 使用するDB接続
	 * @return bool
	 */
	public function lock_for_return($id, \Database_Connection $db)
	{
		$id_column = $this->quoted_column('id', $db);
		$read_result = \DB::query(
			'SELECT '.$id_column
			.' FROM '.$this->quoted_table($db)
			.' WHERE '.$id_column.' = :id FOR UPDATE',
			\DB::SELECT
		)
			->param('id', $id)
			->execute($db);

		return count($read_result) === 1;
	}
}
