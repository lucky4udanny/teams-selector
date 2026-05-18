<script setup>
import {
    CheckCircleIcon,
    ExclamationCircleIcon,
    ExclamationTriangleIcon,
    InformationCircleIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import { computed } from 'vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'info',
        validator: (v) => ['info', 'success', 'warning', 'error'].includes(v),
    },
    dismissible: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['dismiss']);

const classes = computed(() => {
    const map = {
        info: 'ts-alert-info',
        success: 'ts-alert-success',
        warning: 'ts-alert-warning',
        error: 'ts-alert-danger',
    };

    return map[props.variant];
});

const Icon = computed(() => {
    const map = {
        info: InformationCircleIcon,
        success: CheckCircleIcon,
        warning: ExclamationTriangleIcon,
        error: ExclamationCircleIcon,
    };

    return map[props.variant];
});
</script>

<template>
    <div :class="[classes, 'flex items-start gap-3 text-sm']" role="alert">
        <component :is="Icon" class="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
        <div class="min-w-0 flex-1">
            <slot />
        </div>
        <button
            v-if="dismissible"
            type="button"
            class="shrink-0 cursor-pointer rounded p-0.5 opacity-70 hover:opacity-100 active:opacity-60 focus:outline-none focus:ring-2 focus:ring-brand-blue/30"
            aria-label="Dismiss"
            @click="emit('dismiss')"
        >
            <XMarkIcon class="h-4 w-4" />
        </button>
    </div>
</template>
