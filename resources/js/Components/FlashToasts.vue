<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const status = computed(() => page.props.flash?.status);
const error = computed(() => page.props.flash?.error);
</script>

<template>
    <div v-if="status || error" class="mx-auto max-w-7xl px-4 pt-4 sm:px-6 lg:px-8">
        <p v-if="status" class="ts-alert-success text-sm">{{ status }}</p>
        <p v-if="error" class="ts-alert-danger mt-2 text-sm">{{ error }}</p>
    </div>
</template>
