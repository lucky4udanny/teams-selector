<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, useAttrs } from 'vue';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
    href: {
        type: String,
        default: null,
    },
});

defineOptions({ inheritAttrs: false });

const attrs = useAttrs();

const tag = computed(() => (props.href ? Link : 'div'));

// Treat as interactive when href is set or a click listener is bound
const isInteractive = computed(() => !!props.href || !!attrs.onClick);
</script>

<template>
    <component
        :is="tag"
        :href="href ?? undefined"
        class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-brand-mist bg-white px-6 py-16 text-center transition-colors duration-150"
        :class="isInteractive ? 'cursor-pointer hover:border-brand-blue/40 hover:bg-brand-cream/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue' : ''"
        v-bind="attrs"
    >
        <div class="ts-icon-tile-sky mb-4">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4" />
            </svg>
        </div>
        <h3 class="text-lg font-semibold text-brand-navy">{{ title }}</h3>
        <p v-if="description" class="mt-2 max-w-sm text-sm text-brand-blue/70">
            {{ description }}
        </p>
        <div v-if="$slots.default" class="mt-6 flex flex-wrap justify-center gap-3">
            <slot />
        </div>
    </component>
</template>
