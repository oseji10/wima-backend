<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use App\Models\User;
use App\Models\Role;
use App\Models\StaffProfile;
use App\Models\PerformanceReview;
use App\Models\PerformanceGoal;
use App\Models\LeaveType;
use App\Models\LeaveAllocation;
use App\Models\LeaveRequest;
use App\Models\ComplianceItem;
use App\Models\StateCoordinators;
use App\Models\CommunityLead;

/**
 * Staff records ARE users. This controller layers employment data (StaffProfile),
 * performance, leave and compliance on top of the existing users + roles tables.
 * Everywhere a `staff_id` appears it holds a users.id.
 */
class HrController extends Controller
{
    /* =======================================================================
     |  Role scoping  (1 = Admin, 3 = National -> all; 4 = State; 5 = Community)
     |  Staff are scoped by users.state / users.lga (users have no hub).
     * ===================================================================== */

    private function scopedUserIds(): ?array
    {
        $user = Auth::user();

        if (in_array((int) $user->role, [1, 3], true)) {
            return null; // no restriction
        }
        if ((int) $user->role === 4) {
            $stateId = optional(StateCoordinators::where('userId', $user->id)->first())->stateId;
            return User::where('state', $stateId)->pluck('id')->all();
        }
        if ((int) $user->role === 5) {
            $lga = optional(CommunityLead::where('userId', $user->id)->first())->lga;
            return User::where('lga', $lga)->pluck('id')->all();
        }
        return [];
    }

    private function applyUserScope($query, string $column = 'id')
    {
        $ids = $this->scopedUserIds();
        if (is_array($ids)) {
            $query->whereIn($column, $ids);
        }
        return $query;
    }

    private function isManager(): bool
    {
        return in_array((int) Auth::user()->role, [1, 3], true);
    }

    private function assertUserAccess($userId): void
    {
        $ids = $this->scopedUserIds();
        if (is_array($ids) && !in_array((int) $userId, array_map('intval', $ids), true)) {
            abort(403, 'You do not have access to this staff member.');
        }
    }

    /** Flatten a user (+ optional profile) into the shape the UI expects. */
    private function presentStaff(User $u, ?StaffProfile $profile = null): array
    {
        return [
            'id'                => $u->id,
            'full_name'         => trim("{$u->firstName} {$u->lastName}"),
            'firstName'         => $u->firstName,
            'lastName'          => $u->lastName,
            'otherNames'        => $u->otherNames,
            'email'             => $u->email,
            'phoneNumber'       => $u->phoneNumber,
            'role'              => $u->role,
            'role_name'         => optional($u->user_role)->roleName,
            'state'             => $u->state,
            'state_name'        => optional($u->state_info)->stateName ?? optional($u->state_info)->name,
            'lga'               => $u->lga,
            'lga_name'          => optional($u->lga_info)->lgaName ?? optional($u->lga_info)->name,
            'account_status'    => $u->status,
            'employment_status' => $profile->employment_status ?? null,
            'department'        => $profile->department ?? null,
            'job_title'         => $profile->job_title ?? null,
            'profile'           => $profile,
        ];
    }

    /* =======================================================================
     |  Dashboard
     * ===================================================================== */

    public function dashboard()
    {
        $userIds = $this->applyUserScope(User::query())->pluck('id');

        $profiles = StaffProfile::whereIn('user_id', $userIds)->get();
        $byStatus = $profiles->groupBy(fn ($p) => $p->employment_status ?: 'active')->map->count();
        $byDept = $profiles->groupBy(fn ($p) => $p->department ?: 'Unassigned')->map->count();

        $pendingLeave = LeaveRequest::whereIn('staff_id', $userIds)->where('status', 'pending')->count();
        $upcomingLeave = LeaveRequest::whereIn('staff_id', $userIds)
            ->where('status', 'approved')
            ->whereBetween('start_date', [Carbon::today(), Carbon::today()->addDays(30)])->count();

        $compliance = ComplianceItem::whereIn('staff_id', $userIds)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', Carbon::today()->addDays(30))->get();
        $expiringCompliance = $compliance->filter(fn ($c) => $c->computed_status !== 'valid')->count();

        $openReviews = PerformanceReview::whereIn('staff_id', $userIds)
            ->whereIn('status', ['draft', 'in_progress'])->count();

        return response()->json([
            'headcount'           => $userIds->count(),
            'active'              => $byStatus['active'] ?? 0,
            'on_leave'            => $byStatus['on_leave'] ?? 0,
            'by_status'           => $byStatus,
            'by_department'       => $byDept,
            'pending_leave'       => $pendingLeave,
            'upcoming_leave'      => $upcomingLeave,
            'expiring_compliance' => $expiringCompliance,
            'open_reviews'        => $openReviews,
        ]);
    }

    /* =======================================================================
     |  Roles  (read from the existing roles table)
     * ===================================================================== */

    public function roleIndex()
    {
        return response()->json(Role::orderBy('roleName')->get());
    }

    /* =======================================================================
     |  Staff records (= users + employment profile)
     * ===================================================================== */

    public function staffIndex(Request $request)
    {
        $query = $this->applyUserScope(User::with(['user_role', 'state_info', 'lga_info']));

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('firstName', 'like', "%{$search}%")
                  ->orWhere('lastName', 'like', "%{$search}%")
                  ->orWhere('otherNames', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filters that live on the profile
        $profileFilters = array_filter([
            'department'        => $request->query('department'),
            'employment_status' => $request->query('status'),
            'project_id'        => $request->query('project_id'),
        ], fn ($v) => $v !== null && $v !== '');
        if ($profileFilters) {
            $pf = StaffProfile::query();
            foreach ($profileFilters as $col => $val) {
                $pf->where($col, $val);
            }
            $query->whereIn('id', $pf->pluck('user_id'));
        }

        $paginator = $query->orderBy('firstName')->paginate($request->query('per_page', 25));

        $ids = collect($paginator->items())->pluck('id');
        $profiles = StaffProfile::whereIn('user_id', $ids)->get()->keyBy('user_id');
        $paginator->getCollection()->transform(fn ($u) => $this->presentStaff($u, $profiles->get($u->id)));

        return response()->json($paginator);
    }

    public function staffShow($id)
    {
        $this->assertUserAccess($id);
        $user = User::with(['user_role', 'state_info', 'lga_info'])->findOrFail($id);
        $profile = StaffProfile::where('user_id', $id)->first();

        $data = $this->presentStaff($user, $profile);

        if ($profile && $profile->manager_id) {
            $mgr = User::find($profile->manager_id);
            $data['manager_name'] = $mgr ? trim("{$mgr->firstName} {$mgr->lastName}") : null;
        }

        $data['compliance_items'] = ComplianceItem::where('staff_id', $id)->orderBy('expires_at')->get();
        $data['leave_requests'] = LeaveRequest::where('staff_id', $id)
            ->with('leaveType:id,name')->orderByDesc('start_date')->limit(10)->get();
        $data['reviews'] = PerformanceReview::where('staff_id', $id)
            ->orderByDesc('period_end')->limit(5)->get();

        return response()->json($data);
    }

    /**
     * Upsert the employment profile for a user, plus a safe subset of user fields.
     * Does NOT create user accounts (those come from your existing onboarding) and
     * never touches email or password.
     */
    public function staffUpsert(Request $request, $id)
    {
        $this->assertUserAccess($id);
        abort_unless($this->isManager() || (int) Auth::user()->role === 4, 403, 'You cannot edit staff records.');

        $user = User::findOrFail($id);

        $data = $request->validate([
            // safe user fields
            'firstName'         => 'sometimes|string|max:255',
            'lastName'          => 'sometimes|string|max:255',
            'otherNames'        => 'nullable|string|max:255',
            'phoneNumber'       => 'nullable|string|max:40',
            'role'              => 'nullable|integer',
            'state'             => 'nullable|integer',
            'lga'               => 'nullable|integer',
            'account_status'    => 'nullable|string|max:40',
            // profile fields
            'staff_number'      => "nullable|string|max:40|unique:hr_staff_profiles,staff_number,{$id},user_id",
            'job_title'         => 'nullable|string|max:255',
            'department'        => 'nullable|string|max:255',
            'employment_type'   => 'nullable|in:full_time,part_time,contract,volunteer,intern',
            'hub'               => 'nullable|integer',
            'project_id'        => 'nullable|integer',
            'manager_id'        => 'nullable|integer',
            'hire_date'         => 'nullable|date',
            'end_date'          => 'nullable|date',
            'employment_status' => 'nullable|in:active,on_leave,suspended,exited',
            'base_salary'       => 'nullable|numeric|min:0',
            'notes'             => 'nullable|string',
        ]);

        DB::transaction(function () use ($user, $data, $id) {
            $userFields = collect($data)
                ->only(['firstName', 'lastName', 'otherNames', 'phoneNumber', 'role', 'state', 'lga'])
                ->toArray();
            if (array_key_exists('account_status', $data)) {
                $userFields['status'] = $data['account_status'];
            }
            if ($userFields) {
                $user->update($userFields);
            }

            $profileFields = collect($data)->only([
                'staff_number', 'job_title', 'department', 'employment_type', 'hub',
                'project_id', 'manager_id', 'hire_date', 'end_date', 'employment_status',
                'base_salary', 'notes',
            ])->toArray();

            if ($profileFields) {
                StaffProfile::updateOrCreate(
                    ['user_id' => $id],
                    array_merge($profileFields, ['created_by' => Auth::id()])
                );
            }
        });

        return response()->json($this->staffShowData($id));
    }

    private function staffShowData($id): array
    {
        $user = User::with(['user_role', 'state_info', 'lga_info'])->findOrFail($id);
        return $this->presentStaff($user, StaffProfile::where('user_id', $id)->first());
    }

    /* =======================================================================
     |  Performance reviews (linked to M&E KPIs).  staff_id = users.id
     * ===================================================================== */

    public function reviewIndex(Request $request)
    {
        $userIds = $this->applyUserScope(User::query())->pluck('id');
        $query = PerformanceReview::with('staff:id,firstName,lastName')
            ->whereIn('staff_id', $userIds);

        if ($staffId = $request->query('staff_id')) {
            $query->where('staff_id', $staffId);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json($query->orderByDesc('period_end')->orderByDesc('id')->get());
    }

    public function reviewShow($id)
    {
        $review = PerformanceReview::with([
            'staff:id,firstName,lastName',
            'reviewer:id,firstName,lastName',
            'goals.indicator:id,name,code,unit,target,direction',
        ])->findOrFail($id);
        $this->assertUserAccess($review->staff_id);
        return response()->json($review);
    }

    public function reviewStore(Request $request)
    {
        $data = $this->validateReview($request, true);
        $this->assertUserAccess($data['staff_id']);

        $review = DB::transaction(function () use ($data) {
            $review = PerformanceReview::create([
                'staff_id'     => $data['staff_id'],
                'reviewer_id'  => $data['reviewer_id'] ?? Auth::id(),
                'period_label' => $data['period_label'] ?? null,
                'period_start' => $data['period_start'] ?? null,
                'period_end'   => $data['period_end'] ?? null,
                'status'       => $data['status'] ?? 'draft',
                'summary'      => $data['summary'] ?? null,
                'strengths'    => $data['strengths'] ?? null,
                'improvements' => $data['improvements'] ?? null,
                'created_by'   => Auth::id(),
            ]);
            $this->writeGoals($review, $data['goals'] ?? []);
            $this->syncOverall($review);
            return $review;
        });

        return response()->json($review->load('goals.indicator:id,name,code,unit'), 201);
    }

    public function reviewUpdate(Request $request, $id)
    {
        $review = PerformanceReview::findOrFail($id);
        $this->assertUserAccess($review->staff_id);
        $data = $this->validateReview($request, false);

        DB::transaction(function () use ($review, $data) {
            $review->update(collect($data)->except('goals', 'staff_id')->toArray());
            if (isset($data['goals'])) {
                $review->goals()->delete();
                $this->writeGoals($review, $data['goals']);
            }
            $this->syncOverall($review);
        });

        return response()->json($review->fresh('goals.indicator:id,name,code,unit'));
    }

    public function reviewDestroy($id)
    {
        $review = PerformanceReview::findOrFail($id);
        $this->assertUserAccess($review->staff_id);
        $review->goals()->delete();
        $review->delete();
        return response()->json(['message' => 'Review removed']);
    }

    private function validateReview(Request $request, bool $creating): array
    {
        return $request->validate([
            'staff_id'      => ($creating ? 'required' : 'sometimes') . '|integer',
            'reviewer_id'   => 'nullable|integer',
            'period_label'  => 'nullable|string|max:255',
            'period_start'  => 'nullable|date',
            'period_end'    => 'nullable|date',
            'status'        => 'nullable|in:draft,in_progress,completed,acknowledged',
            'summary'       => 'nullable|string',
            'strengths'     => 'nullable|string',
            'improvements'  => 'nullable|string',
            'goals'                => 'nullable|array',
            'goals.*.title'        => 'required_with:goals|string|max:255',
            'goals.*.description'  => 'nullable|string',
            'goals.*.indicator_id' => 'nullable|integer',
            'goals.*.target_value' => 'nullable|numeric',
            'goals.*.actual_value' => 'nullable|numeric',
            'goals.*.weight'       => 'nullable|numeric|min:0',
            'goals.*.score'        => 'nullable|numeric',
            'goals.*.status'       => 'nullable|string|max:20',
        ]);
    }

    private function writeGoals(PerformanceReview $review, array $goals): void
    {
        foreach ($goals as $g) {
            $review->goals()->create([
                'title'        => $g['title'],
                'description'  => $g['description'] ?? null,
                'indicator_id' => $g['indicator_id'] ?? null,
                'target_value' => $g['target_value'] ?? null,
                'actual_value' => $g['actual_value'] ?? null,
                'weight'       => $g['weight'] ?? 1,
                'score'        => $g['score'] ?? null,
                'status'       => $g['status'] ?? 'in_progress',
            ]);
        }
    }

    private function syncOverall(PerformanceReview $review): void
    {
        $review->load('goals');
        $overall = $review->computed_overall;
        if ($overall !== null) {
            $review->overall_score = $overall;
            $review->save();
        }
    }

    /* =======================================================================
     |  Leave management.  staff_id = users.id
     * ===================================================================== */

    public function leaveTypeIndex()
    {
        return response()->json(LeaveType::orderBy('name')->get());
    }

    public function leaveTypeStore(Request $request)
    {
        abort_unless($this->isManager(), 403, 'Only administrators can manage leave types.');
        $data = $request->validate([
            'name'                  => 'required|string|max:255',
            'default_days_per_year' => 'nullable|integer|min:0',
            'paid'                  => 'nullable|boolean',
            'color'                 => 'nullable|string|max:20',
            'active'                => 'nullable|boolean',
        ]);
        return response()->json(LeaveType::create($data), 201);
    }

    public function leaveTypeUpdate(Request $request, $id)
    {
        abort_unless($this->isManager(), 403, 'Only administrators can manage leave types.');
        $type = LeaveType::findOrFail($id);
        $type->update($request->validate([
            'name'                  => 'sometimes|string|max:255',
            'default_days_per_year' => 'nullable|integer|min:0',
            'paid'                  => 'nullable|boolean',
            'color'                 => 'nullable|string|max:20',
            'active'                => 'nullable|boolean',
        ]));
        return response()->json($type->fresh());
    }

    public function leaveIndex(Request $request)
    {
        $userIds = $this->applyUserScope(User::query())->pluck('id');
        $query = LeaveRequest::with('staff:id,firstName,lastName', 'leaveType:id,name,paid,color')
            ->whereIn('staff_id', $userIds);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($staffId = $request->query('staff_id')) {
            $query->where('staff_id', $staffId);
        }

        return response()->json(
            $query->orderByDesc('start_date')->paginate($request->query('per_page', 25))
        );
    }

    public function leaveStore(Request $request)
    {
        $data = $request->validate([
            'staff_id'      => 'required|integer',
            'leave_type_id' => 'required|integer',
            'start_date'    => 'required|date',
            'end_date'      => 'required|date|after_or_equal:start_date',
            'reason'        => 'nullable|string',
        ]);
        $this->assertUserAccess($data['staff_id']);

        $data['days'] = $this->workingDays($data['start_date'], $data['end_date']);
        $data['status'] = 'pending';
        $data['created_by'] = Auth::id();

        $leave = LeaveRequest::create($data);
        return response()->json($leave->load('leaveType:id,name'), 201);
    }

    public function leaveDecide(Request $request, $id)
    {
        $leave = LeaveRequest::findOrFail($id);
        $this->assertUserAccess($leave->staff_id);
        abort_unless($this->isManager() || in_array((int) Auth::user()->role, [4, 5], true), 403);

        $data = $request->validate([
            'status'        => 'required|in:approved,rejected,cancelled',
            'decision_note' => 'nullable|string',
        ]);

        $leave->update([
            'status'        => $data['status'],
            'decision_note' => $data['decision_note'] ?? null,
            'approver_id'   => Auth::id(),
            'decided_at'    => now(),
        ]);

        // If the approved leave covers today, reflect it on the employment profile
        if ($data['status'] === 'approved'
            && Carbon::parse($leave->start_date)->lte(Carbon::today())
            && Carbon::parse($leave->end_date)->gte(Carbon::today())) {
            StaffProfile::where('user_id', $leave->staff_id)->update(['employment_status' => 'on_leave']);
        }

        return response()->json($leave->fresh('leaveType:id,name'));
    }

    public function leaveBalance(Request $request, $userId)
    {
        $this->assertUserAccess($userId);
        $year = (int) $request->query('year', Carbon::today()->year);

        $types = LeaveType::where('active', true)->get();
        $allocations = LeaveAllocation::where('staff_id', $userId)->where('year', $year)
            ->pluck('days_allocated', 'leave_type_id');

        $taken = LeaveRequest::where('staff_id', $userId)
            ->where('status', 'approved')
            ->whereYear('start_date', $year)
            ->get()->groupBy('leave_type_id')->map(fn ($g) => $g->sum('days'));

        $balances = $types->map(function ($t) use ($allocations, $taken) {
            $allocated = $allocations[$t->id] ?? $t->default_days_per_year;
            $used = $taken[$t->id] ?? 0;
            return [
                'leave_type_id' => $t->id,
                'name'          => $t->name,
                'allocated'     => round($allocated, 2),
                'used'          => round($used, 2),
                'remaining'     => round($allocated - $used, 2),
            ];
        });

        return response()->json(['year' => $year, 'balances' => $balances]);
    }

    private function workingDays($start, $end): float
    {
        $start = Carbon::parse($start);
        $end = Carbon::parse($end);
        $days = 0;
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            if (!$d->isWeekend()) {
                $days++;
            }
        }
        return $days;
    }

    /* =======================================================================
     |  Compliance management.  staff_id = users.id
     * ===================================================================== */

    public function complianceIndex(Request $request)
    {
        $userIds = $this->applyUserScope(User::query())->pluck('id');
        $query = ComplianceItem::with('staff:id,firstName,lastName')
            ->whereIn('staff_id', $userIds);

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($staffId = $request->query('staff_id')) {
            $query->where('staff_id', $staffId);
        }

        $items = $query->orderBy('expires_at')->get();

        if ($status = $request->query('status')) {
            $items = $items->filter(fn ($i) => $i->computed_status === $status)->values();
        }

        return response()->json($items);
    }

    public function complianceStore(Request $request)
    {
        $data = $request->validate([
            'staff_id'     => 'required|integer',
            'type'         => 'required|in:certification,contract,training,document,background_check,medical',
            'title'        => 'required|string|max:255',
            'issued_at'    => 'nullable|date',
            'expires_at'   => 'nullable|date',
            'authority'    => 'nullable|string|max:255',
            'document_ref' => 'nullable|string|max:255',
            'notes'        => 'nullable|string',
        ]);
        $this->assertUserAccess($data['staff_id']);
        $data['recorded_by'] = Auth::id();
        return response()->json(ComplianceItem::create($data), 201);
    }

    public function complianceUpdate(Request $request, $id)
    {
        $item = ComplianceItem::findOrFail($id);
        $this->assertUserAccess($item->staff_id);
        $item->update($request->validate([
            'type'         => 'sometimes|in:certification,contract,training,document,background_check,medical',
            'title'        => 'sometimes|string|max:255',
            'issued_at'    => 'nullable|date',
            'expires_at'   => 'nullable|date',
            'authority'    => 'nullable|string|max:255',
            'document_ref' => 'nullable|string|max:255',
            'notes'        => 'nullable|string',
        ]));
        return response()->json($item->fresh());
    }

    public function complianceDestroy($id)
    {
        $item = ComplianceItem::findOrFail($id);
        $this->assertUserAccess($item->staff_id);
        $item->delete();
        return response()->json(['message' => 'Compliance item removed']);
    }
}