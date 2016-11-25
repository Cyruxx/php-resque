<?php

namespace ChrisBoulton\Resque\Tests\Job;

class TestJobWithSetUp
{
    public static $called = false;
    public $args = false;

    public function setUp()
    {
        self::$called = true;
    }

    public function perform()
    {

    }
}