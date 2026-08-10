<?php

/**
 * 認証済み利用者向け貸出履歴APIコントローラ。
 */
class Controller_Table_Loan extends Controller_Base
{
	/** @var Service_Table_Loan 貸出サービス */
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

		$this->service = new Service_Table_Loan();
	}

	/**
	 * 現在の社員が参照できる貸出履歴を検索する。
	 *
	 * @return Response
	 */
	public function get_search()
	{
		return $this->execute_api(
			function ()
			{
				$page = $this->integer_value(\Input::get('page', 1), 'page');
				$search_result = $this->service->search_for_actor(
					$this->employee_id(),
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
							'per_page' => Service_Table_Loan::PER_PAGE,
							'total' => $search_total,
							'total_pages' => $search_total === 0
								? 0
								: (int) ceil($search_total / Service_Table_Loan::PER_PAGE),
						),
					)
				);
			}
		);
	}

	/**
	 * 仕様で定義した貸出検索条件だけを返す。
	 *
	 * @return array
	 */
	protected function search_filters()
	{
		$filters = array();

		foreach (array('loan_id', 'equipment_id') as $name)
		{
			$value = $this->optional_query_integer($name);

			if ($value !== null)
			{
				$filters[$name] = $value;
			}
		}

		$active_only = \Input::get('active_only');

		if ($active_only !== null and $active_only !== '')
		{
			$filters['active_only'] = $this->boolean_value(
				$active_only,
				'active_only'
			);
		}

		$loan_state = \Input::get('loan_state');

		if ($loan_state !== null and $loan_state !== '')
		{
			$filters['loan_state'] = $loan_state;
		}

		return $filters;
	}
}
