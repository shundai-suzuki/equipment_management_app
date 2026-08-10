<?php

/**
 * 認証済み利用者向け備品参照専用APIコントローラ。
 */
class Controller_Table_Equipment extends Controller_Base
{
	/** @var int カテゴリの最大文字数 */
	const MAX_CATEGORY_LENGTH = 20;

	/** @var Service_Table_Equipment 管理者CRUDコントローラと共有する備品サービス */
	protected $service;

	/**
	 * 認証後にサービスを初期化する。
	 *
	 * @return void
	 */
	public function before()
	{
		parent::before();

		if ($this->before_response instanceof \Response)
		{
			return;
		}

		$this->service = new Service_Table_Equipment();
	}

	/**
	 * すべての認証済み権限が参照できる備品を検索する。
	 *
	 * @return Response
	 */
	public function get_search()
	{
		return $this->execute_api(
			function ()
			{
				$page = $this->integer_value(\Input::get('page', 1), 'page');
				$search_result = $this->service->search(
					$page,
					\Input::get('q', ''),
					$this->search_filters()
				);
				$search_total = (int) $search_result['total'];

				return $this->json_success(
					$search_result['rows'],
					200,
					array(
						'pagination' => array(
							'page' => $page,
							'per_page' => Service_Table_Equipment::PER_PAGE,
							'total' => $search_total,
							'total_pages' => $search_total === 0
								? 0
								: (int) ceil($search_total / Service_Table_Equipment::PER_PAGE),
						),
						'category_options' => $search_result['category_options'],
					)
				);
			}
		);
	}

	/**
	 * 有効な備品を1件返す。
	 *
	 * @param  mixed     $id 対象レコードのID
	 * @return Response
	 */
	public function get_read($id)
	{
		return $this->execute_api(
			function () use ($id)
			{
				return $this->json_success(
					$this->service->read(
						$this->integer_value($id, 'id')
					)
				);
			}
		);
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

		if ($category !== null and $category !== '')
		{
			if ( ! is_string($category))
			{
				throw new \InvalidArgumentException('category must be a string.');
			}

			$category = trim($category);

			if ($category === ''
				or mb_strlen($category, 'UTF-8') > static::MAX_CATEGORY_LENGTH)
			{
				throw new \InvalidArgumentException('category is invalid.');
			}

			$filters['category'] = $category;
		}

		$available_only = \Input::get('available_only');

		if ($available_only !== null and $available_only !== '')
		{
			$filters['available_only'] = $this->boolean_value(
				$available_only,
				'available_only'
			);
		}

		return $filters;
	}
}
