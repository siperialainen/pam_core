<?php

namespace PamCore\Geo;

use PamCore\Entity;

class LocationEntity extends Entity
{
    /**
     * @var int
     */
    public $id;

    /**
     * @var double
     */
    public $latitude;

    /**
     * @var double
     */
    public $longitude;

    public function __construct($id = null)
    {
        $this->setModel(new LocationModel());
        parent::__construct();
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return float
     */
    public function getLatitude()
    {
        return $this->latitude;
    }

    /**
     * @param float $latitude
     */
    public function setLatitude($latitude)
    {
        $this->latitude = $latitude;
    }

    /**
     * @return float
     */
    public function getLongitude()
    {
        return $this->longitude;
    }

    /**
     * @param float $longitude
     */
    public function setLongitude($longitude)
    {
        $this->longitude = $longitude;
    }

    public function save()
    {
        if (!($this->getModel() instanceof LocationModel)) {
            throw new \Exception("Model isn't set for " . get_class($this) . " class");
        }

        $this->getModel()->save($this);
    }

}