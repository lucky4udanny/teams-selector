<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class RegisteredUserController extends Controller
{
    /**
     * Registration is invite-only — redirect to login with a clear message.
     */
    public function create(): RedirectResponse
    {
        return redirect()->route('login')
            ->with('status', 'Registration is by invitation only. Please ask your organization administrator to invite you.');
    }

    public function store(): RedirectResponse
    {
        return redirect()->route('login')
            ->with('status', 'Registration is by invitation only.');
    }
}
