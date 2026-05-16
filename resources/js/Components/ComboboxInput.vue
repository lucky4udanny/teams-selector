<script setup>
import {
    Combobox,
    ComboboxButton,
    ComboboxInput,
    ComboboxOption,
    ComboboxOptions,
} from '@headlessui/vue';
import { CheckIcon, ChevronUpDownIcon, XMarkIcon } from '@heroicons/vue/20/solid';
import { computed, ref } from 'vue';

const model = defineModel({
    type: [String, Number, Array, null],
    default: null,
});

const props = defineProps({
    options: {
        type: Array,
        required: true,
    },
    labelKey: {
        type: String,
        default: 'label',
    },
    valueKey: {
        type: String,
        default: 'value',
    },
    placeholder: {
        type: String,
        default: 'Search…',
    },
    multiple: {
        type: Boolean,
        default: false,
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

const query = ref('');

const filteredOptions = computed(() => {
    if (query.value === '') {
        return props.options;
    }

    const q = query.value.toLowerCase();

    return props.options.filter((o) =>
        String(o[props.labelKey]).toLowerCase().includes(q),
    );
});

const selectedSingle = computed({
    get() {
        if (props.multiple) {
            return null;
        }

        return props.options.find((o) => o[props.valueKey] === model.value) ?? null;
    },
    set(option) {
        model.value = option ? option[props.valueKey] : null;
        query.value = '';
    },
});

const selectedMultiple = computed({
    get() {
        if (!props.multiple) {
            return [];
        }

        const values = Array.isArray(model.value) ? model.value : [];

        return props.options.filter((o) => values.includes(o[props.valueKey]));
    },
    set(options) {
        model.value = options.map((o) => o[props.valueKey]);
    },
});

const removeChip = (value) => {
    if (!props.multiple || !Array.isArray(model.value)) {
        return;
    }

    model.value = model.value.filter((v) => v !== value);
};
</script>

<template>
    <Combobox
        v-if="!multiple"
        v-model="selectedSingle"
        :disabled="disabled"
        nullable
    >
        <div class="relative">
            <ComboboxInput
                :class="[
                    'ts-input w-full rounded-lg py-2.5 pl-3 pr-10 text-sm',
                    error ? 'ts-input-error' : '',
                ]"
                :display-value="(o) => (o ? o[labelKey] : '')"
                :placeholder="placeholder"
                @change="query = $event.target.value"
            />
            <ComboboxButton class="absolute inset-y-0 right-0 flex items-center pr-2">
                <ChevronUpDownIcon class="h-5 w-5 text-brand-blue/50" />
            </ComboboxButton>
            <ComboboxOptions
                class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-lg bg-white py-1 text-sm shadow-lg ring-1 ring-black/5"
            >
                <ComboboxOption
                    v-for="option in filteredOptions"
                    :key="option[valueKey]"
                    v-slot="{ active, selected }"
                    :value="option"
                    as="template"
                >
                    <li
                        :class="[
                            active ? 'bg-brand-blue/10' : '',
                            'relative cursor-default select-none py-2 pl-10 pr-4 text-brand-navy',
                        ]"
                    >
                        <span :class="selected ? 'font-semibold' : ''">{{ option[labelKey] }}</span>
                        <span v-if="selected" class="absolute inset-y-0 left-0 flex items-center pl-3 text-brand-blue">
                            <CheckIcon class="h-5 w-5" />
                        </span>
                    </li>
                </ComboboxOption>
            </ComboboxOptions>
        </div>
    </Combobox>

    <Combobox v-else v-model="selectedMultiple" multiple :disabled="disabled">
        <div class="relative">
            <div
                :class="[
                    'ts-input flex min-h-[42px] flex-wrap items-center gap-1 rounded-lg py-1.5 pl-2 pr-10',
                    error ? 'ts-input-error' : '',
                ]"
            >
                <span
                    v-for="chip in selectedMultiple"
                    :key="chip[valueKey]"
                    class="inline-flex items-center gap-1 rounded-md bg-brand-blue/10 px-2 py-0.5 text-xs font-medium text-brand-navy"
                >
                    {{ chip[labelKey] }}
                    <button
                        type="button"
                        class="text-brand-blue/60 hover:text-brand-navy"
                        @click.stop="removeChip(chip[valueKey])"
                    >
                        <XMarkIcon class="h-3.5 w-3.5" />
                    </button>
                </span>
                <ComboboxInput
                    class="min-w-[8rem] flex-1 border-0 bg-transparent py-1 text-sm focus:ring-0"
                    :placeholder="selectedMultiple.length ? '' : placeholder"
                    @change="query = $event.target.value"
                />
            </div>
            <ComboboxButton class="absolute inset-y-0 right-0 flex items-center pr-2">
                <ChevronUpDownIcon class="h-5 w-5 text-brand-blue/50" />
            </ComboboxButton>
            <ComboboxOptions
                class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-lg bg-white py-1 text-sm shadow-lg ring-1 ring-black/5"
            >
                <ComboboxOption
                    v-for="option in filteredOptions"
                    :key="option[valueKey]"
                    v-slot="{ active, selected }"
                    :value="option"
                    as="template"
                >
                    <li
                        :class="[
                            active ? 'bg-brand-blue/10' : '',
                            'relative cursor-default select-none py-2 pl-10 pr-4',
                        ]"
                    >
                        <span :class="selected ? 'font-semibold' : ''">{{ option[labelKey] }}</span>
                        <span v-if="selected" class="absolute inset-y-0 left-0 flex items-center pl-3 text-brand-blue">
                            <CheckIcon class="h-5 w-5" />
                        </span>
                    </li>
                </ComboboxOption>
            </ComboboxOptions>
        </div>
    </Combobox>
</template>
