<script setup>
import { computed } from 'vue';

const props = defineProps({
    value: {
        type: Number,
        default: 0,
    },
    max: {
        type: Number,
        default: 100,
    },
    size: {
        type: Number,
        default: 40,
    },
    stroke: {
        type: Number,
        default: 4,
    },
});

const radius = computed(() => (props.size - props.stroke) / 2);
const circumference = computed(() => 2 * Math.PI * radius.value);
const offset = computed(() => {
    const pct = props.max > 0 ? Math.min(props.value / props.max, 1) : 0;

    return circumference.value * (1 - pct);
});

const color = computed(() => {
    const pct = props.max > 0 ? (props.value / props.max) * 100 : 0;

    if (pct >= 70) {
        return '#16a34a';
    }

    if (pct >= 40) {
        return '#d97706';
    }

    return '#dc2626';
});
</script>

<template>
    <svg :width="size" :height="size" class="-rotate-90" role="img" :aria-label="`${value} of ${max}`">
        <circle
            :cx="size / 2"
            :cy="size / 2"
            :r="radius"
            fill="none"
            stroke="#e8f1f7"
            :stroke-width="stroke"
        />
        <circle
            :cx="size / 2"
            :cy="size / 2"
            :r="radius"
            fill="none"
            :stroke="color"
            :stroke-width="stroke"
            stroke-linecap="round"
            :stroke-dasharray="circumference"
            :stroke-dashoffset="offset"
            class="transition-all duration-300"
        />
    </svg>
</template>
