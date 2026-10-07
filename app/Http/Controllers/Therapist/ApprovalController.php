<?php

namespace App\Http\Controllers\Therapist;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function index(): View
    {
        $parents = User::where('role', User::ROLE_PARENT)->with('reviewer')->orderBy('created_at')->get();

        return view('therapist.approvals', [
            'pending' => $parents->where('status', User::STATUS_PENDING),
            'rejected' => $parents->where('status', User::STATUS_REJECTED),
            'approved' => $parents->where('status', User::STATUS_APPROVED),
        ]);
    }

    /** Approves a pending account, or reconsiders a rejected one. */
    public function approve(Request $request, User $user): RedirectResponse
    {
        return $this->review($request, $user, User::STATUS_APPROVED, 'approved');
    }

    /** Rejects a pending account, or withdraws an approval. The account is kept, so the email can't sign up again. */
    public function reject(Request $request, User $user): RedirectResponse
    {
        return $this->review($request, $user, User::STATUS_REJECTED, 'rejected');
    }

    private function review(Request $request, User $user, string $status, string $verb): RedirectResponse
    {
        Gate::authorize('review', $user);

        $user->forceFill([
            'status' => $status,
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
        ])->save();

        return redirect()->route('therapist.approvals')->with('status', "{$user->name} ({$user->email}) {$verb}.");
    }
}
