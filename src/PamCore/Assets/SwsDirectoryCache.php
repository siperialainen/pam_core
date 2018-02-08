<?php
namespace PamCore\Assets;

use Aws\DynamoDb\BinaryValue;
use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;
use PamCore\Aws\DynamoDB;
use PamCore\Client;
use PamCore\Log;

class SwsDirectoryCache
{
    private function isCacheAllowed()
    {
        $header = getallheaders();
        return
            !isset($header['Cache-Control']) ||
            strpos(getallheaders()['Cache-Control'],'no-cache') === false;
    }

    public function get($tableName, $key)
    {
        if (!$this->isCacheAllowed()) {
            return false;
        }

        try {
            $clientId = Client::get()->getId();
            $keySafe = $this->getSafeKey($key);
            $marshaler = new Marshaler();
            $keyItem = $marshaler->marshalItem(
                [
                    "hash" => $keySafe
                ]
            );

            $params = [
                'TableName' => $tableName,
                'Key' => $keyItem
            ];

            $result = DynamoDB::instance()->getClient()->getItem($params);

            if (
                (isset($result['Item']['client']['S']) && strtolower($result['Item']['client']['S']) === strtolower($clientId))
                &&
                (isset($result['Item']['isSws']['N']) && $result['Item']['isSws']['N'] == 1)
                &&
                isset($result['Item']['cache']['B'])
            )
            {
                return unserialize(gzinflate($result['Item']['cache']['B']));
            }

            return null;
        } catch (DynamoDbException $e) {
            Log::get()->addError("Error getting cache data from DynamoDB: " . $e->getMessage());
            return null;
        }
    }

    public function set($tableName, $key, $data, $expirationTimeout = 3600) //default 1 hour
    {
        try {
            $clientId = Client::get()->getId();
            $keySafe = $this->getSafeKey($key);
            /**
             * Need to gzdeflate() the cache data to reduce the size since DynamoDB has limit of accepting 400kb
             * and this directory cache can grow more than the limit.
             * Since we are deflating the $data, its not longer possible to store this as "String" in DynamoDB
             * So we are storing it as "Binary" value
             */
            $cacheData = new BinaryValue(gzdeflate(serialize($data)));
            $marshaler = new Marshaler();
            $dataItem = $marshaler->marshalItem(
                [
                    "hash" => $keySafe,
                    "cache" => $cacheData,
                    "isSws" => 1,
                    "client" => $clientId,
                    "ttl" => (time() + $expirationTimeout)
                ]
            );

            $params = [
                'TableName' => $tableName,
                'Item' => $dataItem
            ];
            $result = DynamoDB::instance()->getClient()->putItem($params);

            return $result;
        } catch (DynamoDbException $e) {
            Log::get()->addError("Error saving cache data to DynamoDB: " . $e->getMessage());
            return null;
        }
    }

    public function cleanAll($tableName)
    {
        try {
            $clientId = Client::get()->getId();
            $dynamoDbClient = DynamoDB::instance()->getClient();

            $iterator = $dynamoDbClient->getIterator('Scan', [
                'TableName' => $tableName,
                'ScanFilter' => [
                    'isSws' => [
                        'AttributeValueList' => [
                            [
                                'N' => '1'
                            ]
                        ],
                        'ComparisonOperator' => 'EQ'
                    ],
                    'client' => [
                        'AttributeValueList' => [
                            [
                                'S' => $clientId
                            ]
                        ],
                        'ComparisonOperator' => 'EQ'
                    ]
                ]
            ]);

            foreach ($iterator as $item) {
                if (
                    (isset($item['client']['S']) && strtolower($item['client']['S']) === strtolower($clientId))
                    &&
                    (isset($item['isSws']['N']) && $item['isSws']['N'] == 1)
                ) {
                    $dynamoDbClient->deleteItem([
                        'TableName' => $tableName,
                        'Key' => [
                            'hash' => ['S' => $item['hash']['S']]
                        ]
                    ]);
                }
            }
        } catch (DynamoDbException $e) {
            Log::get()->addError("Error removing cache data in DynamoDB: " . $e->getMessage());
            return null;
        }
    }

    private function getSafeKey($key)
    {
        return md5($key);
    }
}