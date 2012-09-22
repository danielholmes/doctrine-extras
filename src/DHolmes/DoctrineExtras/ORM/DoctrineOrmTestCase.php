<?php

namespace DHolmes\DoctrineExtras\ORM;

use PHPUnit_Framework_TestCase;
use InvalidArgumentException;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManager;
use DHolmes\DoctrineExtras\ORM\OperationsHelper;

abstract class DoctrineOrmTestCase extends PHPUnit_Framework_TestCase implements FixtureInterface
{
    /** @inheritDoc */
    public function load(ObjectManager $manager)
    {
        $entities = $this->getTestEntities();
        $allEntities = $this->extractAllEntitiesToPersist($entities);
        array_walk($allEntities, array($manager, 'persist'));
        
        $manager->flush();
    }
    
    /**
     * This is added to help test readability. The idea is that this method would expand all entity
     * relations that would otherwise need to be persisted explicitly. e.g.
     * 
     * protected function getTestEntities()
     * {
     *     return array(new Person(new Country('Australia')));
     * }
     *
     * protected function extractAllEntitiesToPersist(array $entities)
     * {
     *        $all = array();
     *     foreach ($entities as $person)
     *     {
     *         $all[] = $person;
     *         $all[] = $person->getCountry();
     *     }
     *     return $all;
     * }
     *
     * extractAllEntitiesToPersist would usually go in a data source shared abstract test case
     * 
     * @param array $entities
     * @return array 
     */
    protected function extractAllEntitiesToPersist(array $entities)
    {
        return $entities;
    }
    
    /** @return array */
    abstract protected function getTestEntities();
    
    /** @var OperationsHelper */
    private $operationsHelper;
    
    /** @return OperationsHelper */
    private function getOperationsHelper()
    {
        if ($this->operationsHelper === null)
        {
            $this->operationsHelper = $this->createOperationsHelper();
        }        
        return $this->operationsHelper;
    }
    
    /** @return OperationsHelper */
    protected function createOperationsHelper()
    {
        $helper = new OperationsHelper();
        $helper->setIsSchemaCacheEnabled(true);
        // Cannot really compare entities properly with this enabled because actual entity not 
        // inserted in session. Might be possible if compare contents (excluding id and timestamps?)
        // but that could be innacurate
        $helper->setIsFixturesCacheEnabled(false);
        return $helper;
    }
    
    /** @return EntityManager */
    abstract protected function getEntityManager();
    
    protected function setUpDatabase()
    {
        $entityManager = $this->getEntityManager();
        $this->getOperationsHelper()->setUpDatabase($entityManager, array($this));
    }
    
    /**
     * @param object $entity
     * @return object 
     */
    protected function ensureEntityManaged($entity)
    {
        $em = $this->getEntityManager();
        $merged = $em->merge($entity);
        if ($em->getUnitOfWork()->isScheduledForInsert($merged))
        {
            $desc = $this->getEntityDescription($entity);
            throw new InvalidArgumentException(sprintf('Entity %s not within manager', $desc));
        }
        return $merged;
    }
    
    /**
     * @param object $expectedEntity
     * @param object $entity 
     */
    protected function assertSameEntities($expectedEntity, $entity)
    {
        $entityManager = $this->getEntityManager();
        
        if ($expectedEntity !== null)
        {
            $expectedEntity = $entityManager->merge($expectedEntity);
        }
        if ($entity !== null)
        {
            $entity = $entityManager->merge($entity);
        }
        
        $expectedDesc = $this->getEntityDescription($expectedEntity);
        $entityDesc = $this->getEntityDescription($entity);
        
        $assertMessage = sprintf('Entities not the same:' . "\n" . '  %s' . "\n" . '  %s', 
                            $expectedDesc, $entityDesc);
        $this->assertSame($expectedEntity, $entity, $assertMessage);
    }
    
    /**
     * @param object $entity
     * @return string
     */
    private function getEntityDescription($entity)
    {
        $entityString = 'NULL';
        if (method_exists($entity, '__toString'))
        {
            $entityString = sprintf('%s <%s>', (string)$entity, get_class($entity));
        }
        else if ($entity !== null)
        {
            $entityString = sprintf('%s <%s>', spl_object_hash($entity), get_class($entity));
        }
        return $entityString;
    }
    
    /**
     * @param array $expected
     * @param \Traversable|array $actual
     */
    protected function assertEntityCollectionEquals(array $expected, $actual)
    {
        $entityManager = $this->getEntityManager();
        $expected = array_map(function($entity) use ($entityManager)
        {
            return $entityManager->merge($entity);
        }, $expected);

        $actualArray = array();
        foreach ($actual as $entity)
        {
            $actualArray[] = $entityManager->merge($entity);
        }

        $this->assertEquals($expected, $actualArray);

        // Cannot simply compare because comparison goes in to some infinite loop
        /*$idMap = function($entity)
        {
            return $entity->getId();
        };
        $expectedIds = array_map($idMap, $expected);
        $actualIds = array_map($idMap, $actual);
        sort($expectedIds);
        sort($actualIds);

        $this->assertEquals($expectedIds, $actualIds);*/
    }
    
    protected function tearDownDatabase()
    {
        $entityManager = $this->getEntityManager();
        $this->getOperationsHelper()->tearDownDatabase($entityManager);
    }
}
