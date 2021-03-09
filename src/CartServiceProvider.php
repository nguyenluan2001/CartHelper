<?php

namespace Luan\Cart;

use Illuminate\Support\ServiceProvider;
use Luan\Cart\Cart;
use Luan\Cart\Services\Database;
use Luan\Cart\Services\Session;

class CartServiceProvider extends ServiceProvider
{
    function boot()
    {
        $this->loadRoutesFrom(__DIR__ . "/routes/web.php");
        $this->publishes([
            __DIR__."/config/carthelper.php"=>config_path("carthelper.php")
        ]);
    }
    function register()
    {
        // if($this->getStorageService()=="session")
        // {
        //     $this->app->singleton('cart',Session::class);
        // }
        // else
        // {
        //     $this->app->singleton("cart",function()
        //     {
        //         return new Database();
        //     });
        // }
        $this->app->singleton('cart',Cart::class);
    }
    // function getStorageService()
    // {
    //     $class=$this->app['config']->get("carthelper.storage",'session');
    //     switch($class)
    //     {
    //         case "session":
    //             return "session";
    //         break;
    //         case "database":
    //             return "database";
    //         break;
    //         default:
    //             return "session";
    //         break;
    //     }
    // }
}
