<?php

/**
 * 管理者用社員CRUDエンドポイント。
 *
 * @package  app
 */
class Controller_Table_Employee extends Controller_AdminCrud
{
	/**
	 * 社員アカウントを1件一時的に無効化する。
	 *
	 * @param   mixed  $id
	 * @return  Response
	 */
	public function post_deactivate($id)
	{
		return $this->execute_crud(
			function () use ($id)
			{
				return $this->json_success(
					$this->service->deactivate_for_admin(
						$this->actor_id(),
						$this->integer_value($id, 'id')
					)
				);
			}
		);
	}

	/**
	 * 無効な社員アカウントを1件再有効化する。
	 *
	 * @param   mixed  $id
	 * @return  Response
	 */
	public function post_activate($id)
	{
		return $this->execute_crud(
			function () use ($id)
			{
				return $this->json_success(
					$this->service->activate_for_admin(
						$this->actor_id(),
						$this->integer_value($id, 'id')
					)
				);
			}
		);
	}

	/**
	 * 論理削除済み社員を1件復元する。
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
	 * 社員のパスワードを1件再設定する。
	 *
	 * @param   mixed  $id
	 * @return  Response
	 */
	public function post_password($id)
	{
		return $this->execute_crud(
			function () use ($id)
			{
				return $this->json_success(
					$this->service->reset_password_for_admin(
						$this->actor_id(),
						$this->integer_value($id, 'id'),
						Input::post('admin_password'),
						Input::post('password'),
						Input::post('password_confirmation')
					)
				);
			}
		);
	}

	/**
	 * 社員サービスを生成する。
	 *
	 * @return  Service_Table_Employee
	 */
	protected function new_service()
	{
		return new Service_Table_Employee();
	}

	/**
	 * 許可したPOST入力から社員を登録する。
	 *
	 * @param   int  $actor_id
	 * @return  int
	 */
	protected function create_from_post($actor_id)
	{
		return $this->service->create_for_admin(
			$actor_id,
			$this->post_integer('id', 0),
			Input::post('employee_name'),
			$this->post_integer('department_id'),
			Input::post('role'),
			Input::post('password'),
			Input::post('password_confirmation')
		);
	}

	/**
	 * 許可したPOST入力から社員を更新する。
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
			Input::post('employee_name'),
			$this->post_integer('department_id'),
			Input::post('role')
		);
	}

	/**
	 * 社員一覧の検索条件を検証する。
	 *
	 * @return  array
	 */
	protected function search_filters()
	{
		$filters = array();
		$department_id = $this->optional_query_integer('department_id');
		$role = Input::get('role');
		$is_active = Input::get('is_active');

		if ($department_id !== null)
		{
			$filters['department_id'] = $department_id;
		}

		if ($role !== null and $role !== '')
		{
			if ( ! is_string($role)
				or ! in_array($role, array('EMPLOYEE', 'ADMIN'), true))
			{
				throw new InvalidArgumentException('role is invalid.');
			}

			$filters['role'] = $role;
		}

		if ($is_active !== null and $is_active !== '')
		{
			$is_active = $this->integer_value($is_active, 'is_active', 0);

			if ($is_active > 1)
			{
				throw new InvalidArgumentException('is_active is invalid.');
			}

			$filters['is_active'] = $is_active;
		}

		return $filters;
	}
}
