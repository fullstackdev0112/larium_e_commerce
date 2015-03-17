<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace Larium\Shop\Payment;

use Larium\Helper;
use Larium\Shop\Sale\Order;
use Finite\StateMachine\StateMachine;
use Finite\Loader\ArrayLoader;

class PaymentTest extends \PHPUnit_Framework_TestCase
{
    use Helper;

    public function setUp()
    {
        $this->loader = new \FixtureLoader();
    }

    public function testCreateInstance()
    {
        $payment = new Payment();

        $this->assertInstanceOf('Larium\\Shop\\Payment\\PaymentInterface', $payment);

        $this->assertNotNull($payment->getIdentifier());

        $this->assertInstanceOf('Larium\\Shop\\Common\\Collection', $payment->getTransactions());
    }

    public function testPayment()
    {
        $method = $this->getPaymentMethod('creditcard_payment_method');
        $method->setSourceOptions($this->getValidCreditCardOptions());

        $order = new Order();

        $payment = new Payment();

        $order->addPayment($payment);

        $payment->setAmount(100);

        $payment->setPaymentMethod($method);

        $sm = $this->initialize_state_machine($payment);

        $sm->apply('purchase');

        $this->assertEquals('paid', $payment->getState());
    }

    private function initialize_state_machine($payment)
    {

        $config = include __DIR__ . '/../../../../src/config/payment_finite_state.php';

        $loader = new ArrayLoader($config);

        $state_machine = new StateMachine($payment);

        $loader->load($state_machine);

        $state_machine->initialize();

        return $state_machine;
    }
}
