<?php

/**
 * テーブルServiceで共通のCRUD処理を提供する。
 */
abstract class Service_BaseCrud
{
	/** @var int データ未検出時の例外コード */
	const NOT_FOUND_EXCEPTION_CODE = 404;
	/** @var int 入力不正時の例外コード */
	const VALIDATION_EXCEPTION_CODE = 422;
	/** @var int 競合発生時の例外コード */
	const CONFLICT_EXCEPTION_CODE = 409;
	/** @var int 1ページ当たりの表示件数 */
	const PER_PAGE = 10;

	/** @var Model_BaseCrud DB操作を担当するModel */
	protected $model;

	/**
	 * 使用するModelを初期化する。
	 *
	 * @param object|null $model 使用する操作対象Model
	 * @return void
	 */
	public function __construct($model = null)
	{
		$this->model = $model ?: $this->new_model();
	}

	/**
	 * 子Serviceで使用するModelを生成する。
	 *
	 * @return Model_BaseCrud
	 */
	abstract protected function new_model();

	/**
	 * 有効な1行を取得する。
	 *
	 * @param int $id 対象レコードのID
	 * @return array
	 */
	public function read($id)
	{
		$this->assert_positive_id($id);
		$record = $this->model->read($id);

		if ($record === null)
		{
			throw new \RuntimeException(
				'The requested record was not found.',
				static::NOT_FOUND_EXCEPTION_CODE
			);
		}

		return $record;
	}

	/**
	 * 1ページ10件の検索結果を取得する。
	 *
	 * @param int    $page    取得するページ番号
	 * @param string $keyword 検索キーワード
	 * @param array  $filters 検索条件
	 * @return array
	 */
	public function search($page, $keyword = '', array $filters = array())
	{
		$this->assert_positive_id($page);

		return $this->model->search(
			$page,
			static::PER_PAGE,
			is_string($keyword) ? trim($keyword) : '',
			$filters
		);
	}

	/**
	 * Modelへ新規登録を委譲する。
	 *
	 * @param array $create_values 登録する値
	 * @return int
	 */
	protected function create_record(array $create_values)
	{
		return $this->model->create($create_values);
	}

	/**
	 * 存在する行を更新する。
	 *
	 * @param int   $id            対象レコードのID
	 * @param array $update_values 更新する値
	 * @return void
	 */
	protected function update_record($id, array $update_values)
	{
		$this->read($id);
		$this->model->update($id, $update_values);
	}

	/**
	 * 有効な行を論理削除する。
	 *
	 * @param int $id 対象レコードのID
	 * @return void
	 */
	protected function soft_delete_record($id)
	{
		$this->read($id);
		$this->model->soft_delete($id);
	}

	/**
	 * 論理削除済み行を復元する。
	 *
	 * @param int $id 対象レコードのID
	 * @return void
	 */
	protected function restore_record($id)
	{
		$this->assert_positive_id($id);

		if ($this->model->restore($id) !== 1)
		{
			throw new \RuntimeException(
				'The archived record was not found.',
				static::NOT_FOUND_EXCEPTION_CODE
			);
		}
	}

	/**
	 * 正のIDを確認する。
	 *
	 * @param int $id 対象レコードのID
	 * @return void
	 */
	protected function assert_positive_id($id)
	{
		if ( ! is_int($id) or $id < 1)
		{
			throw new \InvalidArgumentException('The ID must be a positive integer.');
		}
	}

	/**
	 * 必須テキストを前後空白なしで返す。
	 *
	 * @param mixed $value      入力値
	 * @param int   $max_length 許可する最大文字数
	 * @return string
	 */
	protected function required_text($value, $max_length)
	{
		$value = is_string($value) ? trim($value) : '';

		if ($value === '' or mb_strlen($value, 'UTF-8') > $max_length)
		{
			throw new \InvalidArgumentException('The text value is invalid.');
		}

		return $value;
	}

	/**
	 * 任意テキストを前後空白なしで返す。
	 *
	 * @param mixed $value      入力値
	 * @param int   $max_length 許可する最大文字数
	 * @return string|null
	 */
	protected function optional_text($value, $max_length)
	{
		if ($value === null or $value === '')
		{
			return null;
		}

		return $this->required_text($value, $max_length);
	}
}
