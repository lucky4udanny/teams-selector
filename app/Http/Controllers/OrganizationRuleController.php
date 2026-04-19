<?php

namespace App\Http\Controllers;

use App\Enums\RuleScope;
use App\Enums\RuleType;
use App\Models\Organization;
use App\Models\Rule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationRuleController extends Controller
{
    public function index(Request $request, Organization $organization): Response
    {
        $this->authorize('view', $organization);

        $rules = $organization->rules()
            ->orderByDesc('weight')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Rule $r) => [
                'id' => $r->id,
                'type' => $r->type->value,
                'scope' => $r->scope->value,
                'weight' => $r->weight,
                'config' => $r->config,
                'sort_order' => $r->sort_order,
            ]);

        $members = $organization->members()->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Rules/Index', [
            'organization' => $this->orgProps($request, $organization),
            'rules' => $rules,
            'members' => $members,
            'ruleTypes' => array_map(fn (RuleType $t) => $t->value, RuleType::cases()),
            'ruleScopes' => array_map(fn (RuleScope $s) => $s->value, RuleScope::cases()),
            'canManage' => Gate::allows('manageRules', $organization),
        ]);
    }

    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('manageRules', $organization);

        $validated = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_column(RuleType::cases(), 'value'))],
            'scope' => ['required', 'in:'.implode(',', array_column(RuleScope::cases(), 'value'))],
            'weight' => ['required', 'integer', 'min:0', 'max:1000000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:32767'],
            'config' => ['required', 'array'],
        ]);

        $type = RuleType::from($validated['type']);
        $this->assertConfigValid($type, $validated['config']);

        $organization->rules()->create([
            'type' => $type,
            'scope' => RuleScope::from($validated['scope']),
            'weight' => $validated['weight'],
            'config' => $validated['config'],
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return redirect()->back();
    }

    public function update(Request $request, Organization $organization, Rule $rule): RedirectResponse
    {
        $this->authorize('manageRules', $organization);
        if ($rule->organization_id !== $organization->id) {
            abort(404);
        }

        $validated = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_column(RuleType::cases(), 'value'))],
            'scope' => ['required', 'in:'.implode(',', array_column(RuleScope::cases(), 'value'))],
            'weight' => ['required', 'integer', 'min:0', 'max:1000000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:32767'],
            'config' => ['required', 'array'],
        ]);

        $type = RuleType::from($validated['type']);
        $this->assertConfigValid($type, $validated['config']);

        $rule->update([
            'type' => $type,
            'scope' => RuleScope::from($validated['scope']),
            'weight' => $validated['weight'],
            'config' => $validated['config'],
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return redirect()->back();
    }

    public function destroy(Request $request, Organization $organization, Rule $rule): RedirectResponse
    {
        $this->authorize('manageRules', $organization);
        if ($rule->organization_id !== $organization->id) {
            abort(404);
        }

        $rule->delete();

        return redirect()->back();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function assertConfigValid(RuleType $type, array $config): void
    {
        $e = fn (string $msg) => ValidationException::withMessages(['config' => $msg]);

        switch ($type) {
            case RuleType::TeamSize:
                if (! isset($config['size']) || (int) $config['size'] < 1) {
                    throw $e('Team size requires positive "size".');
                }
                break;
            case RuleType::GroupSize:
                if (! isset($config['teams_per_group']) || (int) $config['teams_per_group'] < 1) {
                    throw $e('Group size requires positive "teams_per_group".');
                }
                break;
            case RuleType::BannedPair:
                if (! isset($config['member_a_id'], $config['member_b_id']) || (int) $config['member_a_id'] === (int) $config['member_b_id']) {
                    throw $e('Banned pair requires two different member ids.');
                }
                break;
            case RuleType::RepeatPair:
                if (! isset($config['window']) || (int) $config['window'] < 1) {
                    throw $e('Repeat pair requires positive "window" (approvals).');
                }
                break;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function orgProps(Request $request, Organization $organization): array
    {
        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'slug' => $organization->slug,
            'role' => $organization->roleFor($request->user())?->value,
            'logo_url' => $organization->logoPublicUrl(),
            'brand_primary' => $organization->brand_primary,
            'brand_accent' => $organization->brand_accent,
        ];
    }
}
