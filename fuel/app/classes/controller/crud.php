<?php

/**
 * 認証済み社員向け一覧JSONの共通コントローラ。
 */
abstract class Controller_Crud extends Controller_Base
{
	/** @var Service_BaseCrud 検索対象のService */
	protected $service;

	/**
	 * 認証後に認可とService初期化を実行する。
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

		$this->authorize();

		if ( ! ($this->before_response instanceof \Response))
		{
			$this->service = $this->new_service();
		}
	}

	/**
	 * 検索条件とページ番号から一覧JSONを返す。
	 *
	 * @return Response
	 */
	public function get_search()
	{
		return $this->execute_api(function ()
		{
			$page = $this->integer_value(\Input::get('page', 1), 'page');
			$result = $this->search(
				$page, \Input::get('q', ''), $this->search_filters()
			);
			$total = (int) $result['total'];
			$meta = array('pagination' => array(
				'page' => $page,
				'per_page' => Service_BaseCrud::PER_PAGE,
				'total' => $total,
				'total_pages' => $total === 0
					? 0
					: (int) ceil($total / Service_BaseCrud::PER_PAGE),
			));

			if (isset($result['category_options']))
			{
				$meta['category_options'] = $result['category_options'];
			}

			return $this->json_success($result['rows'], $meta);
		});
	}

	/**
	 * 子Controllerに対応するServiceを生成する。
	 *
	 * @return Service_BaseCrud
	 */
	abstract protected function new_service();

	/**
	 * 一覧検索をServiceへ委譲する。
	 *
	 * @param  int    $page    取得するページ番号
	 * @param  mixed  $keyword 検索キーワード
	 * @param  array  $filters 検索条件
	 * @return array
	 */
	protected function search($page, $keyword, array $filters)
	{
		return $this->service->search($page, $keyword, $filters);
	}

	/**
	 * 子Controller固有の認可を追加する。
	 *
	 * @return void
	 */
	protected function authorize()
	{
	}

	/**
	 * 子Controller固有の検索条件を返す。
	 *
	 * @return array
	 */
	protected function search_filters()
	{
		return array();
	}
}
