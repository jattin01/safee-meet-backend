<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    //

    public function webhook(Request $request)
    {
        // Handle the webhook request here
        // You can access the request data using $request->all()
        // For example, you can log the request data for debugging purposes
        \Log::info('Webhook received:', $request->all());

        // Return a response to acknowledge receipt of the webhook
        return response()->json(['message' => 'Webhook received successfully', 'status' => 200]);
    }
}
