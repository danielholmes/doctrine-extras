<?php

namespace DHolmes\DoctrineExtras\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use DHolmes\LangExtra\Enumeration;

abstract class EnumerationType extends Type
{    
    /**
     * @param array $fieldDeclaration
     * @param AbstractPlatform $platform
     * @return string
     */
    public function getSqlDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return 'VARCHAR(50)';
    }
    
    /** @return string */
    abstract protected function getClass();

    /**
     * @param string $value
     * @param AbstractPlatform $platform
     * @return Enumeration
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        $phpValue = null;
        if ($value !== null)
        {
            $class = $this->getClass();
            $phpValue = $class::get($value);
        }
        
        return $phpValue;
    }

    /**
     * @param Enumeration $value
     * @param AbstractPlatform $platform
     * @return string
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        $dbValue = null;
        $class = $this->getClass();
        if ($value instanceof $class)
        {
            $dbValue = $value->getKey();
        }
        else if ($value !== null)
        {
            throw new \Exception(sprintf('Cannot persist value of type "%s', get_class($value)));
        }
        
        return $dbValue;
    }
}