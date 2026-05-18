<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { ref } from 'vue';

const props = defineProps({
    editMemberIds: { type: Array, required: true },
    editSearch: { type: String, required: true },
    editSearchResults: { type: Array, required: true },
    availableCount: { type: Number, default: 0 },
    matchCount: { type: Number, default: null },
    editSaving: { type: Boolean, default: false },
    memberName: { type: Function, required: true },
});

const emit = defineEmits(['update:editSearch', 'remove', 'add', 'save', 'cancel']);

const inputFocused = ref(false);
const showDropdown = ref(false);

const onFocus = () => {
    inputFocused.value = true;
    showDropdown.value = true;
};

const onBlur = () => {
    // Slight delay so mousedown on a list item fires before blur hides it.
    setTimeout(() => {
        inputFocused.value = false;
        showDropdown.value = false;
    }, 150);
};
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
                class="ml-2 cursor-pointer text-brand-blue/40 hover:text-red-600 active:opacity-60"
                :aria-label="`Remove ${memberName(mid)}`"
                @click="emit('remove', mid)"
            >
                ✕
            </button>
        </li>
        <li v-if="!editMemberIds.length" class="text-xs italic text-brand-blue/40">No members yet.</li>
    </ul>

    <!-- Add member -->
    <div class="relative mb-4">
        <div class="mb-1 flex items-center justify-between">
            <span class="text-xs text-brand-blue/50">
                {{ availableCount === 0 ? 'No unassigned members' : `${availableCount} member${availableCount === 1 ? '' : 's'} available to add` }}
            </span>
        </div>
        <input
            type="text"
            :value="editSearch"
            :placeholder="availableCount ? 'Click or type to select a member…' : 'All members are assigned'"
            :disabled="availableCount === 0"
            class="w-full rounded-lg border border-brand-mist px-3 py-1.5 text-sm focus:border-brand-blue focus:outline-none focus:ring-1 focus:ring-brand-blue disabled:cursor-not-allowed disabled:bg-brand-mist/30 disabled:text-brand-blue/40"
            @input="emit('update:editSearch', $event.target.value)"
            @focus="onFocus"
            @blur="onBlur"
        />
        <ul
            v-if="showDropdown && editSearchResults.length"
            class="absolute z-20 mt-1 max-h-52 w-full overflow-y-auto rounded-lg border border-brand-mist bg-white shadow-lg"
        >
            <li
                v-for="m in editSearchResults"
                :key="m.id"
                class="cursor-pointer px-3 py-2 text-sm text-brand-navy hover:bg-brand-mist/40"
                @mousedown.prevent="emit('add', m.id)"
            >
                {{ m.display_name }}
            </li>
            <li
                v-if="(matchCount ?? availableCount) > editSearchResults.length"
                class="px-3 py-1.5 text-xs italic text-brand-blue/40"
            >
                {{ (matchCount ?? availableCount) - editSearchResults.length }} more — type to filter
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
