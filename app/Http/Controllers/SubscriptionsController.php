<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SubscriptionsController extends Controller
{
   public function index()
{
    return view('subscription.index');
}
}
