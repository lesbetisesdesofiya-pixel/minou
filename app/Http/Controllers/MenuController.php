<?php

namespace App\Http\Controllers;

use App\Services\MenuService;

class MenuController extends Controller
{
    public function index()
    {
        $catalog = (new MenuService())->catalog();

        return view('menu', [
            'productsData'     => $catalog['productsData'],
            'garnituresGlobal' => $catalog['garnituresGlobal'],
        ]);
    }
}
