<?php

/**
 * 認証済み利用者向け備品参照APIコントローラ。
 */
class Controller_Table_Equipment extends Controller_Crud
{
	/**
	 * 備品Serviceを生成する。
	 *
	 * @return Service_Table_Equipment
	 */
	protected function new_service()
	{
		return new Service_Table_Equipment();
	}

	/**
	 * 検証済みの備品検索条件を返す。
	 *
	 * @return array
	 */
	protected function search_filters()
	{
		$filters = array();
		$department_id = $this->optional_query_integer('department_id');
		$category = \Input::get('category');

		if ($department_id !== null)
		{
			$filters['department_id'] = $department_id;
		}

		if (is_string($category) and $category !== '')
		{
			$filters['category'] = $category;
		}

		$available_only = \Input::get('available_only');

		if ($available_only !== null and $available_only !== '')
		{
			$filters['available_only'] = $this->boolean_value(
				$available_only, 'available_only'
			);
		}

		return $filters;
	}
}
