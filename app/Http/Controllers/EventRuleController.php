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
        $this->assertSingletonNotDuplicated($event, $type, $scope);
        $this->assertRepeatPairUnique($event, $type, $scope, $validated['config']);

        $rule = $event->rules()->create([
            'type' => $type,
            'scope' => $scope,
            'weight' => $validated['weight'],
            'config' => $validated['config'],
            'sort_order' => 0,
        ]);

        $event->recalculateRuleSortOrders();
        $rule->refresh();

        return redirect()->back()->with('status', 'Rule added.');
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
        $this->assertSingletonNotDuplicated($event, $type, $scope, $rule->id);
        $this->assertRepeatPairUnique($event, $type, $scope, $validated['config'], $rule->id);

        $rule->update([
            'type' => $type,
            'scope' => $scope,
            'weight' => $validated['weight'],
            'config' => $validated['config'],
        ]);

        $event->recalculateRuleSortOrders();

        return redirect()->back()->with('status', 'Rule updated.');
    }

    public function destroy(Request $request, Organization $organization, Event $event, Rule $rule): RedirectResponse
    {
        $this->authorize('update', $event);

        $rule->delete();
        $event->recalculateRuleSortOrders();

        return redirect()->back()->with('status', 'Rule deleted.');
    }

    private function assertGroupScopeAllowed(Event $event, RuleScope $scope): void
    {
        if ($scope === RuleScope::Group && ! $event->uses_groups) {
            throw ValidationException::withMessages([
                'scope' => 'Group scope rules require uses_groups on the event.',
            ]);
        }
    }

    private function assertSingletonNotDuplicated(Event $event, RuleType $type, RuleScope $scope, ?int $exceptRuleId = null): void
    {
        if ($type !== RuleType::Size) {
            return;
        }

        $q = Rule::query()
            ->where('event_id', $event->id)
            ->where('type', $type)
            ->where('scope', $scope);

        if ($exceptRuleId) {
            $q->where('id', '!=', $exceptRuleId);
        }

        if ($q->exists()) {
            throw ValidationException::withMessages([
                'type' => 'Only one size rule per scope ('.$scope->value.') is allowed per event.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function assertRepeatPairUnique(Event $event, RuleType $type, RuleScope $scope, array $config, ?int $exceptRuleId = null): void
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
            ->where('scope', $scope)
            ->where('config->event_id', $priorId);

        if ($exceptRuleId) {
            $q->where('id', '!=', $exceptRuleId);
        }

        if ($q->exists()) {
            throw ValidationException::withMessages([
                'config' => 'An "avoid same members" rule for this prior event and scope already exists.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function assertConfigValid(RuleType $type, array $config, Event $event): void
    {
        $e = fn (string $msg) => ValidationException::withMessages(['config' => $msg]);

        switch ($type) {
            case RuleType::Size:
                if (! isset($config['size']) || (int) $config['size'] < 1) {
                    throw $e('Size rule requires a positive "size".');
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
                $allowed = $event->previousEvents()->pluck('events.id')->all();
                if (! in_array($priorId, $allowed, true)) {
                    throw $e('Repeat pair prior event_id must be one of this event\'s previous events.');
                }
                break;
            case RuleType::SkillLeveling:
                if (! isset($config['min_avg'], $config['max_avg'])
                    || ! is_numeric($config['min_avg'])
                    || ! is_numeric($config['max_avg'])) {
                    throw $e('Skill leveling requires min_avg and max_avg between 0 and 100 with min_avg <= max_avg.');
                }
                $min = (float) $config['min_avg'];
                $max = (float) $config['max_avg'];
                if ($min < 0 || $max < 0 || $min > 100 || $max > 100 || $min > $max) {
                    throw $e('Skill leveling requires min_avg and max_avg between 0 and 100 with min_avg <= max_avg.');
                }
                break;
            case RuleType::MemberAttribute:
                $validAttributes = ['sector_id', 'company', 'gender'];
                $validMatches = ['same', 'different'];
                if (! isset($config['attribute']) || ! in_array($config['attribute'], $validAttributes, true)) {
                    throw $e('Member attribute rule requires "attribute" to be one of: '.implode(', ', $validAttributes).'.');
                }
                if (! isset($config['match']) || ! in_array($config['match'], $validMatches, true)) {
                    throw $e('Member attribute rule requires "match" to be "same" or "different".');
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
