<?php

namespace ChrisBoulton\Resque\Tests;

use ChrisBoulton\Resque\Job\Job;
use ChrisBoulton\Resque\Job\JobStatus;
use ChrisBoulton\Resque\Job\Worker;
use ChrisBoulton\Resque\Resque;
use ChrisBoulton\Resque\Tests\Job\TestJob;

class JobStatusTest extends TestCase
{
    /**
     * @var Worker
     */
    protected $worker;

    public function setUp()
    {
        parent::setUp();

        // Register a worker to test with
        $this->worker = new Worker('jobs');
    }

    public function testJobStatusCanBeTracked()
    {
        $token = Resque::enqueue('jobs', TestJob::class, null, true);
        $status = new JobStatus($token);
        $this->assertTrue($status->isTracking());
    }

    public function testJobStatusIsReturnedViaJobInstance()
    {
        Resque::enqueue('jobs', TestJob::class, null, true);
        $job = Job::reserve('jobs');
        $this->assertEquals(JobStatus::STATUS_WAITING, $job->getStatus());
    }

    public function testQueuedJobReturnsQueuedStatus()
    {
        $token = Resque::enqueue('jobs', TestJob::class, null, true);
        $status = new JobStatus($token);
        $this->assertEquals(JobStatus::STATUS_WAITING, $status->get());
    }
    public function testRunningJobReturnsRunningStatus()
    {
        $token = Resque::enqueue('jobs', 'Failing_Job', null, true);
        $job = $this->worker->reserve();
        $this->worker->workingOn($job);
        $status = new JobStatus($token);
        $this->assertEquals(JobStatus::STATUS_RUNNING, $status->get());
    }

    public function testFailedJobReturnsFailedStatus()
    {
        $token = Resque::enqueue('jobs', 'Failing_Job', null, true);
        $this->worker->work(0);
        $status = new JobStatus($token);
        $this->assertEquals(JobStatus::STATUS_FAILED, $status->get());
    }

    public function testCompletedJobReturnsCompletedStatus()
    {
        $token = Resque::enqueue('jobs', TestJob::class, null, true);
        $this->worker->work(0);
        $status = new JobStatus($token);
        $this->assertEquals(JobStatus::STATUS_COMPLETE, $status->get());
    }

    public function testStatusIsNotTrackedWhenToldNotTo()
    {
        $token = Resque::enqueue('jobs', TestJob::class, null, false);
        $status = new JobStatus($token);
        $this->assertFalse($status->isTracking());
    }

    public function testStatusTrackingCanBeStopped()
    {
        JobStatus::create('test');
        $status = new JobStatus('test');
        $this->assertEquals(JobStatus::STATUS_WAITING, $status->get());
        $status->stop();
        $this->assertFalse($status->get());
    }

    public function testRecreatedJobWithTrackingStillTracksStatus()
    {
        $originalToken = Resque::enqueue('jobs', TestJob::class, null, true);
        $job = $this->worker->reserve();

        // Mark this job as being worked on to ensure that the new status is still
        // waiting.
        $this->worker->workingOn($job);

        // Now recreate it
        $newToken = $job->recreate();

        // Make sure we've got a new job returned
        $this->assertNotEquals($originalToken, $newToken);

        // Now check the status of the new job
        $newJob = Job::reserve('jobs');
        $this->assertEquals(JobStatus::STATUS_WAITING, $newJob->getStatus());
    }
}