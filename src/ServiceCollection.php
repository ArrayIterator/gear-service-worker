<?php
declare(strict_types=1);

namespace ArrayIterator\Gear\ServiceWorker;

use ArrayIterator\Gear\ServiceWorker\Exceptions\ServiceException;
use ArrayIterator\Gear\ServiceWorker\Exceptions\ServiceFrozen;
use ArrayIterator\Gear\ServiceWorker\Exceptions\ServiceLocked;
use ArrayIterator\Gear\ServiceWorker\Exceptions\ServiceNotRegistered;
use ReflectionClass;
use Throwable;

final class ServiceCollection implements ServiceInterface
{
    /**
     * @var array<string, ServiceInterface|class-string<ServiceInterface>>
     */
    protected $registeredService = [];

    /**
     * @var array<string, bool>
     */
    protected $lockedService = [];

    /**
     * @var Services
     */
    private $services;

    /**
     * @param Services $services
     */
    public function __construct(Services $services)
    {
        $this->services = $services;
    }

    /**
     * @return Services
     */
    public function getServices(): Services
    {
        return $this->services;
    }

    /**
     * @param string $serviceName
     * @param class-string<ServiceInterface>|ServiceInterface $service
     *
     * @return bool
     */
    public function register(string $serviceName, $service) : bool
    {
        if (!isset($this->registeredService[$serviceName])) {
            if (!is_subclass_of($service, ServiceInterface::class, true)) {
                throw new ServiceException(
                    sprintf(
                        'Service definition must be instance or subclass of %s',
                        ServiceInterface::class
                    )
                );
            }
            if (is_string($service)) {
                try {
                    $ref     = new ReflectionClass($service);
                    $service = $ref->getName();
                } catch (Throwable $e) {
                    throw new ServiceException($e->getMessage());
                }
            }
            $this->registeredService[$serviceName] = $service;
            return true;
        }

        if (is_object($this->registeredService[$service])) {
            if ($this->registeredService[$service] !== $service) {
                throw new ServiceFrozen($serviceName);
            }
            return true;
        }

        if (is_object($service)) {
            $objName = get_class($service);
        } else {
            try {
                $ref = new ReflectionClass($service);
                $objName = $ref->getName();
            } catch (Throwable $e) {
                throw new ServiceException($e->getMessage());
            }
        }
        if (!empty($this->lockedService[$serviceName])
            && $objName !== $this->registeredService[$serviceName]
        ) {
            throw new ServiceLocked($serviceName);
        }
        $this->registeredService[$serviceName] = $service;
        return true;
    }

    /**
     * @param string ...$services
     */
    public function lock(string ...$services)
    {
        foreach ($services as $item) {
            $this->lockedService[$item] = true;
        }
    }

    /**
     * @return array<int, string>
     */
    public function getRegisteredServiceKeys() : array
    {
        return array_keys($this->registeredService);
    }

    /**
     * @return array<int, string>
     */
    public function getLockedServiceKeys(): array
    {
        return array_keys($this->lockedService);
    }

    /**
     * @param string $serviceName
     *
     * @return ServiceInterface
     */
    public function get(string $serviceName) : ServiceInterface
    {
        if (!isset($this->registeredService[$serviceName])) {
            throw new ServiceNotRegistered($serviceName);
        }

        if (is_string($this->registeredService[$serviceName])) {
            $this->registeredService[$serviceName] = new $this->registeredService[$serviceName]($this->getServices());
        }
        return $this->registeredService[$serviceName];
    }

    /**
     * @param string $serviceName
     * @param class-string<ServiceInterface>|ServiceInterface $service
     *
     * @return bool
     */
    public function append(string $serviceName, $service) : bool
    {
        if ($this->registered($serviceName)) {
            return false;
        }
        return $this->register($serviceName, $service);
    }

    /**
     * @param string $serviceName
     */
    public function remove(string $serviceName)
    {
        if ($this->locked($serviceName)) {
            throw new ServiceLocked($serviceName);
        }
        if (isset($this->registeredService[$serviceName])) {
            throw new ServiceFrozen($serviceName);
        }

        unset($this->registeredService[$serviceName]);
    }

    /**
     * @param string $serviceName
     *
     * @return bool
     */
    public function locked(string $serviceName) : bool
    {
        return isset($this->lockedService[$serviceName]) && $this->registered($serviceName);
    }

    /**
     * @param string $serviceName
     *
     * @return bool
     */
    public function registered(string $serviceName) : bool
    {
        return isset($this->registeredService[$serviceName]);
    }
}
