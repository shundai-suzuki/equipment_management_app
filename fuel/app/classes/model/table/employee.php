<?php

/**
 * 社員のデータベース操作を提供する。
 *
 * @package  app
 */
class Model_Table_Employee extends Model_BaseCrud
{
	/**
	 * このモデルが操作するテーブル。
	 *
	 * @var string
	 */
	protected static $table_name = 'employees';

	/**
	 * 社員の新規行で受け付ける登録値。
	 *
	 * @var array
	 */
	protected static $create_columns = array(
		'employee_name',
		'department_id',
		'role',
		'password_hash',
		'is_active',
	);

	/**
	 * CRUDの読取処理が返す安全な社員列。
	 *
	 * @var array
	 */
	protected static $read_columns = array(
		'id',
		'employee_name',
		'department_id',
		'role',
		'is_active',
		'created_at',
		'updated_at',
		'deleted_at',
	);

	/**
	 * 通常の社員更新で受け付ける列。
	 *
	 * @var array
	 */
	protected static $update_columns = array(
		'employee_name',
		'department_id',
		'role',
	);

	/** キーワード検索の対象となる社員列。
	 *
	 * @var array
	*/
	protected static $search_columns = array('employee_name');

	/**
	 * 社員検索で完全一致させる条件。
	 *
	 * @var array
	 */
	protected static $filter_columns = array(
		'department_id' => 'department_id',
		'role' => 'role',
		'is_active' => 'is_active',
	);

	/**
	 * 整数として返す社員列。
	 *
	 * @var array
	 */
	protected static $integer_columns = array('id', 'department_id', 'is_active');

	/**
	 * 認証に必要な社員項目だけを取得する。
	 *
	 * Service_Authが共通の認証結果を適用する前にパスワードを確認できるよう、
	 * 無効な行と論理削除済み行も返す。
	 *
	 * @param   int                       $id
	 * @param   Database_Connection|null  $db
	 * @return  array|null
	 */
	public function read_for_authentication($id, $db = null)
	{
		$db = $this->connection($db);
		$read_result = \DB::select(
			'id',
			'employee_name',
			'role',
			'password_hash',
			'is_active',
			'deleted_at'
		)
			->from(static::$table_name)
			->where('id', '=', $id)
			->execute($db);

		if (count($read_result) === 0)
		{
			return null;
		}

		return array(
			'id' => (int) $read_result->get('id'),
			'employee_name' => $read_result->get('employee_name'),
			'role' => $read_result->get('role'),
			'password_hash' => $read_result->get('password_hash'),
			'is_active' => (int) $read_result->get('is_active'),
			'deleted_at' => $read_result->get('deleted_at'),
		);
	}

	/**
	 * 社員が有効かつ未削除か確認する。
	 *
	 * @param   int                       $id
	 * @param   Database_Connection|null  $db
	 * @return  bool
	 */
	public function is_active($id, $db = null)
	{
		return $this->has_active_role($id, null, $db);
	}

	/**
	 * 社員が有効な管理者か確認する。
	 *
	 * @param   int                       $id
	 * @param   Database_Connection|null  $db
	 * @return  bool
	 */
	public function is_active_admin($id, $db = null)
	{
		return $this->has_active_role($id, 'ADMIN', $db);
	}

	/**
	 * 有効な管理者をすべてロックし、その件数を返す。
	 *
	 * @param   Database_Connection  $db
	 * @return  int
	 */
	public function lock_active_admin_count(\Database_Connection $db)
	{
		$id = $this->quoted_column('id', $db);
		$role = $this->quoted_column('role', $db);
		$is_active = $this->quoted_column('is_active', $db);
		$deleted_at = $this->quoted_column('deleted_at', $db);
		$read_admin_rows = \DB::query(
			'SELECT '.$id.' FROM '.$this->quoted_table($db)
			.' WHERE '.$role.' = :role'
			.' AND '.$is_active.' = 1'
			.' AND '.$deleted_at.' IS NULL FOR UPDATE',
			\DB::SELECT
		)
			->param('role', 'ADMIN')
			->execute($db);

		return count($read_admin_rows);
	}

	/**
	 * 社員に貸出履歴があるか確認する。
	 *
	 * @param   int                       $id
	 * @param   Database_Connection|null  $db
	 * @return  bool
	 */
	public function has_loan_history($id, $db = null)
	{
		$db = $this->connection($db);
		$read_result = \DB::select(
			array(\DB::expr('1'), 'loan_exists')
		)
			->from('loans')
			->where('employee_id', '=', $id)
			->limit(1)
			->execute($db);

		return (int) $read_result->get('loan_exists', 0) === 1;
	}

	/**
	 * 社員が現在未返却の備品を持っているか確認する。
	 *
	 * @param   int                       $id
	 * @param   Database_Connection|null  $db
	 * @return  bool
	 */
	public function has_active_loans($id, $db = null)
	{
		$db = $this->connection($db);
		$read_result = \DB::select(
			array(\DB::expr('1'), 'active_loan_exists')
		)
			->from('loans')
			->where('employee_id', '=', $id)
			->where('returned_at', 'IS', null)
			->limit(1)
			->execute($db);

		return (int) $read_result->get('active_loan_exists', 0) === 1;
	}

	/**
	 * 社員の論理削除とアカウント無効化を不可分に実行する。
	 *
	 * @param   int                       $id
	 * @param   Database_Connection|null  $db
	 * @return  int
	 */
	public function soft_delete($id, $db = null)
	{
		$db = $this->connection($db);

		return (int) \DB::update(static::$table_name)
			->set(array(
				'is_active' => 0,
				'deleted_at' => \DB::expr('CURRENT_TIMESTAMP'),
				'updated_at' => \DB::expr('CURRENT_TIMESTAMP'),
			))
			->where('id', '=', $id)
			->where('deleted_at', 'IS', null)
			->execute($db);
	}

	/**
	 * 一時的なアカウント利用状態を変更する。
	 *
	 * @param   int                       $id
	 * @param   int                       $is_active
	 * @param   Database_Connection|null  $db
	 * @return  int
	 */
	public function update_active_state($id, $is_active, $db = null)
	{
		$db = $this->connection($db);

		return (int) \DB::update(static::$table_name)
			->set(array(
				'is_active' => $is_active,
				'updated_at' => \DB::expr('CURRENT_TIMESTAMP'),
			))
			->where('id', '=', $id)
			->where('is_active', '!=', $is_active)
			->where('deleted_at', 'IS', null)
			->execute($db);
	}

	/**
	 * 未削除社員1件のパスワードハッシュを置き換える。
	 *
	 * @param   int                       $id
	 * @param   string                    $password_hash
	 * @param   Database_Connection|null  $db
	 * @return  int
	 */
	public function update_password_hash($id, $password_hash, $db = null)
	{
		$db = $this->connection($db);

		return (int) \DB::update(static::$table_name)
			->set(array(
				'password_hash' => $password_hash,
				'updated_at' => \DB::expr('CURRENT_TIMESTAMP'),
			))
			->where('id', '=', $id)
			->where('deleted_at', 'IS', null)
			->execute($db);
	}

	/**
	 * 共通の有効条件と任意の権限条件を適用する。
	 *
	 * @param   int                       $id
	 * @param   string|null               $role
	 * @param   Database_Connection|null  $db
	 * @return  bool
	 */
	protected function has_active_role($id, $role, $db)
	{
		$db = $this->connection($db);
		$read_query = \DB::select(
			array(\DB::expr('1'), 'employee_exists')
		)
			->from(static::$table_name)
			->where('id', '=', $id)
			->where('is_active', '=', 1)
			->where('deleted_at', 'IS', null);

		if ($role !== null)
		{
			$read_query->where('role', '=', $role);
		}

		$read_result = $read_query->execute($db);

		return (int) $read_result->get('employee_exists', 0) === 1;
	}
}
