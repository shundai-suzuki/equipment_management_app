<?php

/**
 * Administrator-only loan and return API Controller.
 *
 * @package  app
 */
class Controller_AdminLoans extends Controller_Admin
{
	/** 
	 * Loan Service. 
	 * 
	 * @var Service_Table_Loan
	 */
	protected $service;

	/** 
	 * Initialize the Service after administrator authorization. 
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

		$this->service = new Service_Table_Loan();
	}

	/**
	 * Register one loan.
	 *
	 * @return  Response
	 */
	public function post_create()
	{
		return $this->execute_api(
			function ()
			{
				$actor_id = $this->employee_id();
				$created_id = $this->service->create_for_admin(
					$actor_id,
					$this->post_integer('id', 0),
					$this->post_integer('employee_id'),
					$this->post_integer('equipment_id'),
					\Input::post('due_date')
				);

				return $this->json_success(
					$this->service->read_for_admin($actor_id, $created_id),
					201
				);
			}
		);
	}

	/**
	 * Mark one active loan as returned.
	 *
	 * @param   mixed  $id
	 * @return  Response
	 */
	public function post_return($id)
	{
		return $this->execute_api(
			function () use ($id)
			{
				return $this->json_success(
					$this->service->return_for_admin(
						$this->employee_id(),
						$this->integer_value($id, 'id'),
						\Input::post('note')
					)
				);
			}
		);
	}
}
