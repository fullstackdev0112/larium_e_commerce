<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
namespace Larium\Shop\Sale;

use Finite\State\State;
use Finite\StateMachine\StateMachine;
use Finite\Loader\ArrayLoader;
use Larium\Shop\Store\Product;
use Larium\Shop\Payment\Payment;
use Larium\Shop\Payment\PaymentMethodInterface;
use Larium\Shop\Shipment\ShippingMethodInterface;
use Larium\Shop\Shipment\Shipment;
use Money\Money;

/**
 * Cart
 *
 * @author  Andreas Kollaros <andreaskollaros@ymail.com>
 * @license MIT {@link http://opensource.org/licenses/mit-license.php}
 */
class Cart implements CartInterface
{
    /**
     * An order instance that belongs to this cart.
     *
     * @var Order
     * @access protected
     */
    protected $order;

    /**
     * state_machine
     *
     * @var Finite\StateMachine\StateMachine
     * @access protected
     */
    protected $state_machine;

    /**
     * {@inheritdoc}
     */
    public function addItem(OrderableInterface $orderable, $quantity=1)
    {
        $item = $this->item_from_orderable($orderable, $quantity);

        // Checks for duplicated item and increase quantity instead of adding.
        if ($order_item = $this->getOrder()->containsItem($item)) {

            $order_item->setQuantity(
                $order_item->getQuantity() + $item->getQuantity()
            );

            $order_item->calculateTotalPrice();

            return $order_item;
        }

        $this->getOrder()->addItem($item);

        return $item;
    }

    /**
     * {@inheritdoc}
     */
    public function removeItem(OrderItem $item)
    {
        $this->getOrder()->removeItem($item);
    }

    /**
     * Gets the Order that handle the Cart.
     * Creates new if does not exist.
     *
     * @access public
     * @return Order
     */
    public function getOrder()
    {
        if (null === $this->order) {
            $this->order = new Order();
            $this->initialize_state_machine();
        }

        return $this->order;
    }

    /**
     * Sets an Order to handle.
     *
     * @param  Order $order
     * @access public
     * @return void
     */
    public function setOrder(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Gets a collection of order items
     *
     * @access public
     * @return array|Traversable
     */
    public function getItems()
    {
        return $this->getOrder()->getItems();
    }

    /**
     * Gets the total number of items in order.
     *
     * @access public
     * @return integer
     */
    public function getItemsCount()
    {
        $items = $this->getItems();

        if (is_array($items)) {
            return count($items);
        } else {
            return $items->count();
        }
    }

    /**
     * Delegate to Order.
     * Gets the total quantity of order items.
     *
     * @access public
     * @return integer
     */
    public function getTotalQuantity()
    {
        return $this->getOrder()->getTotalQuantity();
    }

    /**
     * {@inheritdoc}
     */
    public function addPaymentMethod(
        PaymentMethodInterface $method,
        Money $amount = null
    ) {
        $payment = new Payment();

        $payment->setPaymentMethod($method);
        $payment->setAmount($amount);
        $this->getOrder()->addPayment($payment);

        return $payment;
    }

    /**
     * Applies the given state to Order.
     *
     * @param string $state
     * @access public
     * @return mixed
     */
    public function processTo($state)
    {
        return $this->state_machine->apply($state);
    }

    /**
     * {@inheritdoc}
     */
    public function setShippingMethod(ShippingMethodInterface $shipping_method)
    {
        $shipment = new Shipment();
        $shipment->setShippingMethod($shipping_method);
        $this->getOrder()->addShipment($shipment);

        return $shipment;
    }

    /**
     * Creates an OrderItem from a given Product.
     *
     * @param  OrderableInterface $orderable
     * @param  int                $quantity
     * @access protected
     * @return void
     */
    protected function item_from_orderable(
        OrderableInterface $orderable,
        $quantity = 1
    ) {
        $item = new OrderItem();
        $item->setOrderable($orderable);
        $item->setUnitPrice($orderable->getUnitPrice());
        $item->setQuantity($quantity);
        $item->setDescription($orderable->getDescription());

        return $item;
    }

    protected function initialize_state_machine()
    {
        $states = [
            Order::CART       => ['type' => State::TYPE_INITIAL, 'properties' => []],
            Order::CHECKOUT   => ['type' => State::TYPE_NORMAL, 'properties' => []],
            Order::PARTIAL_PAID => ['type' => State::TYPE_NORMAL, 'properties' => []],
            Order::PAID       => ['type' => State::TYPE_NORMAL, 'properties' => []],
            Order::PROCESSING => ['type' => State::TYPE_NORMAL, 'properties' => []],
            Order::SENT       => ['type' => State::TYPE_NORMAL, 'properties' => []],
            Order::CANCELLED  => ['type' => State::TYPE_FINAL, 'properties' => []],
            Order::DELIVERED  => ['type' => State::TYPE_FINAL, 'properties' => []],
            Order::RETURNED   => ['type' => State::TYPE_FINAL, 'properties' => []],
        ];

        $transitions = [
            'checkout'    => ['from'=>[Order::CART], 'to' => Order::CHECKOUT],
            'partial_pay' => ['from'=>[Order::PAID, Order::PARTIAL_PAID], 'to' => Order::PARTIAL_PAID],
            'pay'         => ['from'=>[Order::CHECKOUT, Order::PARTIAL_PAID], 'to' => Order::PAID],
            'process'     => ['from'=>[Order::PAID], 'to' => Order::PROCESSING],
            'send'        => ['from'=>[Order::PROCESSING], 'to' => Order::SENT],
            'deliver'     => ['from'=>[Order::SENT],'to' => Order::DELIVERED],
            'return'      => ['from'=>[Order::SENT], 'to' => Order::RETURNED],
            'cancel'      => ['from'=>[Order::PAID, Order::PROCESSING], 'to' => Order::CANCELLED],
            'retry'       => ['from'=>[Order::CANCELLED], 'to' => Order::CHECKOUT],
        ];

        $callbacks = [
            'after' => [
                [
                    'from' => [Order::CHECKOUT, Order::PARTIAL_PAID],
                    'to'   => Order::PAID,
                    'do'   => [$this->order, 'processPayments']
                ],
                [
                    'from' => [Order::CHECKOUT, Order::PARTIAL_PAID],
                    'to'   => Order::PAID,
                    'do'   => [$this, 'rollbackPayment']
                ]
            ]
        ];

        $loader = new ArrayLoader(
            [
                'class' => get_class($this->order),
                'states' => $states,
                'transitions' => $transitions,
                'callbacks' => $callbacks
            ]
        );

        $this->state_machine = new StateMachine($this->order);

        $loader->load($this->state_machine);

        $this->state_machine->initialize();

    }

    /**
     * Gets state_machine.
     *
     * @access public
     * @return mixed
     */
    public function getStateMachine()
    {
        return $this->state_machine;
    }

    /**
     * Checks the balance of Order after a `pay` transition.
     * If balance is greater than zero then rollback to `checkout` state to
     * fullfil the payment of the Order.
     *
     * @access public
     * @return void
     */
    public function rollbackPayment()
    {
        if ($this->getOrder()->needsPayment()) {
            $this->processTo('partial_pay');
        }
    }
}
