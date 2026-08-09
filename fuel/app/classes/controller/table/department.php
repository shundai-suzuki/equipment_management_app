<?php

/**
 * Administrator CRUD endpoints for departments.
 *
 * @package  app
 */
class Controller_Table_Department extends Controller_AdminCrud
{
	/**
	 * Restore one archived department.
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
	 * Create the department Service.
	 *
	 * @return  Service_Table_Department
	 */
	protected function new_service()
	{
		return new Service_Table_Department();
	}

	/**
	 * Create a department from allowed POST inputs.
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
	 * Update a department from allowed POST inputs.
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
