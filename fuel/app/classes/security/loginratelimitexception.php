<?php

/**
 * ログイン試行制限の状態を安全に使用できないことを示す。
 */
class Security_LoginRateLimitException extends \RuntimeException
{
}
