<?php

/**
 * HMACで命名したローカルJSONファイルにログイン試行制限を保存する。
 */
class Security_LoginRateLimit
{
	/** @var int 状態ファイルの形式バージョン */
	const STATE_VERSION = 1;
	/** @var int 状態ファイルの最大バイト数 */
	const MAX_STATE_BYTES = 1024;
	/** @var int HMAC鍵の最小バイト数 */
	const MIN_HMAC_KEY_BYTES = 32;
	/** @var int 許可する社員IDの最大値 */
	const MAX_EMPLOYEE_ID = 2147483647;
	/** @var int 社員IDの最大桁数 */
	const MAX_EMPLOYEE_ID_LEN = 10;
	/** @var string ログイン試行制限の状態保存ディレクトリ */
	const STATE_DIR = '/var/cache/fuel/login-rate-limit';

	/** @var string アプリケーション設定で固定するディレクトリ */
	protected $state_dir;

	/** @var string 試行制限の識別子を隠すためだけに使用する秘密値 */
	protected $hmac_key;

	/** @var array 社員番号1件に対する失敗時の制限方針 */
	protected $account_policy;

	/** @var array 直接接続元IP1件に対する失敗時の制限方針 */
	protected $ip_policy;

	/** @var int 未使用状態ファイルを保持する秒数 */
	protected $retention_seconds;

	/**
	 * 設定を検証し、ログイン試行制限を初期化する。
	 *
	 * @param  array|null $config 使用する設定
	 * @return void
	 */
	public function __construct($config = null)
	{
		$config = $config === null
			? \Config::get('login_rate_limit')
			: $config;

		if ( ! is_array($config))
		{
			throw new Security_LoginRateLimitException(
				'The login rate-limit config is not available.'
			);
		}

		$this->state_dir = $this->validate_state_dir(
			isset($config['state_dir']) ? $config['state_dir'] : null
		);
		$this->hmac_key = isset($config['hmac_key'])
			? $config['hmac_key']
			: null;
		$this->account_policy = $this->validate_policy(
			isset($config['account']) ? $config['account'] : null
		);
		$this->ip_policy = $this->validate_policy(
			isset($config['ip']) ? $config['ip'] : null
		);
		$this->retention_seconds = $this->validate_retention_seconds(
			isset($config['retention_seconds']) ? $config['retention_seconds'] : null
		);

		if ( ! is_string($this->hmac_key)
			or strlen($this->hmac_key) < static::MIN_HMAC_KEY_BYTES)
		{
			throw new Security_LoginRateLimitException(
				'The login rate-limit HMAC key is not configured.'
			);
		}

		$this->prepare_state_directory();
	}

	/**
	 * 認証前に社員番号とIPの保存単位を確認する。
	 *
	 * @param  mixed $employee_number 社員番号
	 * @param  mixed $ip_address      接続元IPアドレス
	 * @return bool
	 */
	public function is_blocked($employee_number, $ip_address)
	{
		foreach ($this->build_buckets($employee_number, $ip_address) as $bucket)
		{
			if ($this->bucket_is_blocked($bucket['path']))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * 対象となる各保存単位へ失敗を1回追加する。
	 *
	 * @param  mixed $employee_number 社員番号
	 * @param  mixed $ip_address      接続元IPアドレス
	 * @return bool
	 */
	public function record_failure($employee_number, $ip_address)
	{
		$blocked = false;

		foreach ($this->build_buckets($employee_number, $ip_address) as $bucket)
		{
			if ($this->record_bucket_failure($bucket['path'], $bucket['policy']))
			{
				$blocked = true;
			}
		}

		return $blocked;
	}

	/**
	 * ログイン成功後に社員番号の保存単位だけを消去する。
	 *
	 * @param  mixed $employee_number 社員番号
	 * @return void
	 */
	public function record_success($employee_number)
	{
		$employee_id = $this->normalize_employee_id($employee_number);

		if ($employee_id === null)
		{
			return;
		}

		$this->delete_bucket(
			$this->bucket_path('account', (string) $employee_id)
		);
	}

	/**
	 * 保持期間を過ぎた未使用状態ファイルを削除する。
	 *
	 * @return int
	 */
	public function cleanup()
	{
		$filenames = @scandir($this->state_dir);

		if ( ! is_array($filenames))
		{
			throw new Security_LoginRateLimitException(
				'The login rate-limit state directory cannot be scanned.'
			);
		}

		$deleted = 0;
		$now = time();

		foreach ($filenames as $filename)
		{
			if ($filename === '.' or $filename === '..')
			{
				continue;
			}

			if (preg_match(
				'/\A(?:account|ip)-[0-9a-f]{64}\.json\z/',
				$filename
			) !== 1)
			{
				throw new Security_LoginRateLimitException(
					'The login rate-limit state filename is invalid.'
				);
			}

			$path = $this->state_dir.DIRECTORY_SEPARATOR.$filename;

			if ($this->delete_expired_bucket($path, $now))
			{
				$deleted++;
			}
		}

		return $deleted;
	}

	/**
	 * 公開ディレクトリ外の固定絶対パスだけを受け付ける。
	 *
	 * @param  mixed $state_dir 状態ファイルの保存ディレクトリ
	 * @return string
	 */
	protected function validate_state_dir($state_dir)
	{
		if ($state_dir !== static::STATE_DIR)
		{
			throw new Security_LoginRateLimitException(
				'The login rate-limit state directory is invalid.'
			);
		}

		return $state_dir;
	}

	/**
	 * 正の整数で構成された失敗時の制限方針を検証する。
	 *
	 * @param  mixed $policy ログイン試行制限のポリシー
	 * @return array
	 */
	protected function validate_policy($policy)
	{
		$keys = array('max_failures', 'window_seconds', 'block_seconds');

		if ( ! is_array($policy))
		{
			throw new Security_LoginRateLimitException(
				'The login rate-limit policy is invalid.'
			);
		}

		foreach ($keys as $key)
		{
			if ( ! isset($policy[$key])
				or ! is_int($policy[$key])
				or $policy[$key] < 1)
			{
				throw new Security_LoginRateLimitException(
					'The login rate-limit policy is invalid.'
				);
			}
		}

		return array(
			'max_failures' => $policy['max_failures'],
			'window_seconds' => $policy['window_seconds'],
			'block_seconds' => $policy['block_seconds'],
		);
	}

	/**
	 * 正の整数である清掃保持期間を受け付ける。
	 *
	 * @param  mixed $retention_seconds 状態を保持する秒数
	 * @return int
	 */
	protected function validate_retention_seconds($retention_seconds)
	{
		if ( ! is_int($retention_seconds) or $retention_seconds < 1)
		{
			throw new Security_LoginRateLimitException(
				'The login rate-limit retention period is invalid.'
			);
		}

		return $retention_seconds;
	}

	/**
	 * 非公開状態ディレクトリが存在しない場合は生成する。
	 *
	 * @return void
	 */
	protected function prepare_state_directory()
	{
		if (is_link($this->state_dir))
		{
			throw new Security_LoginRateLimitException(
				'The login rate-limit state directory is unsafe.'
			);
		}

		if ( ! file_exists($this->state_dir)
			and ! @mkdir($this->state_dir, 0700, true))
		{
			clearstatcache(true, $this->state_dir);

			if ( ! is_dir($this->state_dir))
			{
				throw new Security_LoginRateLimitException(
					'The login rate-limit state directory cannot be created.'
				);
			}
		}

		clearstatcache(true, $this->state_dir);

		if (is_link($this->state_dir)
			or ! is_dir($this->state_dir)
			or ! is_readable($this->state_dir)
			or ! is_writable($this->state_dir))
		{
			throw new Security_LoginRateLimitException(
				'The login rate-limit state directory is unavailable.'
			);
		}
	}

	/**
	 * 任意のアカウント保存単位と必須の直接接続元IP保存単位を生成する。
	 *
	 * @param  mixed $employee_number 社員番号
	 * @param  mixed $ip_address      接続元IPアドレス
	 * @return array
	 */
	protected function build_buckets($employee_number, $ip_address)
	{
		$buckets = array();
		$employee_id = $this->normalize_employee_id($employee_number);
		$normalized_ip = $this->normalize_ip($ip_address);

		if ($employee_id !== null)
		{
			$buckets[] = array(
				'path' => $this->bucket_path('account', (string) $employee_id),
				'policy' => $this->account_policy,
			);
		}

		$buckets[] = array(
			'path' => $this->bucket_path('ip', $normalized_ip),
			'policy' => $this->ip_policy,
		);

		return $buckets;
	}

	/**
	 * HMAC入力用に符号付きINT範囲の有効な社員番号を正規化する。
	 *
	 * @param  mixed $employee_number 社員番号
	 * @return int|null
	 */
	protected function normalize_employee_id($employee_number)
	{
		if (is_int($employee_number))
		{
			if ($employee_number < 1
				or $employee_number > static::MAX_EMPLOYEE_ID)
			{
				return null;
			}

			return $employee_number;
		}

		if ( ! is_string($employee_number))
		{
			return null;
		}

		$employee_number = trim($employee_number);

		if ( ! preg_match('/\A[1-9][0-9]*\z/', $employee_number)
			or strlen($employee_number) > static::MAX_EMPLOYEE_ID_LEN)
		{
			return null;
		}

		$employee_id = (int) $employee_number;

		return $employee_id <= static::MAX_EMPLOYEE_ID
			? $employee_id
			: null;
	}

	/**
	 * 直接接続元IPだけを正規化する。
	 *
	 * @param  mixed $ip_address 接続元IPアドレス
	 * @return string
	 */
	protected function normalize_ip($ip_address)
	{
		if ( ! is_string($ip_address)
			or filter_var($ip_address, FILTER_VALIDATE_IP) === false)
		{
			throw new Security_LoginRateLimitException(
				'The direct client IP is unavailable.'
			);
		}

		$packed_ip = inet_pton($ip_address);
		$normalized_ip = $packed_ip === false ? false : inet_ntop($packed_ip);

		if ($normalized_ip === false)
		{
			throw new Security_LoginRateLimitException(
				'The direct client IP is unavailable.'
			);
		}

		return $normalized_ip;
	}

	/**
	 * リクエスト値を含まない固定ファイル名を生成する。
	 *
	 * @param  string $type  エラー種別
	 * @param  string $value 検証する値
	 * @return string
	 */
	protected function bucket_path($type, $value)
	{
		$key = hash_hmac('sha256', $value, $this->hmac_key);

		return $this->state_dir.DIRECTORY_SEPARATOR.$type.'-'.$key.'.json';
	}

	/**
	 * 排他ロック中に保存単位を1件読み、制限期限を確認する。
	 *
	 * @param  string $path 対象ファイルのパス
	 * @return bool
	 */
	protected function bucket_is_blocked($path)
	{
		$locked_file = $this->open_locked_file($path, false);

		if ($locked_file === null)
		{
			return false;
		}

		try
		{
			$state = $this->read_state($locked_file['handle'], false);
			return $state['blocked_until'] > time();
		}
		finally
		{
			$this->close_locked_file($locked_file['handle']);
		}
	}

	/**
	 * 保存単位を1件更新し、更新後の制限状態を返す。
	 *
	 * @param  string $path   対象ファイルのパス
	 * @param  array  $policy ログイン試行制限のポリシー
	 * @return bool
	 */
	protected function record_bucket_failure($path, array $policy)
	{
		$locked_file = $this->open_locked_file($path, true);

		try
		{
			$state = $this->read_state(
				$locked_file['handle'],
				$locked_file['created']
			);
			$now = time();

			if ($state === null
				or $now >= $state['window_started_at'] + $policy['window_seconds'])
			{
				$state = array(
					'version' => static::STATE_VERSION,
					'failed_count' => 0,
					'window_started_at' => $now,
					'blocked_until' => 0,
					'updated_at' => $now,
				);
			}

			$state['failed_count'] = min(
				$state['failed_count'] + 1,
				$policy['max_failures']
			);

			if ($state['failed_count'] >= $policy['max_failures']
				and $state['blocked_until'] <= $now)
			{
				$state['blocked_until'] = $now + $policy['block_seconds'];
			}

			$state['updated_at'] = $now;
			$this->write_state($locked_file['handle'], $state);

			return $state['blocked_until'] > $now;
		}
		finally
		{
			$this->close_locked_file($locked_file['handle']);
		}
	}

	/**
	 * ロック保持中に検証済みの保存単位を1件削除する。
	 *
	 * @param  string $path 対象ファイルのパス
	 * @return void
	 */
	protected function delete_bucket($path)
	{
		$locked_file = $this->open_locked_file($path, false);

		if ($locked_file === null)
		{
			return;
		}

		try
		{
			$this->read_state($locked_file['handle'], false);

			if ( ! @unlink($path))
			{
				throw new Security_LoginRateLimitException(
					'The login rate-limit state cannot be deleted.'
				);
			}
		}
		finally
		{
			$this->close_locked_file($locked_file['handle']);
		}
	}

	/**
	 * ロック中に検証した期限切れ保存単位を1件削除する。
	 *
	 * @param  string $path 対象ファイルのパス
	 * @param  int    $now  現在時刻
	 * @return bool
	 */
	protected function delete_expired_bucket($path, $now)
	{
		$locked_file = $this->open_locked_file($path, false);

		if ($locked_file === null)
		{
			return false;
		}

		try
		{
			$state = $this->read_state($locked_file['handle'], false);

			if ($state['blocked_until'] > $now
				or $state['updated_at'] > $now
				or $now - $state['updated_at'] < $this->retention_seconds)
			{
				return false;
			}

			if ( ! @unlink($path))
			{
				throw new Security_LoginRateLimitException(
					'The login rate-limit state cannot be deleted.'
				);
			}

			return true;
		}
		finally
		{
			$this->close_locked_file($locked_file['handle']);
		}
	}

	/**
	 * 通常の状態ファイルを1件開き、排他ロックを取得する。
	 *
	 * @param  string $path   対象ファイルのパス
	 * @param  bool   $create 状態ファイルを新規作成するか
	 * @return array|null
	 */
	protected function open_locked_file($path, $create)
	{
		if (is_link($path))
		{
			throw new Security_LoginRateLimitException(
				'The login rate-limit state file is unsafe.'
			);
		}

		$exists = file_exists($path);

		if ( ! $create and ! $exists)
		{
			return null;
		}

		$handle = @fopen($path, $create ? 'c+' : 'r+');

		if ($handle === false)
		{
			throw new Security_LoginRateLimitException(
				'The login rate-limit state file cannot be opened.'
			);
		}

		if ( ! @flock($handle, LOCK_EX))
		{
			@fclose($handle);
			throw new Security_LoginRateLimitException(
				'The login rate-limit state file cannot be locked.'
			);
		}

		clearstatcache(true, $path);
		$stat = @fstat($handle);

		if (is_link($path)
			or $stat === false
			or ($stat['mode'] & 0170000) !== 0100000
			or ! @chmod($path, 0600))
		{
			@flock($handle, LOCK_UN);
			@fclose($handle);
			throw new Security_LoginRateLimitException(
				'The login rate-limit state file is unsafe.'
			);
		}

		return array(
			'handle' => $handle,
			'created' => ! $exists,
		);
	}

	/**
	 * サイズ制限内のJSON状態文書を1件読み取り、検証する。
	 *
	 * @param  resource $handle      状態ファイルのハンドル
	 * @param  bool     $allow_empty 空文字を許可するか
	 * @return array|null
	 */
	protected function read_state($handle, $allow_empty)
	{
		$stat = @fstat($handle);

		if ($stat === false or $stat['size'] > static::MAX_STATE_BYTES)
		{
			throw new Security_LoginRateLimitException(
				'The login rate-limit state file is invalid.'
			);
		}

		if (@rewind($handle) === false)
		{
			throw new Security_LoginRateLimitException(
				'The login rate-limit state file cannot be read.'
			);
		}

		$json = stream_get_contents($handle, static::MAX_STATE_BYTES + 1);

		if ($json === false or strlen($json) > static::MAX_STATE_BYTES)
		{
			throw new Security_LoginRateLimitException(
				'The login rate-limit state file cannot be read.'
			);
		}

		if ($json === '' and $allow_empty)
		{
			return null;
		}

		$state = json_decode($json, true);

		if ( ! $this->is_valid_state($state))
		{
			throw new Security_LoginRateLimitException(
				'The login rate-limit state file is invalid.'
			);
		}

		return $state;
	}

	/**
	 * 保存状態の厳密な構造を検証する。
	 *
	 * @param  mixed $state ログイン試行制限の状態
	 * @return bool
	 */
	protected function is_valid_state($state)
	{
		$keys = array(
			'version',
			'failed_count',
			'window_started_at',
			'blocked_until',
			'updated_at',
		);

		if ( ! is_array($state)
			or array_diff($keys, array_keys($state))
			or array_diff(array_keys($state), $keys))
		{
			return false;
		}

		foreach ($keys as $key)
		{
			if ( ! is_int($state[$key]) or $state[$key] < 0)
			{
				return false;
			}
		}

		return $state['version'] === static::STATE_VERSION;
	}

	/**
	 * 状態文書を1件置き換え、全バイトが書き込まれたことを検証する。
	 *
	 * @param  resource $handle 状態ファイルのハンドル
	 * @param  array    $state  ログイン試行制限の状態
	 * @return void
	 */
	protected function write_state($handle, array $state)
	{
		$json = json_encode($state);

		if ( ! is_string($json) or strlen($json) > static::MAX_STATE_BYTES)
		{
			throw new Security_LoginRateLimitException(
				'The login rate-limit state cannot be encoded.'
			);
		}

		if ( ! @ftruncate($handle, 0) or ! @rewind($handle))
		{
			throw new Security_LoginRateLimitException(
				'The login rate-limit state file cannot be written.'
			);
		}

		$written = 0;
		$length = strlen($json);

		while ($written < $length)
		{
			$bytes = @fwrite($handle, substr($json, $written));

			if ($bytes === false or $bytes === 0)
			{
				throw new Security_LoginRateLimitException(
					'The login rate-limit state file cannot be written.'
				);
			}

			$written += $bytes;
		}

		if ( ! @fflush($handle))
		{
			throw new Security_LoginRateLimitException(
				'The login rate-limit state file cannot be flushed.'
			);
		}
	}

	/**
	 * 状態ロックを1件解放し、ハンドルを閉じる。
	 *
	 * @param  resource $handle 状態ファイルのハンドル
	 * @return void
	 */
	protected function close_locked_file($handle)
	{
		$unlocked = @flock($handle, LOCK_UN);
		$closed = @fclose($handle);

		if ( ! $unlocked or ! $closed)
		{
			throw new Security_LoginRateLimitException(
				'The login rate-limit state file cannot be closed.'
			);
		}
	}
}
