<?php

namespace App\Services\Billing;

use App\Models\BillingInvoice;
use App\Models\Institution;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

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
            return false;
        }

        $secret = (string) config('services.billing.webhook_secret', '');

        return $secret !== '' && hash_equals(hash_hmac('sha256', $payload, $secret), $signature);
    }

    /**
     * @return array<string, mixed>
     */
    public function handleWebhook(string $payload, string $signature, ?string $idempotencyKey = null): array
    {
        if (! $this->isConfigured()) {
            return ['status' => 'gateway_disabled'];
        }

        if (! $this->validateWebhookSignature($payload, $signature)) {
            return ['status' => 'invalid_signature'];
        }

        $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            return ['status' => 'invalid_payload'];
        }

        $idempotencyKey = $idempotencyKey ?: ($decoded['event_id'] ?? null);
        $invoiceId = filter_var($decoded['invoice_id'] ?? null, FILTER_VALIDATE_INT);
        $institutionId = filter_var($decoded['institution_id'] ?? null, FILTER_VALIDATE_INT);
        $amount = $decoded['amount'] ?? null;
        $currency = $decoded['currency'] ?? null;
        $externalReference = $decoded['external_reference'] ?? null;
        $providerStatus = strtolower((string) ($decoded['status'] ?? ''));

        if (! is_string($idempotencyKey) || trim($idempotencyKey) === ''
            || $invoiceId === false || $invoiceId < 1
            || $institutionId === false || $institutionId < 1
            || ! is_numeric($amount) || (float) $amount < 0
            || ! is_string($currency) || preg_match('/^[A-Z]{3}$/', strtoupper($currency)) !== 1
            || ! is_string($externalReference) || trim($externalReference) === '') {
            return ['status' => 'invalid_payload'];
        }

        $paymentStatus = match ($providerStatus) {
            'paid', 'succeeded', 'success' => 'paid',
            'failed', 'cancelled', 'canceled' => 'failed',
            'pending', 'processing' => 'pending',
            default => null,
        };

        if ($paymentStatus === null) {
            return ['status' => 'invalid_payment_status'];
        }

        return DB::transaction(function () use (
            $idempotencyKey,
            $invoiceId,
            $institutionId,
            $amount,
            $currency,
            $externalReference,
            $paymentStatus,
            $providerStatus,
        ): array {
            $invoice = BillingInvoice::query()
                ->whereKey($invoiceId)
                ->where('institution_id', $institutionId)
                ->lockForUpdate()
                ->first();

            if ($invoice === null) {
                return ['status' => 'invoice_not_found'];
            }

            $existing = Payment::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return ['status' => 'duplicate_webhook', 'payment_id' => $existing->getKey()];
            }

            if (number_format((float) $invoice->amount, 2, '.', '') !== number_format((float) $amount, 2, '.', '')
                || strtoupper($invoice->currency) !== strtoupper($currency)) {
                return ['status' => 'invoice_amount_or_currency_mismatch'];
            }

            if ($paymentStatus === 'paid' && $invoice->status === 'paid') {
                return ['status' => 'invoice_already_paid'];
            }

            $payment = Payment::query()->firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'institution_id' => $institutionId,
                    'invoice_id' => $invoice->getKey(),
                    'gateway' => (string) config('services.billing.gateway.name', 'configured'),
                    'external_reference' => $externalReference,
                    'status' => $paymentStatus,
                    'amount' => $amount,
                    'currency' => strtoupper($currency),
                    'provider_status' => $providerStatus,
                    'payload_json' => [
                        'event_id' => $idempotencyKey,
                        'external_reference' => $externalReference,
                        'status' => $providerStatus,
                    ],
                    'metadata_json' => ['webhook_verified' => true],
                    'processed_at' => now(),
                ],
            );

            if (! $payment->wasRecentlyCreated) {
                return ['status' => 'duplicate_webhook', 'payment_id' => $payment->getKey()];
            }

            if ($paymentStatus === 'paid') {
                $invoice->forceFill([
                    'status' => 'paid',
                    'paid_at' => now(),
                ])->save();
            }

            return ['status' => 'processed', 'payment_id' => $payment->getKey()];
        });
    }
}
