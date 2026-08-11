<?php

/**
 * 部署の業務規則を適用する。
 */
class Service_Table_Department extends Service_BaseCrud
{
	/**
	 * 有効な部署の選択肢を取得する。
	 *
	 * @return array
	 */
	public function read_options()
	{
		return $this->model->read_options();
	}

	/**
	 * 部署を登録するか、同名の削除済み部署を復元する。
	 *
	 * @param mixed $name 部署名
	 * @return int
	 */
	public function create($name)
	{
		$name = $this->required_text($name);
		$department = $this->model->read_by_name($name);

		if ($department === null)
		{
			return $this->create_record(array('name' => $name));
		}

		if ($department['deleted_at'] === null)
		{
			throw new \RuntimeException(
				'A department with the same name already exists.',
				static::CONFLICT_EXCEPTION_CODE
			);
		}

		$this->restore_record((int) $department['id']);
		return (int) $department['id'];
	}

	/**
	 * 部署名を更新する。
	 *
	 * @param int   $id   対象部署のID
	 * @param mixed $name 部署名
	 * @return void
	 */
	public function update($id, $name)
	{
		$this->update_record($id, array(
			'name' => $this->required_text($name),
		));
	}

	/**
	 * 参照されていない部署を論理削除する。
	 *
	 * @param int $id 対象部署のID
	 * @return void
	 */
	public function soft_delete($id)
	{
		$this->read($id);

		if ($this->model->has_active_references($id))
		{
			throw new \RuntimeException(
				'The department is still referenced.',
				static::CONFLICT_EXCEPTION_CODE
			);
		}

		$this->model->soft_delete($id);
	}

	/**
	 * 論理削除済み部署を復元する。
	 *
	 * @param int $id 対象部署のID
	 * @return void
	 */
	public function restore($id)
	{
		$this->restore_record($id);
	}

	/**
	 * このServiceで使用する部署Modelを生成する。
	 *
	 * @return Model_Table_Department
	 */
	protected function new_model()
	{
		return new Model_Table_Department();
	}
}
