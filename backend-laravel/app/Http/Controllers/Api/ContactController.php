<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    /**
     * Store a new contact message (public endpoint).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'telefono' => 'nullable|string|max:20',
            'mensaje' => 'required|string|max:2000',
        ]);

        $contact = Contact::create($validated);

        return response()->json([
            'message' => 'Mensaje enviado exitosamente',
            'contact' => $contact
        ], 201);
    }

    /**
     * Display a listing of contact messages (admin only).
     */
    public function index()
    {
        $contacts = Contact::orderBy('created_at', 'desc')->get();
        
        return response()->json($contacts);
    }

    /**
     * Display the specified contact message.
     */
    public function show(string $id)
    {
        $contact = Contact::findOrFail($id);
        
        return response()->json($contact);
    }

    /**
     * Remove the specified contact message.
     */
    public function destroy(string $id)
    {
        $contact = Contact::findOrFail($id);
        $contact->delete();

        return response()->json([
            'message' => 'Mensaje eliminado exitosamente'
        ]);
    }
}
