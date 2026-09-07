<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreJobTitleRequest;
use App\Http\Requests\Admin\UpdateJobTitleRequest;
use App\Models\JobTitle;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class JobTitleController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $this->validateListRequest($request);

        return view('job-titles.index', [
            'jobTitles' => $this->paginatedJobTitles($validated),
            'filters' => $validated,
            'statistics' => [
                'total' => JobTitle::count(),
                'active' => JobTitle::active()->count(),
                'inactive' => JobTitle::where('is_active', false)->count(),
                'users_with_titles' => User::query()
                    ->whereNotNull('job_title')
                    ->whereRaw("TRIM(job_title) <> ''")
                    ->count(),
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $validated = $this->validateListRequest($request);

        return response()->json($this->paginatedJobTitles($validated));
    }

    public function show(JobTitle $jobTitle): JsonResponse
    {
        $jobTitle->setAttribute('users_count', $jobTitle->usersCount());

        return response()->json(['data' => $this->serialize($jobTitle)]);
    }

    public function store(StoreJobTitleRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $jobTitle = JobTitle::create([
            'name' => $validated['name'],
            'normalized_name' => $validated['normalized_name'],
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['status'] === 'active',
        ]);

        return response()->json([
            'message' => 'Job title created successfully.',
            'data' => $this->serialize($jobTitle),
        ], 201);
    }

    public function update(UpdateJobTitleRequest $request, JobTitle $jobTitle): JsonResponse
    {
        $validated = $request->validated();
        DB::transaction(function () use ($validated, $jobTitle): void {
            $jobTitle->update([
                'name' => $validated['name'],
                'normalized_name' => $validated['normalized_name'],
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['status'] === 'active',
            ]);
        });

        return response()->json([
            'message' => 'Job title updated successfully.',
            'data' => $this->serialize($jobTitle->refresh()),
        ]);
    }

    public function updateStatus(Request $request, JobTitle $jobTitle): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $jobTitle->update(['is_active' => $validated['status'] === 'active']);

        return response()->json([
            'message' => 'Job title status updated successfully.',
            'data' => $this->serialize($jobTitle),
        ]);
    }

    public function destroy(JobTitle $jobTitle): JsonResponse
    {
        $usersCount = $jobTitle->usersCount();

        if ($usersCount > 0) {
            return response()->json([
                'message' => "This job title is currently assigned to {$usersCount} users and cannot be deleted.",
                'users_count' => $usersCount,
                'can_deactivate' => $jobTitle->is_active,
            ], 422);
        }

        $jobTitle->delete();

        return response()->json(['message' => 'Job title deleted successfully.']);
    }

    private function validateListRequest(Request $request): array
    {
        return $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'in:10,25,50'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'inactive'])],
            'sort' => ['sometimes', Rule::in(['name', 'users_count', 'status', 'created_at', 'updated_at'])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ]);
    }

    private function paginatedJobTitles(array $filters): LengthAwarePaginator
    {
        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';
        $sortColumn = $sort === 'status' ? 'is_active' : $sort;

        $paginator = JobTitle::query()
            ->withUsersCount()
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('name', 'like', '%'.trim($search).'%'))
            ->when(($filters['status'] ?? null) === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->orderBy($sortColumn, $direction)
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();

        $paginator->getCollection()->transform(fn (JobTitle $jobTitle) => $this->serialize($jobTitle));

        return $paginator;
    }

    private function serialize(JobTitle $jobTitle): array
    {
        return [
            'id' => $jobTitle->id,
            'name' => $jobTitle->name,
            'description' => $jobTitle->description,
            'status' => $jobTitle->is_active ? 'active' : 'inactive',
            'is_active' => $jobTitle->is_active,
            'users_count' => (int) ($jobTitle->users_count ?? $jobTitle->usersCount()),
            'created_at' => $jobTitle->created_at,
            'updated_at' => $jobTitle->updated_at,
        ];
    }
}
