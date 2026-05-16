<script setup>
import {
    Listbox,
    ListboxButton,
    ListboxOption,
    ListboxOptions,
} from '@headlessui/vue';
import { CheckIcon, ChevronUpDownIcon } from '@heroicons/vue/20/solid';
import { computed } from 'vue';

const model = defineModel({
    type: [String, Number, null],
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
        default: 'Select…',
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

const selected = computed({
    get() {
        return props.options.find((o) => o[props.valueKey] === model.value) ?? null;
    },
    set(option) {
        model.value = option ? option[props.valueKey] : null;
    },
});
</script>

<template>
    <Listbox v-model="selected" :disabled="disabled" by="value">
        <div class="relative">
            <ListboxButton
                :class="[
                    'ts-input relative w-full cursor-default rounded-lg py-2.5 pl-3 pr-10 text-left text-sm',
                    error ? 'ts-input-error' : '',
                    disabled ? 'cursor-not-allowed opacity-50' : '',
                ]"
            >
                <span :class="selected ? 'text-brand-navy' : 'text-brand-blue/40'">
                    {{ selected ? selected[labelKey] : placeholder }}
                </span>
                <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                    <ChevronUpDownIcon class="h-5 w-5 text-brand-blue/50" aria-hidden="true" />
                </span>
            </ListboxButton>
            <transition
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <ListboxOptions
                    class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-lg bg-white py-1 text-sm shadow-lg ring-1 ring-black/5 focus:outline-none"
                >
                    <ListboxOption
                        v-for="option in options"
                        :key="option[valueKey]"
                        v-slot="{ active, selected: isSelected }"
                        :value="option"
                        as="template"
                    >
                        <li
                            :class="[
                                active ? 'bg-brand-blue/10 text-brand-navy' : 'text-brand-navy',
                                'relative cursor-default select-none py-2 pl-10 pr-4',
                            ]"
                        >
                            <span :class="isSelected ? 'font-semibold' : 'font-normal'">
                                {{ option[labelKey] }}
                            </span>
                            <span
                                v-if="isSelected"
                                class="absolute inset-y-0 left-0 flex items-center pl-3 text-brand-blue"
                            >
                                <CheckIcon class="h-5 w-5" aria-hidden="true" />
                            </span>
                        </li>
                    </ListboxOption>
                </ListboxOptions>
            </transition>
        </div>
    </Listbox>
</template>
