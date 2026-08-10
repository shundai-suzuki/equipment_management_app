<?php
/**
 * 単一のローカルJSONファイルにログイン試行制限を保存する。
 */
class Security_LoginRateLimit
{
	/** @var int 状態ファイルの形式バージョン */
	const STATE_VERSION = 1;
	/** @var int 状態ファイルの最大バイト数 */
	const MAX_STATE_BYTES = 1048576;
	/** @var int 保存できるバケット総数 */
	const MAX_BUCKETS = 4096;
	/** @var int HMAC鍵の最小バイト数 */
	const MIN_HMAC_KEY_BYTES = 32;
	/** @var int 許可する社員IDの最大値 */
	const MAX_EMPLOYEE_ID = 2147483647;
	/** @var int 社員IDの最大桁数 */
	const MAX_EMPLOYEE_ID_LEN = 10;
	/** @var string ログイン試行制限の固定状態ファイル */
	const STATE_FILE = '/var/cache/fuel/login-rate-limit/state.json';
	/** @var string 単一状態ファイルのパス */
	protected $state_file;
	/** @var string バケット識別子を隠すHMAC鍵 */
	protected $hmac_key;
	/** @var array 社員番号単位の制限方針 */
	protected $account_policy;
	/** @var array IP単位の制限方針 */
	protected $ip_policy;
	/** @var int 未使用バケットを保持する秒数 */
	protected $retention_seconds;
	/**
	 * 設定と保存先を検証する。
	 *
	 * @param  array|null	$config	使用する設定
	 * @return void
	 */
	public function __construct($config = null)
	{
		$config = $config === null ? \Config::get('login_rate_limit') : $config;
		if ( ! is_array($config)
			or ! isset($config['state_file'])
			or $config['state_file'] !== static::STATE_FILE)
		{
			throw new Security_LoginRateLimitException(
				'The login rate-limit config is invalid.'
			);
		}
		$this->state_file = $config['state_file'];
		$this->hmac_key = isset($config['hmac_key']) ? $config['hmac_key'] : null;
		$this->retention_seconds = isset($config['retention_seconds']) ? $config['retention_seconds'] : null;
		foreach (array(
			'account' => 'account_policy',
			'ip' => 'ip_policy',
		) as $name => $property)
		{
			$policy = isset($config[$name]) ? $config[$name] : null;
			foreach (array('max_failures', 'window_seconds', 'block_seconds') as $key)
			{
				if ( ! is_array($policy)
					or ! isset($policy[$key])
					or ! is_int($policy[$key])
					or $policy[$key] < 1)
				{
					throw new Security_LoginRateLimitException(
						'The login rate-limit policy is invalid.'
					);
				}
			}
			$this->{$property} = $policy;
		}
		if ( ! is_string($this->hmac_key)
			or strlen($this->hmac_key) < static::MIN_HMAC_KEY_BYTES
			or ! is_int($this->retention_seconds)
			or $this->retention_seconds < 1)
		{
			throw new Security_LoginRateLimitException(
				'The login rate-limit config is invalid.'
			);
		}
		$directory = dirname($this->state_file);
		if (is_link($directory)
			or ( ! is_dir($directory)
				and ! @mkdir($directory, 0700, true)
				and ! is_dir($directory)))
		{
			throw new Security_LoginRateLimitException(
				'The login rate-limit state directory cannot be created.'
			);
		}
		clearstatcache(true, $directory);
		if (is_link($directory)
			or ! is_dir($directory)
			or ! is_readable($directory)
			or ! is_writable($directory))
		{
			throw new Security_LoginRateLimitException(
				'The login rate-limit state directory is unavailable.'
			);
		}
	}
	/**
	 * 社員番号とIPのいずれかがブロック中か確認する。
	 *
	 * @param  mixed	$employee_number	社員番号
	 * @param  mixed	$ip_address			接続元IPアドレス
	 * @return bool
	 */
	public function is_blocked($employee_number, $ip_address)
	{
		$buckets = $this->build_buckets($employee_number, $ip_address);
		return $this->use_state(function (array &$state) use ($buckets)
		{
			$now = time();
			foreach ($buckets as $bucket)
			{
				if (isset($state[$bucket['type']][$bucket['key']])
					and $state[$bucket['type']][$bucket['key']]['blocked_until'] > $now)
				{
					return true;
				}
			}
			return false;
		}, false);
	}
	/**
	 * 社員番号とIPの失敗回数を同じロック内で加算する。
	 *
	 * @param  mixed	$employee_number	社員番号
	 * @param  mixed	$ip_address			接続元IPアドレス
	 * @return bool
	 */
	public function record_failure($employee_number, $ip_address)
	{
		$buckets = $this->build_buckets($employee_number, $ip_address);
		return $this->use_state(function (array &$state) use ($buckets)
		{
			$now = time();
			$blocked = false;
			$this->prune_state($state, $now);
			foreach ($buckets as $bucket)
			{
				$type = $bucket['type'];
				$key = $bucket['key'];
				$policy = $bucket['policy'];
				$current = isset($state[$type][$key])
					? $state[$type][$key]
					: null;
				if ($current === null
					or $now >= $current['window_started_at'] + $policy['window_seconds'])
				{
					$current = array(
						'failed_count' => 0,
						'window_started_at' => $now,
						'blocked_until' => 0,
						'updated_at' => $now,
					);
				}
				$current['failed_count'] = min(
					$current['failed_count'] + 1,
					$policy['max_failures']
				);
				if ($current['failed_count'] >= $policy['max_failures']
					and $current['blocked_until'] <= $now)
				{
					$current['blocked_until'] = $now + $policy['block_seconds'];
				}
				$current['updated_at'] = $now;
				$state[$type][$key] = $current;
				$blocked = $blocked || $current['blocked_until'] > $now;
			}
			return $blocked;
		}, true);
	}
	/**
	 * ログイン成功後に社員番号バケットだけを消去する。
	 *
	 * @param  mixed	$employee_number	社員番号
	 * @return void
	 */
	public function record_success($employee_number)
	{
		$key = $this->employee_key($employee_number);
		if ($key === null)
		{
			return;
		}
		$this->use_state(function (array &$state) use ($key)
		{
			unset($state['accounts'][$key]);
		}, true);
	}
	/**
	 * 保持期間を過ぎた未使用バケットを削除する。
	 *
	 * @return int
	 */
	public function cleanup()
	{
		return $this->use_state(function (array &$state)
		{
			return $this->prune_state($state, time());
		}, true);
	}
	/**
	 * 任意の社員番号バケットと必須のIPバケットを生成する。
	 *
	 * @param mixed	$employee_number	社員番号
	 * @param mixed	$ip_address			接続元IPアドレス
	 * @return array
	 */
	protected function build_buckets($employee_number, $ip_address)
	{
		$buckets = array();
		$employee_key = $this->employee_key($employee_number);
		$packed_ip = is_string($ip_address) ? inet_pton($ip_address) : false;
		$normalized_ip = $packed_ip === false ? false : inet_ntop($packed_ip);
		if ($normalized_ip === false)
		{
			throw new Security_LoginRateLimitException(
				'The direct client IP is unavailable.'
			);
		}
		if ($employee_key !== null)
		{
			$buckets[] = array(
				'type' => 'accounts',
				'key' => $employee_key,
				'policy' => $this->account_policy,
			);
		}
		$buckets[] = array(
			'type' => 'ips',
			'key' => hash_hmac('sha256', $normalized_ip, $this->hmac_key),
			'policy' => $this->ip_policy,
		);
		return $buckets;
	}
	/**
	 * 有効な社員番号をHMACバケットキーへ変換する。
	 *
	 * @param  mixed	$employee_number	社員番号
	 * @return string|null
	 */
	protected function employee_key($employee_number)
	{
		if (is_int($employee_number))
		{
			$employee_id = $employee_number;
		}
		elseif (is_string($employee_number))
		{
			$employee_number = trim($employee_number);
			if (preg_match('/^[1-9][0-9]*$/D', $employee_number) !== 1
				or strlen($employee_number) > static::MAX_EMPLOYEE_ID_LEN)
			{
				return null;
			}
			$employee_id = (int) $employee_number;
		}
		else
		{
			return null;
		}
		if ($employee_id < 1 or $employee_id > static::MAX_EMPLOYEE_ID)
		{
			return null;
		}
		return hash_hmac('sha256', (string) $employee_id, $this->hmac_key);
	}
	/**
	 * 単一状態ファイルを排他ロックして処理する。
	 *
	 * @param  \Closure	$operation	状態へ適用する処理
	 * @param  bool		$write		処理後の状態を書き込むか
	 * @return mixed
	 */
	protected function use_state(\Closure $operation, $write)
	{
		if (is_link($this->state_file))
		{
			throw new Security_LoginRateLimitException(
				'The login rate-limit state file is unsafe.'
			);
		}
		$handle = @fopen($this->state_file, 'c+');
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
		try
		{
			clearstatcache(true, $this->state_file);
			$stat = @fstat($handle);
			if (is_link($this->state_file)
				or $stat === false
				or ($stat['mode'] & 0170000) !== 0100000
				or $stat['size'] > static::MAX_STATE_BYTES
				or ! @chmod($this->state_file, 0600)
				or @rewind($handle) === false)
			{
				throw new Security_LoginRateLimitException(
					'The login rate-limit state file is invalid.'
				);
			}
			$json = stream_get_contents($handle, static::MAX_STATE_BYTES + 1);
			$state = $json === ''
				? array(
					'version' => static::STATE_VERSION,
					'accounts' => array(),
					'ips' => array(),
				)
				: json_decode($json, true);
			if ( ! is_string($json)
				or strlen($json) > static::MAX_STATE_BYTES
				or ! $this->is_valid_state($state))
			{
				throw new Security_LoginRateLimitException(
					'The login rate-limit state file is invalid.'
				);
			}
			$result = $operation($state);
			if ($write)
			{
				$json = $this->is_valid_state($state) ? json_encode($state) : false;
				if ( ! is_string($json)
					or strlen($json) > static::MAX_STATE_BYTES
					or ! @ftruncate($handle, 0)
					or @rewind($handle) === false)
				{
					throw new Security_LoginRateLimitException(
						'The login rate-limit state file cannot be written.'
					);
				}
				if (@fwrite($handle, $json) !== strlen($json)
					or ! @fflush($handle))
				{
					throw new Security_LoginRateLimitException(
						'The login rate-limit state file cannot be written.'
					);
				}
			}
			return $result;
		}
		finally
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
	/**
	 * 状態全体の構造、HMACキー、件数を検証する。
	 *
	 * @param mixed	$state	ログイン試行制限の状態
	 * @return bool
	 */
	protected function is_valid_state($state)
	{
		$state_keys = array('version', 'accounts', 'ips');
		$bucket_keys = array(
			'failed_count',
			'window_started_at',
			'blocked_until',
			'updated_at',
		);
		if ( ! is_array($state)
			or array_diff($state_keys, array_keys($state))
			or array_diff(array_keys($state), $state_keys)
			or ! isset($state['version'])
			or $state['version'] !== static::STATE_VERSION
			or ! is_array($state['accounts'])
			or ! is_array($state['ips'])
			or count($state['accounts']) + count($state['ips']) > static::MAX_BUCKETS)
		{
			return false;
		}
		foreach (array('accounts', 'ips') as $type)
		{
			foreach ($state[$type] as $key => $bucket)
			{
				if ( ! is_string($key)
					or preg_match('/^[0-9a-f]{64}$/D', $key) !== 1
					or ! is_array($bucket)
					or array_diff($bucket_keys, array_keys($bucket))
					or array_diff(array_keys($bucket), $bucket_keys))
				{
					return false;
				}
				foreach ($bucket_keys as $name)
				{
					if ( ! is_int($bucket[$name]) or $bucket[$name] < 0)
					{
						return false;
					}
				}
			}
		}
		return true;
	}
	/**
	 * 期限切れバケットを状態から削除する。
	 *
	 * @param  array	$state	状態全体
	 * @param  int	$now	現在時刻
	 * @return int
	 */
	protected function prune_state(array &$state, $now)
	{
		$deleted = 0;
		foreach (array('accounts', 'ips') as $type)
		{
			foreach ($state[$type] as $key => $bucket)
			{
				if ($bucket['blocked_until'] <= $now
					and $bucket['updated_at'] <= $now
					and $now - $bucket['updated_at'] >= $this->retention_seconds)
				{
					unset($state[$type][$key]);
					$deleted++;
				}
			}
		}
		return $deleted;
	}
}
