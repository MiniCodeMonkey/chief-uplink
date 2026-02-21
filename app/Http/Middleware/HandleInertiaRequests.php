<?php

namespace App\Http\Middleware;

use App\Models\DeviceAuthorization;
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
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'chiefServerUrl' => $this->getChiefServerUrl(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'devices' => fn () => $this->getDevices($request),
            'selectedDeviceId' => fn () => $this->getSelectedDeviceId($request),
            'showOnboarding' => fn () => $this->shouldShowOnboarding($request),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getDevices(Request $request): array
    {
        $user = $request->user();
        if (! $user) {
            return [];
        }

        return DeviceAuthorization::where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->with('cachedProjectStates')
            ->get()
            ->map(function (DeviceAuthorization $device) {
                return [
                    'id' => $device->id,
                    'device_name' => $device->device_name,
                    'os' => $device->os,
                    'arch' => $device->arch,
                    'chief_version' => $device->chief_version,
                    'is_online' => $device->is_online,
                    'last_connected_at' => $device->last_connected_at?->toISOString(),
                    'connection_status' => $this->getConnectionStatus($device),
                    'projects' => $device->cachedProjectStates->map(function ($project) {
                        return [
                            'id' => $project->id,
                            'device_authorization_id' => $project->device_authorization_id,
                            'project_slug' => $project->project_slug,
                            'project_name' => $project->project_name,
                            'status' => $project->status,
                            'git_branch' => $project->git_branch,
                            'current_prd_name' => $project->current_prd_name,
                            'stories_completed' => $project->stories_completed,
                            'stories_total' => $project->stories_total,
                            'active_sessions' => $project->active_sessions,
                            'recent_activity' => $project->recent_activity,
                        ];
                    })->toArray(),
                ];
            })->toArray();
    }

    private function getSelectedDeviceId(Request $request): ?int
    {
        $cookie = $request->cookie('selected_device_id');

        return $cookie ? (int) $cookie : null;
    }

    private function getChiefServerUrl(): ?string
    {
        $url = config('app.url');
        $host = parse_url($url, PHP_URL_HOST);

        if ($host === 'uplink.chiefloop.com') {
            return null;
        }

        return $url;
    }

    private function shouldShowOnboarding(Request $request): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        // Show onboarding only if user has never had any device authorized
        return ! DeviceAuthorization::where('user_id', $user->id)->exists();
    }

    private function getConnectionStatus(DeviceAuthorization $device): string
    {
        if ($device->is_online) {
            return 'online';
        }

        if ($device->last_connected_at === null) {
            return 'never-connected';
        }

        if ($device->last_connected_at->diffInSeconds(now()) < 60) {
            return 'reconnecting';
        }

        return 'offline';
    }
}
