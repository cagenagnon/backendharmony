<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Reservation;
use App\Services\FedaPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    // Crée un enregistrement de paiement (status pending) et crée transaction FedaPay
    public function store(Request $request)
    {
        $data = $request->validate([
            'reservation_id' => 'required|integer|exists:reservations,id',
            'amount' => 'required|numeric|min:0',
            'method' => 'required|string',
            'phone' => 'nullable|string',
            'operator' => 'nullable|string',
        ]);

        $reservation = Reservation::findOrFail($data['reservation_id']);

        if ($reservation->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($reservation->status === 'paid') {
            return response()->json(['message' => 'Reservation already paid'], 400);
        }

        // Mark any existing pending payment as failed before creating a new one
        Payment::where('reservation_id', $reservation->id)
            ->where('status', 'pending')
            ->update(['status' => 'failed']);

        $provider = $data['method'];

        // Accept both generic 'mobile_money' or operator codes like 'mtn'/'moov'
        $mobileOperators = ['mtn', 'moov', 'mobile_money'];
        $isMobile = in_array(strtolower($provider), $mobileOperators, true);

        if ($isMobile && empty($data['phone'])) {
            return response()->json(['message' => 'Phone number is required for mobile money'], 422);
        }

        $payment = Payment::create([
            'reservation_id' => $reservation->id,
            'user_id' => Auth::id(),
            'amount' => $data['amount'],
            'method' => $provider,
            'status' => 'pending',
            'transaction_reference' => null,
        ]);

        $fedapay = new FedaPayService;

        $payload = [
            'amount' => (int) round($payment->amount * 100), // cents if API expects integer
            'currency' => 'XOF',
            'description' => 'Payment for reservation '.$reservation->id,
            'metadata' => [
                'reservation_id' => $reservation->id,
                'payment_id' => $payment->id,
            ],
        ];

        if ($isMobile) {
            $payload['payment_method'] = 'mobile_money';
            $payload['phone'] = $data['phone'];
            // pass operator if provided (e.g., 'mtn' or 'moov')
            if (! empty($data['operator']) && in_array(strtolower($data['operator']), ['mtn', 'moov'], true)) {
                $payload['operator'] = strtolower($data['operator']);
            } elseif (in_array(strtolower($provider), ['mtn', 'moov'], true)) {
                $payload['operator'] = strtolower($provider);
            }
        } else {
            $payload['payment_method'] = 'card';
            $payload['return_url'] = env('APP_URL').'/payments/return';
        }

        $response = $fedapay->createTransaction($payload);

        if (! $response) {
            Log::error('FedaPay create transaction returned null', ['payment_id' => $payment->id]);
            // Dev fallback: simulate a transaction when running locally/sandbox without keys
            if (app()->environment('local') || env('FEDAPAY_MODE') === 'sandbox') {
                $txId = 'dev_tx_'.uniqid();
                $redirect = env('APP_URL').'/payments/return?tx='.$txId;
                $response = ['id' => $txId, 'redirect_url' => $redirect];
            } else {
                return response()->json(['message' => 'Payment provider error'], 500);
            }
        }

        // Extract transaction id and redirect_url where present
        $txId = $response['id'] ?? ($response['data']['id'] ?? null);
        $redirect = $response['redirect_url'] ?? ($response['data']['redirect_url'] ?? null);

        if ($txId) {
            $payment->transaction_reference = $txId;
            $payment->save();
        }

        // In development or when AUTO_APPROVE_PAYMENTS is enabled, auto-approve mobile payments
        if ($isMobile && (app()->environment('local') || env('AUTO_APPROVE_PAYMENTS') === 'true')) {
            $payment->status = 'paid';
            $payment->paid_at = now();
            $payment->save();

            $reservation->status = 'paid';
            $reservation->save();

            return response()->json(['message' => 'Paiement validé (sandbox)', 'status' => 'paid', 'transaction_reference' => $txId], 200);
        }

        if ($provider === 'mobile_money') {
            return response()->json(['message' => 'Validation envoyée', 'transaction_id' => $txId], 200);
        }

        return response()->json(['redirect_url' => $redirect ?? null, 'payment' => $payment], 201);
    }

    // Webhook endpoint for FedaPay notifications
    public function webhook(Request $request)
    {
        $signatureHeader = $request->header('X-Fedapay-Signature') ?? $request->header('x-fedapay-signature');
        $secret = env('FEDAPAY_WEBHOOK_SECRET', null);

        if ($secret) {
            $expected = hash_hmac('sha256', $request->getContent(), $secret);
            if (! hash_equals($expected, (string) $signatureHeader)) {
                Log::warning('Invalid FedaPay webhook signature', ['header' => $signatureHeader]);

                return response()->json(['message' => 'Invalid signature'], 400);
            }
        }

        $payload = $request->json()->all();

        // Try several possible shapes
        $txId = $payload['data']['id'] ?? ($payload['data']['object']['id'] ?? ($payload['id'] ?? null));
        $status = $payload['data']['status'] ?? ($payload['data']['object']['status'] ?? ($payload['status'] ?? null));

        if (! $txId) {
            Log::warning('FedaPay webhook without transaction id', ['payload' => $payload]);

            return response()->json([], 200);
        }

        $payment = Payment::where('transaction_reference', $txId)->first();
        if (! $payment) {
            Log::warning('Payment not found for FedaPay webhook', ['tx' => $txId]);

            return response()->json([], 200);
        }

        if ($status === 'approved' || $status === 'paid') {
            $payment->status = 'paid';
            $payment->paid_at = now();
            $payment->save();

            $reservation = $payment->reservation;
            // Set reservation status to 'paid' after successful payment
            $reservation->status = 'paid';
            $reservation->save();
        } elseif ($status === 'failed' || $status === 'cancelled') {
            $payment->status = 'failed';
            $payment->save();
        }

        return response()->json([], 200);
    }

    // Verify by transaction id (called by frontend polling)
    public function verifyByTransaction($transactionId)
    {
        $fedapay = new FedaPayService;
        $resp = $fedapay->getTransaction($transactionId);

        if (! $resp) {
            return response()->json(['message' => 'Unable to fetch transaction'], 500);
        }

        $status = $resp['status'] ?? ($resp['data']['status'] ?? null);

        $payment = Payment::where('transaction_reference', $transactionId)->first();
        if (! $payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        if ($status === 'approved' || $status === 'paid') {
            $payment->status = 'paid';
            $payment->paid_at = now();
            $payment->save();

            $reservation = $payment->reservation;
            // keep consistent: set reservation status to 'paid'
            $reservation->status = 'paid';
            $reservation->save();
        } elseif ($status === 'failed' || $status === 'cancelled') {
            $payment->status = 'failed';
            $payment->save();
        }

        return response()->json(['status' => $payment->status]);
    }

    // Récupère le reçu / détail du paiement
    public function show($id)
    {
        $payment = Payment::with('reservation.service')->findOrFail($id);

        if ($payment->reservation->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($payment);
    }
}
