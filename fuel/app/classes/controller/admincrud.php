<?php

/**
 * Common Controller for administrator table CRUD APIs.
 *
 * @package  app
 * @extends  Controller_Admin
 */
abstract class Controller_AdminCrud extends Controller_Admin
{
	/**
	 * Table Service selected by the child Controller.
	 *
	 * @var Service_BaseCrud
	 */
	protected $service;

	/**
	 * Initialize the table Service only after Controller authorization.
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
	 * Search active rows.
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
	 * Create one row from the child Controller's allowed inputs.
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
	 * Return one active row.
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
	 * Update one row from the child Controller's allowed inputs.
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
	 * Soft-delete one row.
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
	 * Create the table-specific Service.
	 *
	 * @return  Service_BaseCrud
	 */
	abstract protected function new_service();

	/**
	 * Pass only table-specific create inputs to the Service.
	 *
	 * @param   int  $actor_id
	 * @return  int
	 */
	abstract protected function create_from_post($actor_id);

	/**
	 * Pass only table-specific update inputs to the Service.
	 *
	 * @param   int  $actor_id
	 * @param   int  $id
	 * @return  array
	 */
	abstract protected function update_from_post($actor_id, $id);

	/**
	 * Return validated exact-match search filters.
	 *
	 * @return  array
	 */
	protected function search_filters()
	{
		return array();
	}

	/**
	 * Return the authenticated administrator ID.
	 *
	 * @return  int
	 */
	protected function actor_id()
	{
		return $this->employee_id();
	}

	/**
	 * Execute an action and hide internal exception details.
	 *
	 * @param   Closure  $operation
	 * @return  Response
	 */
	protected function execute_crud(Closure $operation)
	{
		return $this->execute_api($operation);
	}
}
