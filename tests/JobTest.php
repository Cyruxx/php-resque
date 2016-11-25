<?php
namespace ChrisBoulton\Resque\Tests;

use ChrisBoulton\Resque\Connection\Redis;
use ChrisBoulton\Resque\Exception\ResqueException;
use ChrisBoulton\Resque\Exception\ResqueRedisException;
use ChrisBoulton\Resque\Job\Job;
use ChrisBoulton\Resque\Job\Worker;
use ChrisBoulton\Resque\Resque;
use ChrisBoulton\Resque\Statistic\Manager;
use ChrisBoulton\Resque\Tests\Job\FailingJob;
use ChrisBoulton\Resque\Tests\Job\TestJob;
use ChrisBoulton\Resque\Tests\Job\TestJobWithoutPerformMethod;
use ChrisBoulton\Resque\Tests\Job\TestJobWithSetUp;
use ChrisBoulton\Resque\Tests\Job\TestJobWithTearDown;

/**
 * Class JobTest
 * @package ChrisBoulton\Resque\Tests
 */
class JobTest extends TestCase
{
    /**
     * @var Worker
     */
    protected $worker;

    protected function setUp()
    {
        parent::setUp();
        $this->worker = new Worker('jobs');
        $this->worker->registerWorker();
    }

    public function testJobCanBeEnqueued()
    {
        $this->assertTrue((bool) Resque::enqueue('jobs', TestJob::class));
    }

    public function testQeueuedJobCanBeReserved()
    {
        Resque::enqueue('jobs', TestJob::class);

        $job = Job::reserve('jobs');
        if($job == false) {
            $this->fail('Job could not be reserved.');
        }
        $this->assertEquals('jobs', $job->queue);
        $this->assertEquals(TestJob::class, $job->payload['class']);
    }

    public function testObjectArgumentsCannotBePassedToJob()
    {
        $this->expectException(\InvalidArgumentException::class);
        $args = new \stdClass();
        $args->test = 'someValue';
        Resque::enqueue('jobs', TestJob::class, $args);
    }

    public function testQueuedJobReturnsExactSamePassedInArguments()
    {
        $args = [
            'int' => 123,
            'numArray' => [
                1,
                2,
            ],
            'assocArray' => [
                'key1' => 'value1',
                'key2' => 'value2'
            ],
        ];
        Resque::enqueue('jobs', TestJob::class, $args);
        $job = Job::reserve('jobs');

        $this->assertEquals($args, $job->getArguments());
    }

    public function testAfterJobIsReservedItIsRemoved()
    {
        Resque::enqueue('jobs', TestJob::class);
        Job::reserve('jobs');
        $this->expectException(ResqueRedisException::class);
        $this->assertFalse(Job::reserve('jobs'));
    }

    public function testRecreatedJobMatchesExistingJob()
    {
        $args = [
            'int' => 123,
            'numArray' => [
                1,
                2,
            ],
            'assocArray' => [
                'key1' => 'value1',
                'key2' => 'value2'
            ],
        ];

        Resque::enqueue('jobs', TestJob::class, $args);
        $job = Job::reserve('jobs');

        // Now recreate it
        $job->recreate();

        $newJob = Job::reserve('jobs');
        $this->assertEquals($job->payload['class'], $newJob->payload['class']);
        $this->assertEquals($job->payload['args'], $newJob->getArguments());
    }


    public function testFailedJobExceptionsAreCaught()
    {
        $payload = [
            'class' => FailingJob::class,
            'args' => null
        ];
        $job = new Job('jobs', $payload);
        $job->worker = $this->worker;

        $this->worker->perform($job);

        $this->assertEquals(1, Manager::get('failed'));
        $this->assertEquals(1, Manager::get('failed:'.$this->worker));
    }

    public function testJobWithoutPerformMethodThrowsException()
    {
        $this->expectException(ResqueException::class);
        Resque::enqueue('jobs', TestJobWithoutPerformMethod::class);
        $job = $this->worker->reserve();
        $job->worker = $this->worker;
        $job->perform();
    }

    public function testInvalidJobThrowsException()
    {
        $this->expectException(ResqueException::class);
        Resque::enqueue('jobs', 'Invalid_Job');
        $job = $this->worker->reserve();
        $job->worker = $this->worker;
        $job->perform();
    }

    public function testJobWithSetUpCallbackFiresSetUp()
    {
        $payload = [
            'class' => TestJobWithSetUp::class,
            'args' => [
                'somevar',
                'somevar2',
            ],
        ];
        $job = new Job('jobs', $payload);
        $job->perform();

        $this->assertTrue(TestJobWithSetUp::$called);
    }

    public function testJobWithTearDownCallbackFiresTearDown()
    {
        $payload = [
            'class' => TestJobWithTearDown::class,
            'args' => [
                'somevar',
                'somevar2',
            ],
        ];
        $job = new Job('jobs', $payload);
        $job->perform();

        $this->assertTrue(TestJobWithTearDown::$called);
    }

    public function testJobWithNamespace()
    {
        Redis::prefix('php');
        $queue = 'jobs';
        $payload = ['another_value'];
        Resque::enqueue($queue, TestJobWithTearDown::class, $payload);

        $this->assertEquals(Resque::queues(), ['jobs']);
        $this->assertEquals(Resque::size($queue), 1);

        Redis::prefix('resque');
        $this->assertEquals(Resque::size($queue), 0);
    }
}