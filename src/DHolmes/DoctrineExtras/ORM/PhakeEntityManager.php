<?php

namespace DHolmes\DoctrineExtras\ORM;

use Doctrine\ORM\EntityManager;

class PhakeEntityManager
{
    /** @return EntityManager */
    public static function createWorkingMock()
    {
        $em = \Phake::mock('DHolmes\DoctrineExtras\ORM\MockEntityManager');
        \Phake::when($em)->transactional(\Phake::anyParameters())->thenCallParent();
        return $em;
    }
    
    /**
     * @param \Phake_IMock $em
     * @param array $calls 
     */
    public static function verifyWithinTransaction(\Phake_IMock $em, array $calls)
    {
        // TODO: $calls should really be allowed to be in any order
        
        $allVerifications = array_merge(
            array(\Phake::verify($em)->startTransactional()),
            $calls,
            array(\Phake::verify($em)->stopTransactional())
        );
        call_user_func_array(array('Phake', 'inOrder'), $allVerifications);
    }
}

abstract class MockEntityManager extends EntityManager
{
    abstract public function startTransactional();
    abstract public function stopTransactional();
    
    public function transactional(\Closure $op)
    {
        $this->startTransactional();
        $return = $op($this);
        $this->stopTransactional();
        return $return;
    }
}