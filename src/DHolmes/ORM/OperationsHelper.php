<?php

namespace DHolmes\DoctrineExtras\ORM;

use Exception;
use ReflectionClass;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDOSqlite\Driver as PDOSqliteDriver;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\EntityManager;

class OperationsHelper
{
    /** @var boolean */
    private $isSchemaCacheEnabled;
    /** @var boolean */
    private $isFixturesCacheEnabled;
    /** @var array */
    private $fixtureIdCache;
    
    public function __construct()
    {
        $this->isSchemaCacheEnabled = false;
        $this->isFixturesCacheEnabled = false;
        $this->fixtureIdCache = array();
    }
    
    /** @return boolean */
    public function getIsSchemaCacheEnabled()
    {
        return $this->isSchemaCacheEnabled;
    }
    /** @param boolean $isSchemaCacheEnabled */
    public function setIsSchemaCacheEnabled($isSchemaCacheEnabled)
    {
        $this->isSchemaCacheEnabled = $isSchemaCacheEnabled;
    }

    /** @return boolean */
    public function getIsFixturesCacheEnabled()
    {
        return $this->isFixturesCacheEnabled;
    }
    /** @param boolean $isFixturesCacheEnabled */
    public function setIsFixturesCacheEnabled($isFixturesCacheEnabled)
    {
        $this->isFixturesCacheEnabled = $isFixturesCacheEnabled;
    }
    
    /**
     * @param EntityManager $entityManager
     * @param array $fixtures
     */
    public function setUpDatabase(EntityManager $entityManager, array $fixtures = array())
    {
        $connection = $entityManager->getConnection();
        
        $loader = new Loader();
        foreach ($fixtures as $fixture)
        {
            $loader->addFixture($fixture);
        }
        $orderedFixtures = $loader->getFixtures();
        
        if ($this->isFixturesCacheEnabled && $this->isFileBasedSqlite($connection))
        {
            $this->createDatabaseWithFixturesAndBackup($entityManager, $orderedFixtures);
        }
        else
        {
            $this->createDatabaseWithBackup($entityManager);
            $this->loadFixtures($entityManager, $orderedFixtures);
        }
    }
    
    /**
     * @param Connection $connection
     * @return boolean
     */
    private function isFileBasedSqlite(Connection $connection)
    {
        $dbParams = $connection->getParams();
        return $connection->getDriver() instanceof PDOSqlite\Driver && 
            (!isset($dbParams['memory']) || !$dbParams['memory']);
    }
    
    /**
     * @param EntityManager $entityManager
     * @param array $fixtures 
     */
    private function createDatabaseWithFixturesAndBackup(EntityManager $entityManager, 
        array $fixtures = null)
    {
        $connection = $entityManager->getConnection();
        $dbParams = $connection->getParams();
        $dbFilepath = $dbParams['path'];

        $backupFilepath = $this->getBackupFilepath($entityManager, $fixtures);
        if (file_exists($backupFilepath))
        {
            if (!@copy($backupFilepath, $dbFilepath))
            {
                throw new Exception('Error copying database backup');
            }
        }
        else
        {
            $this->createDatabaseWithBackup($entityManager);
            $this->loadFixtures($entityManager, $fixtures);
            
            if (!@copy($dbFilepath, $backupFilepath))
            {
                throw new Exception('Cannot save schema and fixtures backup file');
            }
        }
    }
    
    /**
     * @param EntityManager $entityManager
     * @param array $fixtures
     * @return string
     */
    private function getBackupFilepath(EntityManager $entityManager, array $fixtures = null)
    {
        $connection = $entityManager->getConnection();
        $dbParams = $connection->getParams();
        $dbFilepath = $dbParams['path'];
        
        $metadataFactory = $entityManager->getMetadataFactory();
        $metadatas = $metadataFactory->getAllMetadata();
        
        $dbId = $this->getDatabaseId($metadatas, $fixtures);
    
        return dirname($dbFilepath) . '/' . basename($dbFilepath) . '-' . $dbId . '.db';
    }
    
    /**
     * @param array $metadatas
     * @param array $fixtures
     * @return string
     */
    private function getDatabaseId(array $metadatas, array $fixtures = null)
    {
        $idBase = serialize($metadatas);
        if ($fixtures !== null && count($fixtures) > 0)
        {
            $idBase .= ':';
            foreach ($fixtures as $fixture)
            {
                $fixtureClass = get_class($fixture);
                if (!isset($this->fixtureIdCache[$fixtureClass]))
                {
                    $reflection = new ReflectionClass($fixtureClass);
                    $fixtureId = $fixtureClass . '::' . $reflection->getEndLine();
                    $this->fixtureIdCache[$fixtureClass] = $fixtureId;
                }
                $idBase .= $this->fixtureIdCache[$fixtureClass] . ' ';
            }
        }        
        
        return md5($idBase);
    }
    
    /** @param EntityManager $entityManager */
    private function createDatabaseWithBackup(EntityManager $entityManager)
    {
        $connection = $entityManager->getConnection();
        $dbParams = $connection->getParams();
        $dbFilepath = $dbParams['path'];
        
        $schemaBackupFilepath = $this->getBackupFilepath($entityManager, null);
        if ($this->isSchemaCacheEnabled && file_exists($schemaBackupFilepath))
        {
            if (!@copy($schemaBackupFilepath, $dbFilepath))
            {
                throw new Exception('Error copying database schema backup');
            }
        }
        else
        {
            $this->createDatabase($entityManager);
            if (!@copy($dbFilepath, $schemaBackupFilepath))
            {
                throw new Exception('Cannot save schema backup file');
            }
        }
    }
    
    /** @param EntityManager $entityManager */
    private function createDatabase(EntityManager $entityManager)
    {
        $connection = $entityManager->getConnection();
        $schemaManager = $connection->getSchemaManager();        
        $name = $this->getDatabaseName($entityManager);
        
        $schemaManager->dropDatabase($name);
        $schemaManager->createDatabase($name);
        
        // Schema
        $metadataFactory = $entityManager->getMetadataFactory();
        $metadatas = $metadataFactory->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->createSchema($metadatas);
    }
    
    /**
     * @param EntityManager $entityManager
     * @param array $fixtures
     */
    private function loadFixtures(EntityManager $entityManager, array $fixtures)
    {
        if (count($fixtures) > 0)
        {
            // TODO: Order
            $purger = new ORMPurger($entityManager);
            $executor = new ORMExecutor($entityManager, $purger);
            $executor->execute($fixtures, true);
        }
    }
    
    /** @param EntityManager $entityManager */
    public function tearDownDatabase(EntityManager $entityManager)
    {
        // Only does rows -> truncate
        //$purger = new ORMPurger();
        //$executor = new ORMExecutor($entityManager, $purger);
        //$executor->purge();
        
        $connection = $entityManager->getConnection();
        $schemaManager = $connection->getSchemaManager();
        $entityManager->close();
        $connection->close();
        
        $name = $this->getDatabaseName($entityManager);
        $schemaManager->dropDatabase($name);
    }
    
    /**
     * @param EntityManager $entityManager
     * @return string
     */
    private function getDatabaseName(EntityManager $entityManager)
    {
        $connection = $entityManager->getConnection();
        $params = $connection->getParams();
        $name = 'default';
        if (isset($params['path']))
        {
            $name = $params['path'];
        }
        else if (isset($params['dbname']))
        {
            $name = $params['dbname'];
        }
        return $name;
    }
    
    /** @return OperationsHelper */
    public static function createWithCachedSchemaAndFixtures()
    {
        $helper = new static();
        $helper->setIsSchemaCacheEnabled(true);
        $helper->setIsFixturesCacheEnabled(true);
        return $helper;
    }
}