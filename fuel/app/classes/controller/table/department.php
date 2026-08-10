<?php

/** 管理者用部署検索API。 */
class Controller_Table_Department extends Controller_AdminCrud
{
	/** 部署サービスを生成する。 */
	protected function new_service()
	{
		return new Service_Table_Department();
	}
}
