<?php

namespace PamCore\Assets;

use PamCore\Aws\S3;
use PamCore\Model;

class Icon extends Model
{
    protected $tableName = 'icons';

    protected $idColumn = 'icon_id';
    
    const S3_FOLDER = 'icons';

    private static $svgIconCache = [];

    const LANDMARK_SIZE = 30;

    /**
     * @param $name
     * @param array $options
     * @param string $lengthUnit
     * @return string
     */
    public static function getSvgIconChar($name, $options = [], $lengthUnit = 'px')
    {
        if (!array_key_exists($name, static::$svgIconCache)) {
            if (S3::instance()->doesExist($name . '.svg', static::S3_FOLDER)) {
                static::$svgIconCache[$name] = S3::instance()->getFile($name . '.svg', static::S3_FOLDER);
            } else {
                static::$svgIconCache[$name] = null;
            }
        }
        $data = static::$svgIconCache[$name];
        if (is_null($data)) {
            return null;
        }

        $dom = new \DOMDocument('1.0', 'utf-8');
        $dom->loadXML($data);
        foreach ($dom->documentElement->childNodes as $child) {
            if (!in_array($child->nodeName, ['title', 'style'])) {
                continue;
            }
            $dom->documentElement->removeChild($child);
        }
        foreach ($options as $key => $value) {
            switch ($key) {
                case 'color':
                    $paths = $dom->documentElement->getElementsByTagName('path');
                    foreach($paths as $path) {
                        if ($path->getAttribute('fill') != 'none') {
                            $path->setAttribute('fill', $options['color']);
                        }
                    }
                    break;
                /**
                 * Set custom color of an arrow symbol
                 * Color in SVG file should be set as 'fill' attribute not css style
                 * 'svg-arrow-symbol' class should be set for polygon or path
                 **/
                case 'arrow-symbol-color':
                    $polygons = $dom->documentElement->getElementsByTagName('polygon');
                    foreach ($polygons as $el) {
                        if (strpos($el->getAttribute('class'),'svg-arrow-symbol') !== false && $el->getAttribute('fill') != 'none') {
                            $el->setAttribute('fill', $value);
                        }
                    }
                    $paths = $dom->documentElement->getElementsByTagName('path');
                    foreach($paths as $el) {
                        if (strpos($el->getAttribute('class'),'svg-arrow-symbol') !== false && $el->getAttribute('fill') != 'none') {
                            $el->setAttribute('fill', $value);
                        }
                    }
                    break;
                case 'height':
                    $viewBox = explode(' ', $dom->documentElement->getAttribute('viewBox'));
                    $ratio = $lengthUnit === '%' ? 1 : $viewBox[2] / $viewBox[3];
                    $dom->documentElement->setAttribute('height', $options['height'] . $lengthUnit);
                    if (!isset($options['width']) || round($options['height'] * $ratio) <= $options['width']) {
                        $dom->documentElement->setAttribute('width', round($options['height'] * $ratio) . $lengthUnit);
                    } else {
                        $dom->documentElement->setAttribute('width', $options['width'] . $lengthUnit);
                        $dom->documentElement->setAttribute('height', round($options['width'] / $ratio) . $lengthUnit);
                    }
                    break;
                case 'width':
                    if (!isset($options['height'])) {
                        $viewBox = explode(' ', $dom->documentElement->getAttribute('viewBox'));
                        $ratio = $lengthUnit === '%' ? 1 : $viewBox[2] / $viewBox[3];
                        $dom->documentElement->setAttribute('width', $options['width'] . $lengthUnit);
                        $dom->documentElement->setAttribute('height', round($options['width'] / $ratio) . $lengthUnit);
                    }
                    break;
                default:
                    $dom->documentElement->setAttribute($key, $value);
                    break;
            }
        }
        return $dom->saveXML($dom->documentElement);
    }

    /**
     * @param $options
     * @return string
     */
    public static function getSvgIcon($options)
    {
        $init_opacity = isset($options['init_opacity']) ? $options['init_opacity'] : '';
        $mi_unicode = isset($options['unicode']) ? $options['unicode'] : '';
        $mi_icon_name_id = isset($options['icon_name_id']) ? $options['icon_name_id'] : '';
        $mi_bgclr = isset($options['bgclr']) ? $options['bgclr'] : '';
        $mi_clr = isset($options['clr']) ? $options['clr'] : '';
        $mi_size = isset($options['size']) ? $options['size'] : '';
        $mi_icon_name = isset($options['icon_name']) ? $options['icon_name'] : '';
        $arrow_hex = isset($options['arrow_hex']) ? $options['arrow_hex'] : '';
        $stroke_width = isset($options['stroke_width']) ? $options['stroke_width'] : '';
        $icon_colour = isset($options['icon_colour']) ? $options['icon_colour'] : '';
        $status_hex = isset($options['status_hex']) ? $options['status_hex'] : '';
        $icon_colour = isset($options['icon_colour']) ? $options['icon_colour'] : '';
        $status_hex = isset($options['status_hex']) ? $options['status_hex'] : '';
        $status_val = isset($options['status_val']) ? $options['status_val'] : '';
        $init_opacity = isset($options['init_opacity']) ? $options['init_opacity'] : '';
        $icon_rotation  = isset($options['icon_rotation']) ? $options['icon_rotation'] : '';
        $icon_arrow = isset($options['icon_arrow']) ? $options['icon_arrow'] : '';

        ob_start();
        include($_SERVER["DOCUMENT_ROOT"] . '/img/svg-code/icon_icon_master.php');
        $data = ob_get_clean();
        return $data;
    }

    /**
     * @param $options
     * @return string
     */
    public static function getPngIcon($options)
    {
        $data = static::getSvgIcon($options);
        return static::_convertToPng($data);
    }

    private static function _convertToPng($data)
    {
        $im = new \Imagick();
        $im->setBackgroundColor(new \ImagickPixel('transparent'));
        $im->setResolution(250, 250);
        $im->readImageBlob('<?xml version="1.0" encoding="UTF-8" standalone="no" ?>' . "\n" . $data);
        $im->setImageFormat("png");
        $im->setImageDepth(8);
        $im->setImageResolution(2500, 2500);
        return $im->getImageBlob();
    }

    public static function getByNameId($nameId) {
        global $db;
        $nameId = mysqli_real_escape_string($db, $nameId);
        $q = "SELECT * FROM icons WHERE icon_name_id = '{$nameId}'";
        $result = mysqli_query($db, $q) or die(mysqli_error($db));
        return mysqli_fetch_assoc($result);
    }

    public static function mirrorIconId($iconId) {
        $arrows = get_icons('arrows');
        if (!isset($arrows['icon_' . $iconId])) {
            return $iconId;
        }
        $iconNameId = static::mirrorIconNameId($arrows['icon_' . $iconId]['icon_name_id']);
        foreach($arrows as $kIconId => $arrow) {
            if ($arrow['icon_name_id'] == $iconNameId) {
                return $arrow['icon_id'];
            }
        }

        return $iconId;
    }

    /**
     * @param string $nameId
     * @return mixed|null
     */
    public static function mirrorIconNameId($nameId) {
        $mirrorArray = [
            'arrow-up' => 'arrow-up',
            'arrow-upper-left' => 'arrow-upper-right',
            'arrow-left' => 'arrow-right',
            'arrow-lower-left' => 'arrow-lower-right',
            'arrow-upper-right' => 'arrow-upper-left',
            'arrow-right' => 'arrow-left',
            'arrow-lower-right' => 'arrow-lower-left',
            'arrow-lower' => 'arrow-lower'
        ];

        return isset($mirrorArray[$nameId]) ? $mirrorArray[$nameId] : null;
    }

    /**
     * @param int $si
     * @param bool $mirrorSides
     * @return int
     */
    public static function getDefaultArrowId($si, $mirrorSides) {
        if ($mirrorSides == 'N') $mirrorSides = false;
        if (!$mirrorSides || $si != 2) {
            $iconId = 32;
        } else {
            $iconTmp = static::getByNameId('arrow-right');
            $iconId = is_array($iconTmp) ? $iconTmp['icon_id'] : 32;
        }

        return $iconId;
    }

    public static function getIconColor($iconNameId) {
        switch ($iconNameId) {
            case 'youarehere':
                $iconColor = '#EA222E';
                break;
            case 'accessable-main':
            case 'accessible-main':
                $iconColor = '#1A4C8C';
                break;
            default:
                $iconColor = 'currentColor';
                break;
        }

        return $iconColor;
    }

}