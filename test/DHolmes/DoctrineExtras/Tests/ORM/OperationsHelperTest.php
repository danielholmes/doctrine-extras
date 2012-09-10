<?php

namespace DHolmes\DoctrineExtras\Tests\ORM;

use DHolmes\DoctrineExtras\ORM\OperationsHelper;

class OperationsHelperTest extends \PHPUnit_Framework_TestCase
{
    /** @var OperationsHelper */
    private $helper;

    protected function setUp()
    {
        $this->helper = new OperationsHelper();
        $this->helper->setIsSchemaCacheEnabled(true);
    }

    public function testConstruct()
    {

    }
}
