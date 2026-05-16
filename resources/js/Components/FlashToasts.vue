<script setup>
import Alert from '@/Components/Alert.vue';
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const dismissed = ref({ status: false, error: false });

const status = computed(() => page.props.flash?.status);
const error = computed(() => page.props.flash?.error);

watch([status, error], () => {
    dismissed.value = { status: false, error: false };
});
</script>

<template>
    <div
        v-if="(status && !dismissed.status) || (error && !dismissed.error)"
        class="pointer-events-none fixed right-4 top-4 z-50 flex w-full max-w-sm flex-col gap-2"
    >
        <Transition
            enter-active-class="ease-out duration-200"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0 -translate-y-2"
        >
            <div v-if="status && !dismissed.status" class="pointer-events-auto">
                <Alert variant="success" dismissible @dismiss="dismissed.status = true">
                    {{ status }}
                </Alert>
            </div>
        </Transition>
        <Transition
            enter-active-class="ease-out duration-200"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0 -translate-y-2"
        >
            <div v-if="error && !dismissed.error" class="pointer-events-auto">
                <Alert variant="error" dismissible @dismiss="dismissed.error = true">
                    {{ error }}
                </Alert>
            </div>
        </Transition>
    </div>
</template>
