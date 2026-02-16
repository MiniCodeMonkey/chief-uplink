<?php

namespace App\Http\Controllers;

use App\Models\CachedProjectState;
use App\Models\RunHistory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function overview(Request $request, string $slug): Response
    {
        $project = $this->findProject($request, $slug);

        $recentRuns = RunHistory::where('device_authorization_id', $project->device_authorization_id)
            ->where('project_slug', $project->project_slug)
            ->orderByDesc('started_at')
            ->limit(5)
            ->get()
            ->map(fn (RunHistory $run) => [
                'id' => $run->id,
                'prd_name' => $run->prd_name,
                'status' => $run->status,
                'stories_completed' => $run->stories_completed,
                'stories_total' => $run->stories_total,
                'duration_seconds' => $run->duration_seconds,
                'tokens_used' => $run->tokens_used,
                'error_message' => $run->error_message,
                'started_at' => $run->started_at?->toISOString(),
                'finished_at' => $run->finished_at?->toISOString(),
            ]);

        return Inertia::render('projects/Overview', [
            'projectSlug' => $project->project_slug,
            'projectName' => $project->project_name,
            'project' => [
                'id' => $project->id,
                'device_authorization_id' => $project->device_authorization_id,
                'status' => $project->status,
                'current_prd_name' => $project->current_prd_name,
                'stories_completed' => $project->stories_completed,
                'stories_total' => $project->stories_total,
                'story_details' => $project->story_details,
                'active_sessions' => $project->active_sessions,
                'recent_activity' => $project->recent_activity,
                'git_branch' => $project->git_branch,
                'last_commit_hash' => $project->last_commit_hash,
                'last_commit_message' => $project->last_commit_message,
            ],
            'recentRuns' => $recentRuns,
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
