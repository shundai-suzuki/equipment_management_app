<?php

/**
 * テーブルモデル間で共有するデータベース操作を提供する。
 *
 * @package  app
 */
abstract class Model_BaseCrud extends \Model
{
	/**
	 * 子モデルが宣言する物理テーブル名。
	 *
	 * @var string
	 */
	protected static $table_name = '';

	/**
	 * 子モデルが行を登録するときに受け付ける列。
	 *
	 * @var array
	 */
	protected static $create_columns = array();

	/**
	 * 共通読取処理が返す列。
	 *
	 * @var array
	 */
	protected static $read_columns = array();

	/**
	 * 共通更新処理が受け付ける列。
	 *
	 * @var array
	 */
	protected static $update_columns = array();

	/**
	 * キーワード検索の対象となるテキスト列。
	 *
	 * @var array
	 */
	protected static $search_columns = array();

	/**
	 * 固定のデータベース列に対応付けるリクエスト検索条件名。
	 *
	 * @var array
	 */
	protected static $filter_columns = array();

	/**
	 * データベース文字列から整数へ変換する読取列。
	 *
	 * @var array
	 */
	protected static $integer_columns = array('id');

	/**
	 * 読取処理と単独更新処理に使用する標準データベース接続。
	 *
	 * @var Database_Connection
	 */
	protected $db;

	/**
	 * @param  Database_Connection|string|null  $db
	 */
	public function __construct($db = null)
	{
		$this->db = $db instanceof \Database_Connection
			? $db
			: \Database_Connection::instance($db);
	}

    /**
     * 許可された値を登録し、DBが生成したIDを返す。
     *
     * @param   array                     $create_values
     * @param   Database_Connection|null  $db
     * @return  int
     */
    public function create(array $create_values, $db = null)
    {
        $db = $this->connection($db);
        $this->assert_identifier(static::$table_name);
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
	 * @param   int                       $id
	 * @param   bool                      $include_soft_deleted
	 * @param   Database_Connection|null  $db
	 * @return  array|null
	 */
	public function read($id, $include_soft_deleted = false, $db = null)
	{
		$db = $this->connection($db);
		$this->assert_crud_configuration();

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
	 * @param   int     $page
	 * @param   int     $per_page
	 * @param   string  $keyword
	 * @param   array   $filters
	 * @return  array
	 */
	public function search($page, $per_page, $keyword = '', array $filters = array())
	{
		$this->assert_crud_configuration();
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
	 * @param   int                       $id
	 * @param   array                     $update_values
	 * @param   Database_Connection|null  $db
	 * @return  int
	 */
	public function update($id, array $update_values, $db = null)
	{
		$db = $this->connection($db);
		$this->assert_crud_configuration();
		$this->assert_values(
			$update_values,
			static::$update_columns,
			'update'
		);
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
	 * @param   int                       $id
	 * @param   Database_Connection|null  $db
	 * @return  int
	 */
	public function soft_delete($id, $db = null)
	{
		$db = $this->connection($db);
		$this->assert_crud_configuration();

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
	 * @param   int                       $id
	 * @param   Database_Connection|null  $db
	 * @return  int
	 */
	public function restore($id, $db = null)
	{
		$db = $this->connection($db);
		$this->assert_crud_configuration();

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
	 * @param   int                  $id
	 * @param   bool                 $include_soft_deleted
	 * @param   Database_Connection  $db
	 * @return  array|null
	 */
	public function read_for_update($id, $include_soft_deleted, \Database_Connection $db)
	{
		$this->assert_crud_configuration();
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
	 * @param   Closure  $operation
	 * @return  mixed
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
	 * @param   Database_Query_Builder_Where  $search_query
	 * @param   string                        $keyword
	 * @param   array                         $filters
	 * @return  void
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
				$this->assert_identifier($column);

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

			$column = static::$filter_columns[$name];
			$this->assert_identifier($column);
			$search_query->where($column, '=', $value);
		}
	}

	/**
	 * 行一覧のデータベーススカラー型を変換する。
	 *
	 * @param   array  $read_rows
	 * @return  array
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
	 * @param   array  $read_row
	 * @return  array
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
	 * 子モデルが宣言した固定CRUDメタデータを検証する。
	 *
	 * @return  void
	 */
	protected function assert_crud_configuration()
	{
		$this->assert_identifier(static::$table_name);

		if (empty(static::$read_columns))
		{
			throw new \LogicException('The Model read columns are not configured.');
		}

		foreach (static::$read_columns as $column)
		{
			$this->assert_identifier($column);
		}
	}

	/**
	 * CRUD処理で宣言された列だけが含まれることを必須とする。
	 *
	 * @param   array   $values
	 * @param   array   $allowed_columns
	 * @param   string  $operation
	 * @return  void
	 */
	protected function assert_values(array $values,	array $allowed_columns, $operation)
	{
		foreach ($allowed_columns as $column)
		{
			$this->assert_identifier($column);
		}

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
	 * @param   Database_Connection|null  $db
	 * @return  Database_Connection
	 */
	protected function connection($db = null)
	{
		return $db ?: $this->db;
	}

	/**
	 * 子モデルの固定テーブル名を引用符で囲む。
	 *
	 * @param   Database_Connection  $db
	 * @return  string
	 */
	protected function quoted_table(\Database_Connection $db)
	{
		$this->assert_identifier(static::$table_name);

		return $db->quote_identifier($db->table_prefix(static::$table_name));
	}

	/**
	 * モデルが宣言した固定列名を引用符で囲む。
	 *
	 * @param   string               $column
	 * @param   Database_Connection  $db
	 * @return  string
	 */
	protected function quoted_column($column, \Database_Connection $db)
	{
		$this->assert_identifier($column);

		return $db->quote_identifier($column);
	}

	/**
	 * テーブル・列識別子が安全なクラス定義に由来することを確認する。
	 *
	 * @param   string  $identifier
	 * @return  void
	 */
	protected function assert_identifier($identifier)
	{
		if ( ! is_string($identifier)
			or preg_match('/\A[a-z][a-z0-9_]*\z/', $identifier) !== 1)
		{
			throw new \LogicException('The Model contains an invalid database identifier.');
		}
	}
}
