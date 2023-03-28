<?php

namespace Kanryu\SmaregiAuth
{
    use Psr\Log\AbstractLogger;

    /** 標準出力に書き出すだけの単純なlogger */
    class EchoLogger extends AbstractLogger
    {
        public function log($level, $message, array $context = array())
        {
            echo "[{$level}] {$message}\r\n";
        }
    }
}



