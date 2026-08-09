<?php

/**
 * 管理者用テーブルCRUD APIの共通コントローラ。
 *
 * @package  app
 * @extends  Controller_Admin
 */
abstract class Controller_AdminCrud extends Controller_Admin
{
	/**
	 * 子コントローラが選択するテーブルサービス。
	 *
	 * @var Service_BaseCrud
	 */
	protected $service;

	/**
	 * コントローラでの認可後にだけテーブルサービスを初期化する。
	 *
	 * @return  void
	 */
	public function before()
	{
		parent::before();

		if ($this->before_response instanceof Response)
		{
			return;
		}

		$this->service = $this->new_service();
	}

	/**
	 * 有効な行を検索する。
	 *
	 * @return  Response
	 */
	public function get_search()
	{
		return $this->execute_crud(
			function ()
			{
				$page = $this->integer_value(
					Input::get('page', 1),
					'page'
				);
				$per_page = Service_BaseCrud::PER_PAGE;
				$search_result = $this->service->search_for_admin(
					$this->actor_id(),
					$page,
					Input::get('q', ''),
					$this->search_filters()
				);
				$search_total = (int) $search_result['total'];
				$search_meta = array(
					'pagination' => array(
						'page' => $page,
						'per_page' => $per_page,
						'total' => $search_total,
						'total_pages' => $search_total === 0
							? 0
							: (int) ceil($search_total / $per_page),
					),
				);

				if (isset($search_result['category_options']))
				{
					$search_meta['category_options'] = $search_result['category_options'];
				}

				return $this->json_success(
					$search_result['rows'],
					200,
					$search_meta
				);
			}
		);
	}

	/**
	 * 子コントローラが許可した入力から1行を登録する。
	 *
	 * @return  Response
	 */
	public function post_create()
	{
		return $this->execute_crud(
			function ()
			{
				$actor_id = $this->actor_id();
				$created_id = $this->create_from_post($actor_id);

				return $this->json_success(
					$this->service->read_for_admin($actor_id, $created_id),
					201
				);
			}
		);
	}

	/**
	 * 有効な1行を返す。
	 *
	 * @param   mixed  $id
	 * @return  Response
	 */
	public function get_read($id)
	{
		return $this->execute_crud(
			function () use ($id)
			{
				return $this->json_success(
					$this->service->read_for_admin(
						$this->actor_id(),
						$this->integer_value($id, 'id')
					)
				);
			}
		);
	}

	/**
	 * 子コントローラが許可した入力から1行を更新する。
	 *
	 * @param   mixed  $id
	 * @return  Response
	 */
	public function post_update($id)
	{
		return $this->execute_crud(
			function () use ($id)
			{
				return $this->json_success(
					$this->update_from_post(
						$this->actor_id(),
						$this->integer_value($id, 'id')
					)
				);
			}
		);
	}

	/**
	 * 1行を論理削除する。
	 *
	 * @param   mixed  $id
	 * @return  Response
	 */
	public function post_soft_delete($id)
	{
		return $this->execute_crud(
			function () use ($id)
			{
				return $this->json_success(
					$this->service->soft_delete_for_admin(
						$this->actor_id(),
						$this->integer_value($id, 'id')
					)
				);
			}
		);
	}

	/**
	 * テーブル専用サービスを生成する。
	 *
	 * @return  Service_BaseCrud
	 */
	abstract protected function new_service();

	/**
	 * テーブル専用の登録入力だけをサービスへ渡す。
	 *
	 * @param   int  $actor_id
	 * @return  int
	 */
	abstract protected function create_from_post($actor_id);

	/**
	 * テーブル専用の更新入力だけをサービスへ渡す。
	 *
	 * @param   int  $actor_id
	 * @param   int  $id
	 * @return  array
	 */
	abstract protected function update_from_post($actor_id, $id);

	/**
	 * 検証済みの完全一致検索条件を返す。
	 *
	 * @return  array
	 */
	protected function search_filters()
	{
		return array();
	}

	/**
	 * 認証済み管理者のIDを返す。
	 *
	 * @return  int
	 */
	protected function actor_id()
	{
		return $this->employee_id();
	}

	/**
	 * アクションを実行し、内部例外の詳細を隠す。
	 *
	 * @param   Closure  $operation
	 * @return  Response
	 */
	protected function execute_crud(Closure $operation)
	{
		return $this->execute_api($operation);
	}
}
