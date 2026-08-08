<?php

namespace Fuel\Tasks;

/**
 * Remove expired login rate-limit state files.
 *
 * @package  app
 */
class LoginRateLimitCleanup
{
	/**
	 * Run the cleanup and report the number of deleted files.
	 *
	 * @return  string
	 */
	public static function run()
	{
		$deleted = (new \Security_LoginRateLimit())->cleanup();

		return 'Deleted '.$deleted
			.' expired login rate-limit state file(s).';
	}
}
