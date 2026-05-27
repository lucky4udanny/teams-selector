<script setup>
import ListboxInput from '@/Components/ListboxInput.vue';
import Badge from '@/Components/Badge.vue';
import { rosterStatusVariant } from '@/utils/rosterStatus';
import { computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        required: true,
    },
    included: {
        type: Boolean,
        default: true,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue']);

const statusOptions = [
    { label: 'Pending', value: 'pending' },
    { label: 'Accepted', value: 'accepted' },
    { label: 'Declined', value: 'declined' },
];

const variant = computed(() =>
    rosterStatusVariant({ included: props.included, status: props.modelValue }),
);

const label = computed(() => {
    if (!props.included) {
        return 'Waiting list';
    }

    return statusOptions.find((o) => o.value === props.modelValue)?.label ?? props.modelValue;
});

const onStatusChange = (value) => {
    emit('update:modelValue', value);
};
</script>

<template>
    <div v-if="disabled || !included" class="inline-block">
        <Badge :variant="variant">{{ label }}</Badge>
    </div>
    <div v-else class="w-36">
        <ListboxInput
            :model-value="modelValue"
            :options="statusOptions"
            portal
            @update:model-value="onStatusChange"
        />
    </div>
</template>
