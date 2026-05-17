<script setup>
import Alert from '@/Components/Alert.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormField from '@/Components/FormField.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import OrganizationLayout from '@/Layouts/OrganizationLayout.vue';
import { requireTrimmedName } from '@/utils/formValidation';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, nextTick } from 'vue';

const props = defineProps({
    organization: Object,
    sectors: Array,
});

const canManage = computed(() =>
    ['admin', 'organizer'].includes(props.organization?.role),
);

const form = useForm({
    name: '',
});

const formHasErrors = computed(
    () => Object.keys(form.errors).length > 0 && !form.processing,
);

const submit = () => {
    form.clearErrors();

    const name = requireTrimmedName(form.name, 'Sector name');
    if (!name.ok) {
        form.setError('name', name.message);
        nextTick(() => document.getElementById('sector_name')?.focus());

        return;
    }

    form.name = name.value;
    form.post(route('organizations.sectors.store', props.organization.slug), {
        preserveScroll: true,
        onSuccess: () => form.reset('name'),
        onError: () => nextTick(() => document.getElementById('sector_name')?.focus()),
    });
};

const focusAddForm = () => {
    document.getElementById('sector_name')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    document.getElementById('sector_name')?.focus();
};
</script>

<template>
    <Head title="Sectors" />

    <OrganizationLayout :organization="organization">
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="ts-heading-page">Sectors</h1>
                    <p class="mt-1 text-sm text-brand-blue/70">
                        Sectors label members (for example department or business unit). Used on
                        imports and rosters.
                    </p>
                </div>
                <Link
                    :href="route('organizations.members.index', organization.slug)"
                    class="text-sm font-medium text-brand-blue hover:text-brand-navy"
                >
                    ← Back to members
                </Link>
            </div>
        </template>

        <form
            v-if="canManage"
            class="ts-card-padded mb-8"
            @submit.prevent="submit"
        >
            <h2 class="ts-heading-section mb-4">Add sector</h2>
            <Alert v-if="formHasErrors" variant="error" class="mb-4" role="alert">
                Please fix the error below before adding the sector.
            </Alert>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                <FormField
                    label="Sector name"
                    name="sector_name"
                    :error="form.errors.name"
                    hint="Required. Must be unique in this organization."
                    class="min-w-[16rem] flex-1"
                    required
                >
                    <TextInput
                        id="sector_name"
                        v-model="form.name"
                        :error="!!form.errors.name"
                        autocomplete="off"
                        required
                    />
                </FormField>
                <PrimaryButton :disabled="form.processing || !form.name?.trim()">
                    {{ form.processing ? 'Adding…' : 'Add sector' }}
                </PrimaryButton>
            </div>
        </form>

        <EmptyState
            v-if="!sectors?.length"
            title="No sectors yet"
            description="Add sectors to classify members for imports and reporting."
            @click="focusAddForm"
        >
            <span class="text-sm font-medium text-brand-blue/60">Fill in the form above to add your first sector</span>
        </EmptyState>

        <ul
            v-else
            class="divide-y divide-brand-mist overflow-hidden rounded-xl border border-brand-mist bg-white shadow-sm"
        >
            <li
                v-for="s in sectors"
                :key="s.id"
                class="flex items-center justify-between px-4 py-3 text-sm text-brand-navy"
            >
                <span class="font-medium">{{ s.name }}</span>
            </li>
        </ul>

        <p v-if="!canManage" class="mt-6 text-sm text-brand-blue/70">
            Viewers cannot add sectors.
        </p>
    </OrganizationLayout>
</template>
