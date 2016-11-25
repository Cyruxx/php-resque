<?php


namespace ChrisBoulton\Resque\Exception;


use Exception;

/**
 * Class ResqueRedisException
 * @package ChrisBoulton\Resque\Exception
 */
class ResqueRedisException extends \Exception
{
    /**
     * @var array
     */
    protected $data = [];

    /**
     * ResqueRedisException constructor.
     * @param string $message
     * @param int $code
     * @param array $data
     * @param Exception $previous
     */
    public function __construct($message = '', $code = 500, $data = [], Exception $previous = null)
    {
        $this->data = $data;
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }
}