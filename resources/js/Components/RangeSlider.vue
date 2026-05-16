<script setup>
import { computed } from 'vue';

const model = defineModel({
    type: Number,
    required: true,
});

const props = defineProps({
    min: {
        type: Number,
        default: 0,
    },
    max: {
        type: Number,
        default: 100,
    },
    step: {
        type: Number,
        default: 1,
    },
    id: {
        type: String,
        default: 'range',
    },
});

const percent = computed(() => {
    const range = props.max - props.min;

    if (range <= 0) {
        return 0;
    }

    return ((model.value - props.min) / range) * 100;
});

const trackStyle = computed(() => ({
    background: `linear-gradient(to right, var(--color-brand-blue) 0%, var(--color-brand-blue) ${percent.value}%, var(--color-brand-mist) ${percent.value}%, var(--color-brand-mist) 100%)`,
}));
</script>

<template>
    <div class="relative pt-6">
        <div
            class="pointer-events-none absolute left-0 top-0 -translate-x-1/2 rounded-md bg-brand-navy px-2 py-0.5 text-xs font-semibold text-white shadow-sm transition-all"
            :style="{ left: `${percent}%` }"
        >
            {{ model }}
        </div>
        <input
            :id="id"
            v-model.number="model"
            type="range"
            :min="min"
            :max="max"
            :step="step"
            class="ts-range-track w-full"
            :style="trackStyle"
        />
    </div>
</template>
