<?php

/**
 * Authenticated read-only equipment API Controller.
 *
 * @package  app
 */
class Controller_Table_Equipment extends Controller_Base
{
	const MAX_CATEGORY_LENGTH = 20;

	/**
	 * Equipment Service shared with the administrator CRUD Controller.
	 *
	 * @var Service_Table_Equipment
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

		$this->service = new Service_Table_Equipment();
	}

	/**
	 * Search equipment visible to every authenticated role.
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
					\Input::get('q', ''),
					$this->search_filters()
				);
				$search_total = (int) $search_result['total'];

				return $this->json_success(
					$search_result['rows'],
					200,
					array(
						'pagination' => array(
							'page' => $page,
							'per_page' => Service_Table_Equipment::PER_PAGE,
							'total' => $search_total,
							'total_pages' => $search_total === 0
								? 0
								: (int) ceil($search_total / Service_Table_Equipment::PER_PAGE),
						),
						'category_options' => $search_result['category_options'],
					)
				);
			}
		);
	}

	/**
	 * Return one active equipment row.
	 *
	 * @param   mixed  $id
	 * @return  Response
	 */
	public function get_read($id)
	{
		return $this->execute_api(
			function () use ($id)
			{
				return $this->json_success(
					$this->service->read(
						$this->integer_value($id, 'id')
					)
				);
			}
		);
	}

	/**
	 * Return validated equipment search filters.
	 *
	 * @return  array
	 */
	protected function search_filters()
	{
		$filters = array();
		$department_id = $this->optional_query_integer('department_id');
		$category = \Input::get('category');

		if ($department_id !== null)
		{
			$filters['department_id'] = $department_id;
		}

		if ($category !== null and $category !== '')
		{
			if ( ! is_string($category))
			{
				throw new \InvalidArgumentException('category must be a string.');
			}

			$category = trim($category);

			if ($category === ''
				or mb_strlen($category, 'UTF-8') > static::MAX_CATEGORY_LENGTH)
			{
				throw new \InvalidArgumentException('category is invalid.');
			}

			$filters['category'] = $category;
		}

		return $filters;
	}
}
