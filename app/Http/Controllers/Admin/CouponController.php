<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCouponRequest;
use App\Http\Requests\Admin\UpdateCouponRequest;
use App\Models\Coupon;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CouponController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $this->validateListRequest($request);

        return view('coupons.index', [
            'coupons' => $this->paginatedCoupons($validated),
            'filters' => $validated,
            'plans' => SubscriptionPlan::orderBy('sort_order')->get(['id', 'name', 'slug']),
            'statistics' => [
                'total' => Coupon::count(),
                'active' => Coupon::active()->count(),
                'inactive' => Coupon::where('is_active', false)->count(),
                'redeemed' => (int) Coupon::sum('times_redeemed'),
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $validated = $this->validateListRequest($request);

        return response()->json($this->paginatedCoupons($validated));
    }

    public function show(Coupon $coupon): JsonResponse
    {
        return response()->json(['data' => $this->serialize($coupon)]);
    }

    public function store(StoreCouponRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $coupon = Coupon::create([
            'code' => $validated['code'],
            'description' => $validated['description'] ?? null,
            'discount_type' => $validated['discount_type'],
            'discount_value' => $validated['discount_value'],
            'plan_id' => $validated['plan_id'] ?? null,
            'billing_cycle' => $validated['billing_cycle'] ?? null,
            'max_redemptions' => $validated['max_redemptions'] ?? null,
            'max_redemptions_per_user' => $validated['max_redemptions_per_user'],
            'starts_at' => $validated['starts_at'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'is_active' => $validated['status'] === 'active',
            'created_by' => $request->user('admin')?->id,
        ]);

        return response()->json([
            'message' => 'Coupon created successfully.',
            'data' => $this->serialize($coupon),
        ], 201);
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon): JsonResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $coupon): void {
            $coupon->update([
                'code' => $validated['code'],
                'description' => $validated['description'] ?? null,
                'discount_type' => $validated['discount_type'],
                'discount_value' => $validated['discount_value'],
                'plan_id' => $validated['plan_id'] ?? null,
                'billing_cycle' => $validated['billing_cycle'] ?? null,
                'max_redemptions' => $validated['max_redemptions'] ?? null,
                'max_redemptions_per_user' => $validated['max_redemptions_per_user'],
                'starts_at' => $validated['starts_at'] ?? null,
                'expires_at' => $validated['expires_at'] ?? null,
                'is_active' => $validated['status'] === 'active',
            ]);
        });

        return response()->json([
            'message' => 'Coupon updated successfully.',
            'data' => $this->serialize($coupon->refresh()),
        ]);
    }

    public function updateStatus(Request $request, Coupon $coupon): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $coupon->update(['is_active' => $validated['status'] === 'active']);

        return response()->json([
            'message' => 'Coupon status updated successfully.',
            'data' => $this->serialize($coupon),
        ]);
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        if ($coupon->times_redeemed > 0) {
            return response()->json([
                'message' => "This coupon has already been redeemed {$coupon->times_redeemed} time(s) and cannot be deleted. Disable it instead.",
                'times_redeemed' => $coupon->times_redeemed,
                'can_deactivate' => $coupon->is_active,
            ], 422);
        }

        $coupon->delete();

        return response()->json(['message' => 'Coupon deleted successfully.']);
    }

    private function validateListRequest(Request $request): array
    {
        return $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'in:10,25,50'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'inactive'])],
            'sort' => ['sometimes', Rule::in(['code', 'discount_value', 'times_redeemed', 'status', 'expires_at', 'created_at'])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ]);
    }

    private function paginatedCoupons(array $filters): LengthAwarePaginator
    {
        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';
        $sortColumn = $sort === 'status' ? 'is_active' : $sort;

        $paginator = Coupon::query()
            ->with('plan:id,name,slug')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('code', 'like', '%'.trim(strtoupper($search)).'%'))
            ->when(($filters['status'] ?? null) === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->orderBy($sortColumn, $direction)
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();

        $paginator->getCollection()->transform(fn (Coupon $coupon) => $this->serialize($coupon));

        return $paginator;
    }

    private function serialize(Coupon $coupon): array
    {
        return [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'description' => $coupon->description,
            'discount_type' => $coupon->discount_type,
            'discount_value' => (float) $coupon->discount_value,
            'plan_id' => $coupon->plan_id,
            'plan_name' => $coupon->plan?->name,
            'billing_cycle' => $coupon->billing_cycle,
            'max_redemptions' => $coupon->max_redemptions,
            'max_redemptions_per_user' => $coupon->max_redemptions_per_user,
            'times_redeemed' => $coupon->times_redeemed,
            'starts_at' => $coupon->starts_at,
            'expires_at' => $coupon->expires_at,
            'status' => $coupon->is_active ? 'active' : 'inactive',
            'is_active' => $coupon->is_active,
            'created_at' => $coupon->created_at,
            'updated_at' => $coupon->updated_at,
        ];
    }
}
