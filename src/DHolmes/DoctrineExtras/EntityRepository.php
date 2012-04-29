<?php

namespace DHolmes\DoctrineExtras;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\ORM\EntityRepository as DoctrineEntityRepository;

// TODO: IteratorAggregate support. Probably better via another object, i.e. RepositoryIterator
// TODO: arrayaccess
// TODO: Maybe implement as a helper instead new EntityRepositoryHelper('MyClass', $entityManager)
// TODO: List api style methods: splice, slice, 
class EntityRepository
{
    /** @var string */
    private $entityClass;
    /** @var EntityManager */
    private $entityManager;
    /** @var DoctrineEntityRepository */
    private $repository;
    
    /**
     * @param string $entityClass
     * @param EntityManager $entityManager 
     */
    public function __construct($entityClass, EntityManager $entityManager)
    {
        $this->entityClass = $entityClass;
        $this->entityManager = $entityManager;
    }
    
    /** @return DoctrineEntityRepository */
    private function getRepository()
    {
        if ($this->repository === null)
        {
            $this->repository = $this->entityManager->getRepository($this->entityClass);
        }
        return $this->repository;
    }
    
    /** @param object $entity */
    protected function persistEntityAndFlushIfNoTransaction($entity)
    {
        $this->entityManager->persist($entity);
        $this->flushEntityManagerIfNoTransaction();
    }
    
    /** @param object $entity */
    protected function removeEntityAndFlushIfNoTransaction($entity)
    {
        $this->entityManager->remove($entity);
        $this->flushEntityManagerIfNoTransaction();
    }
    
    protected function flushEntityManagerIfNoTransaction()
    {
        $conn = $this->entityManager->getConnection();
        if (!$conn->isTransactionActive())
        {
            $this->entityManager->flush();
        }
    }
    
    /**
     * @param array $criteria
     * @return object
     */
    protected function findOneBy(array $criteria)
    {
        return $this->getRepository()->findOneBy($criteria);
    }
    
    /**
     * @param array $criteria 
     * @return boolean
     */
    protected function hasBy(array $criteria)
    {
        $expr = $this->getExpressionBuilder();
        $qb = $this->createQueryBuilder('x')
                   ->select($expr->count('x'))
                   ->setMaxResults(1);
        foreach ($criteria as $property => $value)
        {
            $qb->andWhere(sprintf('x.%s = :%s', $property, $property))
               ->setParameter($property, $value);
        }
        return $qb->getQuery()->getSingleScalarResult() > 0;
    }
    
    /** 
     * @param string $sort
     * @param string $order
     * @return array 
     */
    protected function findAll($sort = null, $order = 'ASC')
    {
        $qb = $this->createQueryBuilder('x');
        if ($sort !== null)
        {
            $qb->orderBy(sprintf('x.%s', $sort), $order);
        }
        return $qb->getQuery()->execute();
    }
    
    /**
     * @param mixed $id
     * @return object
     */
    protected function find($id)
    {
        return $this->getRepository()->find($id);
    }
    
    /**
     * @param string $alias
     * @return QueryBuilder
     */
    protected function createQueryBuilder($alias)
    {
        return $this->getRepository()->createQueryBuilder($alias);
    }
    
    /** @return ExpressionBuilder */
    protected function getExpressionBuilder()
    {
        return $this->entityManager->getExpressionBuilder();
    }
    
    /** @return int */
    protected function getEntityCount()
    {
        $expr = $this->getExpressionBuilder();
        $query = $this->createQueryBuilder('e')
                      ->select($expr->count('e'))
                      ->getQuery();
        
        return $query->execute(array(), Query::HYDRATE_SINGLE_SCALAR);
    }
}