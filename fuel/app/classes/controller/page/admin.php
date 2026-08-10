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
	public function action_employees()
	{
		$this->render_resource('employees', '社員管理');
	}

	/**
	 * 部署管理画面を表示する。
	 *
	 * @return void
	 */
	public function action_departments()
	{
		$this->render_resource('departments', '部署管理');
	}

	/**
	 * 通常フォームPOSTを対応するService処理へ振り分ける。
	 *
	 * @param string          $resource   操作対象のリソース名
	 * @param string          $operation  実行する操作名
	 * @param int|string|null $id         操作対象のID
	 * @return void
	 */
	public function action_mutate($resource, $operation, $id = null)
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

		$this->form_result(function () use ($resource, $operation, $id)
		{
			$actor = $this->employee_id();
			$id = $id === null ? null : $this->positive_integer($id, 'id');

			switch ($resource.'/'.$operation)
			{
				case 'departments/create':
					(new Service_Table_Department())->create(\Input::post('name'));
					break;
				case 'departments/update':
					(new Service_Table_Department())->update($id, \Input::post('name'));
					break;
				case 'departments/archive':
					(new Service_Table_Department())->soft_delete($id);
					break;
				case 'departments/restore':
					(new Service_Table_Department())->restore($id);
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
						$id,
						\Input::post('employee_name'),
						$this->post_integer('department_id'),
						\Input::post('role')
					);
					break;
				case 'employees/archive':
					(new Service_Table_Employee())->soft_delete($id);
					break;
				case 'employees/deactivate':
					(new Service_Table_Employee())->deactivate($id);
					break;
				case 'employees/activate':
					(new Service_Table_Employee())->activate($id);
					break;
				case 'employees/restore':
					(new Service_Table_Employee())->restore($id);
					break;
				case 'employees/password':
					(new Service_Table_Employee())->reset_password(
						$actor,
						$id,
						\Input::post('admin_password'),
						\Input::post('password'),
						\Input::post('password_confirmation')
					);
					break;
				case 'equipment/create':
					$this->save_equipment();
					break;
				case 'equipment/update':
					$this->save_equipment($id);
					break;
				case 'equipment/archive':
					(new Service_Table_Equipment())->soft_delete($id);
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
					(new Service_Table_Loan())->return_loan($actor, $id, \Input::post('note'));
					break;
				default:
					throw new \HttpNotFoundException();
			}
		}, $redirects[$resource]);
	}

	/**
	 * 備品の登録または更新入力をServiceへ渡す。
	 *
	 * @param int|null $id 更新対象の備品ID
	 * @return void
	 */
	protected function save_equipment($id = null)
	{
		$service = new Service_Table_Equipment();
		$values = array(
			\Input::post('name'),
			$this->post_integer('department_id'),
			\Input::post('category'),
			$this->post_integer('total_amount'),
			\Input::post('description'),
		);

		if ($id === null)
		{
			$service->create($values[0], $values[1], $values[2], $values[3], $values[4]);
			return;
		}

		$service->update($id, $values[0], $values[1], $values[2], $values[3], $values[4]);
	}
}
