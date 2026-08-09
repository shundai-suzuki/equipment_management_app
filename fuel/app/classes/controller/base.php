<?php

/**
 * Common JSON API Controller.
 *
 * @package  app
 * @extends  Controller_Rest
 */
abstract class Controller_Base extends Controller_Rest
{
	const JSON_CONTENT_TYPE = 'application/json; charset=utf-8';
	const MAX_INTEGER = 2147483647;

	/**
	 * Always return JSON without relying on an AJAX-only header.
	 *
	 * @var string
	 */
	protected $rest_format = 'json';

	/**
	 * true => need authentication, false => can be done without logging in.
	 *
	 * @var bool
	 */
	protected $authentication_required = true;

	/**
	 * Identifier shared by the response and server-side logs.
	 *
	 * @var string
	 */
	protected $request_id;

	/**
	 * Response prepared before an action when access must stop.
	 *
	 * @var Response|null
	 */
	protected $before_response;

	/**
	 * Initialize the request identifier and continued authentication.
	 *
	 * @return  void
	 */
	public function before()
	{
		parent::before();

		$this->request_id = bin2hex(random_bytes(16));

		if ($this->authentication_required and ! \Auth::check())
		{
			$this->before_response = $this->json_error(
				'AUTHENTICATION_REQUIRED',
				'ログインが必要です。',
				401
			);
		}
	}

	/**
	 * Stop dispatch when before() prepared an error response.
	 *
	 * @param   string  $resource
	 * @param   array   $arguments
	 * @return  Response|mixed
	 */
	public function router($resource, $arguments)
	{
		if ($this->before_response instanceof \Response)
		{
			return $this->before_response;
		}

		$response = parent::router($resource, $arguments);

		if ($response === null and $this->response->status === 405)
		{
			return $this->json_error(
				'METHOD_NOT_ALLOWED',
				'このHTTPメソッドは使用できません。',
				405
			);
		}

		return $response;
	}

	/**
	 * Build a successful response with optional list metadata.
	 *
	 * @param   array       $data
	 * @param   int         $status
	 * @param   array|null  $meta
	 * @return  Response
	 */
	protected function json_success(array $data, $status = 200, $meta = null)
	{
		$body = array('data' => $data);

		if ($meta !== null)
		{
			$body['meta'] = $meta;
		}

		$body['request_id'] = $this->request_id;

		return $this->json_response($body, $status);
	}

	/**
	 * Build an error response with optional field messages.
	 *
	 * @param   string      $code
	 * @param   string      $message
	 * @param   int         $status
	 * @param   array|null  $fields
	 * @return  Response
	 */
	protected function json_error($code, $message, $status, $fields = null)
	{
		$error = array(
			'code' => $code,
			'message' => $message,
		);

		if ($fields !== null)
		{
			$error['fields'] = $fields;
		}

		return $this->json_response(
			array(
				'error' => $error,
				'request_id' => $this->request_id,
			),
			$status
		);
	}

	/**
	 * Return the authenticated employee fields allowed in Controllers.
	 *
	 * @return  array|null
	 */
	protected function current_employee()
	{
		$driver = \Auth::instance();

		if ( ! $driver instanceof Auth_Login_Employee)
		{
			return null;
		}

		$id = $driver->get('id');
		$employee_name = $driver->get('employee_name');
		$role = $driver->get('role');

		if ( ! is_int($id)
			or ! is_string($employee_name)
			or ! in_array($role, array('EMPLOYEE', 'ADMIN'), true))
		{
			return null;
		}

		return array(
			'id' => $id,
			'employee_name' => $employee_name,
			'role' => $role,
		);
	}

	/**
	 * Return the authenticated employee ID.
	 *
	 * @return  int
	 */
	protected function employee_id()
	{
		$employee = $this->current_employee();

		if ($employee === null)
		{
			throw new \RuntimeException(
				'The authenticated employee could not be verified.',
				403
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
		return $this->integer_value(\Input::post($name), $name, $minimum);
	}

	/**
	 * Read an optional positive query integer.
	 *
	 * @param   string  $name
	 * @return  int|null
	 */
	protected function optional_query_integer($name)
	{
		$value = \Input::get($name);

		return $value === null or $value === ''
			? null
			: $this->integer_value($value, $name);
	}

	/**
	 * Convert an unsigned decimal input to an integer.
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
			throw new \InvalidArgumentException($name.' must be an integer.');
		}

		if ($integer < $minimum or $integer > static::MAX_INTEGER)
		{
			throw new \InvalidArgumentException($name.' is outside the allowed range.');
		}

		return $integer;
	}

	/**
	 * Convert the query strings true and false to a boolean.
	 *
	 * @param   mixed   $value
	 * @param   string  $name
	 * @return  bool
	 */
	protected function boolean_value($value, $name)
	{
		if ($value === true or $value === 'true')
		{
			return true;
		}

		if ($value === false or $value === 'false')
		{
			return false;
		}

		throw new \InvalidArgumentException($name.' must be true or false.');
	}

	/**
	 * Execute a JSON API operation and hide internal exception details.
	 *
	 * @param   Closure  $operation
	 * @return  Response
	 */
	protected function execute_api(\Closure $operation)
	{
		try
		{
			return $operation();
		}
		catch (\InvalidArgumentException $exception)
		{
			return $this->json_error(
				'VALIDATION_ERROR',
				'入力内容を確認してください。',
				422
			);
		}
		catch (\Database_Exception $exception)
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
		catch (\RuntimeException $exception)
		{
			return $this->runtime_error($exception);
		}
		catch (\Throwable $exception)
		{
			return $this->internal_error($exception);
		}
	}

	/**
	 * Map allowed Service exception codes to generic responses.
	 *
	 * @param   RuntimeException  $exception
	 * @return  Response
	 */
	protected function runtime_error(\RuntimeException $exception)
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
	 * Log safe correlation data and return a generic server error.
	 *
	 * @param   Throwable  $exception
	 * @return  Response
	 */
	protected function internal_error(\Throwable $exception)
	{
		try
		{
			\Log::error(
				'JSON API failure. request_id='.$this->request_id
				.' exception_class='.get_class($exception)
			);
		}
		catch (\Throwable $logging_exception)
		{
			// Keep the client response generic even when logging fails.
		}

		return $this->json_error(
			'INTERNAL_ERROR',
			'処理に失敗しました。時間をおいて再度お試しください。',
			500
		);
	}

	/**
	 * Encode the common body and attach the JSON content type.
	 *
	 * @param   array  $body
	 * @param   int    $status
	 * @return  Response
	 */
	protected function json_response(array $body, $status)
	{
		$json = \Format::forge($body)->to_json();

		if ( ! is_string($json))
		{
			throw new \RuntimeException('The JSON response could not be encoded.');
		}

		return \Response::forge(
			$json,
			$status,
			array('Content-Type' => static::JSON_CONTENT_TYPE)
		);
	}
}
