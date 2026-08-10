<?php

/**
 * テーブルサービス向けに管理者確認と共通CRUD手順を提供する。
 *
 * @package  app
 */
abstract class Service_BaseCrud
{
	const NOT_FOUND_EXCEPTION_CODE = 404;
	const FORBIDDEN_EXCEPTION_CODE = 403;
	const VALIDATION_EXCEPTION_CODE = 422;
	const CONFLICT_EXCEPTION_CODE = 409;
	const PER_PAGE = 10;
	const MAX_KEYWORD_LENGTH = 255;

	/**
	 * 操作を実行する管理者の確認に使用する社員モデル。
	 *
	 * @var Model_Table_Employee|null
	 */
	protected $actor_model;

	/** @var Model_BaseCrud DB操作を担当するモデル。 */
	protected $model;

	/** 使用するModelを受け取り、未指定時は子Serviceの標準Modelを生成する。 */
	public function __construct($model = null)
	{
		if ($model !== null and ! ($model instanceof Model_BaseCrud))
    {
			throw new \InvalidArgumentException(
				'The CRUD model must extend Model_BaseCrud.'
			);
    }
		$this->model = $model ?: $this->new_model();
	}

	/** 子サービス用のモデルを生成する。 */
	abstract protected function new_model();

	/** 外部キーまたは実行者IDが正の整数であることを確認する。 */
	protected function assert_positive_id($id, $name)
	{
		if ( ! is_int($id) or $id < 1)
		{
			throw new \InvalidArgumentException($name.' must be a positive integer.');
		}
	}

	/** 事前確認と登録を同じトランザクションで実行する。 */
	protected function create_record(array $create_values)
	{
		return $this->model->transaction(function ($db) use ($create_values)
		{
			$this->before_create($create_values, $db);

			return $this->model->create($create_values, $db);
		});
	}

	/** 子サービスが登録直前の業務条件を再確認する。 */
	protected function before_create(array $create_values, \Database_Connection $db)
	{
	}

	/**
	 * 有効な1行を返す。
	 *
	 * @param   int  $id
	 * @return  array
	 */
	public function read($id)
	{
		$this->assert_positive_id($id, 'The record ID');

		return $this->read_required_record($id);
	}

	/**
	 * 管理者を再確認してから有効な1行を返す。
	 *
	 * @param   int  $actor_id
	 * @param   int  $id
	 * @return  array
	 */
	public function read_for_admin($actor_id, $id)
	{
		$this->assert_admin_actor($actor_id);

		return $this->read($id);
	}

	/**
	 * 検証済みページングで有効な行一覧を返す。
	 *
	 * @param   int     $page
	 * @param   string  $keyword
	 * @param   array   $filters
	 * @return  array
	 */
	public function search($page, $keyword = '', array $filters = array())
	{
		if ( ! is_int($page) or $page < 1)
		{
			throw new \InvalidArgumentException('The page must be a positive integer.');
		}

		if ( ! is_string($keyword))
		{
			throw new \InvalidArgumentException('The keyword must be a string.');
		}

		$keyword = trim($keyword);

		if (mb_strlen($keyword, 'UTF-8') > static::MAX_KEYWORD_LENGTH)
		{
			throw new \InvalidArgumentException('The keyword is too long.');
		}

		return $this->model->search($page, static::PER_PAGE, $keyword, $filters);
	}

	/**
	 * 検証済みページングで有効な行一覧を返す。
	 *
	 * @param   int     $actor_id
	 * @param   int     $page
	 * @param   string  $keyword
	 * @param   array   $filters
	 * @return  array
	 */
	public function search_for_admin($actor_id, $page, $keyword = '', array $filters = array())
	{
		$this->assert_admin_actor($actor_id);

		return $this->search($page, $keyword, $filters);
	}

	/**
	 * 有効な行が存在することを必須とする。
	 *
	 * @param   int                       $id
	 * @param   Database_Connection|null  $db
	 * @return  array
	 */
	protected function read_required_record($id, $db = null)
	{
		$read_record = $this->model->read($id, false, $db);

		if ($read_record === null)
		{
			throw new \RuntimeException(
				'The requested record was not found.',
				static::NOT_FOUND_EXCEPTION_CODE
			);
		}

		return $read_record;
	}

	/**
	 * ロック済み行を更新し、現在の内容を返す。
	 *
	 * @param   int                       $id
	 * @param   array                     $update_values
	 * @param   Database_Connection|null  $db
	 * @return  array
	 */
	protected function update_and_read_record($id, array $update_values, $db = null)
	{
		$this->model->update($id, $update_values, $db);
		$updated_record = $this->model->read($id, false, $db);

		if ($updated_record === null)
		{
			throw new \RuntimeException(
				'The record changed during the update.',
				static::CONFLICT_EXCEPTION_CODE
			);
		}

		return $updated_record;
	}

	/**
	 * ロック済み行を論理削除し、現在の内容を返す。
	 *
	 * @param   int                       $id
	 * @param   Database_Connection|null  $db
	 * @return  array
	 */
	protected function soft_delete_and_read_record($id, $db = null)
	{
		if ($this->model->soft_delete($id, $db) !== 1)
		{
			throw new \RuntimeException(
				'The record changed during the soft-delete operation.',
				static::CONFLICT_EXCEPTION_CODE
			);
		}

		$soft_deleted_record = $this->model->read($id, true, $db);

		if ($soft_deleted_record === null)
		{
			throw new \RuntimeException(
				'The soft-deleted record could not be read.',
				static::CONFLICT_EXCEPTION_CODE
			);
		}

		return $soft_deleted_record;
	}

	/**
	 * ロック済み行を復元し、有効な内容を返す。
	 *
	 * @param   int                       $id
	 * @param   Database_Connection|null  $db
	 * @return  array
	 */
	protected function restore_and_read_record($id, $db = null)
	{
		if ($this->model->restore($id, $db) !== 1)
		{
			throw new \RuntimeException(
				'The record changed during the restore operation.',
				static::CONFLICT_EXCEPTION_CODE
			);
		}

		return $this->read_required_record($id, $db);
	}

	/**
	 * 実行者が引き続き有効な管理者であることを再確認する。
	 *
	 * @param   int                       $actor_id
	 * @param   Database_Connection|null  $db
	 * @return  void
	 */
	protected function assert_admin_actor($actor_id, $db = null)
	{
		$this->assert_positive_id($actor_id, 'The administrator employee ID');

		if ($this->actor_model === null)
		{
			$this->actor_model = new Model_Table_Employee();
		}

		if ( ! $this->actor_model->is_active_admin($actor_id, $db))
		{
			throw new \RuntimeException(
				'The actor is not an active administrator.',
				static::FORBIDDEN_EXCEPTION_CODE
			);
		}
	}
}
