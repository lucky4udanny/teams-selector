<script setup lang="ts">
import { computed, ref } from 'vue';

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
    label: {
        type: String,
        default: '',
    },
});

const radius = computed(() => (props.size - props.stroke) / 2);
const circumference = computed(() => 2 * Math.PI * radius.value);
const offset = computed(() => {
    const pct = props.max > 0 ? Math.min(props.value / props.max, 1) : 0;
    return circumference.value * (1 - pct);
});

// Green = few violations (good), amber = moderate, red = many (bad)
const color = computed(() => {
    const pct = props.max > 0 ? (props.value / props.max) * 100 : 0;
    if (pct <= 25) return '#16a34a';
    if (pct <= 60) return '#d97706';
    return '#dc2626';
});

const tooltipVisible = ref(false);
const tooltipStyle = ref<Record<string, string>>({});

const TOOLTIP_WIDTH = 224; // w-56 = 14rem
const TOOLTIP_MARGIN = 8;

function showTooltip(e: MouseEvent) {
    const rect = (e.currentTarget as HTMLElement).getBoundingClientRect();

    // Default: horizontally centered below the element
    let left = rect.left + rect.width / 2 - TOOLTIP_WIDTH / 2;

    // Clamp so the tooltip stays within the visible viewport
    left = Math.max(
        TOOLTIP_MARGIN,
        Math.min(left, window.innerWidth - TOOLTIP_WIDTH - TOOLTIP_MARGIN),
    );

    tooltipStyle.value = {
        top: `${rect.bottom + 8}px`,
        left: `${left}px`,
    };
    tooltipVisible.value = true;
}

function hideTooltip() {
    tooltipVisible.value = false;
}
</script>

<template>
    <div
        class="relative shrink-0 cursor-default"
        @mouseenter="showTooltip"
        @mouseleave="hideTooltip"
        @focusin="showTooltip"
        @focusout="hideTooltip"
    >
        <svg
            :width="size"
            :height="size"
            class="-rotate-90"
            role="img"
            :aria-label="label || `${value} of ${max} violations`"
            tabindex="0"
        >
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

        <Teleport to="body">
            <div
                v-if="tooltipVisible"
                role="tooltip"
                class="pointer-events-none fixed z-50 w-56 rounded-lg border border-brand-mist bg-white px-3 py-2 text-xs leading-relaxed text-brand-navy shadow-lg"
                :style="tooltipStyle"
            >
                <p class="font-semibold">{{ value }} violation{{ value === 1 ? '' : 's' }}</p>
                <p class="mt-1 text-brand-blue/70">
                    {{ label || 'Green = few violations, red = many. This reflects how constrained your rules are — all drafts for the same event tend to converge to a similar score.' }}
                </p>
            </div>
        </Teleport>
    </div>
</template>
