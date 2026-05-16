<script setup>
import RangeSlider from '@/Components/RangeSlider.vue';

const model = defineModel({
    type: Number,
    default: 50,
});

defineProps({
    id: {
        type: String,
        default: 'skill',
    },
});
</script>

<template>
    <div>
        <RangeSlider :id="id" v-model="model" :min="0" :max="100" />
        <div class="mt-1 flex justify-between text-xs text-brand-blue/60">
            <span>No skill</span>
            <span>Average</span>
            <span>Best in class</span>
        </div>
    </div>
</template>
