<?php
namespace Luan\Cart;

use Illuminate\Support\Collection;

class CartItem extends Collection
{
    public $id;
    public $rowId;
    public $name;
    public $qty;
    public $price;
    public $options;
    public $subtotal;
    function __construct($id,$name,$qty,$price,$options=[])
    {
     $this->id=$id;
     $this->name=$name;
     $this->qty=$qty;
     $this->price=$price;
     $this->options=$options;   
     $this->rowId="luan-".$this->id;
     $this->subtotal=$qty*$price;
    }
}