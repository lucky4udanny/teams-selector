<script setup>
import { Switch, SwitchGroup, SwitchLabel } from '@headlessui/vue';

const model = defineModel({
    type: Boolean,
    default: false,
});

defineProps({
    label: {
        type: String,
        default: '',
    },
    id: {
        type: String,
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <SwitchGroup as="div" class="flex items-center gap-3">
        <Switch
            :id="id"
            v-model="model"
            :disabled="disabled"
            :class="[
                model ? 'bg-brand-blue' : 'bg-white ring-2 ring-brand-blue/25',
                disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer',
                'relative inline-flex h-6 w-11 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-brand-blue/30 focus:ring-offset-2 data-disabled:cursor-not-allowed data-disabled:opacity-50',
            ]"
        >
            <span
                aria-hidden="true"
                :class="[
                    model ? 'translate-x-5' : 'translate-x-0',
                    'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                ]"
            />
        </Switch>
        <SwitchLabel v-if="label" class="text-sm text-brand-navy">
            {{ label }}
        </SwitchLabel>
    </SwitchGroup>
</template>
