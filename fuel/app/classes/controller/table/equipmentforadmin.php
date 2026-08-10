<?php

/**
 * 管理者用備品検索API。
 */
class Controller_Table_EquipmentForAdmin extends Controller_AdminCrud
{
	/** @var int カテゴリの文字数(UTF-8) */
	const MAX_CATEGORY_LENGTH = 20;

	/**
	 * 備品サービスを生成する。
	 *
	 * @return Service_Table_Equipment
	 */
	protected function new_service()
	{
		return new Service_Table_Equipment();
	}

	/**
	 * 管理者用備品一覧で許可する検索条件を返す。
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

		$category = \Input::get('category');
		if ($category !== null and $category !== '')
		{
			$category = is_string($category) ? trim($category) : '';
			if ($category === '' or mb_strlen($category, 'UTF-8') > static::MAX_CATEGORY_LENGTH)
			{
				throw new \InvalidArgumentException('category is invalid.');
			}
			$filters['category'] = $category;
		}

		$available_only = \Input::get('available_only');
		if ($available_only !== null and $available_only !== '')
		{
			$filters['available_only'] = $this->boolean_value($available_only, 'available_only');
		}

		return $filters;
	}
}
