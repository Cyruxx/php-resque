<?php

namespace ChrisBoulton\Resque\Tests\Job;

class TestJob
{
    public static $called = false;

    public function perform()
    {
        self::$called = true;
    }
}