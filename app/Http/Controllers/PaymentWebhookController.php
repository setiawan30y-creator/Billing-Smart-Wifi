<?php

namespace App\Http\Controllers;

use App\Services\PaymentWebhookService;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, PaymentWebhookService $service)
    {
        [$status, $message] = $service->handle($request, $provider);

        return response()->json(['message' => $message], $status);
    }
}
