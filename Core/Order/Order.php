<?php
namespace Core\Order;

use \Core\Location\Location as Location;
use \Core\Delivery\Delivery as Delivery;

class Order
{
    const COUNT = [
        'min' => 10,
        'max' => 100
    ];
    const COMING = [
        'min' => 1,
        'max' => 30
    ];
    const COOKING = [
        'min' => 10,
        'max' => 30
    ];

    protected $order = [];
    protected $slice = [];
    protected $clone = [];
    protected $delivery = [];
    protected $wrongDelivery = [];

    /**
     * Order constructor.
     * @param array $order
     */
    public function __construct(
        array $order = []
    )
    {
        $order = $this->generate();
        $this->order = $order->getOrders();
    }

    /**
     * Get orders
     * @return array
     */
    public function getOrders()
    {
        return $this->order;
    }

    /**
     * Get clone
     * @return array
     */
    public function getClone()
    {
        $this->setClone()->clusters();

        return $this->clone;
    }

    /**
     * Get slice array orders
     * @return array
     */
    public function getSlice()
    {
        return $this->slice;
    }

    /**
     * Set slice, array slice from clone array
     * @param int $limit slice limit
     * @return $this
     */
    public function setSlice($limit)
    {
        $this->slice = array_slice($this->clone, 0 , $limit, true);

        return $this;
    }

    /**
     * Calculate delivery orders
     * @param array $orders orders objects array
     * @return $this
     */
    public function delivery($orders)
    {
        self::sortDelivery($orders);

        $location = Location::generateLocation(0,0);

        $time = Delivery::getMaxTime($orders);

        foreach ($orders as $key => $order) {
            if (Delivery::isValidDelivery($time, $location, $order)) {
                $this->setDelivery($order, $key);
                $location = $order->location;

                if (!empty($order->children)) {
                    foreach ($order->children as $childKey => $child) {
                        if (Delivery::isValidDelivery($time, $location, $child))
                        {
                            $this->setDelivery($child, $childKey);
                            $location = $child->location;
                        }
                    }
                }
            }
        }

        if (count($this->getDelivery()) == 0) {
            $location = Location::generateLocation(0,0);
            $order = reset($orders);

            if (!Delivery::isValidDelivery($time, $location, $order)) {
                $this->setWrongDelivery($order, key($orders));
            }
        }

        return $this;
    }

    /**
     * Reset delivery object and delete orders from general object
     * @return $this
     */
    public function resetDelivery()
    {
        if ($this->delivery) {
            foreach (array_keys($this->delivery) as $key) {
                unset($this->order[$key]);
            }

            $this->delivery = [];
        }

        if ($this->wrongDelivery) {
            foreach (array_keys($this->wrongDelivery) as $key) {
                unset($this->order[$key]);
            }

            $this->wrongDelivery = [];
        }

        return $this;
    }

    /**
     * Get valid delivery orders
     * @return array
     */
    public function getDelivery()
    {
        return $this->delivery;
    }

    /**
     * Get valid delivery orders
     * @return array
     */
    public function getWrongDelivery()
    {
        return $this->wrongDelivery;
    }

    /**
     * Output generated orders
     */
    public function orderOutput()
    {
        $orders = $this->getOrders();

        foreach ($orders as $id => $order) {
            echo $id.' '.$order->coming.' '.$order->cooking.' '
                .Location::getAxisX($order->location).' '
                .Location::getAxisY($order->location)."\n";
        }
    }

    /**
     * Output calculated routes
     */
    public function deliveryOutput()
    {
        if (count($this->getDelivery()) > 0) {
            echo "Route:\n";
            foreach ($this->getDelivery() as $id => $order) {
                echo $id . ' ' . $order->deliveryTime . "\n";
            }
        }

        if (count($this->getWrongDelivery()) > 0) {
            echo "Route > 60m:\n";
            foreach ($this->getWrongDelivery() as $id => $order) {
                echo $id . ' ' . $order->deliveryTime . "\n";
            }
        }
    }

    /**
     * Add order in array valid delivery
     * @param object $order object order
     * @param int $key order key in general object
     * @return $this
     */
    protected function setDelivery($order, $key)
    {
        if (count($this->getDelivery()) < Delivery::LIMIT_COUNT) {
            $this->delivery[$key] = $order;
        }

        return $this;
    }

    /**
     * Add order in array wrong delivery
     * @param object $order object order
     * @param int $key order key in general object
     * @return $this
     */
    protected function setWrongDelivery($order, $key)
    {
        $this->wrongDelivery[$key] = $order;

        return $this;
    }

    /**
     * Generate orders
     * @return $this
     */
    protected function generate()
    {
        for ($count = 0; $count < self::generateCount(self::COUNT); $count++) {
            $coming = self::generateComing(
                self::COMING
            );

            if (isset($this->order[$count-1]->coming)) {
                $coming = $coming + $this->order[$count-1]->coming;
            }

            $cooking = self::generateCooking(self::COOKING);

            $location = Location::generateLocation();

            $start = $coming;

            if (isset($this->order[$count-2])) {
                $minFinish = min(
                    $this->order[$count-2]->finish,
                    $this->order[$count-1]->finish
                );

                $start = ($coming > $minFinish ? $coming : $minFinish);
            }

            $this->order[] = (object)[
                'coming' => $coming,
                'start' => $start,
                'cooking' => $cooking,
                'location' => $location,
                'route' =>  Location::getRoute(
                    Location::generateLocation(0,0),
                    $location
                ),
                'finish' => $start + $cooking,
            ];
        }

        return $this;
    }

    /**
     * Generate count orders
     * @param array $count range count
     * @return int
     */
    protected function generateCount($count)
    {
        return rand($count['min'], $count['max']);
    }

    /**
     * Generate order coming time
     * @param array $coming range time
     * @return int
     */
    protected function generateComing($coming)
    {
        return rand($coming['min'], $coming['max']);
    }

    /**
     * Generate order cooking time
     * @param array $cooking range time
     * @return int
     */
    protected function generateCooking($cooking)
    {
        return rand($cooking['min'], $cooking['max']);
    }

    /**
     * Sort orders by end cooking time
     * @return $this
     */
    public function sortOrders()
    {
        uasort($this->order, function($a,$b)
        {
            return $a->finish - $b->finish;
        });

        return $this;
    }

    /**
     * Sort orders by distance and save index association
     * @param $orders
     */
    protected function sortDelivery(&$orders)
    {
        uasort($orders, function($a,$b)
        {
            return $a->route - $b->route;
        });
    }

    /**
     * Set clone, clone order objects
     * @return $this
     */
    protected function setClone()
    {
        foreach ($this->order as $key => $order) {
            $clone[$key] = clone $order;
        }

        $this->clone = $clone;

        return $this;
    }

    /**
     * Grouping orders by location
     * @return $this
     */
    protected function clusters()
    {
        $temp = $this->clone;

        foreach ($this->clone as $key => &$order) {
            unset($temp[$key]);

            $nearOrder = Location::getNearPoint(
                $order->location,
                $temp
            );

            if (count($nearOrder) > 0) {
                foreach ($nearOrder as $key => $near) {
                    $order->children[$key] = $near;
                    unset($this->clone[$key]);
                }
            }
        }

        return $this;
    }
}
