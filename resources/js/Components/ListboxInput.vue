<script setup>
import {
    Listbox,
    ListboxButton,
    ListboxOption,
    ListboxOptions,
} from '@headlessui/vue';
import { CheckIcon, ChevronUpDownIcon } from '@heroicons/vue/20/solid';
import { computed, ref } from 'vue';

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
    /**
     * When true, the dropdown is teleported to <body> using position:fixed so it
     * escapes any overflow:hidden ancestor (e.g. a rounded table container).
     */
    portal: {
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

const buttonRef = ref(null);
const portalStyle = ref({});

const optionKey = (option, index) => {
    const raw = option[props.valueKey];
    if (raw === null || raw === undefined) {
        return `opt-null-${index}`;
    }
    if (raw === '') {
        return `opt-empty-${index}`;
    }

    return `opt-${String(raw)}-${index}`;
};

const recalcPortalPosition = () => {
    if (!props.portal) return;
    const el = buttonRef.value?.$el ?? buttonRef.value;
    if (!el) return;

    const rect = el.getBoundingClientRect();
    const estimatedHeight = Math.min(props.options.length * 40 + 8, 240);
    const spaceBelow = window.innerHeight - rect.bottom;
    const openUpward = spaceBelow < estimatedHeight && rect.top > estimatedHeight;

    if (openUpward) {
        portalStyle.value = {
            position: 'fixed',
            bottom: window.innerHeight - rect.top + 'px',
            left: rect.left + 'px',
            width: rect.width + 'px',
            zIndex: 9999,
        };
    } else {
        portalStyle.value = {
            position: 'fixed',
            top: rect.bottom + 4 + 'px',
            left: rect.left + 'px',
            width: rect.width + 'px',
            zIndex: 9999,
        };
    }
};
</script>

<template>
    <Listbox v-model="selected" :disabled="disabled" :by="valueKey">
        <div class="relative">
            <ListboxButton
                ref="buttonRef"
                :class="[
                    'ts-input relative w-full cursor-default rounded-lg py-2.5 pl-3 pr-10 text-left text-sm',
                    error ? 'ts-input-error' : '',
                    disabled ? 'cursor-not-allowed opacity-50' : '',
                ]"
                @click="recalcPortalPosition"
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
                <Teleport v-if="portal" to="body">
                    <ListboxOptions
                        :style="portalStyle"
                        class="max-h-60 overflow-auto rounded-lg bg-white py-1 text-sm shadow-lg ring-1 ring-black/5 focus:outline-none"
                    >
                        <ListboxOption
                            v-for="(option, index) in options"
                            :key="optionKey(option, index)"
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
                </Teleport>
                <ListboxOptions
                    v-else
                    class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-lg bg-white py-1 text-sm shadow-lg ring-1 ring-black/5 focus:outline-none"
                >
                    <ListboxOption
                        v-for="(option, index) in options"
                        :key="optionKey(option, index)"
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
