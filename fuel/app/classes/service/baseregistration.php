<?php

/**
 * テーブルサービス間で共有する登録手順を調整する。
 *
 * @package  app
 */
abstract class Service_BaseRegistration
{
	/**
	 * 子サービスが宣言する物理テーブル名。
	 *
	 * @var string
	 */
	protected static $table_name = '';

	/**
	 * データベース操作に使用するテーブルモデル。
	 *
	 * @var Model_BaseCrud
	 */
	protected $model;

	/**
	 * アプリケーション管理IDの採番に使用するサービス。
	 *
	 * @var Service_IdAllocator
	 */
	protected $id_allocator;

	/**
	 * @param  object|null  $model
	 * @param  object|null  $id_allocator
	 */
	public function __construct($model = null, $id_allocator = null)
	{
		if ($model !== null and ! is_object($model))
		{
			throw new \InvalidArgumentException('The table model must be an object.');
		}

		if ($id_allocator !== null and ! is_object($id_allocator))
		{
			throw new \InvalidArgumentException('The ID allocator service must be an object.');
		}

		$this->model = $model ? $model : $this->new_model();
		$this->id_allocator = $id_allocator ? $id_allocator : new Service_IdAllocator();
	}

	/**
	 * 子サービス用の標準テーブルモデルを生成する。
	 *
	 * @return  Model_BaseCrud
	 */
	abstract protected function new_model();

	/**
	 * 新規行を示すクライアント側の識別値を必須とする。
	 *
	 * @param   mixed  $id
	 * @return  void
	 */
	protected function assert_new_id($id)
	{
		if ($id !== 0)
		{
			throw new \InvalidArgumentException('A new record ID must be 0.');
		}
	}

	/**
	 * 外部キーまたは実行者IDが正の整数であることを必須とする。
	 *
	 * @param   mixed   $id
	 * @param   string  $name
	 * @return  void
	 */
	protected function assert_positive_id($id, $name)
	{
		if ( ! is_int($id) or $id < 1)
		{
			throw new \InvalidArgumentException($name.' must be a positive integer.');
		}
	}

	/**
	 * 同じ接続上でIDを採番し、子モデルを通して登録する。
	 *
	 * @param   array  $create_values
	 * @return  int
	 */
	protected function create_record(array $create_values)
	{
		if (static::$table_name === '')
		{
			throw new \LogicException('The registration table name is not configured.');
		}

		return $this->id_allocator->allocate(
			static::$table_name,
			function ($id, $db) use ($create_values)
			{
				$this->before_create($create_values, $db);

				if ( ! $this->model->create($id, $create_values, $db))
				{
					throw new \RuntimeException('Failed to create the allocated record.');
				}
			}
		);
	}

	/**
	 * 子サービスがトランザクション内でテーブル固有規則を再確認できるようにする。
	 *
	 * @param   array                $create_values
	 * @param   Database_Connection  $db
	 * @return  void
	 */
	protected function before_create(array $create_values, \Database_Connection $db)
	{
	}
}
