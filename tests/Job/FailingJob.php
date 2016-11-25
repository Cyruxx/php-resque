<?php

namespace ChrisBoulton\Resque\Tests\Job;

class FailingJob
{
    public function perform()
    {
        throw new \Exception('Ouch!');
    }
}