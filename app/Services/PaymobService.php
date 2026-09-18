<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;

class PaymobService
{
    protected string $baseUrl = 'https://accept.paymob.com/api';

    public function getAuthToken(): string
    {
        $response = Http::post("{$this->baseUrl}/auth/tokens", [
            'api_key' => config('services.paymob.api_key'),
        ]);

        return $response->json('token');
    }

    public function createOrder(string $authToken, Order $order): int
    {
        $response = Http::post("{$this->baseUrl}/ecommerce/orders", [
            'auth_token' => $authToken,
            'delivery_needed' => false,
            'amount_cents' => (int) round($order->total * 100),
            'currency' => 'EGP',
            'merchant_order_id' => $order->order_number,
            'items' => [],
        ]);

        return $response->json('id');
    }

    public function getPaymentKey(string $authToken, int $paymobOrderId, Order $order): string
    {
        $buyer = $order->buyer;
        $nameParts = explode(' ', trim($buyer->name), 2);

        $response = Http::post("{$this->baseUrl}/acceptance/payment_keys", [
            'auth_token' => $authToken,
            'amount_cents' => (int) round($order->total * 100),
            'expiration' => 3600,
            'order_id' => $paymobOrderId,
            'billing_data' => [
                'apartment' => 'NA',
                'email' => $buyer->email,
                'floor' => 'NA',
                'first_name' => $nameParts[0] ?? 'NA',
                'last_name' => $nameParts[1] ?? 'NA',
                'street' => $order->shipping_address ?? 'NA',
                'building' => 'NA',
                'phone_number' => $buyer->phone ?? 'NA',
                'shipping_method' => 'NA',
                'postal_code' => 'NA',
                'city' => $order->shipping_governorate ?? 'NA',
                'country' => 'EG',
                'state' => 'NA',
            ],
            'currency' => 'EGP',
            'integration_id' => (int) config('services.paymob.integration_id'),
        ]);

        return $response->json('token');
    }

    public function getIframeUrl(string $paymentKey): string
    {
        $iframeId = config('services.paymob.iframe_id');

        return "https://accept.paymob.com/api/acceptance/iframes/{$iframeId}?payment_token={$paymentKey}";
    }

    public function startPayment(Order $order): string
    {
        $authToken = $this->getAuthToken();
        $paymobOrderId = $this->createOrder($authToken, $order);

        $order->update(['paymob_order_id' => $paymobOrderId]);

        $paymentKey = $this->getPaymentKey($authToken, $paymobOrderId, $order);

        return $this->getIframeUrl($paymentKey);
    }

    public function verifyHmac(array $data, string $receivedHmac): bool
    {
        $secret = config('services.paymob.hmac_secret');

        $orderedKeys = [
            'amount_cents', 'created_at', 'currency', 'error_occured',
            'has_parent_transaction', 'id', 'integration_id', 'is_3d_secure',
            'is_auth', 'is_capture', 'is_refunded', 'is_standalone_payment',
            'is_voided', 'order', 'owner', 'pending', 'source_data_pan',
            'source_data_sub_type', 'source_data_type', 'success',
        ];

        $concatenated = '';
        foreach ($orderedKeys as $key) {
            $concatenated .= $data[$key] ?? '';
        }

        $calculatedHmac = hash_hmac('sha512', $concatenated, $secret);

        return hash_equals($calculatedHmac, $receivedHmac);
    }
}