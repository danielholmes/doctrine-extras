<?php

namespace DHolmes\DoctrineExtras\DBAL\Types;

use DateTime;
use DateTimeZone;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Platforms\AbstractPlatform;

abstract class AbstractUTCDateType extends DateTimeType
{
    /** @var DateTimeZone */
    private static $utc = null;
    
    /** @return DateTimeZone */
    private static function getUtc()
    {
        if (self::$utc === null)
        {
            self::$utc = new DateTimeZone('UTC');
        }
        return self::$utc;
    }
    
    private static $default = null;
    
    /** @return DateTimeZone */
    private static function getDefaultTimeZone()
    {
        if (self::$default === null)
        {
            self::$default = new DateTimeZone(date_default_timezone_get());
        }
        return self::$default;
    }
    
    /**
     * @param type $value
     * @param AbstractPlatform $platform
     * @return type 
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if ($value === null)
        {
            return null;
        }
        
        $format = $this->getPlatformFormatString($platform);
        
        $date = new DateTime(null, self::getUtc());
        $date->setTimestamp($value->getTimestamp());
        return $date->format($format);
    }
    
    /**
     * @param AbstractPlatform $platform
     * @return string
     */
    abstract protected function getPlatformFormatString(AbstractPlatform $platform);

    /**
     * @param type $value
     * @param AbstractPlatform $platform
     * @return type 
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        $format = $this->getPlatformFormatString($platform);

        if ($value === null) {
            return null;
        }

        $val = DateTime::createFromFormat($format, $value, self::getUtc());
        if (!$val)
        {
            throw ConversionException::conversionFailed($value, $this->getName());
        }
        $val->setTimeZone(self::getDefaultTimeZone());
        return $val;
    }
}