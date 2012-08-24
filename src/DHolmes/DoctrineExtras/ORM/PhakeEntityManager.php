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
        foreach ($calls as $call)
        {
            \Phake::inOrder(\Phake::verify($em)->beginTransaction(), $call);
            \Phake::inOrder(
                $call,
                \Phake::verify($em)->flush(),
                \Phake::verify($em)->commit()
            );
        }
    }
}

abstract class MockEntityManager extends EntityManager
{    
    public function transactional(\Closure $op)
    {
        $this->beginTransaction();
        $return = $op($this);
        $this->flush();
        $this->commit();
        return $return;
    }
}
