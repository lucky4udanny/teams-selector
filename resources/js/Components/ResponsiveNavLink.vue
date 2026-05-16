<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
    },
});

const classes = computed(() =>
    props.active
        ? 'block w-full border-l-4 border-brand-orange bg-brand-blue/5 py-2 ps-3 pe-4 text-start text-base font-semibold text-brand-navy transition duration-150 ease-in-out focus:outline-none'
        : 'block w-full border-l-4 border-transparent py-2 ps-3 pe-4 text-start text-base font-medium text-brand-blue/80 transition duration-150 ease-in-out hover:border-brand-blue/30 hover:bg-brand-mist/50 hover:text-brand-navy focus:outline-none',
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
