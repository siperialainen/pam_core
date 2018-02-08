<?php

namespace PamCore\Assets;

use PamCore\Model;
use PamCore\TokenableTrait;

/**
 * Class Tokens
 * @package Pam
 */
class Tokens extends Model
{
    use TokenableTrait;

    protected $tableName = 'asset_tokens';

    protected $idColumn = 'id';

    /**
     * @param $assetId
     * @return int
     */
    public function generateAssetToken($assetId)
    {
        return $this->insert(
            [
                'assetId' => $assetId,
                'deviceToken' => null,
                'assetToken' => $this->generateToken(),
            ],
            true
        );
    }

    /**
     * @param $assetId
     * @return mixed|null
     */
    public function getOneByAssetId($assetId)
    {
        return $this->getOneByFields(['assetId' => $assetId]);
    }

    /**
     * @param $assetId
     * @param $token
     * @return bool
     */
    public function isDeviceTokenExists($assetId, $token)
    {
        if ($this->getOneByFields([
            'assetId' => $assetId,
            'deviceToken' => $token,
        ])) {
            return true;
        }

        return false;
    }

    /**
     * @param $assetId
     * @param $assetToken
     * @return bool|string
     */
    public function processAssetToken($assetId, $assetToken)
    {
        $tokensData = $this->getOneByFields([
            'assetId' => $assetId,
            'assetToken' => $assetToken,
        ]);

        if (!$tokensData || $tokensData['deviceToken']) {
            return false;
        }

        $deviceToken = $this->generateToken();

        $this->update(
            $tokensData['id'],
            [
                'deviceToken' => $deviceToken,
            ]
        );

        return $deviceToken;
    }
}