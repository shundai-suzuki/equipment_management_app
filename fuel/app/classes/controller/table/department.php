<?php

/**
 * 管理者用部署CRUDエンドポイント。
 *
 * @package  app
 */
class Controller_Table_Department extends Controller_AdminCrud
{
	/**
	 * 論理削除済み部署を1件復元する。
	 *
	 * @param   mixed  $id
	 * @return  Response
	 */
	public function post_restore($id)
	{
		return $this->execute_crud(
			function () use ($id)
			{
				return $this->json_success(
					$this->service->restore_for_admin(
						$this->actor_id(),
						$this->integer_value($id, 'id')
					)
				);
			}
		);
	}

	/**
	 * 部署サービスを生成する。
	 *
	 * @return  Service_Table_Department
	 */
	protected function new_service()
	{
		return new Service_Table_Department();
	}

	/**
	 * 許可したPOST入力から部署を登録する。
	 *
	 * @param   int  $actor_id
	 * @return  int
	 */
	protected function create_from_post($actor_id)
	{
		return $this->service->create_for_admin(
			$actor_id,
			$this->post_integer('id', 0),
			Input::post('name')
		);
	}

	/**
	 * 許可したPOST入力から部署を更新する。
	 *
	 * @param   int  $actor_id
	 * @param   int  $id
	 * @return  array
	 */
	protected function update_from_post($actor_id, $id)
	{
		return $this->service->update_for_admin(
			$actor_id,
			$id,
			Input::post('name')
		);
	}
}
