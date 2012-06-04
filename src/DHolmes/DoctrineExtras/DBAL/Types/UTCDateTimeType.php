<?php

namespace DHolmes\DoctrineExtras\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;

class UTCDateTimeType extends AbstractUTCDateType
{
    /**
     * @param AbstractPlatform $platform
     * @return string
     */
    protected function getPlatformFormatString(AbstractPlatform $platform)
    {
        return $platform->getDateTimeFormatString();
    }
}