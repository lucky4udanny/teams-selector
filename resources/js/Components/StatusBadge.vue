<script setup>
import ListboxInput from '@/Components/ListboxInput.vue';
import Badge from '@/Components/Badge.vue';
import { rosterStatusVariant } from '@/utils/rosterStatus';
import { computed } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const props = defineProps({
    included: {
        type: Boolean,
        default: true,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const statusOptions = [
    { label: 'Pending', value: 'pending' },
    { label: 'Accepted', value: 'accepted' },
    { label: 'Declined', value: 'declined' },
];

const variant = computed(() =>
    rosterStatusVariant({ included: props.included, status: model.value }),
);

const label = computed(() => {
    if (!props.included) {
        return 'Waiting list';
    }

    return statusOptions.find((o) => o.value === model.value)?.label ?? model.value;
});
</script>

<template>
    <div v-if="disabled || !included" class="inline-block">
        <Badge :variant="variant">{{ label }}</Badge>
    </div>
    <div v-else class="w-36">
        <ListboxInput v-model="model" :options="statusOptions" portal />
    </div>
</template>
