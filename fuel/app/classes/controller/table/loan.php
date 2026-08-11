<?php

/**
 * 認証済み利用者向け貸出履歴APIコントローラ。
 */
class Controller_Table_Loan extends Controller_Crud
{
	/**
	 * 貸出Serviceを生成する。
	 *
	 * @return Service_Table_Loan
	 */
	protected function new_service()
	{
		return new Service_Table_Loan();
	}

	/**
	 * 閲覧者の権限に応じた貸出履歴を検索する。
	 *
	 * @param  int    $page    取得するページ番号
	 * @param  mixed  $keyword 検索キーワード
	 * @param  array  $filters 検索条件
	 * @return array
	 */
	protected function search($page, $keyword, array $filters)
	{
		return $this->service->search_for_actor(
			$this->employee_id(),
			$page,
			$keyword,
			$filters
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
				$active_only,	'active_only'
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
