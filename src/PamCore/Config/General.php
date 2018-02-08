<?php
namespace PamCore\Config;


class General
{
    const SECS_8_HOURS = 28800;
    /**
     * @var static
     */
    private static $instance;

    private $version;

    /**
     * Can be 'pam', 'worker' or 'sws'
     *
     * @var string
     */
    private $app = 'pam';

    /**
     * Can be 'aws' or 'docker'
     *
     * @var string
     */
    private $env = 'aws';

    private $stackName = 'dev';

    private function __construct()
    {}

    /**
     * @return static
     */
    public static function get()
    {
        if (!static::$instance) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    public function setDockerEnv()
    {
        $this->env = 'docker';
    }

    public function setAwsEnv()
    {
        $this->env = 'aws';
    }

    public function getEnv()
    {
        return $this->env;
    }

    public function isAwsEnv() {
        return $this->getEnv() === 'aws';
    }

    public function isDockerEnv() {
        return $this->getEnv() === 'docker';
    }

    public function setStackName($name)
    {
        $this->stackName = $name;
    }

    public function getStackName()
    {
        return $this->stackName;
    }

    public function setAppName($name)
    {
        $this->app = $name;
    }

    public function getAppName()
    {
        return $this->app;
    }

    /**
     * Detects if site should work in demo mode
     * @return bool
     */
    public function isDemo()
    {
        return substr(strtoupper(\PamCore\Client::get()->getName()), 0, 4) == "DEMO";
    }

    /**
     * Detects if site is running on development stack
     * @return bool
     */
    public function isDevStack()
    {
        global $STACK_NAME;
        return in_array($STACK_NAME, ['dev', 'devinn']);
    }

    /**
     * Detects if site is running on demo stack
     * @return bool
     */
    public function isDemoStack()
    {
        global $STACK_NAME;
        return in_array($STACK_NAME, ['demous', 'demoaws']);
    }

    /**
     * Detects if site is running on staging stack
     * @return bool
     */
    public function isStagingStack()
    {
        global $STACK_NAME;
        return in_array($STACK_NAME, ['staging']);
    }

    /**
     * Detects if site is running on prod stack
     * @return bool
     */
    public function isProdStack()
    {
        global $STACK_NAME;
        return in_array($STACK_NAME, ['prod', 'produs']);
    }

    public function getAppLogFilePath()
    {
        $customLog = '/var/log/mediabankpam/app.log';
        return is_writable($customLog) ? $customLog : 'php://stderr';
    }

    public function getLanguages()
    {
        $languages = [
            [
                'language' => 'Simplified Chinese',
                'abbr' => 'zh',
                'active' => 'Y'
            ],
            [
                'language' => 'Korean',
                'abbr' => 'ko',
                'active' => 'N'
            ],
            [
                'language' => 'Arabic',
                'abbr' => 'ar',
                'active' => 'N'
            ],
        ];
        if (\PamCore\Client::isClientInitiated() && \PamCore\Client::get()->getId() == 'yyz') {
            $languages[0] = [
                'language' => 'French',
                'abbr' => 'fr',
                'active' => 'Y'
            ];
        }
        return $languages;
    }

    public function getHelpCentreUrl()
    {
        return 'https://mediabankpam.atlassian.net/wiki/display/PHC/PAM+%7C+HELP+CENTRE';
    }

    public function getSupportEmail()
    {
        return 'support@mediabankpam.com';
    }

    public function getVersion()
    {
        if (!$this->version) {
            $filenameVersion = dirname($_SERVER["DOCUMENT_ROOT"]) . "/version.txt";
            if (file_exists($filenameVersion)) {
                $this->version = trim(file_get_contents($filenameVersion));
            } else {
                $this->version = "local";
            }
        }
        return $this->version;
    }
}