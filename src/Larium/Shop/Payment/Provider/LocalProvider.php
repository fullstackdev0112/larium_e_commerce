<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace Larium\Shop\Payment\Provider;

use Larium\Shop\Payment\PaymentProviderInterface;
use Larium\Shop\Payment\PaymentSourceInterface;

class LocalProvider implements PaymentProviderInterface
{
    protected $payment_source;

    public function purchase($amount, array $options=array())
    {
        $response = new Response();
        $response->setSuccess(true);

        return $response;
    }

    public function setPaymentSource(PaymentSourceInterface $payment_source)
    {
        $this->payment_source = $payment_source;
    }
}