<?php

/**
 * 部署のデータベース操作を提供する。
 *
 * @package  app
 */
class Model_Table_Department extends Model_BaseCrud
{
	/**
	 * このモデルが操作するテーブル。
	 *
	 * @var string
	 */
	protected static $table_name = 'departments';

	/**
	 * 部署の新規行で受け付ける登録値。
	 *
	 * @var array
	 */
	protected static $create_columns = array('name');

	/**
	 * 部署CRUDの読取処理が返す列。
	 *
	 * @var array
	 */
	protected static $read_columns = array(
		'id',
		'name',
		'created_at',
		'updated_at',
		'deleted_at',
	);

	/**
	 * 部署更新で受け付ける列。
	 *
	 * @var array
	 */
	protected static $update_columns = array('name');

	/**
	 * キーワード検索の対象となる部署列。
	 *
	 * @var array
	 */
	protected static $search_columns = array('name');

	/**
	 * 論理削除済み行を含め、名称で部署を取得する。
	 *
	 * @param   string                    $name
	 * @param   Database_Connection|null  $db
	 * @return  array|null
	 */
	public function read_by_name($name, $db = null)
	{
		$db = $this->connection($db);
		$read_result = \DB::select('id', 'name', 'deleted_at')
			->from(static::$table_name)
			->where('name', '=', $name)
			->execute($db);

		if (count($read_result) === 0)
		{
			return null;
		}

		return array(
			'id' => (int) $read_result->get('id'),
			'name' => $read_result->get('name'),
			'deleted_at' => $read_result->get('deleted_at'),
		);
	}

	/**
	 * 部署を新しい行から使用できるか確認する。
	 *
	 * @param   int                       $id
	 * @param   Database_Connection|null  $db
	 * @return  bool
	 */
	public function is_active($id, $db = null)
	{
		$db = $this->connection($db);
		$read_result = \DB::select(
			array(\DB::expr('1'), 'is_active')
			)
			->from(static::$table_name)
			->where('id', '=', $id)
			->where('deleted_at', 'IS', null)
			->execute($db);

		return (int) $read_result->get('is_active', 0) === 1;
	}

	/**
	 * 有効な社員または備品が部署を使用中か確認する。
	 *
	 * @param   int                       $id
	 * @param   Database_Connection|null  $db
	 * @return  bool
	 */
	public function has_active_references($id, $db = null)
	{
		$db = $this->connection($db);
		$employee_result = \DB::select(
			array(\DB::expr('1'), 'employee_exists')
		)
			->from('employees')
			->where('department_id', '=', $id)
			->where('deleted_at', 'IS', null)
			->limit(1)
			->execute($db);

		if ((int) $employee_result->get('employee_exists', 0) === 1)
		{
			return true;
		}

		$equipment_result = \DB::select(
			array(\DB::expr('1'), 'equipment_exists')
		)
			->from('equipments')
			->where('department_id', '=', $id)
			->where('deleted_at', 'IS', null)
			->limit(1)
			->execute($db);

		return (int) $equipment_result->get('equipment_exists', 0) === 1;
	}
}
