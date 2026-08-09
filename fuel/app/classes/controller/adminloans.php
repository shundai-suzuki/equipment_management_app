<?php

/**
 * 管理者専用の貸出・返却APIコントローラ。
 *
 * @package  app
 */
class Controller_AdminLoans extends Controller_Admin
{
	/**
	 * 貸出サービス。
	 *
	 * @var Service_Table_Loan
	 */
	protected $service;

	/**
	 * 管理者認可後にサービスを初期化する。
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
	 * 貸出を1件登録する。
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
	 * 貸出中の1件を返却済みにする。
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
