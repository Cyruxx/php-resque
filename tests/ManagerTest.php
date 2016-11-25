<?php


namespace ChrisBoulton\Resque\Tests;


use ChrisBoulton\Resque\Statistic\Manager;

/**
 * Class ManagerTest
 * @package ChrisBoulton\Resque\Tests
 */
class ManagerTest extends TestCase
{
    public function testStatCanBeIncremented()
    {
        Manager::incr('test_incr');
        Manager::incr('test_incr');
        $this->assertEquals(2, $this->redisClient->get('resque:stat:test_incr'));
    }

    public function testStatCanBeIncrementedByX()
    {
        Manager::incr('test_incrX', 10);
        Manager::incr('test_incrX', 11);
        $this->assertEquals(21, $this->redisClient->get('resque:stat:test_incrX'));
    }

    public function testStatCanBeDecremented()
    {
        Manager::incr('test_decr', 22);
        Manager::decr('test_decr');
        $this->assertEquals(21, $this->redisClient->get('resque:stat:test_decr'));
    }

    public function testStatCanBeDecrementedByX()
    {
        Manager::incr('test_decrX', 22);
        Manager::decr('test_decrX', 11);
        $this->assertEquals(11, $this->redisClient->get('resque:stat:test_decrX'));
    }

    public function testGetStatByName()
    {
        Manager::incr('test_get', 100);
        $this->assertEquals(100, Manager::get('test_get'));
    }

    public function testGetUnknownStatReturns0()
    {
        $this->assertEquals(0, Manager::get('test_get_unknown'));
    }
}