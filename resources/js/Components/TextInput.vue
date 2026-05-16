<script setup>
import { onMounted, ref } from 'vue';

defineProps({
    error: {
        type: Boolean,
        default: false,
    },
});

const model = defineModel({
    type: [String, Number],
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        ref="input"
        v-model="model"
        :class="['ts-input w-full', error ? 'ts-input-error' : '']"
    />
</template>
