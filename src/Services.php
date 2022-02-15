<?php
declare(strict_types=1);

namespace ArrayIterator\Gear\ServiceWorker;

/**
 * @mixin ServiceCollection
 *
 * @method static array getRegisteredServices()
 * @method static array getRegisteredServiceKeys()
 * @method static array getLockedServiceKeys()
 * @method static Services getServices()
 * @method static ServiceInterface get(string $serviceName)
 * @method static bool register(string $serviceName, $service)
 * @method static bool append(string $serviceName, $service)
 * @method static void remove(string $serviceName)
 * @method static void lock(string ...$serviceName)
 * @method static bool locked(string $serviceName)
 * @method static bool registered(string $serviceName)
 */
final class Services
{
    const EVENTS = 'core.events';
    const COLLECTIONS = 'core.collections';

    /**
     * @var Services
     */
    private static $instance;

    /**
     * @var array<string, string>
     */
    private $preserved_services = [
        self::EVENTS => Events::class,
        self::COLLECTIONS => ServiceCollection::class,
    ];

    /**
     * @var ServiceCollection
     */
    private $serviceCollections;

    /**
     * Constructor
     * @private
     */
    private function __construct()
    {
        self::$instance = $this;
        $this->serviceCollections = new ServiceCollection($this);
        foreach ($this->preserved_services as $key => $item) {
            if ($key === self::COLLECTIONS) {
                $item = $this->serviceCollections;
            }
            $this->serviceCollections->register($key, $item);
            $this->serviceCollections->lock($key);
        }
    }

    /**
     * @return string[]
     */
    public function getPreservedServices(): array
    {
        return $this->preserved_services;
    }

    /**
     * @return ServiceCollection
     */
    public function getServiceCollections(): ServiceCollection
    {
        return $this->serviceCollections;
    }

    /**
     * @return Services
     */
    public static function getInstance() : Services
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @param string $name
     * @param array $arguments
     *
     * @return false|mixed
     */
    public static function __callStatic(string $name, array $arguments)
    {
        return call_user_func_array(
            [self::getInstance()->getServiceCollections(), $name],
            $arguments
        );
    }

    /**
     * @param string $name
     * @param array $arguments
     *
     * @return false|mixed
     */
    public function __call(string $name, array $arguments)
    {
        return call_user_func_array(
            [$this->getServiceCollections(), $name],
            $arguments
        );
    }

    /**
     * @return Events
     */
    public static function events() : ServiceInterface
    {
        return self::get(self::EVENTS);
    }

    /**
     * @param string $name
     * @param ...$args
     *
     * @return mixed|null
     * @see Events::dispatch()
     */
    public static function dispatchEvent(string $name, ...$args)
    {
        return self::events()->dispatch($name, ...$args);
    }

    /**
     * @param string $name
     * @param ...$args
     *
     * @see Events::trigger()
     */
    public static function triggerEvent(string $name, ...$args)
    {
        self::events()->trigger($name, ...$args);
    }

    /**
     * @param string $name
     * @param callable|null $callable
     * @param int|null $priority
     *
     * @return bool
     * @see Events::inDispatch()
     */
    public static function inEvent(string $name, callable $callable = null, int $priority = null) : bool
    {
        return self::events()->inDispatch($name, $callable, $priority);
    }

    /**
     * @param string $name
     * @param callable|null $callable
     * @param int|null $priority
     *
     * @return bool
     * @see Events::dispatched()
     */
    public static function doingEvent(string $name, callable $callable = null, int $priority = null) : bool
    {
        return self::events()->dispatched($name, $callable, $priority) > 0;
    }

    /**
     * @param string $name
     * @param callable $callable
     * @param int $priority
     *
     * @return string
     * @see Events::add()
     */
    public static function addEvent(string $name, callable $callable, int $priority = 10): string
    {
        return self::events()->add($name, $callable, $priority);
    }

    /**
     * @param string $name
     * @param callable|null $callable
     * @param int|null $priority
     *
     * @return bool
     * @see Events::exist()
     */
    public static function hasEvent(string $name, callable $callable = null, int $priority = null): bool
    {
        return self::events()->exist($name, $callable, $priority);
    }
}
