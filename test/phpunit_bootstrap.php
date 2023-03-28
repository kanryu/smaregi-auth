<?php
ini_set('memory_limit', '512M');

$_SERVER['ENV_DIVISION'] = $_ENV['ENV_DIVISION'];

// PROTOCOL = http
$_SERVER["HTTPS"] = null;

// ホスト名  (リクエスト時のFQDN)
$_SERVER["HTTP_HOST"] = 'localhost.webapi.example.com';

// リモートIPアドレス
$_SERVER["REMOTE_ADDR"] = '127.0.0.1';

// ドキュメントルート 物理ディレクトリ
$_SERVER["DOCUMENT_ROOT"] = __DIR__ . '/../samples';


ini_set('error_reporting', E_ALL & ~E_STRICT & ~E_NOTICE); // PHP5でStrict Standardsの警告を抑制

require_once("vendor/autoload.php");

use Psr\Log\AbstractLogger;

/** 出力される内容を記録するだけの単純なlogger */
class TestLogger extends AbstractLogger
{
    public $logs = array();
    public function log($level, $message, array $context = array())
    {
        $this->logs[$level] = isset($this->logs[$level]) ? $this->logs[$level] : array(); 
        $this->logs[$level][] = $message;
    }
}

