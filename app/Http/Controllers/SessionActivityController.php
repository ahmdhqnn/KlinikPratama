<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SessionActivityController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()->is_active && $request->user()->hasRole(...User::ROLES), 403);
        $request->session()->put('clinical_last_activity', now()->timestamp);

        return $this->status($request);
    }

    public function status(Request $request): Response
    {
        abort_unless($request->user()->is_active && $request->user()->hasRole(...User::ROLES), 403);

        return response()->json([
            'userId' => $request->user()->id,
            'expiresAt' => ((int) $request->session()->get('clinical_last_activity') + config('clinical_security.idle_minutes') * 60) * 1000,
            'serverNow' => now()->getTimestampMs(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
