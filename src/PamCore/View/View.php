<?php

namespace PamCore\View;

use PamCore\Client;
use PamCore\Config\General;
use PamCore\Partial;

class View
{
    /**
     * Return filename with full path with checking of subfolder by clientId
     *
     * @param $controller
     * @param $view
     * @return null|string
     */
    public static function getFileName($controller, $view)
    {
        $path = $_SERVER["DOCUMENT_ROOT"] . '/inc/view/' . $controller . '/';
        try {
            $clientId = Client::get()->getId();
            if (is_dir($path . $clientId)) {
                $filename = $path . $clientId . '/' . $view . '.php';
            } else {
                $filename = $path . '/' . $view . '.php';
            }
        } catch (\Exception $e) {
            //cant retrieve client info
            $filename = $path . '/' . $view . '.php';
        }

        return is_file($filename) ? $filename : null;
    }

    /**
     * Returns rendered partial
     *
     * @param $controller
     * @param $view
     * @param array $data
     *
     * @return string
     */
    public static function renderPartial($controller, $view, $data = [])
    {
        return (new Partial(static::getFileName($controller, $view), $data))->process();
    }

    /**
     * Add version parameter to URL to force invalidate browser cache of aseets (i.e. JS and CSS files) in case of new PAM version
     * @param string $url
     * @return string
     */
    public static function assetUrl($url)
    {
        return $url . (false === strstr($url, '?') ? '?' : '&') . 'v=' . General::get()->getVersion();
    }
}
