<?php

namespace App\Http\Controllers\Aac;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $logs = $request->user()->usageLogs();

        return view('aac.activity', [
            'today' => (clone $logs)->whereDate('created_at', today())->count(),
            'week' => (clone $logs)->where('created_at', '>=', now()->subDays(7))->count(),
            'top' => (clone $logs)
                ->selectRaw('sentence, lang, COUNT(*) as total')
                ->groupBy('sentence', 'lang')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
            'recent' => (clone $logs)->latest()->limit(50)->get(),
            'logging' => $request->user()->aacSettings()['log_usage'],
        ]);
    }

    public function clear(Request $request): RedirectResponse
    {
        $request->user()->usageLogs()->delete();

        return redirect()->route('aac.activity')->with('status', 'History cleared.');
    }
}
