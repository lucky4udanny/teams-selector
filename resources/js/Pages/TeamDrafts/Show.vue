<script setup>
import Alert from '@/Components/Alert.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ProgressRing from '@/Components/ProgressRing.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import OrganizationLayout from '@/Layouts/OrganizationLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    organization: Object,
    event: Object,
    teamDraft: Object,
    conflicts: Array,
    orgMembers: Array,
    canUpdate: Boolean,
    canFinalize: Boolean,
});

const state = computed(() =>
    props.teamDraft?.state && typeof props.teamDraft.state === 'object' ? props.teamDraft.state : {},
);

const teams = computed(() => (Array.isArray(state.value.teams) ? state.value.teams : []));
const groups = computed(() => (Array.isArray(state.value.groups) ? state.value.groups : []));
const violations = computed(() => (Array.isArray(state.value.violations) ? state.value.violations : []));
const blockingErrors = computed(() =>
    Array.isArray(state.value.blocking_errors) ? state.value.blocking_errors : [],
);
const totalPenalty = computed(() => state.value.total_penalty ?? null);

const memberMap = computed(() => {
    const m = new Map();
    (props.orgMembers || []).forEach((row) => m.set(row.id, row.display_name));

    return m;
});

const memberName = (id) => memberMap.value.get(id) ?? `Member #${id}`;

const teamNamesForm = useForm({
    team_names: [],
    group_names: [],
});

const syncNamesFromState = () => {
    const tn = state.value.team_names;
    const gn = state.value.group_names;
    const tc = teams.value.length;
    const gc = groups.value.length;
    teamNamesForm.team_names = Array.from({ length: tc }, (_, i) => tn?.[i] ?? '');
    teamNamesForm.group_names = Array.from({ length: gc }, (_, i) => gn?.[i] ?? '');
};

watch(
    () => props.teamDraft?.state,
    () => syncNamesFromState(),
    { immediate: true, deep: true },
);

const saveNames = () => {
    teamNamesForm.patch(
        route('organizations.events.team-drafts.update-names', [
            props.organization.slug,
            props.event.id,
            props.teamDraft.id,
        ]),
        { preserveScroll: true },
    );
};

const finalizeProcessing = ref(false);
const showFinalize = ref(false);

const runFinalize = () => {
    finalizeProcessing.value = true;
    router.post(
        route('organizations.events.team-drafts.finalize', [
            props.organization.slug,
            props.event.id,
            props.teamDraft.id,
        ]),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                finalizeProcessing.value = false;
                showFinalize.value = false;
            },
        },
    );
};

const showDelete = ref(false);
const deleteProcessing = ref(false);

const runDelete = () => {
    deleteProcessing.value = true;
    router.delete(
        route('organizations.events.team-drafts.destroy', [
            props.organization.slug,
            props.event.id,
            props.teamDraft.id,
        ]),
        {
            preserveScroll: true,
            onFinish: () => {
                deleteProcessing.value = false;
                showDelete.value = false;
            },
        },
    );
};

const penaltyHue = computed(() => {
    const p = totalPenalty.value;
    if (p === null || p === undefined) {
        return 'border-brand-mist';
    }
    if (p <= 10) {
        return 'border-emerald-200 bg-emerald-50/60';
    }
    if (p <= 50) {
        return 'border-amber-200 bg-amber-50/60';
    }

    return 'border-red-200 bg-red-50/50';
});
</script>

<template>
    <Head :title="`Draft — ${event.name}`" />

    <OrganizationLayout :organization="organization">
        <template #header>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-brand-blue/70">
                        <Link
                            class="font-medium hover:text-brand-navy"
                            :href="
                                `${route('organizations.events.show', {
                                    organization: organization.slug,
                                    event: event.id,
                                })}?tab=drafts`
                            "
                        >
                            ← {{ event.name }}
                        </Link>
                    </p>
                    <h1 class="ts-heading-page mt-1">
                        {{ teamDraft.name || `Draft #${teamDraft.id}` }}
                    </h1>
                    <p class="mt-1 text-sm text-brand-blue/70">
                        Created {{ teamDraft.created_at ? new Date(teamDraft.created_at).toLocaleString() : '—' }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <div
                        class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm"
                        :class="penaltyHue"
                    >
                        <ProgressRing
                            v-if="totalPenalty !== null"
                            :value="Math.min(totalPenalty, 100)"
                            :max="100"
                            :size="36"
                        />
                        <span class="font-medium text-brand-navy">
                            Penalty:
                            <span v-if="totalPenalty !== null">{{ totalPenalty }}</span>
                            <span v-else>—</span>
                        </span>
                    </div>
                    <span
                        v-if="teamDraft.is_final || event.is_finalized"
                        class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800"
                    >
                        Final
                    </span>
                </div>
            </div>
        </template>

        <Alert v-if="blockingErrors.length" variant="error" class="mb-6">
            <p class="font-semibold">Blocking errors</p>
            <ul class="mt-2 list-inside list-disc space-y-1">
                <li v-for="(err, i) in blockingErrors" :key="`b${i}`">{{ err }}</li>
            </ul>
        </Alert>

        <Alert v-else-if="violations.length" variant="warning" class="mb-6">
            <p class="font-semibold">{{ violations.length }} violation(s)</p>
            <ul class="mt-2 max-h-48 overflow-auto text-xs">
                <li v-for="(v, i) in violations" :key="`v${i}`" class="font-mono">
                    {{ typeof v === 'string' ? v : JSON.stringify(v) }}
                </li>
            </ul>
        </Alert>

        <Alert v-if="conflicts?.length" variant="info" class="mb-6">
            <p class="font-semibold">{{ conflicts.length }} planning note(s)</p>
            <ul class="mt-2 list-inside list-disc space-y-1 text-xs">
                <li v-for="(c, i) in conflicts" :key="`c${i}`">{{ typeof c === 'string' ? c : JSON.stringify(c) }}</li>
            </ul>
        </Alert>

        <form
            v-if="canUpdate"
            class="ts-card-padded mb-8"
            @submit.prevent="saveNames"
        >
            <h2 class="ts-heading-section mb-4">Names</h2>
            <div class="grid gap-6 lg:grid-cols-2">
                <div>
                    <h3 class="mb-3 text-sm font-semibold text-brand-navy">Teams</h3>
                    <div class="space-y-2">
                        <div
                            v-for="(t, ti) in teams"
                            :key="`tn${ti}`"
                            class="flex items-center gap-2"
                        >
                            <span class="w-8 text-xs text-brand-blue/60">{{ ti + 1 }}</span>
                            <TextInput
                                v-model="teamNamesForm.team_names[ti]"
                                class="flex-1 text-sm"
                                :placeholder="`Team ${ti + 1}`"
                            />
                        </div>
                    </div>
                </div>
                <div>
                    <h3 class="mb-3 text-sm font-semibold text-brand-navy">Groups</h3>
                    <div class="space-y-2">
                        <div
                            v-for="(g, gi) in groups"
                            :key="`gn${gi}`"
                            class="flex items-center gap-2"
                        >
                            <span class="w-8 text-xs text-brand-blue/60">{{ gi + 1 }}</span>
                            <TextInput
                                v-model="teamNamesForm.group_names[gi]"
                                class="flex-1 text-sm"
                                :placeholder="`Group ${gi + 1}`"
                            />
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-6">
                <PrimaryButton type="submit" :disabled="teamNamesForm.processing">Save names</PrimaryButton>
            </div>
        </form>

        <div class="grid gap-6 lg:grid-cols-2">
            <section
                v-for="(team, ti) in teams"
                :key="`team${ti}`"
                class="rounded-xl border border-brand-mist bg-white p-4 shadow-sm"
            >
                <h3 class="mb-3 font-semibold text-brand-navy">
                    {{ teamNamesForm.team_names[ti] || `Team ${ti + 1}` }}
                </h3>
                <ul class="space-y-1 text-sm text-brand-blue/90">
                    <li v-for="mid in team.member_ids || []" :key="mid">
                        {{ memberName(mid) }}
                    </li>
                </ul>
            </section>
        </div>

        <div
            v-if="canFinalize && !event.is_finalized"
            class="mt-10 flex flex-wrap items-center gap-3 border-t border-brand-mist pt-8"
        >
            <PrimaryButton type="button" :disabled="blockingErrors.length > 0 || finalizeProcessing" @click="showFinalize = true">
                Finalize event with this draft
            </PrimaryButton>
            <p v-if="blockingErrors.length" class="text-sm text-amber-800">
                Resolve blocking errors before finalizing.
            </p>
        </div>

        <div v-if="canUpdate && !teamDraft.is_final" class="mt-6 flex flex-wrap gap-3">
            <SecondaryButton type="button" class="text-red-700 hover:bg-red-50" @click="showDelete = true">
                Delete draft
            </SecondaryButton>
        </div>

        <ConfirmDialog
            :show="showFinalize"
            title="Finalize this event?"
            message="This locks the final team assignment for the event using this draft."
            confirm-label="Finalize"
            :processing="finalizeProcessing"
            @close="showFinalize = false"
            @confirm="runFinalize"
        />

        <ConfirmDialog
            :show="showDelete"
            title="Delete this draft?"
            message="Non-final drafts can be regenerated from the event."
            confirm-label="Delete draft"
            :processing="deleteProcessing"
            @close="showDelete = false"
            @confirm="runDelete"
        />
    </OrganizationLayout>
</template>
