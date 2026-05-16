<script setup>
defineProps({
    variant: {
        type: String,
        default: 'icon',
    },
});

const sources = {
    icon: '/icon_small_blue.svg',
    full: '/Logo.svg',
    wordmark: '/Wordmark.svg',
};
</script>

<template>
    <img
        :src="sources[variant] ?? sources.icon"
        alt=""
        class="block h-full w-auto object-contain"
    />
</template>
