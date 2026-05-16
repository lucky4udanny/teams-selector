<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

defineProps({
    editMemberIds: { type: Array, required: true },
    editSearch: { type: String, required: true },
    editSearchResults: { type: Array, required: true },
    editSaving: { type: Boolean, default: false },
    memberName: { type: Function, required: true },
});

const emit = defineEmits(['update:editSearch', 'remove', 'add', 'save', 'cancel']);
</script>

<template>
    <!-- Current members with remove buttons -->
    <ul class="mb-3 space-y-1">
        <li
            v-for="mid in editMemberIds"
            :key="mid"
            class="flex items-center justify-between rounded-lg bg-brand-mist/40 px-2 py-1 text-sm"
        >
            <span class="text-brand-navy">{{ memberName(mid) }}</span>
            <button
                type="button"
                class="ml-2 text-brand-blue/40 hover:text-red-600"
                :aria-label="`Remove ${memberName(mid)}`"
                @click="emit('remove', mid)"
            >
                ✕
            </button>
        </li>
        <li v-if="!editMemberIds.length" class="text-xs italic text-brand-blue/40">No members — type to add.</li>
    </ul>

    <!-- Search to add member -->
    <div class="relative mb-4">
        <input
            type="text"
            :value="editSearch"
            placeholder="Search to add a member…"
            class="w-full rounded-lg border border-brand-mist px-3 py-1.5 text-sm focus:border-brand-blue focus:outline-none focus:ring-1 focus:ring-brand-blue"
            @input="emit('update:editSearch', $event.target.value)"
        />
        <ul
            v-if="editSearchResults.length"
            class="absolute z-20 mt-1 w-full rounded-lg border border-brand-mist bg-white shadow-lg"
        >
            <li
                v-for="m in editSearchResults"
                :key="m.id"
                class="cursor-pointer px-3 py-2 text-sm text-brand-navy hover:bg-brand-mist/40"
                @mousedown.prevent="emit('add', m.id)"
            >
                {{ m.display_name }}
            </li>
        </ul>
    </div>

    <!-- Save / Cancel -->
    <div class="flex gap-2">
        <PrimaryButton type="button" :disabled="editSaving" @click="emit('save')">
            {{ editSaving ? 'Saving…' : 'Save' }}
        </PrimaryButton>
        <SecondaryButton type="button" :disabled="editSaving" @click="emit('cancel')">
            Cancel
        </SecondaryButton>
    </div>
</template>
