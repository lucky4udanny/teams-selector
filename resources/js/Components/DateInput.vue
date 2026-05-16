<script setup>
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';
import { computed } from 'vue';

const model = defineModel({
    type: [String, Date, null],
    default: null,
});

const props = defineProps({
    id: {
        type: String,
        default: 'date',
    },
    error: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const toYmd = (value) => {
    if (value instanceof Date) {
        const y = value.getFullYear();
        const m = String(value.getMonth() + 1).padStart(2, '0');
        const day = String(value.getDate()).padStart(2, '0');

        return `${y}-${m}-${day}`;
    }

    const text = String(value).trim();
    if (text === '') {
        return null;
    }

    return text.length >= 10 ? text.slice(0, 10) : text;
};

const internal = computed({
    get() {
        if (model.value == null || model.value === '') {
            return null;
        }

        return toYmd(model.value);
    },
    set(value) {
        model.value = value == null || value === '' ? null : toYmd(value);
    },
});
</script>

<template>
    <VueDatePicker
        :uid="id"
        v-model="internal"
        :disabled="disabled"
        :enable-time-picker="false"
        auto-apply
        format="yyyy-MM-dd"
        model-type="yyyy-MM-dd"
        text-input
        :input-class-name="error ? 'ts-input ts-input-error' : 'ts-input'"
        class="ts-date-picker"
    />
</template>

<style>
.ts-date-picker .dp__input {
    border-radius: 0.5rem;
    border: 1px solid var(--color-brand-mist, #e8f1f7);
    padding-top: 0.625rem;
    padding-bottom: 0.625rem;
    font-size: 0.875rem;
    line-height: 1.25rem;
    color: var(--color-brand-navy, #0a3557);
    box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
}
.ts-date-picker .dp__input:focus {
    border-color: var(--color-brand-blue, #1a6893);
    outline: none;
    box-shadow: 0 0 0 2px rgb(26 104 147 / 0.3);
}
</style>
