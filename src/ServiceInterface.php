<?php
declare(strict_types=1);

namespace ArrayIterator\Gear\ServiceWorker;

interface ServiceInterface
{
    /**
     * @param Services $services
     */
    public function __construct(Services $services);

    /**
     * @return Services
     */
    public function getServices() : Services;
}