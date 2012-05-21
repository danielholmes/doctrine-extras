<?php

namespace DHolmes\DoctrineExtras\DBAL\Types;

use Doctrine\DBAL\Types;
use Doctrine\DBAL\Platforms\AbstractPlatform;

class JsonType extends Type
{
    const JSON = 'json';

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return $platform->getClobTypeDeclarationSQL($fieldDeclaration);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        return json_encode($value);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        if ($value === null) {
            return null;
        }

        $value = (is_resource($value)) ? stream_get_contents($value) : $value;
        $val = json_decode($value);
        if ($val === null && $value != 'null') {
            throw ConversionException::conversionFailed($value, $this->getName());
        }
        return $val;
    }

    public function getName()
    {
        return self::JSON;
    }
}