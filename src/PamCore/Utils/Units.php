<?php
namespace PamCore\Utils;

class Units
{
    const SYSTEM_METRIC = 'metric';
    const SYSTEM_IMPERIAL = 'imperial';
    const UNITS_LENGTH = 'length';
    const UNITS_WEIGHT = 'weight';

    private $system;

    private $meta = [
        self::SYSTEM_IMPERIAL => [
            'name' => 'Imperial',
            self::UNITS_LENGTH => [
                'multiply' => 1,
                'name' => 'inches',
                'shortname' => 'in',
                'precision' => 2,
            ],
            self::UNITS_WEIGHT => [
                'multiply' => 1,
                'name' => 'pounds',
                'shortname' => 'lb',
                'precision' => 2,
            ],
        ],
        self::SYSTEM_METRIC => [
            'name' => 'Metric',
            self::UNITS_LENGTH => [
                'multiply' => 25.4,
                'name' => 'millimeters',
                'shortname' => 'mm',
                'precision' => 0,
            ],
            self::UNITS_WEIGHT => [
                'multiply' => 0.45359237,
                'name' => 'kilograms',
                'shortname' => 'kg',
                'precision' => 3,
            ],
        ],
    ];

    public function __construct($system)
    {
        $this->system = $system;
    }

    public function getSystem()
    {
        return $this->system;
    }

    public function getAllSystems()
    {
        return array_keys($this->meta);
    }

    public function getAltUnits($system = null)
    {
        return new Units(self::SYSTEM_METRIC  == ($system ? $system : $this->system)
            ? self::SYSTEM_IMPERIAL : self::SYSTEM_METRIC);
    }

    public function getSystemName($system = null)
    {
        return $this->meta[$system ? $system : $this->system]['name'];
    }

    public function getDisplayValue($unit, $value, $system = null)
    {
        return round($value, $this->meta[$system ? $system : $this->system][$unit]['precision']);
    }

    public function getUnitsShortName($unit, $system = null)
    {
        return $this->meta[$system ? $system : $this->system][$unit]['shortname'];
    }

    public function getUnitsPrecision($unit, $system = null)
    {
        return $this->meta[$system ? $system : $this->system][$unit]['precision'];
    }

    public function convertToMetric($unit, $value)
    {
        if ($this->system === self::SYSTEM_METRIC) {
            return (double)$value;
        } else {
            return $this->convertToAlt($unit, $value, null);
        }
    }

    public function convertToAlt($unit, $value, $system)
    {
        return (double)$value * $this->getAltMultiplier($unit, $system ? $system : $this->system);
    }

    public function getAltMultiplier($unit, $system = null)
    {
        $from = $system ? $system : $this->system;
        $to = self::SYSTEM_METRIC  == $from ? self::SYSTEM_IMPERIAL : self::SYSTEM_METRIC;
        return (double)$this->meta[$to][$unit]['multiply'] / (double)$this->meta[$from][$unit]['multiply'];
    }
}