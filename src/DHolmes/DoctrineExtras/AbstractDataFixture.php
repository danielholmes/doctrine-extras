<?php

namespace DHolmes\DoctrineExtras;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\Persistence\ObjectManager;

abstract class AbstractDataFixture extends AbstractFixture
{
    /**
     * @param ObjectManager $objectManager
     * @param string $name
     * @return object
     */
    protected function getSafeReference(ObjectManager $objectManager, $name)
    {
        // TODO: Proper way to get id, i.e. through class mapping
        $reference = $this->getReference($name);
        return $objectManager->find(get_class($reference), $reference->getId());
    }
}