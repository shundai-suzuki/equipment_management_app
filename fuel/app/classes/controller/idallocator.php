<?php

/**
 * アプリケーション管理IDが必要な登録処理用の基底コントローラ。
 *
 * このコントローラはID採番アクションを公開しない。具体的な登録用
 * コントローラがallocate_id()を呼び、サービス層だけへ処理を委譲する。
 *
 * @package  app
 * @extends  Controller
 */
abstract class Controller_IdAllocator extends Controller
{
	/**
	 * ID採番に使用するサービス。
	 *
	 * @var Service_IdAllocator
	 */
	protected $id_allocator_service;

	/**
	 * 登録処理を振り分ける前にサービス依存を準備する。
	 *
	 * @return  void
	 */
	public function before()
	{
		parent::before();

		$this->id_allocator_service = new Service_IdAllocator();
	}

	/**
	 * ID採番をサービス層へ委譲する。
	 * コントローラ（INSERT処理）→サービス→モデル→DB
	 *
	 * @param   string   $table
	 * @param   Closure  $operation
	 * @return  int
	 */
	protected function allocate_id($table, \Closure $operation)
	{
		if ($this->id_allocator_service === null)
		{
			throw new \LogicException('The ID allocator service has not been initialized.');
		}

		return $this->id_allocator_service->allocate($table, $operation);
	}
}
