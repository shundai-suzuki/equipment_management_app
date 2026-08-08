<?php

/**
 * Administrator CRUD endpoints for employees.
 *
 * @package  app
 */
class Controller_Table_Employee extends Controller_AdminCrud
{
	/**
	 * Temporarily disable one employee account.
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
	 * Re-enable one inactive employee account.
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
	 * Restore one archived employee.
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
	 * Reset one employee password.
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
	 * Create the employee Service.
	 *
	 * @return  Service_Table_Employee
	 */
	protected function new_service()
	{
		return new Service_Table_Employee();
	}

	/**
	 * Create an employee from allowed POST inputs.
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
	 * Update an employee from allowed POST inputs.
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
	 * Validate employee list filters.
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
