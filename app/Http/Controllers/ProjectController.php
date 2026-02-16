<?php

namespace App\Http\Controllers;

use App\Models\CachedProjectState;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function overview(Request $request, string $slug): Response
    {
        $project = $this->findProject($request, $slug);

        return Inertia::render('projects/Overview', [
            'projectSlug' => $project->project_slug,
            'projectName' => $project->project_name,
        ]);
    }

    public function run(Request $request, string $slug): Response
    {
        $project = $this->findProject($request, $slug);

        return Inertia::render('projects/Run', [
            'projectSlug' => $project->project_slug,
            'projectName' => $project->project_name,
        ]);
    }

    public function diffs(Request $request, string $slug): Response
    {
        $project = $this->findProject($request, $slug);

        return Inertia::render('projects/Diffs', [
            'projectSlug' => $project->project_slug,
            'projectName' => $project->project_name,
        ]);
    }

    public function prds(Request $request, string $slug): Response
    {
        $project = $this->findProject($request, $slug);

        return Inertia::render('projects/Prds', [
            'projectSlug' => $project->project_slug,
            'projectName' => $project->project_name,
        ]);
    }

    public function settings(Request $request, string $slug): Response
    {
        $project = $this->findProject($request, $slug);

        return Inertia::render('projects/Settings', [
            'projectSlug' => $project->project_slug,
            'projectName' => $project->project_name,
        ]);
    }

    private function findProject(Request $request, string $slug): CachedProjectState
    {
        return CachedProjectState::where('project_slug', $slug)
            ->whereHas('deviceAuthorization', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->firstOrFail();
    }
}
