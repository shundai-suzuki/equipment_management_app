<?php

/**
 * Authenticated read-only department API Controller.
 *
 * @package  app
 */
class Controller_Table_DepartmentLookup extends Controller_Base
{
	/**
	 * Department Service shared with the administrator CRUD Controller.
	 *
	 * @var Service_Table_Department
	 */
	protected $service;

	/** Initialize the Service after authentication. */
	public function before()
	{
		parent::before();

		if ($this->before_response instanceof \Response)
		{
			return;
		}

		$this->service = new Service_Table_Department();
	}

	/**
	 * Search active departments visible to every authenticated role.
	 *
	 * @return  Response
	 */
	public function get_search()
	{
		return $this->execute_api(
			function ()
			{
				$page = $this->integer_value(\Input::get('page', 1), 'page');
				$search_result = $this->service->search(
					$page,
					\Input::get('q', '')
				);
				$search_total = (int) $search_result['total'];

				return $this->json_success(
					$search_result['rows'],
					200,
					array(
						'pagination' => array(
							'page' => $page,
							'per_page' => Service_Table_Department::PER_PAGE,
							'total' => $search_total,
							'total_pages' => $search_total === 0
								? 0
								: (int) ceil($search_total / Service_Table_Department::PER_PAGE),
						),
					)
				);
			}
		);
	}
}
