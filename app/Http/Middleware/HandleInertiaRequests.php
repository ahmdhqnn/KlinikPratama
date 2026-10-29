<?php

namespace App\Http\Middleware;

use App\NurseQueueCounts;
use App\PerawatClinicResolver;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $doctorProfile = $request->user()?->role === 'dokter' ? $request->user()->nakes : null;
        $practiceClinic = $doctorProfile?->is_active ? $doctorProfile->poliklinik : null;

        return [
            ...parent::share($request),
            'modules' => config('clinic.modules'),
            'nurseQueues' => fn (): ?array => $this->nurseQueues($request),
            'auth' => [
                'user' => $request->user() ? [
                    ...$request->user()->only('id', 'name', 'email', 'role'),
                    'roleLabel' => $request->user()->role_label,
                    'clinicType' => $practiceClinic?->is_active ? $practiceClinic->jenis : null,
                    'photoUrl' => $request->user()->photo_path ? route('profile.photo', ['v' => basename($request->user()->photo_path)]) : null,
                ] : null,
            ],
            'flash' => [
                'success' => fn (): ?string => $request->session()->get('success'),
                'error' => fn (): ?string => $request->session()->get('error'),
            ],
        ];
    }

    /** @return array{assessment: int, treatments: int}|null */
    private function nurseQueues(Request $request): ?array
    {
        if (! $request->user()?->isPerawat()) {
            return null;
        }

        $nurse = app(PerawatClinicResolver::class)->assignedNakes($request->user());

        return $nurse === null
            ? ['assessment' => 0, 'treatments' => 0]
            : app(NurseQueueCounts::class)->forClinic((int) $nurse->poliklinik_id);
    }
}
