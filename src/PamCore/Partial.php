<?php
namespace PamCore;

class Partial
{
    /**
     * Required js collection
     * @var \PamCore\HeadScript
     */
    public static $headScript;

    /**
     * Template variables
     * @var array
     */
    protected $__params = [];

    /**
     * Template file path
     * @var string|array
     */
    protected $__path;

    /**
     * Partial constructor.
     * @param string|array $path
     * @param array $params
     */
    public function __construct($path, $params = [])
    {
        $this->__path = $path;
        $this->__params = $params;
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->__params;
    }

    /**
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->__params[$name];
    }

    /**
     * @param $path
     * @param $params
     * @param bool $appendParams
     * @return string
     */
    public function partial($path, $params = [], $appendParams = true)
    {
        $partial = new Partial($path, $params + ($appendParams ? $this->__params : []));
        return $partial->process();
    }

    /**
     * @return string
     */
    public function process()
    {
        ob_start();
        extract($this->__params);
        $paths = is_array($this->__path) ? $this->__path : [$this->__path];
        $foundPath = null;
        foreach ($paths as $path) {
            if (!is_file($path)) {
                continue;
            }
            $foundPath = $path;
            include($path);
            break;
        }
        if (!$foundPath) {
            throw new \Exception("Partial file not found: " . implode(',', $paths));
        }
        return ob_get_clean();
    }

    /**
     * @return HeadScript
     */
    public function getHeadScript()
    {
        if (!static::$headScript) {
            static::$headScript = new HeadScript();
        }

        return static::$headScript;
    }
}