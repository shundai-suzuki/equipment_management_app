<?php

namespace Fuel\Tasks;

/**
 * 空の業務テーブルへ動作確認用の初期データを投入する。
 */
class Inventorysetup
{
	/** @var int パスワードの最小文字数 */
	const MIN_PASSWORD_LENGTH = 7;
	/** @var int パスワードの最大文字数 */
	const MAX_PASSWORD_LENGTH = 10;

	/** @var array .envから読み取るパスワード設定名 */
	protected static $password_keys = array(
		'password1',
		'password2',
		'password3',
		'password4',
		'password5',
	);

	/**
	 * 初期データを投入する。
	 *
	 * @return void
	 */
	public static function run()
	{
		$password_hashes = static::create_password_hashes();
		$db = \Database_Connection::instance();
		if ( ! $db->start_transaction())
		{
			throw new \RuntimeException('Failed to start the initial-data transaction.');
		}

		try
		{
			static::assert_empty_tables($db);
			$departments = static::insert_departments($db);
			$employees = static::insert_employees($departments, $password_hashes,	$db);
			$equipments = static::insert_equipments($departments, $db);
			static::insert_loans($employees, $equipments, $db);

			if ( ! $db->commit_transaction())
			{
				throw new \RuntimeException('Failed to commit the initial-data transaction.');
			}
		}
		catch (\Throwable $exception)
		{
			if ($db->in_transaction()
				and ! $db->rollback_transaction())
			{
				throw new \RuntimeException(
					'Failed to rollback the initial-data transaction.',
					0,
					$exception
				);
			}

			throw $exception;
		}

		\Cli::write('Initial data was created.');
	}

	/**
	 * .envから読み込んだパスワードをハッシュ化する。
	 *
	 * @return array
	 */
	protected static function create_password_hashes()
	{
		$hashes = array();

		foreach (static::read_passwords() as $password)
		{
			$hash = password_hash($password, PASSWORD_DEFAULT);
			if ($hash === false)
			{
				throw new \RuntimeException('Failed to create the password hash.');
			}
			$hashes[] = $hash;
		}

		return $hashes;
	}

	/**
	 * Task専用の.envから5件のパスワードを読み取る。
	 *
	 * @return array
	 */
	protected static function read_passwords()
	{
		$password_file = __DIR__.DIRECTORY_SEPARATOR.'demopassword';
		if ( ! is_file($password_file) or ! is_readable($password_file))
		{
			throw new \RuntimeException('The initial-password file is unavailable.');
		}

		$lines = @file($password_file, FILE_IGNORE_NEW_LINES);
		if ( ! is_array($lines))
		{
			throw new \RuntimeException('The initial-password file cannot be read.');
		}

		$passwords = array();
		foreach ($lines as $line)
		{
			$line = trim($line);
			if ($line === '' or substr($line, 0, 1) === '#')
			{
				continue;
			}

			if (preg_match(
				'/\A(password[1-5])\s*=\s*"([A-Za-z0-9]{7,10})"\s*,?\s*\z/',
				$line,
				$matches
			) !== 1)
			{
				throw new \RuntimeException('The initial-password file is invalid.');
			}

			if (isset($passwords[$matches[1]]))
			{
				throw new \RuntimeException('The initial-password file is invalid.');
			}

			$passwords[$matches[1]] = $matches[2];
		}

		$ordered_passwords = array();
		foreach (static::$password_keys as $key)
		{
			if ( ! isset($passwords[$key]))
			{
				throw new \RuntimeException('The initial-password file is incomplete.');
			}

			$ordered_passwords[] = $passwords[$key];
		}

		if (count($passwords) !== count(static::$password_keys))
		{
			throw new \RuntimeException('The initial-password file is invalid.');
		}

		return $ordered_passwords;
	}

	/**
	 * 全対象テーブルが空であることを検証する。
	 *
	 * @return void
	 */
	protected static function assert_empty_tables(\Database_Connection $db)
	{
		foreach (array('departments', 'employees', 'equipments', 'loans') as $table)
		{
			$count = \DB::select(array(\DB::expr('COUNT(*)'), 'record_count'))
				->from($table)
				->execute($db)
				->get('record_count', 0);

			if ((int) $count !== 0)
			{
				throw new \RuntimeException(
					'Initial data can only be created in empty tables.'
				);
			}
		}
	}

	/**
	 * 部署の初期データを登録する。
	 *
	 * @return array
	 */
	protected static function insert_departments(\Database_Connection $db)
	{
		$model = new \Model_Table_Department($db);
		$ids = array();

		foreach (array('総務部', '開発部', '営業部') as $name)
		{
			$ids[] = $model->create(array('name' => $name), $db);
		}

		return $ids;
	}

	/**
	 * 社員の初期データを登録する。
	 *
	 * @param array               $departments     部署ID一覧
	 * @param array               $password_hashes 保存するパスワードハッシュ一覧
	 * @param Database_Connection $db              使用するDB接続
	 * @return array
	 */
	protected static function insert_employees(array $departments, array $password_hashes, \Database_Connection $db)
	{
		$model = new \Model_Table_Employee($db);
		$employees = array(
			array('管理者A', $departments[0], 'ADMIN'),
			array('管理者B', $departments[1], 'ADMIN'),
			array('社員A', $departments[0], 'EMPLOYEE'),
			array('社員B', $departments[1], 'EMPLOYEE'),
			array('社員C', $departments[2], 'EMPLOYEE'),
		);
		$ids = array();

		foreach ($employees as $index => $employee)
		{
			$ids[] = $model->create(array(
				'employee_name' => $employee[0],
				'department_id' => $employee[1],
				'role' => $employee[2],
				'password_hash' => $password_hashes[$index],
				'is_active' => 1,
			), $db);
		}

		return $ids;
	}

	/**
	 * 備品の初期データを登録する。
	 *
	 * @param array               $departments 部署ID一覧
	 * @param Database_Connection $db          使用するDB接続
	 * @return array
	 */
	protected static function insert_equipments(array $departments,	\Database_Connection $db)
	{
		$model = new \Model_Table_Equipment($db);
		$equipments = array(
			array('ノートPC', $departments[1], 'PC', 3, '開発用ノートPC'),
			array('外部モニター', $departments[1], '周辺機器', 5, '24インチモニター'),
			array('Webカメラ', $departments[1], '周辺機器', 4, '会議用Webカメラ'),
			array('キーボード', $departments[1], '周辺機器', 6, '日本語配列キーボード'),
			array('マウス', $departments[1], '周辺機器', 6, 'ワイヤレスマウス'),
			array('プロジェクター', $departments[0], '会議用品', 2, '会議室用プロジェクター'),
			array('HDMIケーブル', $departments[0], '会議用品', 8, '会議室接続用'),
			array('タブレット', $departments[2], 'PC', 2, '営業説明用タブレット'),
			array('デジタルカメラ', $departments[2], '撮影機器', 1, '商品撮影用'),
			array('ホワイトボード', $departments[0], '会議用品', 2, '移動式ホワイトボード'),
			array('ラベルプリンター', $departments[0], '文具', 1, '備品管理ラベル用'),
		);
		$ids = array();

		foreach ($equipments as $equipment)
		{
			$ids[] = $model->create(array(
				'name' => $equipment[0],
				'department_id' => $equipment[1],
				'category' => $equipment[2],
				'total_amount' => $equipment[3],
				'description' => $equipment[4],
			), $db);
		}

		return $ids;
	}

	/**
	 * 貸出の初期データを登録する。
	 *
	 * @param array               $employees  社員ID一覧
	 * @param array               $equipments 備品ID一覧
	 * @param Database_Connection $db         使用するDB接続
	 * @return void
	 */
	protected static function insert_loans(array $employees, array $equipments, \Database_Connection $db) 
	{
		$model = new \Model_Table_Loan($db);
		$loans = array(
			array($employees[2], $equipments[0], '2026-08-18', '2026-08-08', null, null, '開発作業用'),
			array($employees[3], $equipments[1], '2026-08-20', '2026-08-10', null, null, '外部モニター利用'),
			array($employees[4], $equipments[7], '2026-08-22', '2026-08-11', null, null, '営業説明用'),
			array($employees[2], $equipments[5], '2026-07-15', '2026-07-01', '2026-07-10', $employees[1], '会議で使用'),
		);

		foreach ($loans as $loan)
		{
			$model->create(array(
				'employee_id' => $loan[0],
				'equipment_id' => $loan[1],
				'due_date' => $loan[2],
				'loaned_at' => $loan[3],
				'loaned_by' => $employees[0],
				'returned_at' => $loan[4],
				'returned_by' => $loan[5],
				'note' => $loan[6],
			), $db);
		}
	}
}
