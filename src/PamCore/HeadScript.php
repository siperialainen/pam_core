<?php
namespace PamCore;

class HeadScript
{
    const TYPE_FILE = 'file';
    const TYPE_SCRIPT = 'script';

    private $data = [];

    public function add($index, $class, $data, $type, $attrs)
    {
        $md5 = md5(serialize([$class, $data, $type, $attrs]));
        if (count(array_filter($this->data, function($item) use ($md5) {
            return $md5 == $item[0];
        }))) {
            return false;
        }

        array_splice($this->data, $index, 0, [[$md5, $class, $data, $type, $attrs]]);
        return true;
    }

    /**
     * Add script to the end
     *
     * @param $class
     * @param $data
     * @param $type
     * @param $attrs
     * @return bool
     */
    public function append($class, $data, $type, $attrs)
    {
        return $this->add(count($this->data), $class, $data, $type, $attrs);
    }

    /**
     * Add script to the top
     *
     * @param $class
     * @param $data
     * @param $type
     * @param $attrs
     * @return bool
     */
    public function prepend($class, $data, $type, $attrs)
    {
        return  $this->add(0, $class, $data, $type, $attrs);
    }

    public function appendFile($src, $type = 'text/javascript', $attrs = [])
    {
        return $this->append(self::TYPE_FILE, $src, $type, $attrs);
    }

    public function appendScript($script, $type = 'text/javascript', $attrs = [])
    {
        return $this->append(self::TYPE_SCRIPT, $script, $type, $attrs);
    }

    public function __toString()
    {
        $ret = [];
        foreach ($this->data as $item) {
            list(, $class, $data, $type) = $item;
            switch ($class) {
                case self::TYPE_FILE:
                    $ret[] = "<script src='$data' type='$type'></script>";
                    break;
                case self::TYPE_SCRIPT:
                    $ret[] = "<script type='$type'>$data</script>";
                    break;
            }
        }
        return implode("\n", $ret);
    }
}