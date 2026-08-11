<?php

/**
 * HTMLエラー画面を生成する。
 */
class Controller_Page_Error extends \Controller
{
	/**
	 * 未一致ルートへ404画面を返す。
	 *
	 * @return Response
	 */
	public function action_not_found()
	{
		return \Response::forge(\View::forge('404'), 404);
	}
}
