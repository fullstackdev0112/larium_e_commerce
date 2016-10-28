<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace Larium\Shop\Sale\Command\Cart;

use Larium\Shop\Sale\Cart;
use Larium\Shop\Sale\Repository\OrderRepositoryInterface;

class CartRemoveItemHandler
{
    private $orderRepository;

    public function __construct(OrderRepositoryInterface $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    public function handle(CartRemoveItemCommand $command)
    {
        if (false === $cart = $this->getCart($command->orderNumber)) {
            throw new \InvalidArgumentException(
                sprintf('Could not find Cart with number `%s`', $command->orderNumber)
            );
        }

        $identifier = $command->identifier;
        $item = $cart->getItems()->select(function ($i) use ($identifier) {
            return $i->getIdentifier() == $identifier;
        });

        if (false === $item) {
            throw new \InvalidArgumentException(
                sprintf('Order item `%s` does not exist.', $identifier)
            );
        }

        $cart->removeItem($item);

        return $cart;
    }

    private function getCart($orderNumber)
    {
        if ($order = $this->orderRepository->getOneByNumber($orderNumber)) {
            return new Cart($order);
        }

        return false;
    }
}
