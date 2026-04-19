<script setup>
import OrganizationLayout from '@/Layouts/OrganizationLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    organization: Object,
    draft: Object,
    memberIds: Array,
    members: Array,
    canApprove: Boolean,
});

const state = computed(() => props.draft.state || {});

const approveForm = useForm({
    title: '',
    notes: '',
    occurred_at: '',
});

const generate = () => {
    router.post(
        route('organizations.drafts.generate', [
            props.organization.slug,
            props.draft.id,
        ]),
    );
};

const approve = () => {
    approveForm.post(
        route('organizations.drafts.approve', [
            props.organization.slug,
            props.draft.id,
        ]),
    );
};

const destroyDraft = () => {
    if (!confirm('Delete this draft?')) return;
    router.delete(
        route('organizations.drafts.destroy', [
            props.organization.slug,
            props.draft.id,
        ]),
    );
};

const nameById = computed(() => {
    const m = {};
    for (const x of props.members) {
        m[x.id] = x.name;
    }
    return m;
});
</script>

<template>
    <Head :title="draft.name || 'Draft'" />

    <OrganizationLayout :organization="organization">
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h1 class="text-2xl font-bold text-slate-900">
                    {{ draft.name || 'Draft' }}
                </h1>
                <div class="flex gap-2">
                    <PrimaryButton type="button" @click="generate"
                        >Generate teams</PrimaryButton
                    >
                    <DangerButton type="button" @click="destroyDraft"
                        >Delete</DangerButton
                    >
                </div>
            </div>
        </template>

        <div
            v-if="state.blocking_errors?.length"
            class="mb-6 ts-alert-danger text-sm"
        >
            <p v-for="(err, i) in state.blocking_errors" :key="i">{{ err }}</p>
        </div>

        <div
            v-else-if="state.teams?.length"
            class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
        >
            <div class="mb-2 flex items-center justify-between">
                <h2 class="font-semibold text-slate-900">Result</h2>
                <span class="text-sm text-slate-600"
                    >Penalty {{ state.total_penalty ?? 0 }}</span
                >
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <div
                    v-for="(g, gi) in state.groups || []"
                    :key="gi"
                    class="rounded-xl border border-slate-100 bg-slate-50 p-4"
                >
                    <p class="mb-2 text-xs font-semibold uppercase text-slate-500">
                        Group {{ gi + 1 }}
                    </p>
                    <div
                        v-for="ti in g.team_indices || []"
                        :key="ti"
                        class="mb-3 last:mb-0"
                    >
                        <p class="text-xs text-slate-500">Team {{ ti + 1 }}</p>
                        <ul class="mt-1 text-sm text-slate-900">
                            <li
                                v-for="mid in (state.teams[ti] || {}).member_ids ||
                                []"
                                :key="mid"
                            >
                                {{ nameById[mid] || mid }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="state.violations?.length"
            class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4"
        >
            <h3 class="mb-2 text-sm font-semibold text-amber-900">Violations</h3>
            <ul class="space-y-1 text-xs text-amber-900">
                <li v-for="(v, i) in state.violations" :key="i">
                    {{ v.detail }} (weight {{ v.weight }})
                </li>
            </ul>
        </div>

        <form
            v-if="canApprove && state.teams?.length && !state.blocking_errors?.length"
            class="max-w-xl rounded-2xl border border-emerald-200 bg-emerald-50/50 p-6"
            @submit.prevent="approve"
        >
            <h2 class="mb-4 text-sm font-semibold text-emerald-900">
                Approve selection
            </h2>
            <div class="space-y-3">
                <div>
                    <InputLabel for="title" value="Title" />
                    <TextInput
                        id="title"
                        v-model="approveForm.title"
                        class="mt-1 block w-full"
                    />
                    <InputError :message="approveForm.errors.title" />
                </div>
                <div>
                    <InputLabel for="notes" value="Notes" />
                    <textarea
                        id="notes"
                        v-model="approveForm.notes"
                        rows="2"
                        class="mt-1 block w-full rounded-md border-slate-300 shadow-sm"
                    />
                </div>
                <div>
                    <InputLabel for="occurred_at" value="Date" />
                    <TextInput
                        id="occurred_at"
                        v-model="approveForm.occurred_at"
                        type="date"
                        class="mt-1 block w-full"
                    />
                </div>
            </div>
            <div class="mt-4">
                <PrimaryButton :disabled="approveForm.processing"
                    >Approve</PrimaryButton
                >
            </div>
            <InputError class="mt-2" :message="approveForm.errors.approve" />
        </form>
    </OrganizationLayout>
</template>
