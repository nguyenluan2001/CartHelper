<?php

namespace Luan\Cart;

use Illuminate\Session\SessionManager;
use Illuminate\Support\Collection;
use  Illuminate\Support\Str;

class Cart
{
    public $session;
    public $name = "cart";
    function __construct(SessionManager $session)
    {
        $this->session = $session;
    }
    function getCart()
    {
       
            $cart = $this->session->get($this->name);
            return $cart instanceof Collection ? $cart : new Collection();
    }
    function add($id, $name, $qty, $price, $options = [])
    {
        if(isset($this->session->get($this->name)[$id]))
        {
            $row=session($this->name)[$id];
            $this->updateRow($row,$qty);
        }
        else
        {
            $this->insertRow($id, $name, $qty, $price, $options = []);
            
        }
    }
    function updateRow($row,$qty)
    {
        $cart=$this->getCart();
        $row->qty=$row->qty+$qty;
        $row->subtotal=$row->qty*$row->price;
        $cart->put($row->id,$row);
        $this->save($cart);

    }
    function insertRow($id, $name, $qty, $price, $options = [])
    {
        $newRow=$this->makeRow($id, $name, $qty, $price, $options);
        $cart=$this->getCart();
        $cart->put($id,$newRow);
        $this->save($cart);
    }
    function makeRow($id, $name, $qty, $price, $options = [])
    {
        return new CartItem($id, $name, $qty, $price, $options);
    }
    function content()
    {
        return $this->session->get($this->name);
    }
    function total()
    {
        $total=0;
        foreach($this->content() as $item)
        {
            $total+=$item->subtotal;
        }
        return $total;
    }
    function count()
    {
        $count=0;
        foreach($this->content() as $item)
        {
            $count+=$item->qty;
        }
        return $count;
    }
    function remove($id)
    {
        $this->content()->forget($id);
    }
    function destroy()
    {
       $this->save(null);
    }
    function save($cart)
    {
        $this->session->put($this->name,$cart);
    }
}
