<?php

/**
 * Common Controller for administrator table CRUD APIs.
 *
 * @package  app
 * @extends  Controller_Admin
 */
abstract class Controller_AdminCrud extends Controller_Admin
{
	const MAX_INTEGER = 2147483647;

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
	 * Soft-delete one row with the supplied reason.
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
						$this->integer_value($id, 'id'),
						Input::post('reason', '')
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
		$employee = $this->current_employee();

		if ($employee === null)
		{
			throw new RuntimeException(
				'The administrator could not be verified.',
				Service_BaseCrud::FORBIDDEN_EXCEPTION_CODE
			);
		}

		return $employee['id'];
	}

	/**
	 * Read and convert a required POST integer.
	 *
	 * @param   string  $name
	 * @param   int     $minimum
	 * @return  int
	 */
	protected function post_integer($name, $minimum = 1)
	{
		return $this->integer_value(Input::post($name), $name, $minimum);
	}

	/**
	 * Read an optional positive query integer.
	 *
	 * @param   string  $name
	 * @return  int|null
	 */
	protected function optional_query_integer($name)
	{
		$value = Input::get($name);

		return $value === null or $value === ''
			? null
			: $this->integer_value($value, $name);
	}

	/**
	 * Convert a decimal input without accepting signs or exponents.
	 *
	 * @param   mixed   $value
	 * @param   string  $name
	 * @param   int     $minimum
	 * @return  int
	 */
	protected function integer_value($value, $name, $minimum = 1)
	{
		if (is_int($value))
		{
			$integer = $value;
		}
		elseif (is_string($value)
			and preg_match('/\A(?:0|[1-9][0-9]*)\z/', $value) === 1)
		{
			$integer = (int) $value;
		}
		else
		{
			throw new InvalidArgumentException($name.' must be an integer.');
		}

		if ($integer < $minimum or $integer > static::MAX_INTEGER)
		{
			throw new InvalidArgumentException($name.' is outside the allowed range.');
		}

		return $integer;
	}

	/**
	 * Execute an action and hide internal exception details.
	 *
	 * @param   Closure  $operation
	 * @return  Response
	 */
	protected function execute_crud(Closure $operation)
	{
		try
		{
			return $operation();
		}
		catch (InvalidArgumentException $exception)
		{
			return $this->json_error(
				'VALIDATION_ERROR',
				'入力内容を確認してください。',
				422
			);
		}
		catch (Database_Exception $exception)
		{
			if ((int) $exception->getCode() === 1062)
			{
				return $this->json_error(
					'CONFLICT',
					'同じ内容のデータが既に存在します。',
					409
				);
			}

			return $this->internal_error($exception);
		}
		catch (RuntimeException $exception)
		{
			return $this->runtime_error($exception);
		}
		catch (Throwable $exception)
		{
			return $this->internal_error($exception);
		}
	}

	/**
	 * Map an allowed Service exception code to a generic response.
	 *
	 * @param   RuntimeException  $exception
	 * @return  Response
	 */
	protected function runtime_error(RuntimeException $exception)
	{
		$responses = array(
			403 => array('AUTHORIZATION_FAILED', 'この操作を行う権限がありません。'),
			404 => array('NOT_FOUND', '対象のデータが見つかりません。'),
			409 => array('CONFLICT', '現在のデータ状態では処理できません。'),
			422 => array('VALIDATION_ERROR', '入力内容を確認してください。'),
		);
		$status = (int) $exception->getCode();

		if (isset($responses[$status]))
		{
			return $this->json_error(
				$responses[$status][0],
				$responses[$status][1],
				$status
			);
		}

		return $this->internal_error($exception);
	}

	/**
	 * Log only safe correlation data and return a generic server error.
	 *
	 * @param   Throwable  $exception
	 * @return  Response
	 */
	protected function internal_error(Throwable $exception)
	{
		try
		{
			Log::error(
				'CRUD API failure. request_id='.$this->request_id
				.' exception_class='.get_class($exception)
			);
		}
		catch (Throwable $logging_exception)
		{
			// Keep the client response generic even when logging fails.
		}

		return $this->json_error(
			'INTERNAL_ERROR',
			'処理に失敗しました。時間をおいて再度お試しください。',
			500
		);
	}
}
