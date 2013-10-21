<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace Larium\Shop\Sale;

use Larium\Shop\Payment\Payment;
use Larium\Shop\Payment\PaymentMethod;
use Larium\Shop\Payment\CreditCard;
use Finite\Event\TransitionEvent;
use Larium\Shop\Payment\Provider\RedirectResponse;

class OrderTest extends \PHPUnit_Framework_TestCase
{

    protected $loader;

    public function setUp()
    {
        $this->loader = new \FixtureLoader();
    }

    public function testCartAddingItems()
    {
        $cart = new Cart();

        $product1 = $this->getProduct('product_1');
        $variant1 = $product1->getDefaultVariant();
        $item1 = $cart->addItem($variant1);

        $this->assertEquals(1, $cart->getItemsCount());

        $product2 = $this->getProduct('product_2');
        $variant2 = $product2->getDefaultVariant();
        $item2 = $cart->addItem($variant2);

        $this->assertEquals(2, $cart->getItemsCount());
        $this->assertEquals(2, $cart->getTotalQuantity());

        $this->assertEquals(21, $cart->getOrder()->getTotalAmount());

    }

    public function testCartAddSameVariant()
    {
        $cart = new Cart();

        $product = $this->getProduct('product_1');
        $variant = $product->getDefaultVariant();

        $cart->addItem($variant);
        $cart->addItem($variant);

        $this->assertEquals(1, $cart->getItemsCount());
        $this->assertEquals(2, $cart->getOrder()->getTotalQuantity());
    }


    public function testOrderContainsItem()
    {
        $cart = new Cart();
        $order = $cart->getOrder();
        $product = $this->getProduct('product_1');
        $variant = $product->getDefaultVariant();
        $item = $cart->addItem($variant);

        $this->assertTrue(false !== $order->containsItem($item));

        $item = $this->getOrderItem('order_item_1');

        $item->setOrder($order);

        $this->assertTrue(false !== $order->containsItem($item));
    }


    public function testOrderPaymentWithCreditCard()
    {
        $cart = $this->getCartWithOneItem();

        $cart->processTo('checkout');

        $method = $this->getPaymentMethod('creditcard_payment_method');
        $method->setSourceOptions($this->getValidCreditCardOptions());

        $payment = $cart->addPaymentMethod($method);

        $cart->processTo('pay');

        $this->assertEquals('paid', $cart->getOrder()->getState());
        $this->assertEquals('paid', $payment->getState());
        $this->assertEquals('1', $method->getPaymentSource()->getNumber());
        $this->assertEquals(0, $cart->getOrder()->getBalance());
    }

    public function testOrderPaymentWithCashOnDelivery()
    {
        $cart = $this->getCartWithOneItem();

        $total_amount = $cart->getOrder()->getTotalAmount();

        $cart->processTo('checkout');
        //In checkout state you can add payment and shipment methods, billing and
        //shipping addresses etc.

        $method = $this->getPaymentMethod('cash_on_delivery_payment_method');
        $payment = $cart->addPaymentMethod($method);

        $cart->processTo('pay');

        $this->assertTrue($cart->getOrder()->getTotalAmount() > $total_amount);
        $this->assertEquals('paid', $cart->getOrder()->getState());
        $this->assertEquals('paid', $payment->getState());
        $this->assertEquals(0, $cart->getOrder()->getBalance());
    }

    public function testRedirectPayment()
    {
        $cart = $this->getCartWithOneItem();

        $cart->processTo('checkout');

        $method = $this->getPaymentMethod('redirect_payment_method');
        $method->setSourceOptions($this->getValidCreditCardOptions());

        $payment = $cart->addPaymentMethod($method);

        $response = $cart->processTo('pay');

        $this->assertInstanceOf('Larium\Shop\Payment\Provider\RedirectResponse', $response);
        $this->assertEquals('unpaid', $payment->getState());
    }

    public function testOrderWithShippingMethod()
    {
        $cart = $this->getCartWithOneItem();

        $cart->processTo('checkout');

        $payment_method = $this->getPaymentMethod('cash_on_delivery_payment_method');
        $payment = $cart->addPaymentMethod($payment_method);

        $shipping_method = $this->getShippingMethod('courier_shipping_method');

        $this->assertEquals(5, $shipping_method->calculateCost($cart->getOrder()));

        $cart->setShippingMethod($shipping_method);

        $response = $cart->processTo('pay');

        $this->assertEquals(0, $cart->getOrder()->getBalance());

        $this->assertEquals(5, $cart->getOrder()->getShippingCost());
    }

    public function testRemovePaymentWillRemoveAdjustmentToo()
    {
        $cart = $this->getCartWithOneItem();

        $cart->processTo('checkout');

        $total_amount = $cart->getOrder()->getTotalAmount();

        $payment_method = $this->getPaymentMethod('cash_on_delivery_payment_method');
        $payment = $cart->addPaymentMethod($payment_method);

        $this->assertTrue($cart->getOrder()->getAdjustments()->count() != 0);

        $cart->getOrder()->removePayment($payment);

        $this->assertTrue($cart->getOrder()->getAdjustments()->count() == 0);

        $this->assertEquals($total_amount, $cart->getOrder()->getTotalAmount());
    }

    /*- ( Fixtures ) -------------------------------------------------------- */

    private function getCartWithOneItem()
    {
        $cart = new Cart();
        $product = $this->getProduct('product_1');
        $variant = $product->getDefaultVariant();
        $item = $cart->addItem($variant);

        return $cart;
    }

    private function getProduct($id)
    {
        $data = $this->loader->getData();

        $hydrator = new \Hydrator('Larium\Shop\\Store\\Product');

        return $hydrator->hydrate($data[$id], $id);
    }

    private function getOrderItem($id)
    {
        $data = $this->loader->getData();

        $hydrator = new \Hydrator('Larium\Shop\\Sale\\OrderItem');

        return $hydrator->hydrate($data[$id], $id);
    }

    private function getPaymentMethod($id)
    {
        $data = $this->loader->getData();

        $hydrator = new \Hydrator('Larium\Shop\\Payment\\PaymentMethod');

        return $hydrator->hydrate($data[$id], $id);
    }

    private function getValidCreditCardOptions()
    {
        return array(
            'first_name' => 'John',
            'last_name' => 'Doe',
            'month' => '2',
            'year' => date('Y') + 5,
            'number'=>'1'
        );
    }

    private function getShippingMethod($id)
    {
        $data = $this->loader->getData();

        $hydrator = new \Hydrator('Larium\Shop\\Shipment\\ShippingMethod');

        return $hydrator->hydrate($data[$id], $id);
    }
}
