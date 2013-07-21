<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace Larium\Calculator;

class CalculatorInterface
{
    /**
     * Computes and returns a final value based on given object.
     *
     * @param mixed $object
     * @access public
     * @return number
     */
    public function compute($object = null);
}
