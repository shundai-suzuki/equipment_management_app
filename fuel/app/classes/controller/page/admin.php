<?php

/**
 * 管理者画面と通常フォームによる状態変更を処理する。
 */
class Controller_Page_Admin extends Controller_Page_Base
{
	/**
	 * 画面処理前に管理者権限を確認する。
	 *
	 * @return void
	 */
	public function before()
	{
		parent::before();

		if ( ! $this->is_admin)
		{
			throw new \HttpNoAccessException();
		}
	}

	/**
	 * 社員管理画面を表示する。
	 *
	 * @return void
	 */
	public function get_employees()
	{
		$this->render_resource('employees', '社員管理');
	}

	/**
	 * 部署管理画面を表示する。
	 *
	 * @return void
	 */
	public function get_departments()
	{
		$this->render_resource('departments', '部署管理');
	}

	/**
	 * 通常フォームPOSTを対応するService処理へ振り分ける。
	 *
	 * @param string          $resource   操作対象のリソース名
	 * @param string          $operation  実行する操作名
	 * @param int|string|null $target_id  操作対象のID
	 * @return void
	 */
	public function post_mutate($resource, $operation, $target_id = null)
	{
		$redirects = array(
			'departments' => 'admin/departments',
			'employees' => 'admin/employees',
			'equipment' => 'equipment',
			'loans' => 'loans',
		);

		if ( ! isset($redirects[$resource]))
		{
			throw new \HttpNotFoundException();
		}

		$this->form_result(function () use ($resource, $operation, $target_id)
		{
			$actor = $this->employee_id();
			$target_id = $target_id === null ? null : $this->positive_integer($target_id, 'id');

			switch ($resource.'/'.$operation)
			{
				case 'departments/create':
					(new Service_Table_Department())->create(\Input::post('name'));
					break;
				case 'departments/update':
					(new Service_Table_Department())->update($target_id, \Input::post('name'));
					break;
				case 'departments/soft_delete':
					(new Service_Table_Department())->soft_delete($target_id);
					break;
				case 'departments/restore':
					(new Service_Table_Department())->restore($target_id);
					break;
				case 'employees/create':
					(new Service_Table_Employee())->create(
						\Input::post('employee_name'),
						$this->post_integer('department_id'),
						\Input::post('role'),
						\Input::post('password'),
						\Input::post('password_confirmation')
					);
					break;
				case 'employees/update':
					(new Service_Table_Employee())->update(
						$target_id,
						\Input::post('employee_name'),
						$this->post_integer('department_id'),
						\Input::post('role')
					);
					break;
				case 'employees/soft_delete':
					(new Service_Table_Employee())->soft_delete($target_id);
					break;
				case 'employees/deactivate':
					(new Service_Table_Employee())->deactivate($target_id);
					break;
				case 'employees/activate':
					(new Service_Table_Employee())->activate($target_id);
					break;
				case 'employees/restore':
					(new Service_Table_Employee())->restore($target_id);
					break;
				case 'employees/password':
					(new Service_Table_Employee())->reset_password(
						$actor,
						$target_id,
						\Input::post('admin_password'),
						\Input::post('password'),
						\Input::post('password_confirmation')
					);
					break;
				case 'equipment/create':
					$this->save_equipment();
					break;
				case 'equipment/update':
					$this->save_equipment($target_id);
					break;
				case 'equipment/soft_delete':
					(new Service_Table_Equipment())->soft_delete($target_id);
					break;
				case 'loans/create':
					(new Service_Table_Loan())->create(
						$actor,
						$this->post_integer('employee_id'),
						$this->post_integer('equipment_id'),
						\Input::post('due_date')
					);
					break;
				case 'loans/return':
					(new Service_Table_Loan())->return_loan($actor, $target_id, \Input::post('note'));
					break;
				default:
					throw new \HttpNotFoundException();
			}
		}, $redirects[$resource]);
	}

	/**
	 * 備品の登録または更新入力をServiceへ渡す。
	 *
	 * @param int|null $target_id 更新対象の備品ID
	 * @return void
	 */
	protected function save_equipment($target_id = null)
	{
		$service = new Service_Table_Equipment();
		$post_values = array(
			\Input::post('name'),
			$this->post_integer('department_id'),
			\Input::post('category'),
			\Input::post('total_amount'),
			\Input::post('description'),
		);

		if ($target_id === null)
		{
			$service->create($post_values[0], $post_values[1], $post_values[2], $post_values[3], $post_values[4]);
			return;
		}

		$service->update($target_id, $post_values[0], $post_values[1], $post_values[2], $post_values[3], $post_values[4]);
	}
}
