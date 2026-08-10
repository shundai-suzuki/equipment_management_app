<?php
/**
 * Fuelは高速で軽量なコミュニティ主導のPHP5フレームワーク。
 *
 * @package    Fuel
 * @version    1.8
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2016 Fuel Development Team
 * @link       http://fuelphp.com
 */

/**
 * Welcomeコントローラ。
 *
 * 基本的なコントローラ例。レスポンス本文とステータスの
 * 設定方法を示す。
 *
 * @package  app
 * @extends  Controller
 */
class Controller_Welcome extends Controller
{
	/**
	 * 基本のウェルカムメッセージ。
	 *
	 * @access  public
	 * @return  Response
	 */
	public function action_index()
	{
		return Response::forge(View::forge('welcome/index'));
	}

	/**
	 * 一般的な「Hello, Bob!」形式の例。Presenterの
	 * 使用方法を示す。
	 *
	 * @access  public
	 * @return  Response
	 */
	public function action_hello()
	{
		return Response::forge(Presenter::forge('welcome/hello'));
	}

	/**
	 * アプリケーションの404アクション。
	 *
	 * @access  public
	 * @return  Response
	 */
	public function action_404()
	{
		return Response::forge(Presenter::forge('welcome/404'), 404);
	}
}
