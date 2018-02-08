<?php

namespace PamCore;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Maxbanton\Cwh\Handler\CloudWatch;
use PamCore\Config\General;

class Log
{
    CONST DAYS_TO_KEEP = 20;

    /**
     * @var Logger
     */
    private static $defaultCloudWatchLogger;

    /**
     * @var Logger
     */
    private static $localLogger;

    private static $groupName = 'php';

    /**
     * Get Logger instance: CloudWatch or local file depending on environment
     *
     * Example:
     * $log->debug('Foo');
     * $log->warning('Bar');
     * $log->error('Baz');
     *
     * @param string|null $streamName
     * @param array $awsCustomConfig
     *
     * @return Logger
     */
    public static function get($streamName = null, array $awsCustomConfig = [])
    {
        global $AWSconfig;

        if (empty($awsCustomConfig)) {
            $awsCustomConfig = $AWSconfig;
        }

        $logger = null;
        $default = false;
        if (empty($streamName)) {
            if (static::$defaultCloudWatchLogger !== null) {
                return static::$defaultCloudWatchLogger;
            }
            $streamName = General::get()->getStackName() . '-' . General::get()->getAppName();
            $default = true;
        }

        if (General::get()->isAwsEnv() && !empty($awsCustomConfig)) {
            $client = new CloudWatchLogsClient($awsCustomConfig['default']);
            $handler = new CloudWatch($client, static::$groupName, $streamName, static::DAYS_TO_KEEP, 10000);
            $logger = new Logger('app_logger', [$handler]);
            if ($default) {
                static::$defaultCloudWatchLogger = $logger;
            }
        } else {
            if (!static::$localLogger) {
                $logFilePath = General::get()->getAppLogFilePath();
                $streamHandler = new StreamHandler($logFilePath, Logger::DEBUG);
                static::$localLogger = new Logger('app_logger', [$streamHandler]);
            }
            $logger = static::$localLogger;
        }
        return $logger;
    }

    /**
     * Write log message with type defined in $errno
     *
     * @param int $errno Predefined PHP error type constant
     * @param string $message
     */
    public static function write($errno, $message)
    {
        $logger = static::get();
        $context = static::getDefaultContext();
        switch ($errno) {
            case E_WARNING:
            case E_USER_WARNING:
                $logger->warning($message, $context);
                break;
            case E_NOTICE:
            case E_USER_NOTICE:
                $logger->notice($message, $context);
                break;
            case E_ERROR:
            case E_PARSE:
            case E_CORE_ERROR:
            case E_CORE_WARNING:
            case E_COMPILE_ERROR:
            case E_COMPILE_WARNING:
            case E_RECOVERABLE_ERROR:
            case E_USER_ERROR:
                $logger->error($message, $context);
                break;
            default:
                $logger->info($message, $context);
                break;
        }
    }

    public static function warning($message) {
        static::get()->warning($message, static::getDefaultContext());
    }
    public static function notice($message) {
        static::get()->notice($message, static::getDefaultContext());
    }
    public static function error($message) {
        static::get()->error($message, static::getDefaultContext());
    }
    public static function info($message) {
        static::get()->info($message, static::getDefaultContext());
    }
    /**
     * Custom error handler callback
     * Write log and echo if stack isn't prod and demo
     *
     * @param int $errno Predefined PHP error type constant
     * @param string $message
     */
    public static function errorHandler($errno, $message)
    {
        ob_start();
        debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);
        $trace = ob_get_clean();
        $message = $message . "\n" . $trace;
        static::write($errno, $message);
        if (!General::get()->isProdStack() && !General::get()->isDemoStack()) {
            echo $message;
        }
    }

    /**
     * Custom exception handler callback
     * Write log and echo if stack isn't prod and demo
     *
     * @param \Exception $exception
     */
    public static function exceptionHandler(\Exception $exception)
    {
        $message = $exception->getMessage() . "\n" . $exception->getTraceAsString();
        static::get()->error($message, static::getDefaultContext());
        if (!General::get()->isProdStack() && !General::get()->isDemoStack()) {
            echo $message;
        }
    }

    /**
     * Custom shutdown handler
     * Set 500 Internal Server Error if header wasn't sent yet
     * Log last error
     */
    public static function shutdownHandler()
    {
        $err = error_get_last();

        if (!isset($err)) {
            return;
        }

        $handledErrorTypes = [
            E_ERROR,
            E_PARSE,
            E_CORE_ERROR,
            E_CORE_WARNING,
            E_COMPILE_ERROR,
            E_COMPILE_WARNING
        ];

        if (!in_array($err['type'], $handledErrorTypes)) {
            return;
        }

        if (!headers_sent()) {
            header('HTTP/1.1 500 Internal Server Error');
        }

        ob_start();
        debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);
        $trace = ob_get_clean();
        $message = $err['message'] . "\n" . $trace;
        static::write($err['type'], $message);
    }

    /**
     * Return default context for a log: current version and client ID
     *
     * @return array
     */
    public static function getDefaultContext() {
        try {
            $clientId = Client::get()->getId();
        } catch(\Exception $e) {
            $clientId = 'session-not-started';
        }

        return [
            'version' => General::get()->getVersion(),
            'clientid' => $clientId
        ];
    }
}