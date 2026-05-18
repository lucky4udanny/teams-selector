<script setup>
import TextInput from '@/Components/TextInput.vue';
import { computed, ref } from 'vue';

const props = defineProps({
    members: {
        type: Array,
        default: () => [],
    },
    emptyMessage: {
        type: String,
        default: 'Everyone in the organization is already on this roster.',
    },
});

const selectedIds = defineModel({
    type: Array,
    default: () => [],
});

const search = ref('');

const selectedSet = computed(() => new Set(selectedIds.value.map((id) => Number(id))));

const filteredMembers = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) {
        return props.members;
    }

    return props.members.filter((m) => {
        const name = String(m.display_name ?? '').toLowerCase();
        const email = String(m.email ?? '').toLowerCase();
        const sector = String(m.sector?.name ?? '').toLowerCase();

        return name.includes(q) || email.includes(q) || sector.includes(q);
    });
});

const filteredIds = computed(() => filteredMembers.value.map((m) => Number(m.id)));

const allFilteredSelected = computed(
    () =>
        filteredIds.value.length > 0 &&
        filteredIds.value.every((id) => selectedSet.value.has(id)),
);

const someFilteredSelected = computed(
    () =>
        filteredIds.value.some((id) => selectedSet.value.has(id)) && !allFilteredSelected.value,
);

const setSelected = (ids) => {
    selectedIds.value = [...new Set(ids.map((id) => Number(id)))];
};

const toggleMember = (id, checked) => {
    const next = new Set(selectedSet.value);
    const n = Number(id);
    if (checked) {
        next.add(n);
    } else {
        next.delete(n);
    }
    setSelected([...next]);
};

const toggleSelectAllFiltered = (checked) => {
    if (!checked) {
        const remove = new Set(filteredIds.value);
        setSelected(selectedIds.value.filter((id) => !remove.has(Number(id))));

        return;
    }

    const next = new Set(selectedSet.value);
    for (const id of filteredIds.value) {
        next.add(id);
    }
    setSelected([...next]);
};

const clearSelection = () => {
    selectedIds.value = [];
};
</script>

<template>
    <div v-if="!members.length" class="rounded-lg border border-dashed border-brand-mist bg-brand-cream/50 px-4 py-6 text-center text-sm text-brand-blue/70">
        {{ emptyMessage }}
    </div>

    <div v-else class="space-y-3">
        <TextInput
            v-model="search"
            type="search"
            placeholder="Search by name, email, or sector…"
            class="w-full text-sm"
        />

        <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
            <span class="text-brand-navy">
                <span class="font-medium">{{ selectedIds.length }}</span>
                selected
                <span v-if="search.trim()" class="text-brand-blue/60">
                    · {{ filteredMembers.length }} shown
                </span>
                <span v-else class="text-brand-blue/60"> · {{ members.length }} available </span>
            </span>
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="cursor-pointer font-medium text-brand-blue hover:text-brand-navy active:opacity-70"
                    @click="toggleSelectAllFiltered(true)"
                >
                    Select all{{ search.trim() ? ' shown' : '' }}
                </button>
                <span class="text-brand-mist" aria-hidden="true">|</span>
                <button
                    type="button"
                    class="cursor-pointer font-medium text-brand-blue hover:text-brand-navy active:opacity-70 disabled:cursor-not-allowed disabled:opacity-40"
                    :disabled="selectedIds.length === 0"
                    @click="clearSelection"
                >
                    Clear
                </button>
            </div>
        </div>

        <div
            class="max-h-64 overflow-y-auto rounded-lg border border-brand-mist bg-white"
            role="listbox"
            aria-multiselectable="true"
            :aria-label="`${members.length} organization members available to add`"
        >
            <label
                v-if="filteredMembers.length"
                class="flex cursor-pointer items-center gap-3 border-b border-brand-mist bg-brand-cream/80 px-3 py-2 text-sm font-medium text-brand-navy sticky top-0 z-[1]"
            >
                <input
                    type="checkbox"
                    class="rounded border-brand-mist"
                    :checked="allFilteredSelected"
                    :indeterminate="someFilteredSelected"
                    @change="toggleSelectAllFiltered($event.target.checked)"
                />
                <span>Select all in list</span>
            </label>

            <p
                v-if="search.trim() && !filteredMembers.length"
                class="px-3 py-6 text-center text-sm text-brand-blue/60"
            >
                No members match your search.
            </p>

            <label
                v-for="member in filteredMembers"
                :key="member.id"
                class="flex cursor-pointer items-start gap-3 border-b border-brand-mist/80 px-3 py-2.5 last:border-b-0 hover:bg-brand-cream/40"
                role="option"
                :aria-selected="selectedSet.has(Number(member.id))"
            >
                <input
                    type="checkbox"
                    class="mt-0.5 rounded border-brand-mist"
                    :checked="selectedSet.has(Number(member.id))"
                    @change="toggleMember(member.id, $event.target.checked)"
                />
                <span class="min-w-0 flex-1">
                    <span class="block font-medium text-brand-navy">{{ member.display_name }}</span>
                    <span class="block text-xs text-brand-blue/60">
                        <span v-if="member.email">{{ member.email }}</span>
                        <span v-else class="italic">No email</span>
                        <span v-if="member.sector?.name"> · {{ member.sector.name }}</span>
                    </span>
                </span>
            </label>
        </div>
    </div>
</template>
