<script setup>
import { computed } from 'vue';

const HEX_RE = /^#[0-9A-Fa-f]{6}$/;

/** Valid #RRGGBB (trimmed, lowercased) or fallback for picker/swatch. */
const resolveHex = (value, fallback) => {
    const trimmed = value?.trim() ?? '';
    return HEX_RE.test(trimmed) ? trimmed.toLowerCase() : fallback;
};

const model = defineModel({
    type: String,
    required: true,
});

const props = defineProps({
    id: {
        type: String,
        required: true,
    },
    placeholder: {
        type: String,
        default: '#000000',
    },
});

const pickerValue = computed(() =>
    resolveHex(model.value, props.placeholder),
);

const swatchStyle = computed(() => ({
    backgroundColor: pickerValue.value,
}));

const onPickerInput = (event) => {
    model.value = event.target.value;
};

const onTextInput = (event) => {
    let value = event.target.value.trim();
    if (value && !value.startsWith('#')) {
        value = `#${value}`;
    }
    model.value = value;
};

const onTextBlur = () => {
    const normalized = resolveHex(model.value, '');
    if (normalized) {
        model.value = normalized;
    }
};
</script>

<template>
    <div
        class="mt-1 flex rounded-lg shadow-sm ring-1 ring-inset ring-brand-mist focus-within:ring-2 focus-within:ring-brand-blue/30"
    >
        <div class="relative shrink-0">
            <input
                :id="`${id}-picker`"
                type="color"
                :value="pickerValue"
                class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                :aria-label="`Pick ${id} color`"
                @input="onPickerInput"
            />
            <div
                class="flex h-10 w-11 items-center justify-center rounded-l-lg border-r border-brand-mist bg-brand-cream"
                aria-hidden="true"
            >
                <span
                    class="h-6 w-6 rounded-md border border-brand-navy/10 shadow-inner ring-1 ring-inset ring-black/5"
                    :style="swatchStyle"
                />
            </div>
        </div>
        <input
            :id="id"
            type="text"
            :value="model"
            :placeholder="placeholder"
            spellcheck="false"
            autocapitalize="off"
            class="block min-w-0 flex-1 rounded-r-lg border-0 bg-white py-2 pl-3 pr-3 font-mono text-sm text-brand-navy placeholder:text-brand-blue/40 focus:ring-0"
            @input="onTextInput"
            @blur="onTextBlur"
        />
    </div>
</template>
