<?php
namespace PamCore;

class Entity
{
    /**
     * @var Model
     */
    protected $_model;

    /**
     * Entity constructor set attributes from $data array or JSON, if there are defined setters by name
     * e.g. if attribute's name is 'code', there should be defined function setCode($code) in a child class
     *
     * Function name is 'set' plus 'CapitalizedAttributeName'
     *
     * @param array|string $data Array or JSON string
     */
    public function __construct($data = null)
    {
        if (!empty($data) && is_string($data)) {
            $data = json_decode($data, true);
        }
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $setterName = 'set' . $this->getAttrFuncNameSuffix($k);
                if (method_exists($this, $setterName)) {
                    $this->{$setterName}($v);
                }
            }
        }
    }

    private function getAttrFuncNameSuffix($attributeName)
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $attributeName)));
    }

    /**
     * @param Model $model
     */
    protected function setModel(Model $model)
    {
        $this->_model = $model;
    }

    /**
     * @return Model
     */
    public function getModel() {
        return $this->_model;
    }

    /**
     * Get all entity attributes values in one array
     * It gets data using getters
     * Attributes with '_' (underscore) in the beginning considered as private
     *
     * @return array
     */
    public function getArray()
    {
        $data = [];
        $thisClass = get_class($this);
        $reflection = new \ReflectionClass($this);
        $attributes = [];
        foreach ($reflection->getProperties(\ReflectionProperty::IS_PRIVATE) as $prop) {
            $attributes [] = $prop->getName();
        };
        $functions = get_class_methods($thisClass);

        foreach ($attributes as $name) {
            if (strpos($name, '_') !== 0 && in_array('get' . $this->getAttrFuncNameSuffix($name), $functions)) {
                $data[$name] = $this->{'get' . $this->getAttrFuncNameSuffix($name)}();
            }
        }

        return $data;
    }

    public function create()
    {
        if (!($this->getModel() instanceof Model)) {
            throw new \Exception("Model isn't set for " . get_class($this) . " class");
        }

        $id = $this->getModel()->insert($this->getArray());
        $this->_setId($id);
        return $id;
    }

    public function delete()
    {
        if (!($this->getModel() instanceof Model)) {
            throw new \Exception("Model isn't set for " . get_class($this) . " class");
        }

        $id = $this->_getId();
        if ($id !== null) {
            $this->getModel()->delete($id);
            return true;
        }

        return false;
    }

    private function _getId()
    {
        if (!($this->getModel() instanceof Model)) {
            throw new \Exception("Model isn't set for " . get_class($this) . " class");
        }

        $id = null;
        $idGetterFuncName = 'get' . $this->getAttrFuncNameSuffix($this->getModel()->getIdColumn());
        if (is_callable([$this, $idGetterFuncName])) {
            $id = $this->{$idGetterFuncName}();
        }

        return $id;
    }

    private function _setId($id)
    {
        if (!($this->getModel() instanceof Model)) {
            throw new \Exception("Model isn't set for " . get_class($this) . " class");
        }

        $idGetterFuncName = 'set' . $this->getAttrFuncNameSuffix($this->getModel()->getIdColumn());
        if (is_callable([$this, $idGetterFuncName])) {
            $this->{$idGetterFuncName}($id);
            return true;
        }

        return false;
    }
}