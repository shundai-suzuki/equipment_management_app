<?php

/**
 * JSON APIの共通コントローラ。
 */
abstract class Controller_Base extends Controller_Rest
{
	/** @var string JSONレスポンスのContent-Type */
	const JSON_CONTENT_TYPE = 'application/json; charset=utf-8';
	/** @var int 許可する整数の最大値 */
	const MAX_INTEGER = 2147483647;

	/** @var string AJAX専用ヘッダに依存せず、常にJSONを返す */
	protected $rest_format = 'json';

	/** @var bool trueの場合は認証必須、falseの場合は未ログインでも実行可能 */
	protected $authentication_required = true;

	/** @var string レスポンスとサーバ側ログで共有する識別子 */
	protected $request_id;

	/** @var Response|null アクセスを停止する場合にアクション実行前に用意するレスポンス */
	protected $before_response;

	/**
	 * リクエスト識別子と継続認証を初期化する。
	 *
	 * @return void
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
	 * before()がエラーレスポンスを用意した場合は振り分け処理を停止する。
	 *
	 * @param  string         $resource  操作対象のリソース名
	 * @param  array          $arguments ルーターへ渡された引数
	 * @return Response|mixed
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
	 * 任意の一覧メタデータを含む成功レスポンスを生成する。
	 *
	 * @param  array      $data   Viewへ渡すデータ
	 * @param  int        $status HTTPステータスコード
	 * @param  array|null $meta   レスポンスの付加情報
	 * @return Response
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
	 * 任意の項目別メッセージを含むエラーレスポンスを生成する。
	 *
	 * @param  string     $code    エラーコード
	 * @param  string     $message エラーメッセージ
	 * @param  int        $status  HTTPステータスコード
	 * @param  array|null $fields  入力項目ごとのエラー
	 * @return Response
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
	 * コントローラで使用を許可する認証済み社員項目を返す。
	 *
	 * @return array|null
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
	 * 認証済み社員のIDを返す。
	 *
	 * @return int
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
	 * 必須のPOST整数値を取得して変換する。
	 *
	 * @param  string $name    対象の名前
	 * @param  int    $minimum 許可する最小値
	 * @return int
	 */
	protected function post_integer($name, $minimum = 1)
	{
		return $this->integer_value(\Input::post($name), $name, $minimum);
	}

	/**
	 * 任意の正のクエリ整数値を取得する。
	 *
	 * @param  string   $name 対象の名前
	 * @return int|null
	 */
	protected function optional_query_integer($name)
	{
		$value = \Input::get($name);

		return $value === null or $value === ''
			? null
			: $this->integer_value($value, $name);
	}

	/**
	 * 符号なし10進数の入力を整数へ変換する。
	 *
	 * @param  mixed  $value   検証する値
	 * @param  string $name    対象の名前
	 * @param  int    $minimum 許可する最小値
	 * @return int
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
	 * クエリ文字列のtrueとfalseを真偽値へ変換する。
	 *
	 * @param  mixed  $value 検証する値
	 * @param  string $name  対象の名前
	 * @return bool
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
	 * JSON API処理を実行し、内部例外の詳細を隠す。
	 *
	 * @param  Closure $operation 実行する操作名
	 * @return Response
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
	 * 許可したサービス例外コードを共通レスポンスへ変換する。
	 *
	 * @param  RuntimeException $exception 発生した例外
	 * @return Response
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
	 * 安全な追跡情報を記録し、共通のサーバエラーを返す。
	 *
	 * @param  Throwable $exception 発生した例外
	 * @return Response
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
			// ログ出力に失敗しても、クライアントへのレスポンスを共通形式に保つ。
		}

		return $this->json_error(
			'INTERNAL_ERROR',
			'処理に失敗しました。時間をおいて再度お試しください。',
			500
		);
	}

	/**
	 * 共通本文を符号化し、JSONのContent-Typeを付与する。
	 *
	 * @param  array $body   レスポンス本文
	 * @param  int   $status HTTPステータスコード
	 * @return Response
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
