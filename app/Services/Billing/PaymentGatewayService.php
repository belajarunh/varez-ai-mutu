<?php

namespace App\Services\Billing;

use App\Models\BillingInvoice;
use App\Models\Institution;
use App\Models\Payment;
use Illuminate\Support\Str;

class PaymentGatewayService
{
    public function isConfigured(): bool
    {
        return (bool) config('services.billing.gateway.enabled', false);
    }

    /**
     * @return array<string, mixed>
     */
    public function createManualSubscription(Institution $institution, BillingInvoice $invoice): array
    {
        return [
            'status' => 'manual_activation_pending',
            'gateway' => 'manual',
            'institution_id' => $institution->getKey(),
            'invoice_id' => $invoice->getKey(),
            'message' => 'Payment gateway is not configured. Activation is pending admin approval.',
        ];
    }

    public function validateWebhookSignature(string $payload, string $signature): bool
    {
        if (! $this->isConfigured()) {
            return true;
        }

        $secret = (string) config('services.billing.webhook_secret', '');

        return hash_hmac('sha256', $payload, $secret) === $signature;
    }

    /**
     * @return array<string, mixed>
     */
    public function handleWebhook(string $payload, string $signature, ?string $idempotencyKey = null): array
    {
        if (! $this->validateWebhookSignature($payload, $signature)) {
            return ['status' => 'invalid_signature'];
        }

        $idempotencyKey ??= (string) Str::uuid();

        $existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return ['status' => 'duplicate_webhook', 'payment_id' => $existing->getKey()];
        }

        $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        $payment = Payment::query()->create([
            'institution_id' => $decoded['institution_id'] ?? 0,
            'invoice_id' => $decoded['invoice_id'] ?? null,
            'gateway' => $decoded['gateway'] ?? 'manual',
            'external_reference' => $decoded['external_reference'] ?? null,
            'idempotency_key' => $idempotencyKey,
            'status' => $decoded['status'] ?? 'processed',
            'amount' => $decoded['amount'] ?? 0,
            'currency' => $decoded['currency'] ?? 'IDR',
            'provider_status' => $decoded['provider_status'] ?? 'processed',
            'payload_json' => $decoded,
            'metadata_json' => ['webhook_verified' => true],
            'processed_at' => now(),
        ]);

        return ['status' => 'processed', 'payment_id' => $payment->getKey()];
    }
}
