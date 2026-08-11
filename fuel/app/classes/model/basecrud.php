<?php

/**
 * テーブルモデル間で共有するデータベース操作を提供する。
 */
abstract class Model_BaseCrud extends \Model
{
	/** @var string 子モデルが宣言する物理テーブル名 */
	protected static $table_name = '';

	/** @var array 子モデルが行をcreateするときに指定する列 */
	protected static $create_columns = array();

	/** @var array read処理が返す列 */
	protected static $read_columns = array();

	/** @var array 子モデルが行をupdateするときに指定する列 */
	protected static $update_columns = array();

	/** @var array キーワード検索の対象となるテキスト列 */
	protected static $search_columns = array();

	/** @var array データベース列に対応付けるリクエスト検索条件名 */
	protected static $filter_columns = array();

	/** @var array データベース文字列から整数へ変換する読取列 */
	protected static $integer_columns = array('id');

	/** @var Database_Connection 読取処理と単独更新処理に使用する標準データベース接続 */
	protected $db;

	/**
	 * 使用するDB接続を初期化する。
	 *
	 * @param  Database_Connection|string|null $db 使用するDB接続
	 * @return void
	 */
	public function __construct($db = null)
	{
		$this->db = $db instanceof \Database_Connection
			? $db
			: \Database_Connection::instance($db);
	}

    /**
     * 指定された値を登録し、DBが生成したIDを返す。
     *
     * @param  array                    $create_values 登録する値
     * @param  Database_Connection|null $db            使用するDB接続
     * @return int
     */
    public function create(array $create_values, $db = null)
    {
        $db = $this->connection($db);
        $this->assert_values($create_values, static::$create_columns, 'create');
        $create_values['created_at'] = \DB::expr('CURRENT_TIMESTAMP');
        $create_values['updated_at'] = \DB::expr('CURRENT_TIMESTAMP');

        $result = \DB::insert(static::$table_name)
            ->set($create_values)
            ->execute($db);

        if ( ! isset($result[0], $result[1]) or (int) $result[0] < 1 or (int) $result[1] !== 1)
        {
            throw new \RuntimeException('Failed to create the record.');
        }

        return (int) $result[0];
    }

	/**
	 * 子モデルが許可した読取列だけを使用して1行取得する。
	 *
	 * @param  int                      $id                   対象レコードのID
	 * @param  bool                     $include_soft_deleted 論理削除済みデータを含めるか
	 * @param  Database_Connection|null $db                   使用するDB接続
	 * @return array|null
	 */
	public function read($id, $include_soft_deleted = false, $db = null)
	{
		$db = $this->connection($db);
		$read_query = \DB::select_array(static::$read_columns)
			->from(static::$table_name)
			->where('id', '=', $id);

		if ( ! $include_soft_deleted)
		{
			$read_query->where('deleted_at', 'IS', null);
		}

		$read_rows = $read_query->execute($db)
			->as_array();

		return empty($read_rows) ? null : $this->format_row($read_rows[0]);
	}

	/**
	 * 固定の検索条件とページングで有効な行を検索する。
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

		$search_count_query = \DB::select(
			array(\DB::expr('COUNT(*)'), 'total')
		)
			->from(static::$table_name);
		$this->apply_search_conditions($search_count_query, $keyword, $filters);
		$search_count_result = $search_count_query->execute($this->db);
		$search_total = (int) $search_count_result->get('total', 0);

		$search_query = \DB::select_array(static::$read_columns)
			->from(static::$table_name);
		$this->apply_search_conditions($search_query, $keyword, $filters);
		$search_rows = $search_query
			->order_by('updated_at', 'DESC')
			->order_by('id', 'DESC')
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
	 * 子モデルが許可した列だけを更新する。
	 *
	 * @param  int                      $id            対象レコードのID
	 * @param  array                    $update_values 更新する値
	 * @param  Database_Connection|null $db            使用するDB接続
	 * @return int
	 */
	public function update($id, array $update_values, $db = null)
	{
		$db = $this->connection($db);
		$this->assert_values($update_values, static::$update_columns, 'update');
		$update_values['updated_at'] = \DB::expr('CURRENT_TIMESTAMP');

		return (int) \DB::update(static::$table_name)
			->set($update_values)
			->where('id', '=', $id)
			->where('deleted_at', 'IS', null)
			->execute($db);
	}

	/**
	 * 有効な1行を論理削除する。
	 *
	 * @param  int                      $id 対象レコードのID
	 * @param  Database_Connection|null $db 使用するDB接続
	 * @return int
	 */
	public function soft_delete($id, $db = null)
	{
		$db = $this->connection($db);
		return (int) \DB::update(static::$table_name)
			->set(array(
				'deleted_at' => \DB::expr('CURRENT_TIMESTAMP'),
				'updated_at' => \DB::expr('CURRENT_TIMESTAMP'),
			))
			->where('id', '=', $id)
			->where('deleted_at', 'IS', null)
			->execute($db);
	}

	/**
	 * 論理削除済みの1行を復元する。
	 *
	 * @param  int                      $id 対象レコードのID
	 * @param  Database_Connection|null $db 使用するDB接続
	 * @return int
	 */
	public function restore($id, $db = null)
	{
		$db = $this->connection($db);
		return (int) \DB::update(static::$table_name)
			->set(array(
				'deleted_at' => null,
				'updated_at' => \DB::expr('CURRENT_TIMESTAMP'),
			))
			->where('id', '=', $id)
			->where('deleted_at', 'IS NOT', null)
			->execute($db);
	}

	/**
	 * テーブル固有の業務更新前に1行取得する。
	 *
	 * @param  int                 $id                   対象レコードのID
	 * @param  bool                $include_soft_deleted 論理削除済みデータを含めるか
	 * @param  Database_Connection $db                   使用するDB接続
	 * @return array|null
	 */
	public function read_for_update($id, $include_soft_deleted, \Database_Connection $db)
	{
		$columns = array();

		foreach (static::$read_columns as $column)
		{
			$columns[] = $this->quoted_column($column, $db);
		}

		$sql = 'SELECT '.implode(', ', $columns)
			.' FROM '.$this->quoted_table($db)
			.' WHERE '.$this->quoted_column('id', $db).' = :id';

		if ( ! $include_soft_deleted)
		{
			$sql .= ' AND '.$this->quoted_column('deleted_at', $db).' IS NULL';
		}

		$read_rows = \DB::query($sql.' FOR UPDATE', \DB::SELECT)
			->param('id', $id)
			->execute($db)
			->as_array();

		return empty($read_rows) ? null : $this->format_row($read_rows[0]);
	}

	/**
	 * テーブル固有の業務更新を1つのトランザクションで実行する。
	 *
	 * @param  Closure $operation 実行する操作名
	 * @return mixed
	 */
	public function transaction(\Closure $operation)
	{
		if ( ! $this->db->start_transaction())
		{
			throw new \RuntimeException('Failed to start the CRUD transaction.');
		}

		try
		{
			$operation_result = $operation($this->db);

			if ( ! $this->db->commit_transaction())
			{
				throw new \RuntimeException('Failed to commit the CRUD transaction.');
			}

			return $operation_result;
		}
		catch (\Throwable $exception)
		{
			if ($this->db->in_transaction()
				and ! $this->db->rollback_transaction())
			{
				throw new \RuntimeException(
					'Failed to rollback the CRUD transaction.',
					0,
					$exception
				);
			}

			throw $exception;
		}
	}

	/**
	 * 1.有効行、2.キーワードによるID・名称検索、3.完全一致（部署、権限、カテゴリ）を適用する。
	 *
	 * @param  Database_Query_Builder_Where $search_query 検索クエリ
	 * @param  string                       $keyword      検索キーワード
	 * @param  array                        $filters      検索条件
	 * @return void
	 */
	protected function apply_search_conditions($search_query, $keyword, array $filters)
	{
		$search_query->where('deleted_at', 'IS', null);

		if ($keyword !== '')
		{
			$search_query->and_where_open();
			$has_condition = false;

			if (preg_match('/\A[1-9][0-9]*\z/', $keyword) === 1)
			{
				$search_query->where('id', '=', (int) $keyword);
				$has_condition = true;
			}

			foreach (static::$search_columns as $column)
			{
				if ($has_condition)
				{
					$search_query->or_where($column, 'LIKE', '%'.$keyword.'%');
				}
				else
				{
					$search_query->where($column, 'LIKE', '%'.$keyword.'%');
					$has_condition = true;
				}
			}

			$search_query->and_where_close();
		}

		foreach ($filters as $name => $value)
		{
			if ( ! array_key_exists($name, static::$filter_columns))
			{
				throw new \InvalidArgumentException('The search filter is not allowed.');
			}

			$search_query->where(static::$filter_columns[$name], '=', $value);
		}
	}

	/**
	 * 行一覧のデータベーススカラー型を変換する。
	 *
	 * @param  array $read_rows 取得した行
	 * @return array
	 */
	protected function format_rows(array $read_rows)
	{
		foreach ($read_rows as $key => $read_row)
		{
			$read_rows[$key] = $this->format_row($read_row);
		}

		return $read_rows;
	}

	/**
	 * データベース文字列として返された整数列を変換する。
	 *
	 * @param  array $read_row 取得した1行
	 * @return array
	 */
	protected function format_row(array $read_row)
	{
		foreach (static::$integer_columns as $column)
		{
			if (array_key_exists($column, $read_row) and $read_row[$column] !== null)
			{
				$read_row[$column] = (int) $read_row[$column];
			}
		}

		return $read_row;
	}

	/**
	 * 登録または更新値が子Modelで定義した列と完全一致することを確認する。
	 *
	 * @param array  $values          登録または更新する値
	 * @param array  $allowed_columns 指定できる列
	 * @param string $operation       実行する操作名
	 * @return void
	 */
	protected function assert_values(array $values, array $allowed_columns, $operation)
	{
		$keys = array_keys($values);

		if (array_diff($keys, $allowed_columns)
			or array_diff($allowed_columns, $keys))
		{
			throw new \InvalidArgumentException(
				'The '.$operation.' values do not match the allowed columns.'
			);
		}
	}

	/**
	 * トランザクション接続が指定された場合はその接続を使用する。
	 *
	 * @param  Database_Connection|null $db 使用するDB接続
	 * @return Database_Connection
	 */
	protected function connection($db = null)
	{
		return $db ?: $this->db;
	}

	/**
	 * 子モデルの固定テーブル名を引用符で囲む。
	 *
	 * @param  Database_Connection $db 使用するDB接続
	 * @return string
	 */
	protected function quoted_table(\Database_Connection $db)
	{
		return $db->quote_identifier($db->table_prefix(static::$table_name));
	}

	/**
	 * モデルが宣言した固定列名を引用符で囲む。
	 *
	 * @param  string              $column 対象列名
	 * @param  Database_Connection $db     使用するDB接続
	 * @return string
	 */
	protected function quoted_column($column, \Database_Connection $db)
	{
		return $db->quote_identifier($column);
	}
}
