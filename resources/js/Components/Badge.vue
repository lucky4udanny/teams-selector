<script setup>
const props = defineProps({
    variant: {
        type: String,
        default: 'neutral',
        validator: (v) => ['info', 'success', 'warning', 'danger', 'neutral'].includes(v),
    },
});

const variantClass = {
    info: 'ts-badge ts-badge-info',
    success: 'ts-badge ts-badge-success',
    warning: 'ts-badge ts-badge-warning',
    danger: 'ts-badge ts-badge-danger',
    neutral: 'ts-badge ts-badge-neutral',
};
</script>

<template>
    <span :class="variantClass[variant]">
        <slot />
    </span>
</template>
