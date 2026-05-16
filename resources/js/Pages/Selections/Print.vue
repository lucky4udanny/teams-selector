<script setup>
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    organization: Object,
    selection: Object,
});

const snap = computed(() => props.selection.snapshot || {});

const branding = computed(() => snap.value.branding || {});

const nameById = computed(() => {
    const m = {};
    for (const x of snap.value.members || []) {
        m[x.id] = x.name;
    }
    return m;
});

const printStyles = computed(() => ({
    '--print-brand': branding.value.brand_primary || '#111827',
}));

const printPage = () => window.print();
</script>

<template>
    <Head title="Print" />

    <div
        class="min-h-screen bg-white p-8 text-brand-navy print:p-4"
        :style="printStyles"
    >
        <header
            class="no-print mb-6 flex items-center justify-between border-b border-brand-mist pb-4"
        >
            <span class="text-sm text-brand-blue/80">Print preview</span>
            <button
                type="button"
                class="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white"
                @click="printPage"
            >
                Print
            </button>
        </header>

        <div class="mb-8 flex items-start gap-6 border-b border-brand-mist pb-6">
            <img
                v-if="branding.logo_url"
                :src="branding.logo_url"
                alt=""
                class="h-16 max-w-[200px] object-contain"
            />
            <div>
                <h1
                    class="text-2xl font-bold"
                    :style="{ color: 'var(--print-brand)' }"
                >
                    {{ selection.title || 'Team selection' }}
                </h1>
                <p v-if="snap.notes" class="mt-2 text-sm text-brand-blue/80">
                    {{ snap.notes }}
                </p>
            </div>
        </div>

        <section
            v-for="(g, gi) in snap.groups || []"
            :key="gi"
            class="mb-8 break-inside-avoid"
        >
            <h2
                class="mb-4 border-b border-brand-mist pb-2 text-lg font-semibold"
                :style="{ color: 'var(--print-brand)' }"
            >
                Group {{ gi + 1 }}
            </h2>
            <div class="grid gap-6 md:grid-cols-2">
                <div
                    v-for="ti in g.team_indices || []"
                    :key="ti"
                    class="rounded-lg border border-brand-mist p-4"
                >
                    <p class="text-xs font-medium uppercase text-brand-blue/70">
                        Team {{ ti + 1 }}
                    </p>
                    <ul class="mt-2 space-y-1">
                        <li
                            v-for="mid in (snap.teams[ti] || {}).member_ids || []"
                            :key="mid"
                            class="text-sm"
                        >
                            {{ nameById[mid] || mid }}
                        </li>
                    </ul>
                </div>
            </div>
        </section>
    </div>
</template>
