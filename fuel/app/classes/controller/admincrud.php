<?php

/**
 * 管理者用検索・詳細APIの共通コントローラ。
 */
abstract class Controller_AdminCrud extends Controller_Admin
{
	/** @var Service_BaseCrud 検索対象のテーブルサービス */
	protected $service;

	/**
	 * 認可完了後にテーブルサービスを初期化する。
	 *
	 * @return void
	 */
	public function before()
	{
		parent::before();

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
			$result = $this->service->search_for_admin(
				$this->employee_id(),
				$page,
				\Input::get('q', ''),
				$this->search_filters()
			);
			$total = (int) $result['total'];
			$meta = array('pagination' => array(
				'page' => $page,
				'per_page' => Service_BaseCrud::PER_PAGE,
				'total' => $total,
				'total_pages' => $total ? (int) ceil($total / Service_BaseCrud::PER_PAGE) : 0,
			));

			if (isset($result['category_options']))
			{
				$meta['category_options'] = $result['category_options'];
			}

			return $this->json_success($result['rows'], 200, $meta);
		});
	}

	/**
	 * 指定IDの詳細JSONを返す。
	 *
	 * @param mixed $id 対象レコードのID
	 * @return Response
	 */
	public function get_read($id)
	{
		return $this->execute_api(function () use ($id)
		{
			return $this->json_success($this->service->read_for_admin(
				$this->employee_id(),
				$this->integer_value($id, 'id')
			));
		});
	}

	/**
	 * 子Controllerに対応するサービスを生成する。
	 *
	 * @return Service_BaseCrud
	 */
	abstract protected function new_service();

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
