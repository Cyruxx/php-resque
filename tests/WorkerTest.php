<?php
namespace ChrisBoulton\Resque\Tests;

use ChrisBoulton\Resque\Job\Job;
use ChrisBoulton\Resque\Job\Worker;
use ChrisBoulton\Resque\Resque;
use ChrisBoulton\Resque\Statistic\Manager;
use ChrisBoulton\Resque\Tests\Job\TestJob;

/**
 * Class WorkerTest
 * @package ChrisBoulton\Resque\Tests
 */
class WorkerTest extends TestCase
{
    public function testWorkerRegistersInList()
    {
        $worker = new Worker('*');
        $worker->registerWorker();
        $this->assertTrue((bool) $this->redisClient->sismember('resque:workers', (string) $worker));
    }

    public function testGetAllWorkers()
    {
        for ($i = 0; $i < 3; $i++) {
            $worker = new Worker('queue_' . $i);
            $worker->registerWorker();
        }
        $this->assertCount(3, Worker::all());
    }

    public function testGetWorkerById()
    {
        $worker = new Worker('*');
        $worker->registerWorker();

        $newWorker = Worker::find($worker->getId());
        $this->assertEquals($worker->getId(), $newWorker->getId());
    }

    public function testInvalidWorkerDoesNotExist()
    {
        $this->assertFalse(Worker::exists('Meh'));
    }

    public function testWorkerCanUnregister()
    {
        $worker = new Worker('*');
        $worker->registerWorker();
        $worker->unregisterWorker();

        $this->assertFalse(Worker::exists($worker->getId()));
        $this->assertCount(0, Worker::all());
        $this->assertCount(0, $this->redisClient->smembers('resque:workers'));
    }

    public function testPausedWorkerDoesNotPickUpJobs()
    {
        $worker = new Worker('*');
        $worker->pauseProcessing();

        Resque::enqueue('jobs', TestJob::class);
        $worker->work(0);
        $worker->work(0);
        $this->assertEquals(0, Manager::get('processed'));
    }

    public function testResumedWorkerPicksUpJobs()
    {
        $worker = new Worker('*');
        $worker->pauseProcessing();
        Resque::enqueue('jobs', TestJob::class);
        $worker->work(0);

        $this->assertEquals(0, Manager::get('processed'));
        $worker->unPauseProcessing();
        $worker->work(0);

        $this->assertEquals(1, Manager::get('processed'));
    }

    public function testWorkerCanWorkOverMultipleQueues()
    {
        $worker = new Worker([
            'queue1',
            'queue2'
        ]);
        $worker->registerWorker();

        Resque::enqueue('queue1', 'Test_Job_1');
        Resque::enqueue('queue2', 'Test_Job_2');

        $job = $worker->reserve();
        $this->assertInstanceOf(Job::class, $job);
        $this->assertEquals('queue1', $job->queue);

        $job = $worker->reserve();
        $this->assertInstanceOf(Job::class, $job);
        $this->assertEquals('queue2', $job->queue);
    }

    public function testWorkerWorksQueuesInSpecifiedOrder()
    {
        $worker = new Worker([
            'high',
            'medium',
            'low'
        ]);
        $worker->registerWorker();

        // Queue the jobs in a different order
        Resque::enqueue('low', 'Test_Job_1');
        Resque::enqueue('high', 'Test_Job_2');
        Resque::enqueue('medium', 'Test_Job_3');

        // Now check we get the jobs back in the right order
        $job = $worker->reserve();
        $this->assertEquals('high', $job->queue);

        $job = $worker->reserve();
        $this->assertEquals('medium', $job->queue);

        $job = $worker->reserve();
        $this->assertEquals('low', $job->queue);
    }

    public function testWorkerDoesNotWorkOnUnknownQueues()
    {
        $worker = new Worker('queue1');
        $worker->registerWorker();
        Resque::enqueue('queue2', 'Test_Job_2');
        $this->assertFalse($worker->reserve());
    }

    public function testWorkerClearsItsStatusWhenNotWorking()
    {
        Resque::enqueue('jobs', TestJob::class);
        $worker = new Worker('jobs');

        $job = $worker->reserve();
        $worker->workingOn($job);
        $worker->doneWorking();
        $this->assertEquals([], $worker->job());
    }

    public function testWorkerRecordsWhatItIsWorkingOn()
    {
        $worker = new Worker('jobs');
        $worker->registerWorker();

        $payload = [
            'class' => TestJob::class
        ];

        $job = new Job('jobs', $payload);
        $worker->workingOn($job);

        $job = $worker->job();
        $this->assertNotEmpty($job['queue']);
        $this->assertEquals('jobs', $job['queue']);

        if (empty($job['run_at'])) {
            $this->fail('Job does not have an run_at time.');
        }
        $this->assertEquals($payload, $job['payload']);
    }

    public function testWorkerErasesItsStatsWhenShutdown()
    {
        Resque::enqueue('jobs', TestJob::class);
        Resque::enqueue('jobs', 'Invalid_Job');

        $worker = new Worker('jobs');
        $worker->work(0);
        $worker->work(0);

        $this->assertEquals(0, $worker->getStat('processed'));
        $this->assertEquals(0, $worker->getStat('failed'));
    }

    public function testWorkerCleansUpDeadWorkersOnStartup()
    {
        // Register a good worker
        $goodWorker = new Worker('jobs');
        $goodWorker->registerWorker();
        $workerId = explode(':', $goodWorker->getId());

        // Register some bad workers
        $worker = new Worker('jobs');
        $worker->setId($workerId[0].':1:jobs');
        $worker->registerWorker();

        $worker = new Worker(array('high', 'low'));
        $worker->setId($workerId[0].':2:high,low');
        $worker->registerWorker();

        $this->assertCount(3, Worker::all());

        $goodWorker->pruneDeadWorkers();

        // There should only be goodworker left
        $this->assertCount(1, Worker::all());
    }

    public function testDeadWorkerCleanUpDoesNotCleanUnknownWorkers()
    {
        // Register a bad worker on this machine
        $worker = new Worker('jobs');
        $workerId = explode(':', $worker);
        $worker->setId($workerId[0].':1:jobs');
        $worker->registerWorker();

        // Register some other false workers
        $worker = new Worker('jobs');
        $worker->setId('my.other.host:1:jobs');
        $worker->registerWorker();

        $this->assertCount(2, Worker::all());

        $worker->pruneDeadWorkers();

        // my.other.host should be left
        $workers = Worker::all();
        $this->assertEquals(1, count($workers));
        $this->assertInstanceOf(Worker::class, $workers[0]);
        $this->assertEquals($worker->getId(), $workers[0]->getId());
    }

    public function testWorkerFailsUncompletedJobsOnExit()
    {
        $worker = new Worker('jobs');
        $worker->registerWorker();

        $payload = array(
            'class' => TestJob::class
        );
        $job = new Job('jobs', $payload);

        $worker->workingOn($job);
        $worker->unregisterWorker();

        $this->assertEquals(1, Manager::get('failed'));
    }
}