<?php

namespace App\Event;

use App\Entity\Product;
use Symfony\Contracts\EventDispatcher\Event;

class ProductEvent extends Event
{
    final public const PURCHASED = 'product.purchase';

    public function __construct(Product $product){
        $this->product = $product;
    }
    final public function getProduct(): Product{
        return $this->product;
    }

}
