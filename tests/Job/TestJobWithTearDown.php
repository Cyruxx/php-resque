<?php

namespace ChrisBoulton\Resque\Tests\Job;

class TestJobWithTearDown
{
    public static $called = false;
    public $args = false;

    public function perform()
    {

    }

    public function tearDown()
    {
        self::$called = true;
    }
}