<?php

namespace ChrisBoulton\Resque\Tests;


use Predis\Client;

/**
 * Class TestCase
 * @package ChrisBoulton\Resque\Tests
 */
class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Client
     */
    protected $redisClient;

    protected function setUp()
    {
        parent::setUp();
        // Create a redis connection
        $this->redisClient = new Client('tcp://127.0.0.1');

        // Flush redis
        $this->redisClient->flushall();
    }

}