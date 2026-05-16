<script setup>
import OrganizationLayout from '@/Layouts/OrganizationLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    organization: Object,
    selection: Object,
});

const snap = computed(() => props.selection.snapshot || {});

const nameById = computed(() => {
    const m = {};
    for (const x of snap.value.members || []) {
        m[x.id] = x.name;
    }
    return m;
});
</script>

<template>
    <Head :title="selection.title || 'Selection'" />

    <OrganizationLayout :organization="organization">
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h1 class="text-2xl font-bold text-brand-navy">
                    {{ selection.title || 'Selection' }}
                </h1>
                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="
                            route('organizations.selections.print', [
                                organization.slug,
                                selection.id,
                            ])
                        "
                        target="_blank"
                    >
                        <SecondaryButton type="button">Print</SecondaryButton>
                    </Link>
                    <a
                        :href="
                            route('organizations.selections.export.csv', [
                                organization.slug,
                                selection.id,
                            ])
                        "
                    >
                        <SecondaryButton type="button">CSV</SecondaryButton>
                    </a>
                    <a
                        :href="
                            route('organizations.selections.export.xlsx', [
                                organization.slug,
                                selection.id,
                            ])
                        "
                    >
                        <PrimaryButton type="button">Excel</PrimaryButton>
                    </a>
                </div>
            </div>
        </template>

        <p v-if="selection.notes" class="mb-6 text-sm text-brand-blue/80">
            {{ selection.notes }}
        </p>

        <div class="grid gap-4 md:grid-cols-2">
            <div
                v-for="(g, gi) in snap.groups || []"
                :key="gi"
                class="rounded-2xl border border-brand-mist bg-white p-4 shadow-sm"
            >
                <h2 class="mb-3 text-sm font-semibold text-brand-navy">
                    Group {{ gi + 1 }}
                </h2>
                <div
                    v-for="ti in g.team_indices || []"
                    :key="ti"
                    class="mb-4 border-b border-brand-mist pb-4 last:mb-0 last:border-0 last:pb-0"
                >
                    <p class="text-xs font-medium text-brand-blue/70">
                        Team {{ ti + 1 }}
                    </p>
                    <ul class="mt-1 text-sm text-brand-navy">
                        <li
                            v-for="mid in (snap.teams[ti] || {}).member_ids || []"
                            :key="mid"
                        >
                            {{ nameById[mid] || mid }}
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div
            v-if="snap.violations?.length"
            class="mt-8 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
        >
            <p class="font-semibold">Recorded violations</p>
            <ul class="mt-2 list-inside list-disc">
                <li v-for="(v, i) in snap.violations" :key="i">
                    {{ v.detail }}
                </li>
            </ul>
        </div>
    </OrganizationLayout>
</template>
