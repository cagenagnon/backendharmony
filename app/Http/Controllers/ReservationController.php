<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReservationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        if ($user->role === 'admin') {
            return Reservation::with(['user', 'service'])->get();
        }

        return Reservation::with('service')->where('user_id', $user->id)->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'reserved_at' => 'required|date|after:now',
            'notes' => 'nullable|string',
        ]);

        // Gestion des conflits de date (même service, même date)
        $conflict = Reservation::where('service_id', $validated['service_id'])
            ->where('reserved_at', $validated['reserved_at'])
            ->exists();
        if ($conflict) {
            return response()->json(['error' => 'Ce créneau est déjà réservé.'], 409);
        }

        $reservation = Reservation::create([
            'user_id' => $user->id,
            'service_id' => $validated['service_id'],
            'reserved_at' => $validated['reserved_at'],
            'status' => 'pending',
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json($reservation, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = Auth::user();
        $reservation = Reservation::with(['user', 'service'])->findOrFail($id);
        if ($user->role !== 'admin' && $reservation->user_id !== $user->id) {
            return response()->json(['error' => 'Non autorisé'], 403);
        }

        return $reservation;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = Auth::user();
        $reservation = Reservation::findOrFail($id);
        if ($user->role !== 'admin' && $reservation->user_id !== $user->id) {
            return response()->json(['error' => 'Non autorisé'], 403);
        }

        $validated = $request->validate([
            'service_id' => 'sometimes|exists:services,id',
            'reserved_at' => 'sometimes|date|after:now',
            'notes' => 'nullable|string',
            'status' => 'sometimes|in:pending,confirmed,cancelled,completed,paid,failed',
        ]);

        // Gestion des conflits de date si modification du créneau
        if (isset($validated['service_id']) || isset($validated['reserved_at'])) {
            $serviceId = $validated['service_id'] ?? $reservation->service_id;
            $reservedAt = $validated['reserved_at'] ?? $reservation->reserved_at;
            $conflict = Reservation::where('id', '!=', $reservation->id)
                ->where('service_id', $serviceId)
                ->where('reserved_at', $reservedAt)
                ->exists();
            if ($conflict) {
                return response()->json(['error' => 'Ce créneau est déjà réservé.'], 409);
            }
        }

        $reservation->update($validated);

        return $reservation;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = Auth::user();
        $reservation = Reservation::findOrFail($id);
        if ($user->role !== 'admin' && $reservation->user_id !== $user->id) {
            return response()->json(['error' => 'Non autorisé'], 403);
        }
        $reservation->delete();

        return response()->json(['message' => 'Réservation supprimée']);
    }
}
