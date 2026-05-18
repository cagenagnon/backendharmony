<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string',
        ]);

        $contact = Contact::create($data);

        // Send notification email to site owner (uses configured mailer, default 'log' in local)
        try {
            $body = "New contact message:\n\nName: {$contact->name}\nEmail: {$contact->email}\nSubject: {$contact->subject}\n\nMessage:\n{$contact->message}\n";
            Mail::raw($body, function ($message) use ($contact) {
                $message->to(config('mail.from.address'))
                        ->subject('Nouveau message de contact: ' . ($contact->subject ?: 'sans sujet'));
            });
        } catch (\Throwable $e) {
            // Log failure but don't fail the request
            logger()->error('Contact mail send failed: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'data' => $contact], 201);
    }
}
