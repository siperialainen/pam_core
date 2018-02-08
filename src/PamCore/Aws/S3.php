<?php
namespace PamCore\Aws;

use Aws\S3\S3Client;
use PamCore\Client;
use Aws\S3\Exception\S3Exception;

try {
    $clientId = Client::get()->getId();
    S3::setClientId($clientId);
} catch(\Exception $e) {
    global $PAM_site_name;
    if (!isset($PAM_site_name)) {
        if (!defined('WITHOUT_CONNECTS_TO_DB')) {
            define('WITHOUT_CONNECTS_TO_DB', true);
        }

        require dirname($_SERVER["DOCUMENT_ROOT"]).'/connections/config.php';
    }
    S3::setClientId($PAM_site_name);
}

/**
 * Class S3
 * Should be used only after loading main config file
 *
 * @package Pam\Aws
 */
class S3
{

    const RAW_IMAGE_FOLDER = 'pam-img-raw';
    const GALLERY_FOLDER = 'gallery';
    const SWS_FOLDER = 'sws';

    private static $instance;
    /**
     * @var S3Client $s3client
     */
    private static $s3client;
    private static $bucket;
    private static $clientId = "test"; // used as prefix key
    private static $presignedExpires = "+10 minutes";

    private function __construct() {
    }

    private function __clone() {
    }

    /**
     * @param array $config Should be full config array like `site/connections/aws.config.php`
     * @return null|S3
     */
    public static function instance(array $config = array())
    {
        if (!static::$instance) {
            static::$instance = new static();
            if (self::$s3client === null) {
                // default config
                global $AWSconfig;
                if (empty($config) && isset($AWSconfig)) {
                    $config = $AWSconfig;
                }

                self::$bucket = $config["s3"]["bucket"];
                self::$s3client = new S3Client($config["s3"]);
                if (array_key_exists("presignedExpires", $config["s3"])) {
                    static::setPresignedExpires($config["s3"]["presignedExpires"]);
                }
            }
        }
        return static::$instance;
    }

    /**
     * @return string
     */
    public static function getBucket()
    {
        return self::$bucket;
    }

    /**
     * Set clientId
     *
     * @param string $clientId
     */
    public static function setClientId($clientId){
        static::$clientId = trim(str_replace(array(' ', '..'), array('-', '.'), strtolower($clientId)), '.');
    }

    /**
     * @return string
     */
    public static function getClientId() {
        return static::$clientId;
    }

    /**
     * @return string
     */
    public static function getPresignedExpires()
    {
        return self::$presignedExpires;
    }

    /**
     * @param string $presignedExpires
     */
    public static function setPresignedExpires($presignedExpires)
    {
        self::$presignedExpires = $presignedExpires;
    }

    /**
     * @return S3Client
     */
    public function getClient()
    {
        return self::$s3client;
    }

    public function getContentType($ext)
    {
        switch($ext) {
            case 'mp3':
            case 'mpeg3':
            case 'mpeg':
                $contentType = 'audio/' . $ext;
                break;
            case 'svg':
                $contentType = 'image/svg+xml';
                break;
            case 'sql':
            case 'pdf':
            case 'zip':
                $contentType = 'application/' . $ext;
                break;
            case 'indd':
                $contentType = 'application/x-indesign';
                break;
            case 'ai':
                $contentType = 'application/illustrator';
                break;
            default:
                $contentType = 'image/' . $ext;
                break;
        }

        return $contentType;
    }

    /**
     * Save object to S3
     * If $customFilename is not specified and such file content in var $file already exists in S3 bucket.
     * this function returns filename without uploading to S3.
     *
     * @param string $file              Content of a file
     * @param string $ext               Extension to be set in ContentType header
     * @param string $customFilename    If it isn't set, filename will be client GUID plus '/' plus sha512 from file's content
     * @param string $subFolder         Additional subfolder to set after clientId and before filename
     * @param string $preFolder         Additional prefolder to set before clientId
     *
     * @return string|null              Name of the file without path
     */
    public function save($file, $ext, $customFilename = null, $subFolder = '', $preFolder = '')
    {
        if (is_string($customFilename)) {
            $fileName = $customFilename;
        } else {
            $fileName = $this->getHash($file);
            if ($this->doesExist($fileName)) {
                return $fileName;
            }
        }

        $fileName = $this->getKey($fileName, $subFolder, $preFolder);

        $result = $this->getClient()->putObject(array(
            'Bucket' => self::$bucket,
            'Key'    => $fileName,
            'Body'   => $file,
            'ContentType' => $this->getContentType($ext),
        ));
        if (!$result || $result['@metadata']['statusCode'] !== 200) {
            return null;
        }
        $filename = explode('/', $result['ObjectURL']);
        $filename = end($filename);
        return $filename;
    }

    /**
     * Make a copy a file from local storage to S3 bucket
     *
     * @param string        $filepath Full path with filename
     * @param string        $contentType e.g. 'text/plain'
     * @param string|null   $customFilename
     * @param string        $subFolder
     * @param string        $preFolder
     * @param bool          $forceDownloading
     *
     * @return string|null
     */
    public function put($filepath, $contentType, $customFilename = null, $subFolder = '', $preFolder = '', $forceDownloading = false) {
        if (!is_file($filepath)) {
            return null;
        }
        if (is_string($customFilename)) {
            $fileName = $customFilename;
        } else {
            $fileName = basename($filepath);
        }

        $key = $this->getKey($fileName, $subFolder, $preFolder);

        $options = [
            'Bucket' => self::$bucket,
            'Key'    => $key,
            'SourceFile'   => $filepath,
            'ContentType' => $contentType
        ];
        if ($forceDownloading) {
            $options['ContentDisposition'] = "attachment; filename=$fileName";
        }

        $result = $this->getClient()->putObject($options);

        if (!$result || $result['@metadata']['statusCode'] !== 200) {
            return null;
        }
        $filename = explode('/', $result['ObjectURL']);
        $filename = end($filename);
        return $filename;
    }

    /**
     * @param string $keyFrom
     * @param string|null $customFilenameTo
     * @param string $subFolderFrom
     * @param string $preFolderFrom
     * @param string $subFolderTo
     * @param string $preFolderTo
     *
     * @return string
     */
    public function copy($keyFrom, $customFilenameTo = null, $subFolderFrom = '', $preFolderFrom = '', $subFolderTo = '', $preFolderTo = '') {
        $fileNameFrom = $this->getKey($keyFrom, $subFolderFrom, $preFolderFrom);

        if (is_string($customFilenameTo)) {
            $keyTo = $customFilenameTo;
        } else {
            $keyTo = $keyFrom;
        }

        $filenameTo = $this->getKey($keyTo, $subFolderTo, $preFolderTo);

        $result = $this->getClient()->copyObject(
            [
                'Bucket' => self::$bucket,
                'Key'    => $filenameTo,
                'CopySource' => self::$bucket . '/' . $fileNameFrom
            ]
        );
        if (!$result || $result['@metadata']['statusCode'] !== 200) {
            return null;
        }

        $filename = explode('/', $result['ObjectURL']);
        $filename = end($filename);
        return $filename;
    }

    /**
     * Get presigned URL for an object.
     * Expires can be configured via config or function static::setPresignedExpires($presignedExpires)
     * Related AWS Docs: https://docs.aws.amazon.com/aws-sdk-php/v3/guide/service/s3-presigned-url.html
     *
     * @param string $key       Key without clientId subkey
     * @param string $subFolder Additional subfolder to set after clientId and before filename
     * @param string $preFolder Additional part to be set before clientId
     * @param string $customExpires e.g. '+10 minutes'
     * @param string $customFileName e.g. 'MyLittlePony.jpeg'
     *
     * @return string
     */
    public function getPresignedUrl($key, $subFolder = '', $preFolder = '', $customExpires = null, $customFileName = null)
    {
        $args = [
            'Bucket' => self::$bucket,
            'Key' => $this->getKey($key, $subFolder, $preFolder),
        ];
        if ($customFileName) {
            $args['ResponseContentDisposition'] = "attachment; filename={$customFileName}";
        }
        $cmd = $this->getClient()->getCommand('GetObject', $args);
        $expires = $customExpires ? $customExpires : static::getPresignedExpires();
        $request = $this->getClient()->createPresignedRequest($cmd, $expires);
        return (string)$request->getUri();
    }

    /**
     * @param $key
     * @param string $subFolder
     * @param string $preFolder
     * @param bool $throwException
     * @return mixed|null
     * @throws \Exception
     */
    public function getFile($key, $subFolder = '', $preFolder = '', $throwException = false) {
        try {
            // Get the object
            $result = $this->getClient()->getObject(array(
                'Bucket' => self::$bucket,
                'Key'    => $this->getKey($key, $subFolder, $preFolder)
            ));

            return $result['Body'];
        } catch (S3Exception $e) {
            if ($throwException) {
                throw new \Exception('Unable to get file from S3 bucket: ' . $e->getMessage());
            }
            return null;
        }
    }

    public function getStreamName($key, $subFolder = '', $preFolder = '') {
        $bucket = self::$bucket;
        $key = $this->getKey($key, $subFolder, $preFolder);
        return "s3://$bucket/$key";
    }

    /**
     * @param $prefix
     * @return \Iterator
     */
    public function listObjectsIterator($prefix) {
        return $this->getClient()->getIterator('ListObjects', array(
            "Bucket" => self::$bucket,
            "Prefix" => $this->getKey('', $prefix)
        ));
    }

    /**
     * Returns hash string from file's content
     *
     * @param $file
     *
     * @return mixed
     */
    public function getHash($file) {
        return hash("sha512", $file);
    }

    /**
     * Delete object form S3
     *
     * @param $filename
     * @param string $subFolder Additional subfolder to set after clientId and before filename
     * @param string $preFolder Additional prefolder to set before clientId
     *
     * @return bool
     */
    public function delete($filename, $subFolder = '', $preFolder = '') {
        $result = $this->getClient()->deleteObject(array(
            'Bucket' => self::$bucket,
            'Key'    => $this->getKey($filename, $subFolder, $preFolder)
        ));
        if (!$result || $result['@metadata']['statusCode'] !== 204) {
            return false;
        }
        return true;
    }

    /**
     * Deletes all object with prefix $filename.
     * BE CAREFUL USING THIS FUNCTION AND CHECK S3 BUCKET PERMISSIONS FOR THIS OPERATION
     *
     * @param string $filename Prefix
     * @param string $subFolder Additional subfolder to set after clientId and before filename
     * @param string $preFolder Additional prefolder to set before clientId
     *
     * @return bool
     */
    public function deleteAll($filename, $subFolder = '', $preFolder = '') {
        $this->getClient()->deleteMatchingObjects(
            self::$bucket,
            $this->getKey($filename, $subFolder, $preFolder),
            '#.*#'
        );

        return true;
    }

    /**
     * @param string $filename
     * @param string $subFolder Additional subfolder to set after clientId and before filename
     * @param string $preFolder Additional part to be set before clientId
     *
     * @return bool TRUE if object exists, otherwise FALSE
     */
    public function doesExist($filename, $subFolder = '', $preFolder = '') {
        return $this->getClient()->doesObjectExist(self::$bucket, $this->getKey($filename, $subFolder, $preFolder));
    }

    /**
     * Checks does object exists by file content using hash
     *
     * @param $file
     * @param string $subFolder
     * @param string $preFolder
     * @return bool
     */
    public function doesExistByContent($file, $subFolder = '', $preFolder = '') {
        $filename = $this->getHash($file);
        return $this->doesExist($filename, $subFolder, $preFolder);
    }

    /**
     * @param $filename
     * @param string $subFolder
     * @param string $preFolder
     * @return string
     */
    public function getKey($filename, $subFolder = '', $preFolder = '') {
        if (strlen($subFolder) > 0) {
            $subFolder .= '/';
        }
        if (strlen($preFolder)) {
            $preFolder .= '/';
        }
        return $preFolder . self::$clientId . '/' . $subFolder . $filename;
    }

    /**
     * @param $filename
     * @param string $subFolder
     * @param string $preFolder
     * @return null|string
     */
    public function getExtByContentType($filename, $subFolder = '', $preFolder = '') {
        $headers = $this->getClient()->headObject([
            "Bucket" => self::$bucket,
            "Key" => $this->getKey($filename, $subFolder, $preFolder)
        ]);
        $contentType = $headers['Content-Type'];
        switch($contentType) {
            case 'image/svg+xml':
                $ext = 'svg';
                break;
            case 'image/jpg':
            case 'image/jpeg':
                $ext = 'jpg';
                break;
            case 'image/png':
                $ext = 'png';
                break;
            default:
                $ext = null;
                break;
        }

        return $ext;
    }

    /**
     * @param $header
     * @param $filename
     * @param string $subFolder
     * @param string $preFolder
     * @return array|null
     */
    public function getHeader($header, $filename, $subFolder = '', $preFolder = '') {
        $headers = $this->getClient()->headObject([
            "Bucket" => self::$bucket,
            "Key" => $this->getKey($filename, $subFolder, $preFolder)
        ])->toArray();

        return isset($headers[$header]) ? $headers[$header] : null;
    }

    public function registerStreamWrapper() {
        self::$s3client->registerStreamWrapper();
    }

    /**
     * @return string
     */
    public static function getBucketUrl() {
        global $AWSconfig;
        if (isset($AWSconfig['s3']['endpoint'])) {
            $url = $AWSconfig['s3']['endpoint'] . '/' . $AWSconfig['s3']['bucket'];
        } else {
            if ($AWSconfig['s3']['region'] == 'us-east-1') {
                $url = "https://s3.amazonaws.com/{$AWSconfig['s3']['bucket']}";
            } else {
                $url = "https://s3-{$AWSconfig['s3']['region']}.amazonaws.com/{$AWSconfig['s3']['bucket']}";
            }
        }

        return $url;
    }

    /**
     * @param \Iterator $files
     * @return \Generator
     */
    public function getPresignedUrlsInBulkGenerator(\Iterator $files) {
        foreach($files as $file) {
            $cmd = $this->getClient()->getCommand('GetObject', [
                'Bucket' => self::$bucket,
                'Key' => $file['Key']
            ]);
            $request = $this->getClient()->createPresignedRequest($cmd, static::getPresignedExpires());
            yield ['key' => $file['Key'], 'url' => (string)$request->getUri()];
        }
    }

}