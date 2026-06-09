<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;

use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\MeForm;
use App\Models\MeFormField;
use App\Models\MeSubmission;
use App\Models\Hubs;
use App\Models\StateCoordinators;
use App\Models\CommunityLead;

class MonitoringController extends Controller
{
    /* =======================================================================
     |  Role scoping  (1 = Admin, 3 = National -> all; 4 = State; 5 = Community)
     * ===================================================================== */

    private function scopedHubIds(): ?array
    {
        $user = Auth::user();
        if (in_array((int) $user->role, [1, 3], true)) {
            return null; // no restriction
        }
        if ((int) $user->role === 4) {
            $stateId = optional(StateCoordinators::where('userId', $user->id)->first())->stateId;
            return Hubs::where('state', $stateId)->pluck('hubId')->all();
        }
        if ((int) $user->role === 5) {
            $lga = optional(CommunityLead::where('userId', $user->id)->first())->lga;
            return Hubs::where('lga', $lga)->pluck('hubId')->all();
        }
        return [];
    }

    private function applyHubScope($query, string $column = 'hub')
    {
        $ids = $this->scopedHubIds();
        if (is_array($ids)) {
            $query->whereIn($column, $ids);
        }
        return $query;
    }

    private function isManager(): bool
    {
        return in_array((int) Auth::user()->role, [1, 3], true);
    }

    private function periodWindow(Request $request): array
    {
        if ($request->query('from') || $request->query('to')) {
            $from = $request->query('from') ?: '1970-01-01';
            $to   = $request->query('to') ?: Carbon::today()->toDateString();
        } else {
            $from = Carbon::today()->startOfMonth()->toDateString();
            $to   = Carbon::today()->endOfMonth()->toDateString();
        }
        return [$from, $to];
    }

    /* =======================================================================
     |  KPI configuration
     * ===================================================================== */

    public function indicatorIndex(Request $request)
    {
        $query = Indicator::with('form:id,name,code');
        if ($request->boolean('donor_only')) {
            $query->where('is_donor_visible', true);
        }
        if ($projectId = $request->query('project_id')) {
            $query->where('project_id', $projectId);
        } elseif ($program = $request->query('program')) {
            $query->where('program', $program);
        }
        return response()->json(
            $query->orderBy('sort_order')->orderBy('name')->get()
        );
    }

    public function indicatorStore(Request $request)
    {
        abort_unless($this->isManager(), 403, 'Only administrators can configure KPIs.');
        $data = $this->validateIndicator($request);
        $data['code'] = $data['code'] ?? Str::slug($data['name'], '_');
        return response()->json(Indicator::create($data), 201);
    }

    public function indicatorUpdate(Request $request, $id)
    {
        abort_unless($this->isManager(), 403, 'Only administrators can configure KPIs.');
        $indicator = Indicator::findOrFail($id);
        $indicator->update($this->validateIndicator($request, $indicator->id));
        return response()->json($indicator->fresh('form:id,name,code'));
    }

    public function indicatorDestroy($id)
    {
        abort_unless($this->isManager(), 403, 'Only administrators can configure KPIs.');
        Indicator::findOrFail($id)->delete();
        return response()->json(['message' => 'Indicator removed']);
    }

    private function validateIndicator(Request $request, $ignoreId = null): array
    {
        return $request->validate([
            'name'              => 'required|string|max:255',
            'code'              => "nullable|string|max:60|unique:me_indicators,code,{$ignoreId}",
            'description'       => 'nullable|string',
            'unit'              => 'nullable|string|max:30',
            'level'             => 'nullable|string|max:30',
            'project_id'        => 'nullable|integer',
            'program'           => 'nullable|string|max:255',
            'source_type'       => 'required|in:form,manual,computed',
            'form_id'           => 'nullable|integer',
            'field_key'         => 'nullable|string|max:60',
            'aggregation'       => 'nullable|in:sum,average,count,latest,ratio',
            'numerator_field'   => 'nullable|string|max:60',
            'denominator_field' => 'nullable|string|max:60',
            'formula'           => 'nullable|string|max:2000',
            'baseline'          => 'nullable|numeric',
            'target'            => 'nullable|numeric',
            'direction'         => 'nullable|in:increase,decrease',
            'frequency'         => 'nullable|in:monthly,quarterly,annual',
            'is_donor_visible'  => 'nullable|boolean',
            'active'            => 'nullable|boolean',
            'sort_order'        => 'nullable|integer',
        ]);
    }

    /* =======================================================================
     |  Field data collection forms
     * ===================================================================== */

    public function formIndex()
    {
        return response()->json(MeForm::withCount('fields')->orderBy('name')->get());
    }

    public function formShow($id)
    {
        return response()->json(MeForm::with('fields')->findOrFail($id));
    }

    public function formStore(Request $request)
    {
        abort_unless($this->isManager(), 403, 'Only administrators can manage forms.');
        $data = $request->validate([
            'name'                  => 'required|string|max:255',
            'code'                  => 'nullable|string|max:60|unique:me_forms,code',
            'description'           => 'nullable|string',
            'active'                => 'nullable|boolean',
            'fields'                => 'required|array|min:1',
            'fields.*.label'        => 'required|string|max:255',
            'fields.*.key'          => 'nullable|string|max:60',
            'fields.*.type'         => 'required|in:text,textarea,number,select,boolean,date',
            'fields.*.options'      => 'nullable|array',
            'fields.*.required'     => 'nullable|boolean',
            'fields.*.unit'         => 'nullable|string|max:30',
        ]);

        $form = MeForm::create([
            'name'        => $data['name'],
            'code'        => $data['code'] ?? Str::slug($data['name'], '_'),
            'description' => $data['description'] ?? null,
            'active'      => $data['active'] ?? true,
            'created_by'  => Auth::id(),
        ]);

        foreach ($data['fields'] as $i => $f) {
            $form->fields()->create([
                'key'        => $f['key'] ?? Str::slug($f['label'], '_'),
                'label'      => $f['label'],
                'type'       => $f['type'],
                'options'    => $f['options'] ?? null,
                'required'   => $f['required'] ?? false,
                'unit'       => $f['unit'] ?? null,
                'sort_order' => $i,
            ]);
        }

        return response()->json($form->load('fields'), 201);
    }

    public function formUpdate(Request $request, $id)
    {
        abort_unless($this->isManager(), 403, 'Only administrators can manage forms.');
        $form = MeForm::findOrFail($id);
        $data = $request->validate([
            'name'                  => 'sometimes|string|max:255',
            'description'           => 'nullable|string',
            'active'                => 'nullable|boolean',
            'fields'                => 'sometimes|array|min:1',
            'fields.*.label'        => 'required_with:fields|string|max:255',
            'fields.*.key'          => 'nullable|string|max:60',
            'fields.*.type'         => 'required_with:fields|in:text,textarea,number,select,boolean,date',
            'fields.*.options'      => 'nullable|array',
            'fields.*.required'     => 'nullable|boolean',
            'fields.*.unit'         => 'nullable|string|max:30',
        ]);

        $form->update(collect($data)->only(['name', 'description', 'active'])->toArray());

        if (isset($data['fields'])) {
            $form->increment('version');
            $form->fields()->delete();
            foreach ($data['fields'] as $i => $f) {
                $form->fields()->create([
                    'key'        => $f['key'] ?? Str::slug($f['label'], '_'),
                    'label'      => $f['label'],
                    'type'       => $f['type'],
                    'options'    => $f['options'] ?? null,
                    'required'   => $f['required'] ?? false,
                    'unit'       => $f['unit'] ?? null,
                    'sort_order' => $i,
                ]);
            }
        }

        return response()->json($form->fresh('fields'));
    }

    /* =======================================================================
     |  Submissions (the field data)
     * ===================================================================== */

    public function submissionIndex(Request $request)
    {
        $query = $this->applyHubScope(
            MeSubmission::with('form:id,name,code', 'submitter:id,name', 'hub.lgas')
        );
        if ($formId = $request->query('form_id')) {
            $query->where('form_id', $formId);
        }
        if ($hub = $request->query('hub')) {
            $query->where('hub', $hub);
        }
        return response()->json(
            $query->orderByDesc('submission_date')->orderByDesc('id')
                ->paginate($request->query('per_page', 25))
        );
    }

    public function submissionStore(Request $request)
    {
        $base = $request->validate([
            'form_id'         => 'required|integer|exists:me_forms,id',
            'hub'             => 'nullable|integer',
            'submission_date' => 'required|date',
            'location'        => 'nullable|string|max:255',
            'notes'           => 'nullable|string',
            'data'            => 'required|array',
        ]);

        // Hub-scope on write
        $ids = $this->scopedHubIds();
        if (is_array($ids) && $base['hub'] && !in_array((int) $base['hub'], array_map('intval', $ids), true)) {
            abort(403, 'You cannot submit data for that hub.');
        }

        // Validate required fields against the form definition
        $form = MeForm::with('fields')->findOrFail($base['form_id']);
        foreach ($form->fields as $field) {
            if ($field->required && !filled($base['data'][$field->key] ?? null)) {
                return response()->json([
                    'message' => "Field '{$field->label}' is required.",
                ], 422);
            }
        }

        $base['submitted_by'] = Auth::id();
        $submission = MeSubmission::create($base);

        return response()->json($submission->load('form:id,name,code', 'submitter:id,name'), 201);
    }

    public function submissionShow($id)
    {
        $submission = MeSubmission::with('form.fields', 'submitter:id,name', 'hub.lgas')->findOrFail($id);
        $ids = $this->scopedHubIds();
        if (is_array($ids) && $submission->hub && !in_array((int) $submission->hub, array_map('intval', $ids), true)) {
            abort(403);
        }
        return response()->json($submission);
    }

    /* =======================================================================
     |  Automated KPI computation engine
     * ===================================================================== */

    /** Compute a form-sourced indicator from submissions in window + hub scope. */
    private function computeFromForm(Indicator $ind, string $from, string $to, ?array $hubIds): ?float
    {
        if (!$ind->form_id) {
            return null;
        }
        $q = MeSubmission::where('form_id', $ind->form_id)
            ->whereBetween('submission_date', [$from, $to]);
        if (is_array($hubIds)) {
            $q->whereIn('hub', $hubIds);
        }
        $rows = $q->get();
        if ($rows->isEmpty() && $ind->aggregation !== 'count') {
            return $rows->count() ? null : 0.0;
        }

        $vals = fn ($key) => $rows
            ->map(fn ($r) => is_numeric($r->data[$key] ?? null) ? (float) $r->data[$key] : null)
            ->filter(fn ($v) => $v !== null);

        switch ($ind->aggregation) {
            case 'count':
                return (float) $rows->count();
            case 'average':
                $v = $vals($ind->field_key);
                return $v->isEmpty() ? 0.0 : round($v->avg(), 4);
            case 'latest':
                $last = $rows->sortByDesc('submission_date')->first();
                $x = $last->data[$ind->field_key] ?? null;
                return is_numeric($x) ? (float) $x : 0.0;
            case 'ratio':
                $num = $vals($ind->numerator_field)->sum();
                $den = $vals($ind->denominator_field)->sum();
                return $den == 0 ? 0.0 : round(($num / $den) * 100, 4);
            case 'sum':
            default:
                return round($vals($ind->field_key)->sum(), 4);
        }
    }

    /** Read the latest manual value within the window (optionally summed across hubs). */
    private function computeFromManual(Indicator $ind, string $from, string $to, ?array $hubIds): ?float
    {
        $q = IndicatorValue::where('indicator_id', $ind->id)
            ->whereBetween('period_date', [$from, $to]);
        if (is_array($hubIds)) {
            $q->whereIn('hub', $hubIds);
        }
        $rows = $q->get();
        if ($rows->isEmpty()) {
            return null;
        }
        // Sum across hubs, take the latest period per hub
        return round(
            $rows->groupBy('hub')->map(fn ($g) => $g->sortByDesc('period_date')->first()->value)->sum(),
            4
        );
    }

    /**
     * Compute every indicator for the window, resolving "computed" formulas last.
     * Returns [code => value].
     */
    private function computeAll($indicators, string $from, string $to, ?array $hubIds): array
    {
        $resolved = [];

        // Pass 1: form + manual
        foreach ($indicators as $ind) {
            if ($ind->source_type === 'form') {
                $resolved[$ind->code] = $this->computeFromForm($ind, $from, $to, $hubIds);
            } elseif ($ind->source_type === 'manual') {
                $resolved[$ind->code] = $this->computeFromManual($ind, $from, $to, $hubIds);
            }
        }

        // Pass 2: computed formulas referencing other codes
        foreach ($indicators as $ind) {
            if ($ind->source_type === 'computed') {
                $resolved[$ind->code] = $this->evaluateFormula($ind->formula, $resolved);
            }
        }

        return $resolved;
    }

    /**
     * Safe arithmetic evaluator. Supports + - * / ( ) and indicator codes.
     * No eval(): tokenizes then runs shunting-yard to RPN and evaluates.
     */
    private function evaluateFormula(?string $formula, array $vars): ?float
    {
        if (!$formula) {
            return null;
        }
        // Substitute codes with their numeric values (longest first to avoid partial hits)
        $codes = array_keys($vars);
        usort($codes, fn ($a, $b) => strlen($b) <=> strlen($a));
        $expr = $formula;
        foreach ($codes as $code) {
            $val = $vars[$code];
            $expr = preg_replace('/\b' . preg_quote($code, '/') . '\b/', is_null($val) ? '0' : (string) $val, $expr);
        }

        // Tokenize: numbers, operators, parens only
        if (!preg_match('/^[0-9+\-*\/().\s]*$/', $expr)) {
            return null; // unresolved symbol -> refuse rather than guess
        }
        preg_match_all('/\d+\.?\d*|[+\-*\/()]/', $expr, $m);
        $tokens = $m[0];

        $prec = ['+' => 1, '-' => 1, '*' => 2, '/' => 2];
        $out = [];
        $ops = [];
        foreach ($tokens as $t) {
            if (is_numeric($t)) {
                $out[] = (float) $t;
            } elseif (isset($prec[$t])) {
                while ($ops && end($ops) !== '(' && $prec[end($ops)] >= $prec[$t]) {
                    $out[] = array_pop($ops);
                }
                $ops[] = $t;
            } elseif ($t === '(') {
                $ops[] = $t;
            } elseif ($t === ')') {
                while ($ops && end($ops) !== '(') {
                    $out[] = array_pop($ops);
                }
                array_pop($ops); // discard '('
            }
        }
        while ($ops) {
            $out[] = array_pop($ops);
        }

        // Evaluate RPN
        $stack = [];
        foreach ($out as $t) {
            if (is_float($t)) {
                $stack[] = $t;
            } else {
                $b = array_pop($stack);
                $a = array_pop($stack);
                if ($a === null || $b === null) {
                    return null;
                }
                $stack[] = match ($t) {
                    '+' => $a + $b,
                    '-' => $a - $b,
                    '*' => $a * $b,
                    '/' => $b == 0 ? 0 : $a / $b,
                    default => 0,
                };
            }
        }
        $result = array_pop($stack);
        return $result === null ? null : round($result, 4);
    }

    /** Manual value entry for manual-source indicators. */
    public function indicatorValueStore(Request $request, $id)
    {
        $indicator = Indicator::findOrFail($id);
        $data = $request->validate([
            'hub'         => 'nullable|integer',
            'period_date' => 'required|date',
            'value'       => 'required|numeric',
            'notes'       => 'nullable|string',
        ]);

        $ids = $this->scopedHubIds();
        if (is_array($ids) && $data['hub'] && !in_array((int) $data['hub'], array_map('intval', $ids), true)) {
            abort(403, 'You cannot record values for that hub.');
        }

        $value = IndicatorValue::updateOrCreate(
            ['indicator_id' => $indicator->id, 'hub' => $data['hub'] ?? null, 'period_date' => $data['period_date']],
            ['value' => $data['value'], 'source' => 'manual', 'notes' => $data['notes'] ?? null, 'recorded_by' => Auth::id()]
        );

        return response()->json($value, 201);
    }

    /* =======================================================================
     |  Dashboards
     * ===================================================================== */

    private function buildScorecard($indicators, string $from, string $to, ?array $hubIds): array
    {
        $values = $this->computeAll($indicators, $from, $to, $hubIds);

        return $indicators->map(function (Indicator $ind) use ($values) {
            $value = $values[$ind->code] ?? null;
            $attainment = $ind->attainment($value);
            $status = 'no_data';
            if ($attainment !== null) {
                $status = $attainment >= 100 ? 'on_track' : ($attainment >= 70 ? 'at_risk' : 'off_track');
            }
            return [
                'id'         => $ind->id,
                'name'       => $ind->name,
                'code'       => $ind->code,
                'unit'       => $ind->unit,
                'level'      => $ind->level,
                'program'    => $ind->program,
                'baseline'   => $ind->baseline,
                'target'     => $ind->target,
                'direction'  => $ind->direction,
                'value'      => $value,
                'attainment' => $attainment,
                'status'     => $status,
            ];
        })->values()->all();
    }

    public function executiveDashboard(Request $request)
    {
        [$from, $to] = $this->periodWindow($request);
        $hubIds = $this->scopedHubIds();
        if ($hub = $request->query('hub')) {
            $hubIds = [(int) $hub];
        }

        $query = Indicator::where('active', true);
        if ($projectId = $request->query('project_id')) {
            $query->where('project_id', $projectId);
        } elseif ($program = $request->query('program')) {
            $query->where('program', $program);
        }
        $indicators = $query->orderBy('sort_order')->orderBy('name')->get();

        $scorecard = $this->buildScorecard($indicators, $from, $to, $hubIds);

        $counts = ['on_track' => 0, 'at_risk' => 0, 'off_track' => 0, 'no_data' => 0];
        foreach ($scorecard as $s) {
            $counts[$s['status']]++;
        }

        return response()->json([
            'period'    => ['from' => $from, 'to' => $to],
            'summary'   => $counts,
            'programs'  => $indicators->pluck('program')->filter()->unique()->values(),
            'scorecard' => $scorecard,
        ]);
    }

    public function donorDashboard(Request $request)
    {
        [$from, $to] = $this->periodWindow($request);
        $hubIds = $this->scopedHubIds();

        $indicators = Indicator::where('active', true)
            ->where('is_donor_visible', true)
            ->orderBy('program')->orderBy('sort_order')->get();

        $scorecard = $this->buildScorecard($indicators, $from, $to, $hubIds);

        // Group by program for a clean donor view
        $byProgram = collect($scorecard)->groupBy(fn ($s) => $s['program'] ?: 'General')
            ->map(fn ($items, $program) => [
                'program'    => $program,
                'indicators' => $items->values(),
                'avg_attainment' => round(
                    collect($items)->pluck('attainment')->filter(fn ($v) => $v !== null)->avg() ?? 0, 1
                ),
            ])->values();

        return response()->json([
            'period'   => ['from' => $from, 'to' => $to],
            'programs' => $byProgram,
        ]);
    }

    /** Time series for one indicator across recent periods (for trend charts). */
    public function indicatorTrend(Request $request, $id)
    {
        $indicator = Indicator::findOrFail($id);
        $hubIds = $this->scopedHubIds();
        $months = (int) $request->query('months', 6);

        $series = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $anchor = Carbon::today()->subMonths($i);
            $from = $anchor->copy()->startOfMonth()->toDateString();
            $to   = $anchor->copy()->endOfMonth()->toDateString();
            $val = $this->computeAll(collect([$indicator]), $from, $to, $hubIds)[$indicator->code] ?? null;
            $series[] = ['period' => $anchor->format('M Y'), 'value' => $val];
        }

        return response()->json([
            'indicator' => $indicator->only(['id', 'name', 'unit', 'target', 'direction']),
            'series'    => $series,
        ]);
    }
}