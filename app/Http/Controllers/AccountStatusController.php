<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountStatusController extends Controller
{
    /** Where pending and rejected accounts land. Approved accounts go straight to the board. */
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->isApproved()) {
            return redirect()->route('aac.board');
        }

        return view('account.status', ['user' => $user]);
    }
}
