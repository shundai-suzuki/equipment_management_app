<?php

/**
 * 管理者用部署検索API。
 */
class Controller_Table_Department extends Controller_Admin
{
	/**
	 * 部署サービスを生成する。
	 *
	 * @return Service_Table_Department
	 */
	protected function new_service()
	{
		return new Service_Table_Department();
	}
}
