<?php

namespace ChrisBoulton\Resque\Tests;


use ChrisBoulton\Resque\Event\ResqueEvent;
use ChrisBoulton\Resque\Exception\ResqueDontPerformException;
use ChrisBoulton\Resque\Job\Job;
use ChrisBoulton\Resque\Job\Worker;
use ChrisBoulton\Resque\Resque;
use ChrisBoulton\Resque\Tests\Job\TestJob;

class EventTest extends TestCase
{
    protected $callbacksHit = [];
    
    /**
     * @var Worker
     */
    protected $worker;

    public function setUp()
    {
        TestJob::$called = false;

        // Register a worker to test with
        $this->worker = new Worker('jobs');
        $this->worker->registerWorker();
    }

    public function tearDown()
    {
        ResqueEvent::clearListeners();
        $this->callbacksHit = [];
    }

    public function getEventTestJob()
    {
        $payload = array(
            'class' => TestJob::class,
            'args' => array(
                'somevar',
            ),
        );
        $job = new Job('jobs', $payload);
        $job->worker = $this->worker;
        return $job;
    }

    public function eventCallbackProvider()
    {
        return [
            ['beforePerform', 'beforePerformEventCallback'],
            ['afterPerform', 'afterPerformEventCallback'],
            ['afterFork', 'afterForkEventCallback'],
        ];
    }

    /**
     * @dataProvider eventCallbackProvider
     */
    public function testEventCallbacksFire($event, $callback)
    {
        ResqueEvent::listen($event, array($this, $callback));

        $job = $this->getEventTestJob();
        $this->worker->perform($job);
        $this->worker->work(0);

        $this->assertContains($callback, $this->callbacksHit, $event . ' callback (' . $callback .') was not called');
    }

    public function testBeforeForkEventCallbackFires()
    {
        $event = 'beforeFork';
        $callback = 'beforeForkEventCallback';

        ResqueEvent::listen($event, [$this, $callback]);
        Resque::enqueue('jobs', TestJob::class, array(
            'somevar'
        ));
        $job = $this->getEventTestJob();
        $this->worker->work(0);
        $this->assertContains($callback, $this->callbacksHit, $event . ' callback (' . $callback .') was not called');
    }

    public function testBeforePerformEventCanStopWork()
    {
        $callback = 'beforePerformEventDontPerformCallback';
        ResqueEvent::listen('beforePerform', array($this, $callback));

        $job = $this->getEventTestJob();

        $this->assertFalse($job->perform());
        $this->assertContains($callback, $this->callbacksHit, $callback . ' callback was not called');
        $this->assertFalse(TestJob::$called, 'Job was still performed though Job_DontPerform was thrown');
    }

    public function testAfterEnqueueEventCallbackFires()
    {
        $callback = 'afterEnqueueEventCallback';
        $event = 'afterEnqueue';

        ResqueEvent::listen($event, array($this, $callback));
        Resque::enqueue('jobs', TestJob::class, array(
            'somevar'
        ));
        $this->assertContains($callback, $this->callbacksHit, $event . ' callback (' . $callback .') was not called');
    }

    public function testStopListeningRemovesListener()
    {
        $callback = 'beforePerformEventCallback';
        $event = 'beforePerform';

        ResqueEvent::listen($event, array($this, $callback));
        ResqueEvent::stopListening($event, array($this, $callback));

        $job = $this->getEventTestJob();
        $this->worker->perform($job);
        $this->worker->work(0);

        $this->assertNotContains($callback, $this->callbacksHit,
            $event . ' callback (' . $callback .') was called though ResqueEvent::stopListening was called'
        );
    }


    public function beforePerformEventDontPerformCallback($instance)
    {
        $this->callbacksHit[] = __FUNCTION__;
        throw new ResqueDontPerformException();
    }

    public function assertValidEventCallback($function, $job)
    {
        $this->callbacksHit[] = $function;
        if (!$job instanceof Job) {
            $this->fail('Callback job argument is not an instance of Job');
        }
        $args = $job->getArguments();
        $this->assertEquals($args[0], 'somevar');
    }

    public function afterEnqueueEventCallback($class, $args)
    {
        $this->callbacksHit[] = __FUNCTION__;
        $this->assertEquals(TestJob::class, $class);
        $this->assertEquals(array(
            'somevar',
        ), $args);
    }

    public function beforePerformEventCallback($job)
    {
        $this->assertValidEventCallback(__FUNCTION__, $job);
    }

    public function afterPerformEventCallback($job)
    {
        $this->assertValidEventCallback(__FUNCTION__, $job);
    }

    public function beforeForkEventCallback($job)
    {
        $this->assertValidEventCallback(__FUNCTION__, $job);
    }

    public function afterForkEventCallback($job)
    {
        $this->assertValidEventCallback(__FUNCTION__, $job);
    }

}