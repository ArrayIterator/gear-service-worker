<?php
declare(strict_types=1);

namespace ArrayIterator\Gear\ServiceWorker\Exceptions;

use RuntimeException;
use Throwable;

class ServiceNotRegistered extends ServiceException
{
    /**
     * @var string
     */
    protected $serviceName;

    /**
     * @param string $serviceName
     */
    public function __construct(string $serviceName)
    {
        $this->serviceName = $serviceName;
        $message = sprintf('Service %s is not registered.', $serviceName);
        parent::__construct($message);
    }

    /**
     * @return string
     */
    public function getServiceName(): string
    {
        return $this->serviceName;
    }
}