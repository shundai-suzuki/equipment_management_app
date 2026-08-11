<?php

/**
 * 管理者用社員検索API。
 */
class Controller_Table_Employee extends Controller_Admin
{
	/**
	 * 社員サービスを生成する。
	 *
	 * @return Service_Table_Employee
	 */
	protected function new_service()
	{
		return new Service_Table_Employee();
	}

	/**
	 * 社員一覧で許可する検索条件を返す。
	 *
	 * @return array
	 */
	protected function search_filters()
	{
		$filters = array();
		$department_id = $this->optional_query_integer('department_id');

		if ($department_id !== null)
		{
			$filters['department_id'] = $department_id;
		}

		$role = \Input::get('role');
		if (is_string($role) and $role !== '')
		{
			$filters['role'] = $role;
		}

		$is_active = \Input::get('is_active');
		if ($is_active !== null and $is_active !== '')
		{
			$is_active = $this->integer_value($is_active, 'is_active', 0);
			$filters['is_active'] = $is_active;
		}

		return $filters;
	}
}
