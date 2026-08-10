<?php

/**
 * 部署登録規則を適用し、永続化を委譲する。
 */
class Service_Table_Department extends Service_BaseCrud
{
	/** @var int 名前の最大文字数 */
	const MAX_NAME_LENGTH = 255;

	/** @var string このサービスが登録するテーブル */
	protected static $table_name = 'departments';

	/**
	 * 有効な部署の選択肢を返す。
	 *
	 * @return array
	 */
	public function read_options()
	{
		return $this->model->read_options();
	}

	/**
	 * 部署を登録するか、同名の論理削除済み部署を復元する。
	 *
	 * @param  string $name 対象の名前
	 * @return int
	 */
	public function create($name)
	{
		$name = $this->normalize_name($name);
		$read_department = $this->model->read_by_name($name);

		if ($read_department !== null)
		{
			return $this->restore_or_reject($read_department);
		}

		try
		{
			return $this->create_record(array('name' => $name));
		}
		catch (\Database_Exception $exception)
		{
			return $this->handle_create_exception($name, $exception);
		}
	}

	/**
	 * 管理者を再確認してから部署を登録する。
	 *
	 * @param  int    $actor_id 操作する管理者の社員ID
	 * @param  string $name     対象の名前
	 * @return int
	 */
	public function create_for_admin($actor_id, $name)
	{
		$this->assert_admin_actor($actor_id);

		return $this->create($name);
	}

	/**
	 * 部署名を更新する。
	 *
	 * @param  int    $actor_id 操作する管理者の社員ID
	 * @param  int    $id       対象レコードのID
	 * @param  string $name     対象の名前
	 * @return array
	 */
	public function update_for_admin($actor_id, $id, $name)
	{
		$this->assert_positive_id($id, 'The department ID');
		$name = $this->normalize_name($name);

		return $this->model->transaction(
			function ($db) use ($actor_id, $id, $name)
			{
				$this->assert_admin_actor($actor_id, $db);
				$department_before_update = $this->model->read_for_update($id, false, $db);

				if ($department_before_update === null)
				{
					throw new \RuntimeException(
						'The department was not found.',
						static::NOT_FOUND_EXCEPTION_CODE
					);
				}

				$read_duplicate = $this->model->read_by_name($name, $db);

				if ($read_duplicate !== null and (int) $read_duplicate['id'] !== $id)
				{
					throw new \RuntimeException(
						'A department with the same name already exists.',
						static::CONFLICT_EXCEPTION_CODE
					);
				}

				return $this->update_and_read_record($id, array('name' => $name), $db);
			}
		);
	}

	/**
	 * 未使用の部署を論理削除する。
	 *
	 * @param  int $actor_id 操作する管理者の社員ID
	 * @param  int $id       対象レコードのID
	 * @return array
	 */
	public function soft_delete_for_admin($actor_id, $id)
	{
		$this->assert_positive_id($id, 'The department ID');

		return $this->model->transaction(
			function ($db) use ($actor_id, $id)
			{
				$this->assert_admin_actor($actor_id, $db);

				$department_before_delete = $this->model->read_for_update($id, true, $db);

				if ($department_before_delete === null)
				{
					throw new \RuntimeException(
						'The department was not found.',
						static::NOT_FOUND_EXCEPTION_CODE
					);
				}

				if ($department_before_delete['deleted_at'] !== null)
				{
					throw new \RuntimeException(
						'The department is already archived.',
						static::CONFLICT_EXCEPTION_CODE
					);
				}

				if ($this->model->has_active_references($id, $db))
				{
					throw new \RuntimeException(
						'The department is still referenced.',
						static::CONFLICT_EXCEPTION_CODE
					);
				}

				return $this->soft_delete_and_read_record($id, $db);
			}
		);
	}

	/**
	 * 論理削除済み部署を1件復元する。
	 *
	 * @param  int $actor_id 操作する管理者の社員ID
	 * @param  int $id       対象レコードのID
	 * @return array
	 */
	public function restore_for_admin($actor_id, $id)
	{
		$this->assert_positive_id($id, 'The department ID');

		return $this->model->transaction(
			function ($db) use ($actor_id, $id)
			{
				$this->assert_admin_actor($actor_id, $db);
				$department_before_restore = $this->model->read_for_update(
					$id,
					true,
					$db
				);

				if ($department_before_restore === null)
				{
					throw new \RuntimeException(
						'The department was not found.',
						static::NOT_FOUND_EXCEPTION_CODE
					);
				}

				if ($department_before_restore['deleted_at'] === null)
				{
					throw new \RuntimeException(
						'The department is not archived.',
						static::CONFLICT_EXCEPTION_CODE
					);
				}

				return $this->restore_and_read_record($id, $db);
			}
		);
	}

	/**
	 * このサービスで使用する部署モデルを生成する。
	 *
	 * @return Model_Table_Department
	 */
	protected function new_model()
	{
		return new Model_Table_Department();
	}

	/**
	 * 部署名を正規化して検証する。
	 *
	 * @param  mixed $name 対象の名前
	 * @return string
	 */
	protected function normalize_name($name)
	{
		if ( ! is_string($name))
		{
			throw new \InvalidArgumentException('The department name must be a string.');
		}

		$name = trim($name);

		if ($name === '')
		{
			throw new \InvalidArgumentException('The department name is required.');
		}

		if (mb_strlen($name, 'UTF-8') > static::MAX_NAME_LENGTH)
		{
			throw new \InvalidArgumentException('The department name must not exceed 255 characters.');
		}

		return $name;
	}

	/**
	 * 一致する論理削除済み行を復元するか、有効な重複行を拒否する。
	 *
	 * @param  array $read_department 取得した部署情報
	 * @return int
	 */
	protected function restore_or_reject(array $read_department)
	{
		if ($read_department['deleted_at'] === null)
		{
			throw new \RuntimeException(
				'A department with the same name already exists.',
				static::CONFLICT_EXCEPTION_CODE
			);
		}

		$id = (int) $read_department['id'];

		return $this->model->transaction(
			function ($db) use ($id)
			{
				$department_before_restore = $this->model->read_for_update(
					$id,
					true,
					$db
				);

				if ($department_before_restore === null)
				{
					throw new \RuntimeException(
						'The archived department could not be read.',
						static::CONFLICT_EXCEPTION_CODE
					);
				}

				if ($department_before_restore['deleted_at'] === null)
				{
					return $id;
				}

				$this->restore_and_read_record($id, $db);

				return $id;
			}
		);
	}

	/**
	 * 部署名の重複を競合へ変換する。
	 *
	 * @param  string             $name      対象の名前
	 * @param  Database_Exception $exception 発生した例外
	 * @return int
	 */
	protected function handle_create_exception($name, \Database_Exception $exception)
	{
		if ((int) $exception->getCode() !== 1062)
		{
			throw $exception;
		}

		$read_department = $this->model->read_by_name($name);

		if ($read_department === null)
		{
			throw $exception;
		}

		return $this->restore_or_reject($read_department);
	}
}
