<?php
namespace ChrisBoulton\Resque;
use ChrisBoulton\Resque\Connection\Redis;
use ChrisBoulton\Resque\Event\ResqueEvent;
use ChrisBoulton\Resque\Exception\ResqueRedisException;
use ChrisBoulton\Resque\Job\Job;

/**
 * Base Resque class.
 *
 * @package		Resque
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class Resque
{
	const VERSION = '1.2';

	/**
	 * @var Redis Instance of Redis that talks to redis.
	 */
	public static $redis = null;

	/**
	 * @var mixed Host/port combination separated by a colon, or a nested
	 * array of server switch host/port pairs
	 */
	protected static $redisServer = null;

	/**
	 * @var int ID of Redis database to select.
	 */
	protected static $redisDatabase = 0;

	/**
	 * @var int PID of current process. Used to detect changes when forking
	 *  and implement "thread" safety to avoid race conditions.
	 */
	 protected static $pid = null;

	/**
	 * Given a host/port combination separated by a colon, set it as
	 * the redis server that Resque will talk to.
	 *
	 * @param mixed $server Host/port combination separated by a colon, or
	 *                      a nested array of servers with host/port pairs.
	 * @param int $database
	 */
	public static function setBackend($server, $database = 0)
	{
		self::$redisServer   = $server;
		self::$redisDatabase = $database;
		self::$redis         = null;
	}

	/**
	 * Return an instance of the Redis class instantiated for Resque.
	 *
	 * @return Redis Instance of Redis.
	 */
	public static function redis()
	{
		// Detect when the PID of the current process has changed (from a fork, etc)
		// and force a reconnect to redis.
		$pid = getmypid();
		if (self::$pid !== $pid) {
			self::$redis = null;
			self::$pid   = $pid;
		}

		if(!is_null(self::$redis)) {
			return self::$redis;
		}

		$server = self::$redisServer;
		if (empty($server)) {
			$server = 'localhost:6379';
		}

        $connectionString = $server;

		if(!is_array($server) && strpos($server, 'unix:') === false) {
                $connectionString = 'tcp://' . $server;
        }

        self::$redis = new Redis($connectionString, [
            'parameters' => [
                'database' => self::$redisDatabase
            ]
        ]);

		return self::$redis;
	}

	/**
	 * Push a job to the end of a specific queue. If the queue does not
	 * exist, then create it as well.
	 *
	 * @param string $queue The name of the queue to add the job to.
	 * @param array $item Job description as an array to be JSON encoded.
	 */
	public static function push($queue, $item)
	{
		self::redis()->sadd('queues', $queue);
		self::redis()->rpush('queue:' . $queue, json_encode($item));
	}

	/**
	 * Pop an item off the end of the specified queue, decode it and
	 * return it.
	 *
	 * @param string $queue The name of the queue to fetch an item from.
	 * @return array|null Decoded item from the queue, or null on failure.
	 */
	public static function pop($queue)
	{
		$item = self::redis()->lpop('queue:' . $queue);
		if(!$item) {
			return null;
		}

		return json_decode($item, true);
	}

	/**
	 * Return the size (number of pending jobs) of the specified queue.
	 *
	 * @param string $queue name of the queue to be checked for pending jobs
	 *
	 * @return int The size of the queue.
	 */
	public static function size($queue)
	{
		return self::redis()->llen('queue:' . $queue);
	}

	/**
	 * Create a new job and save it to the specified queue.
	 *
	 * @param string $queue The name of the queue to place the job in.
	 * @param string $class The name of the class that contains the code to execute the job.
	 * @param array $args Any optional arguments that should be passed when the job is executed.
	 * @param boolean $trackStatus Set to true to be able to monitor the status of a job.
	 *
	 * @return string
	 */
	public static function enqueue($queue, $class, $args = null, $trackStatus = false)
	{
		$result = Job::create($queue, $class, $args, $trackStatus);
		if ($result) {
			ResqueEvent::trigger('afterEnqueue', array(
				'class' => $class,
				'args'  => $args,
				'queue' => $queue,
			));
		}

		return $result;
	}

	/**
	 * Reserve and return the next available job in the specified queue.
	 *
	 * @param string $queue Queue to fetch next available job from.
	 * @return Job|bool Instance of Job to be processed, false if none or error.
	 */
	public static function reserve($queue)
	{
	    try {
            return Job::reserve($queue);
        } catch (ResqueRedisException $e) {
	        return false;
        }
	}

	/**
	 * Get an array of all known queues.
	 *
	 * @return array Array of queues.
	 */
	public static function queues()
	{
		$queues = self::redis()->smembers('queues');
		if(!is_array($queues)) {
			$queues = array();
		}
		return $queues;
	}
}
