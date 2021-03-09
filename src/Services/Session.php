<?php

namespace Luan\Cart\Services;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Session\SessionManager;
use Illuminate\Contracts\Events\Dispatcher;
use Luan\Cart\Collections\Item;

class Session
{
    protected $session;

    protected $event;

    protected $name = 'cart.session';
        protected $model;

    public function __construct(SessionManager $session, Dispatcher $event)
    {
        $this->session = $session;

        $this->event = $event;
    }
    public function name($name)
    {
        $this->name = $name;
        return $this;
    }

    public function getName()
    {
        return $this->name;
    }
    /**
     *  Associate as Eloquent Model to Shopping Cart
     *
     * @param $model
     * @return $this
     * @throws Exception
     */
    public function associate($model)
    {
        if (!class_exists($model)) {
            throw new Exception("Invalid model name '$model'.");
        }
        $this->model = $model;
        return $this;
    }

    /**
     *  Get the associated Eloquent Model attached with Shopping Cart
     *
     * @return mixed
     */
    public function getModel()
    {
        return $this->model;
    }
    public function all()
    {
        return $this->getCart();
    }
    protected function getCart()
    {
        $cart = $this->session->get($this->name);

        return $cart instanceof Collection ? $cart : new Collection();
    }
    /**
     *  Get the row from Shopping Cart
     *
     * @param $rawId
     * @return Item|null
     */
    public function get($rawId)
    {
        $row = $this->getCart()->get($rawId);

        return is_null($row) ? null : new Item($row);
    }
    /**
     *  Generate a Raw Id for Item rows (__raw_id)
     *
     * @param $id
     * @param $attributes
     * @return string
     */
    protected function generateRawId($id, $attributes)
    {
        ksort($attributes);

        return md5($id . serialize($attributes));
    }
    /**
     *  Add an Item to Shopping Cart
     *
     * @param $id
     * @param $name
     * @param $qty
     * @param $price
     * @param $attributes
     * @return bool|mixed
     * @throws Exception
     */
    public function add($id, $name = null, $qty = null, $price = null, array $attributes = [])
    {
        $cart = $this->getCart();

        $this->event->push('cart.adding', [$attributes, $cart]);

        $row = $this->addRow($id, $name, $qty, $price, $attributes);

        $this->event->push('cart.added', [$attributes, $cart]);

        return $row;
    }
    /**
     *  Add a row in Shopping Cart if exist then update it
     *
     * @param $id
     * @param $name
     * @param $qty
     * @param $price
     * @param array $attributes
     * @return bool|Item|mixed
     * @throws Exception
     */
    protected function addRow($id, $name, $qty, $price, array $attributes = [])
    {
        if (!is_numeric($qty) || $qty < 1) {
            throw new Exception('Invalid quantity.');
        }

        if (!is_numeric($price) || $price < 0) {
            throw new Exception('Invalid price.');
        }
        $cart = $this->getCart();

        $rawId = $this->generateRawId($id, $attributes);

        if ($row = $cart->get($rawId)) {
            $row = $this->updateQty($rawId, $row->qty + $qty);
        } else {
            $row = $this->insertRow($rawId, $id, $name, $qty, $price, $attributes);
        }

        return $row;
    }
    /**
     *  Update quantity of a Row
     *
     * @param $rawId
     * @param $qty
     * @return bool|mixed
     */
    protected function updateQty($rawId, $qty)
    {
        if ($qty <= 0) {
            return $this->remove($rawId);
        }
        return $this->updateRow($rawId, ['qty' => $qty]);
    }
    /**
     *  Remove an Item from Shopping Cart
     *
     * @param $rawId
     * @return bool
     */
    public function remove($rawId)
    {
        if (!$row = $this->get($rawId)) {
            return true;
        }

        $cart = $this->getCart();

        $this->event->push('cart.removing', [$row, $cart]);
        $cart->forget($rawId);

        $this->event->push('cart.removed', [$row, $cart]);

        $this->save($cart);

        return true;
    }
    /**
     *  Update whole row of Shopping Cart
     *
     * @param $rawId
     * @param array $attributes
     * @return mixed
     */
    protected function updateRow($rawId, array $attributes)
    {
        $cart = $this->getCart();
        $row = $cart->get($rawId);
        foreach ($attributes as $key => $value) {
            $row->put($key, $value);
        }

        if (count(array_intersect(array_keys($attributes), ['qty', 'price']))) {
            $row->put('total', $row->qty * $row->price);
        }

        $cart->put($rawId, $row);

        return $row;
    }
    /**
     * Insert a new row in Shopping Cart
     *
     * @param $rawId
     * @param $id
     * @param $name
     * @param $qty
     * @param $price
     * @param array $attributes
     * @return Item
     */
    protected function insertRow($rawId, $id, $name, $qty, $price, $attributes = [])
    {
        $newRow = $this->makeRow($rawId, $id, $name, $qty, $price, $attributes);

        $cart = $this->getCart();

        $cart->put($rawId, $newRow);

        $this->save($cart);

        return $newRow;
    }
    /**
     *  Make a new row in Shopping Cart
     *
     * @param $rawId
     * @param $id
     * @param $name
     * @param $qty
     * @param $price
     * @param array $attributes
     * @return Item
     */
    protected function makeRow($rawId, $id, $name, $qty, $price, array $attributes = [])
    {
        return new Item(array_merge(
            [
                '__raw_id' => $rawId,
                'id' => $id,
                'name' => $name,
                'qty' => $qty,
                'price' => $price,
                'total' => $qty * $price,
                '__model' => $this->model,
            ],
            $attributes
        ));
    }
    /**
     *  Update an Item in Shopping Cart
     *
     * @param $rawId
     * @param $attribute
     * @return bool|mixed
     * @throws Exception
     */
    public function update($rawId, $attribute)
    {
        if (!$row = $this->get($rawId)) {
            throw new Exception('Item not found.');
        }

        $cart = $this->getCart();

        $this->event->push('cart.updating', [$row, $cart]);

        if (is_array($attribute)) {
            $raw = $this->updateAttribute($rawId, $attribute);
        } else {
            $raw = $this->updateQty($rawId, $attribute);
        }

        $this->event->push('cart.updated', [$row, $cart]);

        return $raw;
    }
    /**
     *   Update selected attributes of Shopping Cart
     *
     * @param $rawId
     * @param $attributes
     * @return mixed
     */
    protected function updateAttribute($rawId, $attributes)
    {
        return $this->updateRow($rawId, $attributes);
    }
    /**
     *  Save record to Session
     *
     * @param $cart
     * @return mixed
     */
    protected function save($cart)
    {
        $this->session->put($this->name, $cart);
        return $cart;
    }
    /**
     *  Destroy the current Shopping Cart
     *
     * @return bool
     */
    public function destroy()
    {
        $cart = $this->getCart();

        $this->event->push('cart.destroying', $cart);

        $this->save(null);

        $this->event->push('cart.destroyed', $cart);

        return true;
    }
    /**
     * An alias to ShoppingCart::destroy();
     */
    public function clean()
    {
        $this->destroy();
    }
}
