<?php
// Somewhere in our application, we need to register:
ResqueEvent::listen('afterEnqueue', array('My_Resque_Plugin', 'afterEnqueue'));
ResqueEvent::listen('beforeFirstFork', array('My_Resque_Plugin', 'beforeFirstFork'));
ResqueEvent::listen('beforeFork', array('My_Resque_Plugin', 'beforeFork'));
ResqueEvent::listen('afterFork', array('My_Resque_Plugin', 'afterFork'));
ResqueEvent::listen('beforePerform', array('My_Resque_Plugin', 'beforePerform'));
ResqueEvent::listen('afterPerform', array('My_Resque_Plugin', 'afterPerform'));
ResqueEvent::listen('onFailure', array('My_Resque_Plugin', 'onFailure'));

class My_Resque_Plugin
{
	public static function afterEnqueue($class, $arguments)
	{
		echo "Job was queued for " . $class . ". Arguments:";
		print_r($arguments);
	}
	
	public static function beforeFirstFork($worker)
	{
		echo "Worker started. Listening on queues: " . implode(', ', $worker->queues(false)) . "\n";
	}
	
	public static function beforeFork($job)
	{
		echo "Just about to fork to run " . $job;
	}
	
	public static function afterFork($job)
	{
		echo "Forked to run " . $job . ". This is the child process.\n";
	}
	
	public static function beforePerform($job)
	{
		echo "Cancelling " . $job . "\n";
	//	throw new Resque_Job_DontPerform;
	}
	
	public static function afterPerform($job)
	{
		echo "Just performed " . $job . "\n";
	}
	
	public static function onFailure($exception, $job)
	{
		echo $job . " threw an exception:\n" . $exception;
	}
}