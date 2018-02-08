<?php
namespace PamCore;

use PamCore\Db\Exception;
use PamCore\Utils\Date;
use PamCore\Utils\Units;

class Client
{
    /**
     * @var Client
     */
    private static $instance;

    private $client;

    private $dateFormat;

    /**
     * @var string
     */
    protected $baseurl;

    private function __construct($client)
    {
        $this->client = $client;
        $dateFormats = Date::getDateFormats();
        $clientDateFormatId = $this->client['dateFormatId'];
        if (isset($dateFormats[$clientDateFormatId])) {
            $this->dateFormat = $dateFormats[$clientDateFormatId]['dateFormat'];
        } else {
            $this->dateFormat = Date::getDefaultDateFormat();
        }
    }

    public static function isClientInitiated()
    {
        return (bool) static::$instance;
    }

    public static function get()
    {
        return static::init();
    }

    public static function init($clientId = null)
    {
        if (!static::$instance) {
            $client = static::fetchClient($clientId);
            static::$instance = new static($client);
        }
        return static::$instance;
    }

    public static function changeClient($clientId)
    {
        $client = static::fetchClient($clientId);
        static::$instance = new static($client);
        return static::$instance;
    }

    private static function fetchClient($clientId = null)
    {
        global $db;
        if (!$clientId) {
            $clientId = isset($_SESSION['USER']) ? $_SESSION['USER']['clientId']: null;
        }
        if ($clientId) {
            $clientId = mysqli_real_escape_string($db, $clientId);
            $result = mysqli_query($db, "SELECT * FROM client where id = '{$clientId}'");
            $client = mysqli_fetch_assoc($result);
            if (null === $client) {
                throw new \Exception("Client with ID '{$clientId}' does not exist");
            }
            return $client;
        } else {
            throw new \Exception('Unable to retrieve client by logged in user - session is not started yet');
        }
    }

    public function getId()
    {
        return $this->client['id'];
    }
    
    public function getName()
    {
        return $this->client['name'];
    }

    public function getPassword()
    {
        return $this->client['password'];
    }

    public function getDateFormat($format, $type = Date::FORMAT_TYPE_PHP)
    {
        return Date::getDateFormat($format, $type, $this->dateFormat);
    }

    public function getDateFormatId()
    {
        return $this->client['dateFormatId'];
    }

    public function setDateFormatId($dateFormatId)
    {
        global $db;
        if (!array_key_exists($dateFormatId, Date::getDateFormats())) {
            throw new \Exception('Unknown date format.');
        }
        $this->dateFormat = Date::getDateFormats()[$dateFormatId];
        $safeDataFormatId = mysqli_real_escape_string($db, $dateFormatId);
        $q = "UPDATE `client` SET `dateFormatId` = '{$safeDataFormatId}' WHERE `id` = '{$this->client['id']}'";
        if (!mysqli_query($db, $q)) {
            throw new \Exception(mysqli_error($db));
        };
    }

    /**
     * @return \PamCore\Utils\Units
     */
    public function getUnits()
    {
        return new Units($this->client['units']);
    }

    public function setUnitsSystem($unitsSystemId, $convert = true)
    {
        global $db;
        if (!in_array($unitsSystemId, $this->getUnits()->getAllSystems())) {
            throw new \Exception('Unknown units system.');
        }

        if ($unitsSystemId === $this->client['units']) {
            return;
        }

        $clientUnits = new Units($this->client['units']);

        if (!mysqli_begin_transaction($db)) {
            throw new Exception(mysqli_error($db));
        }

        $safeUnitsSystemId = mysqli_real_escape_string($db, $unitsSystemId);
        $q = "UPDATE `client` SET `units` = '{$safeUnitsSystemId}' WHERE `id` = '{$this->client['id']}'";
        if (!mysqli_query($db, $q)) {
            throw new \Exception(mysqli_error($db));
        }

        if ($convert) {
            foreach (['width', 'height', 'mount_height', 'depth'] as $field) {
                $q = "UPDATE `asset_type_templates` SET `{$field}` = `{$field}` * {$clientUnits->getAltMultiplier(Units::UNITS_LENGTH)}";
                if (!mysqli_query($db, $q)) {
                    throw new \Exception(mysqli_error($db));
                }
            }
            foreach (['weight'] as $field) {
                $q = "UPDATE `asset_type_templates` SET `{$field}` = `{$field}` * {$clientUnits->getAltMultiplier(Units::UNITS_WEIGHT)}";
                if (!mysqli_query($db, $q)) {
                    throw new \Exception(mysqli_error($db));
                }
            }
        }

        if (false === mysqli_commit($db)) {
            throw new Exception(mysqli_error($db));
        }

        $this->client['units'] = $unitsSystemId;
    }

    public function getTimeZone()
    {
        return new \DateTimeZone($this->client['timeZone']);
    }

    public function setTimeZone(\DateTimeZone $timezone)
    {
        global $db;
        $this->client['timezone'] = $timezone->getName();
        $safeTimeZone = mysqli_real_escape_string($db, $this->client['timezone']);
        $q = "UPDATE client SET timezone = '{$safeTimeZone}' WHERE id = '{$this->client['id']}'";
        if (!mysqli_query($db, $q)) {
            throw new \Exception(mysqli_error($db));
        }
    }

    public function date($format, $timestamp = null)
    {
        $date = new \DateTime(null, $this->getTimeZone());
        if ($timestamp) {
            $date->setTimestamp($timestamp);
        }
        return $date->format($format);
    }

    public function shortDate($timestamp = null)
    {
        return $this->date($this->getDateFormat(Date::FORMAT_SHORT_DATE), $timestamp);
    }

    public function shortDateTime($timestamp = null)
    {
        return $this->date($this->getDateFormat(Date::FORMAT_SHORT_DATE_TIME), $timestamp);
    }

    public function displayDateTime($timestamp = null)
    {
        return $this->date($this->getDateFormat(Date::FORMAT_DISPLAY_DATE_TIME), $timestamp);
    }

    public function displayDate($timestamp = null)
    {
        return $this->date($this->getDateFormat(Date::FORMAT_DISPLAY_DATE), $timestamp);
    }

    public function longDate($timestamp = null)
    {
        return $this->date($this->getDateFormat(Date::FORMAT_LONG_DATE), $timestamp);
    }

    /**
     * @return string
     */
    public function getBaseurl()
    {
        return $this->baseurl;
    }

    /**
     * @param string $baseurl
     */
    public function setBaseurl($baseurl)
    {
        $this->baseurl = $baseurl;
    }
}