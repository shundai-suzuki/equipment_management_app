<?php

/**
 * 認証済み利用者向け部署参照専用APIコントローラ。
 */
class Controller_Table_DepartmentLookup extends Controller_Base
{
	/** @var Service_Table_Department 管理者CRUDコントローラと共有する部署サービス */
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

		$this->service = new Service_Table_Department();
	}

	/**
	 * すべての認証済み権限が参照できる有効な部署を検索する。
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
					\Input::get('q', '')
				);
				$search_total = (int) $search_result['total'];

				return $this->json_success(
					$search_result['rows'],
					200,
					array(
						'pagination' => array(
							'page' => $page,
							'per_page' => Service_Table_Department::PER_PAGE,
							'total' => $search_total,
							'total_pages' => $search_total === 0
								? 0
								: (int) ceil($search_total / Service_Table_Department::PER_PAGE),
						),
					)
				);
			}
		);
	}
}
