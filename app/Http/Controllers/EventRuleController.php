<?php

namespace App\Http\Controllers;

use App\Enums\RuleScope;
use App\Enums\RuleType;
use App\Models\Event;
use App\Models\Member;
use App\Models\Organization;
use App\Models\Rule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EventRuleController extends Controller
{
    public function store(Request $request, Organization $organization, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_column(RuleType::cases(), 'value'))],
            'scope' => ['required', 'in:'.implode(',', array_column(RuleScope::cases(), 'value'))],
            'weight' => ['required', 'integer', 'min:0', 'max:1000000'],
            'config' => ['required', 'array'],
        ]);

        $type = RuleType::from($validated['type']);
        $scope = RuleScope::from($validated['scope']);

        $this->assertGroupScopeAllowed($event, $scope);
        $this->assertConfigValid($type, $validated['config'], $event);
        $this->assertSingletonNotDuplicated($event, $type);
        $this->assertRepeatPairUnique($event, $type, $validated['config']);

        $rule = $event->rules()->create([
            'type' => $type,
            'scope' => $scope,
            'weight' => $validated['weight'],
            'config' => $validated['config'],
            'sort_order' => 0,
        ]);

        $this->recalculateSortOrders($event);
        $rule->refresh();

        return redirect()->back();
    }

    public function update(Request $request, Organization $organization, Event $event, Rule $rule): RedirectResponse
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_column(RuleType::cases(), 'value'))],
            'scope' => ['required', 'in:'.implode(',', array_column(RuleScope::cases(), 'value'))],
            'weight' => ['required', 'integer', 'min:0', 'max:1000000'],
            'config' => ['required', 'array'],
        ]);

        $type = RuleType::from($validated['type']);
        $scope = RuleScope::from($validated['scope']);

        $this->assertGroupScopeAllowed($event, $scope);
        $this->assertConfigValid($type, $validated['config'], $event);
        $this->assertSingletonNotDuplicated($event, $type, $rule->id);
        $this->assertRepeatPairUnique($event, $type, $validated['config'], $rule->id);

        $rule->update([
            'type' => $type,
            'scope' => $scope,
            'weight' => $validated['weight'],
            'config' => $validated['config'],
        ]);

        $this->recalculateSortOrders($event);

        return redirect()->back();
    }

    public function destroy(Request $request, Organization $organization, Event $event, Rule $rule): RedirectResponse
    {
        $this->authorize('update', $event);

        $rule->delete();
        $this->recalculateSortOrders($event);

        return redirect()->back();
    }

    private function assertGroupScopeAllowed(Event $event, RuleScope $scope): void
    {
        if ($scope === RuleScope::Group && ! $event->uses_groups) {
            throw ValidationException::withMessages([
                'scope' => 'Group scope rules require uses_groups on the event.',
            ]);
        }
    }

    private function assertSingletonNotDuplicated(Event $event, RuleType $type, ?int $exceptRuleId = null): void
    {
        if (! in_array($type, [RuleType::TeamSize, RuleType::GroupSize], true)) {
            return;
        }

        $q = Rule::query()->where('event_id', $event->id)->where('type', $type);
        if ($exceptRuleId) {
            $q->where('id', '!=', $exceptRuleId);
        }
        if ($q->exists()) {
            throw ValidationException::withMessages([
                'type' => 'Only one '.$type->value.' rule is allowed per event.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function assertRepeatPairUnique(Event $event, RuleType $type, array $config, ?int $exceptRuleId = null): void
    {
        if ($type !== RuleType::RepeatPair) {
            return;
        }

        $priorId = (int) ($config['event_id'] ?? 0);
        if ($priorId < 1) {
            return;
        }

        $q = Rule::query()
            ->where('event_id', $event->id)
            ->where('type', RuleType::RepeatPair)
            ->where('config->event_id', $priorId);

        if ($exceptRuleId) {
            $q->where('id', '!=', $exceptRuleId);
        }

        if ($q->exists()) {
            throw ValidationException::withMessages([
                'config' => 'A repeat_pair rule for this prior event already exists.',
            ]);
        }
    }

    private function recalculateSortOrders(Event $event): void
    {
        $rules = Rule::query()
            ->where('event_id', $event->id)
            ->orderByDesc('weight')
            ->orderBy('id')
            ->get();

        foreach ($rules as $i => $rule) {
            $rule->update(['sort_order' => $i]);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function assertConfigValid(RuleType $type, array $config, Event $event): void
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
                $this->assertMembersInOrg($event, [(int) $config['member_a_id'], (int) $config['member_b_id']]);
                break;
            case RuleType::PreferredPair:
                if (! isset($config['member_a_id'], $config['member_b_id']) || (int) $config['member_a_id'] === (int) $config['member_b_id']) {
                    throw $e('Preferred pair requires two different member ids.');
                }
                $this->assertMembersInOrg($event, [(int) $config['member_a_id'], (int) $config['member_b_id']]);
                break;
            case RuleType::RepeatPair:
                $priorId = (int) ($config['event_id'] ?? 0);
                if ($priorId < 1) {
                    throw $e('Repeat pair requires a prior "event_id".');
                }
                $allowed = $event->previousEvents()->pluck('id')->all();
                if (! in_array($priorId, $allowed, true)) {
                    throw $e('Repeat pair prior event_id must be one of this event\'s previous events.');
                }
                break;
            case RuleType::SkillLeveling:
                $min = (int) ($config['min_avg'] ?? -1);
                $max = (int) ($config['max_avg'] ?? -1);
                if ($min < 0 || $max > 100 || $min > $max) {
                    throw $e('Skill leveling requires min_avg and max_avg between 0 and 100 with min_avg <= max_avg.');
                }
                break;
        }
    }

    /**
     * @param  list<int>  $memberIds
     */
    private function assertMembersInOrg(Event $event, array $memberIds): void
    {
        $orgId = $event->organization_id;
        $count = Member::query()
            ->where('organization_id', $orgId)
            ->whereIn('id', $memberIds)
            ->count();

        if ($count !== count(array_unique($memberIds))) {
            throw ValidationException::withMessages(['config' => 'Pair members must belong to the organization.']);
        }
    }
}
