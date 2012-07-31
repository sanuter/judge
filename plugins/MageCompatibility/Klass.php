<?php
namespace MageCompatibility;

class Klass
{
    const TYPE_UNKNOWN = '-1';

    protected $name;

    protected $type=self::TYPE_UNKNOWN;

    /**
     * create Klass with given name
     * 
     * @param string $name 
     * @return Klass
     */
    public function __construct($name, $type=null)
    {
        $this->setName($name);
        if (false == empty($type)) {
            $this->setType($type);
        }
    }

    /**
     * set class name
     * 
     * @param string $name 
     * @return Klass
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function getType()
    {
        return self::TYPE_UNKNOWN == $this->type ? null : $this->type;
    }

    public function getName()
    {
        return $this->getMagentoClassName($this->name, $this->type);
    }

    public function isExtensionClass($identifier, $filePathPattern, $extensionPath)
    {
        if (0 < preg_match('/^([a-zA-Z0-9]+_)+[a-zA-Z0-9]+$/', $identifier)) {
            /* we got a class name */
            $className = $identifier;
            $token = 'class ' . $className;
            $command = 'grep -rEl "' . $token . '" ' . $extensionPath . '/app';
            exec($command, $filesWithThatToken, $return);
        } else {
            $filePathPattern = 'app/code/*/*/*/' . $filePathPattern;
            list($extensionName, $class) = explode('/', $identifier);
            $classPathItems = explode('_', $class);
            foreach ($classPathItems as $pathItem) {
                $filePathPattern .= '/' . ucfirst($pathItem);
            }
            $filePathPattern .= '.php';
            $files = glob($extensionPath . '/' . $filePathPattern);
            return (0 < count($files));
        }
    }

    protected function getMagentoClassName($identifier, $type)
    {
        if (0 < preg_match('/^([a-zA-Z0-9]+_)+[a-zA-Z0-9]+$/', $identifier)) {
            return $identifier;
        }
        list($extensionName, $class) = explode('/', $identifier);
        $className = 'Mage_' . ucfirst($extensionName) . '_' . ucfirst($type);

        $classPathItems = explode('_', $class);
        foreach ($classPathItems as $pathItem) {
            $className .= '_' . ucfirst($pathItem);
        }
        return $className;
    }
}

