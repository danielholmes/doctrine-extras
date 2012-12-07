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
        return $platform->getClobTypeDeclarationSQL($fieldDeclaration);
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
     * @param mixed $value
     * @param AbstractPlatform $platform
     * @return string
     * @throws \InvalidArgumentException
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
                    $type = is_object($enum) ? get_class($enum) : gettype($enum);
                    throw new \InvalidArgumentException(sprintf('Cannot persist value of type "%s"', $type));
                }
            }
            $dbValue = join(self::SEPARATOR, $dbValueComps);
        }
        else
        {
            throw new \InvalidArgumentException(sprintf('Expecting array (found type %s)', $value));
        }

        return $dbValue;
    }
}