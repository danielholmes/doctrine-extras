<?php

namespace DHolmes\DoctrineExtras\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use DHolmes\LangExtra\Enumeration;

abstract class EnumerationCollectionType extends Type
{
    const SEPARATOR = "\n";

    /**
     * @param array $fieldDeclaration
     * @param AbstractPlatform $platform
     * @return string
     */
    public function getSqlDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return 'TEXT';
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
            $phpValue = array();
            if (!empty($value))
            {
                foreach (explode(self::SEPARATOR, $value) as $key)
                {
                    $class = $this->getClass();
                    $phpValue[] = $class::get($key);
                }
            }
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
        if (is_array($value))
        {
            $dbValueComps = array();
            $class = $this->getClass();
            foreach ($value as $enum)
            {
                if ($enum instanceof $class)
                {
                    $dbValueComps[] = $enum->getKey();
                }
                else
                {
                    throw new \Exception(sprintf('Cannot persist value of type "%s', get_class($enum)));
                }
            }
            $dbValue = join(self::SEPARATOR, $dbValueComps);
        }
        else
        {
            throw new \Exception(sprintf('Cannot persist value of type "%s', get_class($value)));
        }
        
        return $dbValue;
    }
}