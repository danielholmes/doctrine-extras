<?php

namespace DHolmes\DoctrineExtras\ORM;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\ORM\EntityRepository as DoctrineEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;

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
    
    /** @return EntityManager */
    protected function getEntityManager()
    {
        return $this->entityManager;
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
        $this->flushEntityManagerIfNoTransaction($entity);
    }
    
    /** @param object $entity */
    protected function removeEntityAndFlushIfNoTransaction($entity)
    {
        $this->entityManager->remove($entity);
        $this->flushEntityManagerIfNoTransaction($entity);
    }
    
    /** @param object $entity */
    protected function flushEntityManagerIfNoTransaction($entity)
    {
        $conn = $this->entityManager->getConnection();
        if (!$conn->isTransactionActive())
        {
            $this->entityManager->flush($entity);
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
            if ($value === null)
            {
                $qb->andWhere(sprintf('x.%s IS NULL', $property));
            }
            else
            {
                $qb->andWhere(sprintf('x.%s = :%s', $property, $property))
                   ->setParameter($property, $value);
            }
        }
        return $qb->getQuery()->getSingleScalarResult() > 0;
    }
    
    /**
     * @param object $entity 
     * @return boolean
     */
    protected function has($entity)
    {
        return $this->entityManager->contains($entity);
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
            $sortProperty = $sort;
            $sortAlias = 'x';
            while (($dotPos = strpos($sortProperty, '.')) !== false)
            {
                $parentAlias = $sortAlias;
                $sortAlias = substr($sortProperty, 0, $dotPos);
                $sortProperty = substr($sortProperty, $dotPos + 1);
                $qb->leftJoin(sprintf('%s.%s', $parentAlias, $sortAlias), $sortAlias);
            }
            $qb->orderBy(sprintf('%s.%s', $sortAlias, $sortProperty), $order);
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
     * @param QueryBuilder $qb
     * @return int
     */
    protected function countQueryBuilder(QueryBuilder $qb)
    {
        $paginator = new Paginator($qb->getQuery());
        return $paginator->count();
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $qb
     * @param int $offset
     * @param int|null $limit
     * @return array
     */
    protected function sliceQueryBuilder(QueryBuilder $qb, $offset = 0, $limit = null)
    {
        if ($offset > 0 || $limit !== null)
        {
            $qb->setFirstResult($offset);
            if ($limit === null)
            {
                // From MySQL docs, but should work okay on other platforms as well
                $qb->setMaxResults(9999999999);
            }
            else
            {
                $qb->setMaxResults($limit);
            }
        }

        return $qb->getQuery()->execute();
    }

    /**
     * @param QueryBuilder $qb
     * @param int $offset
     * @param int $limit
     * @return \Iterator
     */
    protected function sliceQueryBuilderAsIterator(QueryBuilder $qb, $offset = 0, $limit = null)
    {
        $results = $this->sliceQueryBuilder($qb, $offset, $limit);
        return new \ArrayIterator($results);
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

