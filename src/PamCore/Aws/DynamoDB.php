<?php
namespace PamCore\Aws;

use Aws\DynamoDb\DynamoDbClient;
use Aws\Sdk;

class DynamoDB
{
    private static $instance;
    /**
     * @var DynamoDbClient $dynamoDbClient
     */
    private static $dynamoDbClient;

    private function __construct() {
    }

    private function __clone() {
    }

    /**
     * @param array $config Should be full config array like `site/connections/aws.config.php`
     * @return null|DynamoDB
     */
    public static function instance(array $config = array())
    {
        if (!static::$instance) {
            static::$instance = new static();
            if (self::$dynamoDbClient === null) {
                // default config
                global $AWSconfig;
                if (empty($config) && isset($AWSconfig)) {
                    $config = $AWSconfig;
                }

                self::$dynamoDbClient = (new Sdk($config['default']))->createDynamoDb();
            }
        }
        return static::$instance;
    }

    /**
     * Get default TTL for DynamoDb record
     *
     * @return int
     */
    public static function getDefaultTtl()
    {
        return (time() + (8 * 3600));
    }

    /**
     * @return DynamoDbClient
     */
    public function getClient()
    {
        return self::$dynamoDbClient;
    }
}