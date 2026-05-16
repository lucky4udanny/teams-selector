<script setup>
import OrganizationLayout from '@/Layouts/OrganizationLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    organization: Object,
    selections: Array,
});
</script>

<template>
    <Head title="Selections" />

    <OrganizationLayout :organization="organization">
        <template #header>
            <h1 class="text-2xl font-bold text-brand-navy">Approved selections</h1>
        </template>

        <ul class="space-y-2">
            <li v-for="s in selections" :key="s.id">
                <Link
                    :href="
                        route('organizations.selections.show', [
                            organization.slug,
                            s.id,
                        ])
                    "
                    class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-brand-mist bg-white px-4 py-3 shadow-sm transition hover:border-brand-blue/35"
                >
                    <span class="font-medium text-brand-navy">{{
                        s.title || 'Selection #' + s.id
                    }}</span>
                    <span class="text-xs text-brand-blue/70">{{
                        s.created_at
                    }}</span>
                    <span class="w-full text-xs text-amber-800 sm:w-auto"
                        >Penalty {{ s.total_penalty }}</span
                    >
                </Link>
            </li>
        </ul>
    </OrganizationLayout>
</template>
