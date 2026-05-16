<script setup>
import { Switch } from '@headlessui/vue';

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
});
</script>

<template>
    <Switch.Group as="div" class="flex items-center gap-3">
        <Switch
            :id="id"
            v-model="model"
            :class="[
                model ? 'bg-brand-blue' : 'bg-brand-mist',
                'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-brand-blue/30 focus:ring-offset-2',
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
        <Switch.Label v-if="label" class="text-sm text-brand-navy">
            {{ label }}
        </Switch.Label>
    </Switch.Group>
</template>
