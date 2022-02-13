<?php
declare(strict_types=1);

namespace ArrayIterator\Gear\ServiceWorker;

use Countable;
use RuntimeException;

/**
 * $events = new Events();
 * $events->add($name, function($value) {return $newValue;// do code}, 10);
 * $result = $events->dispatch($name, $value); // will be return $newValue
 * $events->trigger(); // call dispatch with void result
 * $isDispatched = $events->dispatcher($name) > 0;
 */
class Events implements Countable, ServiceInterface
{
    /**
     * @var array<string, array<int, array<int, array<string, string|callable>>>>
     */
    protected $events = [];

    /**
     * @var array<array<string>>
     */
    protected $currentDispatches = [];

    /**
     * @var array<string, array<string, array<int, int>>>
     */
    protected $dispatched = [];

    /**
     * @var array
     */
    protected $currentEvents = [];

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
     * @param callable $callable
     *
     * @return string|null
     */
    protected function createHash(callable $callable)
    {
        if (is_string($callable)) {
            $hash = $callable;
        } elseif (is_array($callable)) {
            if (is_object(reset($callable))) {
                $hash = spl_object_hash(reset($callable)).'::'.next($callable);
            } elseif (is_string(reset($callable))) {
                $hash = implode('::', $callable);
            }
        } else {
            /**
             * @var object $callable
             */
            $hash = spl_object_hash($callable);
        }
        return $hash??null;
    }

    /**
     * @param string $name
     * @param callable $callable
     * @param int $priority
     *
     * @return string
     */
    public function add(
        string $name,
        callable $callable,
        int $priority = 10
    ) : string {
        if (!isset($this->events[$name])) {
            $this->events[$name] = [];
        }

        $hash = $this->createHash($callable);
        $this->events[$name][$priority][] = [
            'hash' => $hash,
            'function' => $callable,
        ];
        ksort($this->events[$name]);
        return $hash;
    }

    /**
     * Get count callable called
     *
     * @param string $name
     * @param callable|null $callable
     * @param int|null $priority
     *
     * @return int
     * @noinspection PhpUnused
     */
    public function dispatched(string $name, callable $callable = null, int $priority = null) : int
    {
        $called = 0;
        if (!isset($this->dispatched[$name])) {
            return $called;
        }
        if ($callable) {
            $hash = $this->createHash($callable);
            if (!isset($this->dispatched[$name][$hash])) {
                return $called;
            }
            if ($priority !== null) {
                if (!isset($this->dispatched[$name][$hash][$priority])) {
                    return $called;
                }
                return $this->dispatched[$name][$hash][$priority];
            }
            foreach ($this->dispatched[$name][$hash] as $item) {
                $called += $item;
            }
            return $called;
        }

        foreach ($this->dispatched[$name] as $item) {
            foreach ($item as $subItem) {
                $called+= $subItem;
            }
        }

        return $called;
    }

    /**
     * @param string $name
     * @param callable|null $callable
     * @param int|null $priority
     *
     * @return bool
     * @noinspection PhpUnused
     */
    public function inDispatch(string $name, callable $callable = null, int $priority = null) : bool
    {
        if ($callable) {
            $hash = $this->createHash($callable);
            if ($priority !== null) {
                return isset($this->currentDispatches[$name][$hash][$priority]);
            }
            return isset($this->currentDispatches[$name][$hash]);
        }

        return isset($this->currentDispatches[$name]);
    }

    /**
     * @param string $name
     * @param ...$arguments
     *
     * @return mixed
     */
    public function dispatch(string $name, ...$arguments)
    {
        $key = 0;
        if (count($arguments) === 0) {
            $value = null;
        } else {
            $value = reset($arguments);
            $key = key($arguments);
        }

        if (!isset($this->events[$name])) {
            return $value;
        }

        reset($this->events[$name]);
        do {
            $current = current($this->events[$name]);
            $prior  = key($this->events[$name]);
            reset($this->events[$name][$prior]);
            if (!$current) {
                break;
            }
            do {
                $callback = current($this->events[$name][$prior]);
                if (!$callback) {
                    break;
                }
                $hash = $callback['hash'];
                if (!isset($this->currentDispatches[$name])) {
                    $this->currentDispatches[$name] = [];
                }
                if (!isset($this->currentDispatches[$name][$hash])) {
                    $this->currentDispatches[$name][$hash] = [];
                }
                if (!empty($this->currentDispatches[$name][$hash][$prior])) {
                    throw new RuntimeException(
                        sprintf(
                            'Event %s(%s)(%d) still in progress',
                            $name,
                            $hash,
                            $prior
                        )
                    );
                }

                $this->currentDispatches[$name][$hash][$prior] = true;
                if (!isset($this->dispatched[$name])) {
                    $this->dispatched[$name] = [];
                }
                if (!isset($this->dispatched[$name][$hash])) {
                    $this->dispatched[$name][$hash] = [];
                }
                if (!isset($this->dispatched[$name][$hash][$prior])) {
                    $this->dispatched[$name][$hash][$prior] = 0;
                }

                $this->dispatched[$name][$hash][$prior]++;
                $this->currentEvents[$prior][$hash] = true;
                // not using call_user_func[array] to allow call pass by reference
                $arguments[$key] = $callback['function'](...$arguments);
                unset($this->currentEvents[$prior][$hash]);
                unset($this->currentDispatches[$name][$hash][$prior]);
                if (empty($this->currentDispatches[$name][$hash])) {
                    unset($this->currentDispatches[$name][$hash]);
                }
                if (empty($this->currentEvents[$prior])) {
                    unset($this->currentEvents[$prior]);
                }
            } while (!empty($this->events[$name])
                     && !empty($this->events[$name][$prior])
                     && next($this->events[$name][$prior]) !== false
            );
        } while (!empty($this->events[$name]) && next($this->events[$name]) !== false);
        if (empty($this->currentDispatches[$name])) {
            unset($this->currentDispatches[$name]);
        }

        return $value;
    }

    /**
     * @param string $name
     * @param ...$arguments
     *
     * @see Events::dispatch()
     *
     */
    public function trigger(string $name, ...$arguments)
    {
        $this->dispatch($name, ...$arguments);
    }

    /**
     * Count event by name
     *
     * @param string $name
     * @param callable|null $callable
     * @param int|null $priority
     *
     * @return int
     */
    public function countEvent(string $name, callable $callable = null, int $priority = null) : int
    {
        if (!isset($this->events[$name])) {
            return 0;
        }

        $counted = 0;
        $hash = $callable ? $this->createHash($callable) : false;
        foreach ($this->events[$name] as $prior => $item) {
            if ($hash === false) {
                $counted += count($item);
                continue;
            }
            if ($hash === null) {
                continue;
            }
            if ($priority !== null && $priority !== $prior) {
                continue;
            }
            foreach ($item as $callableArray) {
                if ($hash === $callableArray['hash']) {
                    $counted++;
                }
            }
            if (empty($this->events[$name][$prior])) {
                unset($this->events[$name][$prior]);
            }
        }

        return $counted;
    }

    /**
     * Check event(s) if exists
     *
     * @param string $name
     * @param callable|null $callable
     * @param int|null $priority
     *
     * @return bool
     */
    public function exist(string $name, callable $callable = null, int $priority = null) : bool
    {
        return $this->countEvent($name, $callable, $priority) > 0;
    }

    /**
     * Remove Event(s)
     *
     * @param string $name
     * @param callable|null $functionToRemove
     * @param int|null $priority
     *
     * @return int
     */
    public function remove(string $name, callable $functionToRemove = null, int $priority = null) : int
    {
        if (!isset($this->events[$name])) {
            return 0;
        }

        $removed = 0;
        $hash = $functionToRemove ? $this->createHash($functionToRemove) : false;
        foreach ($this->events[$name] as $prior => $item) {
            if ($hash === false) {
                $removed += count($item);
                unset($this->events[$name][$prior]);
                continue;
            }
            if ($hash === null) {
                continue;
            }
            if ($priority !== null && $priority !== $prior) {
                continue;
            }
            foreach ($item as $key => $callableArray) {
                if ($hash === $callableArray['hash']) {
                    $removed++;
                    unset($this->events[$name][$prior][$key]);
                }
            }

            if (empty($this->events[$name][$prior])) {
                unset($this->events[$name][$prior]);
            } else {
                $this->events[$name][$prior] = array_values($this->events[$name][$prior]);
            }
        }
        if (empty($this->events[$name])) {
            unset($this->events[$name]);
        }

        return $removed;
    }

    /**
     * @see Countable
     * @return int
     */
    public function count() : int
    {
        $total = 0;
        foreach ($this->events as $item) {
            foreach ($item as $subItem) {
                $total += count($subItem);
            }
        }

        return $total;
    }
}
