<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { ExclamationCircleIcon } from '@heroicons/vue/24/solid';

defineProps({
    label: {
        type: String,
        default: '',
    },
    name: {
        type: String,
        required: true,
    },
    error: {
        type: String,
        default: '',
    },
    required: {
        type: Boolean,
        default: false,
    },
    hint: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div>
        <InputLabel v-if="label" :for="name" :value="label">
            <span v-if="required" class="text-red-500">*</span>
        </InputLabel>
        <div class="relative mt-1">
            <slot :error="error" />
            <ExclamationCircleIcon
                v-if="error"
                class="pointer-events-none absolute right-3 top-1/2 h-5 w-5 -translate-y-1/2 text-red-500"
                aria-hidden="true"
            />
        </div>
        <p v-if="hint && !error" class="mt-1 text-xs text-brand-blue/60">
            {{ hint }}
        </p>
        <InputError class="mt-1" :message="error" />
    </div>
</template>
