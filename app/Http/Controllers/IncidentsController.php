<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IncidentsController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->validatedFilters($request);

        $incidents = $this->filteredIncidents($filters)
            ->latest('created_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        if ($request->ajax()) {
            return view('incidents.partials.results', compact('incidents'));
        }

        $activeSos = Incident::where('type', 'sos')->whereIn('status', ['open', 'investigating'])->count();
        $resolvedToday = Incident::where('status', 'resolved')->whereDate('resolved_at', today())->count();
        $underReview = Incident::where('status', 'investigating')->count();

        $totalCount = Incident::count();
        $resolvedCount = Incident::where('status', 'resolved')->count();
        $resolutionRate = $totalCount > 0 ? round(($resolvedCount / $totalCount) * 100, 1) : 0;

        return view('incidents.index', [
            'incidents' => $incidents,
            'filters' => $filters,
            'activeSos' => $activeSos,
            'resolvedToday' => $resolvedToday,
            'underReview' => $underReview,
            'resolutionRate' => $resolutionRate,
        ]);
    }

    /** Export every incident matching the same filters used by the list. */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->validatedFilters($request);
        $filename = 'incidents-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($filters): void {
            $handle = fopen('php://output', 'w');

            // Helps spreadsheet applications recognise the UTF-8 CSV.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'Incident ID', 'Reporter Name', 'Email', 'Phone', 'Type',
                'Description', 'Location', 'Status', 'Reported At', 'Resolved At',
            ]);

            $this->filteredIncidents($filters)
                ->chunkById(500, function ($incidents) use ($handle): void {
                    foreach ($incidents as $incident) {
                        fputcsv($handle, [
                            $incident->id,
                            $incident->reporter?->display_name ?: $incident->reporter?->name ?: 'Anonymous',
                            $incident->reporter?->email ?? '',
                            $incident->reporter?->phone ?? '',
                            $incident->type_label,
                            $incident->description ?? '',
                            $incident->meeting?->location ?? '',
                            $incident->status_label,
                            optional($incident->created_at)->format('Y-m-d H:i:s'),
                            optional($incident->resolved_at)->format('Y-m-d H:i:s'),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{search?: string|null, start_date?: string|null, end_date?: string|null} */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);
    }

    private function filteredIncidents(array $filters): Builder
    {
        return Incident::query()
            ->with([
                'reporter:id,name,display_name,email,phone',
                'meeting:id,location',
            ])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $query->whereHas('reporter', function (Builder $reporters) use ($like): void {
                    $reporters->where(function (Builder $reporter) use ($like): void {
                        $reporter->where('name', 'like', $like)
                            ->orWhere('display_name', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhere('phone', 'like', $like);
                    });
                });
            })
            ->when(
                $filters['start_date'] ?? null,
                fn (Builder $query, string $date) => $query->where(
                    'created_at',
                    '>=',
                    CarbonImmutable::createFromFormat('Y-m-d', $date)->startOfDay(),
                ),
            )
            ->when(
                $filters['end_date'] ?? null,
                fn (Builder $query, string $date) => $query->where(
                    'created_at',
                    '<',
                    CarbonImmutable::createFromFormat('Y-m-d', $date)->addDay()->startOfDay(),
                ),
            );
    }
}
