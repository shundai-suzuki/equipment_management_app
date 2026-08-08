<?php

/**
 * Administrator CRUD endpoints for equipment.
 *
 * @package  app
 */
class Controller_Table_Equipment extends Controller_AdminCrud
{
	const MAX_CATEGORY_LENGTH = 20;

	/**
	 * Create the equipment Service.
	 *
	 * @return  Service_Table_Equipment
	 */
	protected function new_service()
	{
		return new Service_Table_Equipment();
	}

	/**
	 * Create equipment from allowed POST inputs.
	 *
	 * @param   int  $actor_id
	 * @return  int
	 */
	protected function create_from_post($actor_id)
	{
		return $this->service->create_for_admin(
			$actor_id,
			$this->post_integer('id', 0),
			Input::post('name'),
			$this->post_integer('department_id'),
			Input::post('category'),
			$this->post_integer('total_amount'),
			Input::post('description')
		);
	}

	/**
	 * Update equipment from allowed POST inputs.
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
			Input::post('name'),
			$this->post_integer('department_id'),
			Input::post('category'),
			$this->post_integer('total_amount'),
			Input::post('description')
		);
	}

	/**
	 * Validate equipment list filters.
	 *
	 * @return  array
	 */
	protected function search_filters()
	{
		$filters = array();
		$department_id = $this->optional_query_integer('department_id');
		$category = Input::get('category');

		if ($department_id !== null)
		{
			$filters['department_id'] = $department_id;
		}

		if ($category !== null and $category !== '')
		{
			if ( ! is_string($category))
			{
				throw new InvalidArgumentException('category must be a string.');
			}

			$category = trim($category);

			if ($category === ''
				or mb_strlen($category, 'UTF-8') > static::MAX_CATEGORY_LENGTH)
			{
				throw new InvalidArgumentException('category is invalid.');
			}

			$filters['category'] = $category;
		}

		return $filters;
	}

}
