<script setup>
import EmptyState from '@/Components/EmptyState.vue';
import FormField from '@/Components/FormField.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import OrganizationLayout from '@/Layouts/OrganizationLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

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

const submit = () => {
    form.post(route('organizations.sectors.store', props.organization.slug), {
        preserveScroll: true,
        onSuccess: () => form.reset('name'),
    });
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
                        Sectors label members (for example department or business unit). Used on imports
                        and rosters.
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
            class="ts-card-padded mb-8 flex flex-col gap-4 sm:flex-row sm:items-end"
            @submit.prevent="submit"
        >
            <FormField label="New sector name" name="sector_name" :error="form.errors.name" class="min-w-[16rem] flex-1" required>
                <TextInput
                    id="sector_name"
                    v-model="form.name"
                    :error="!!form.errors.name"
                    autocomplete="off"
                />
            </FormField>
            <PrimaryButton :disabled="form.processing">Add sector</PrimaryButton>
        </form>

        <EmptyState
            v-if="!sectors?.length"
            title="No sectors yet"
            description="Add sectors to classify members for imports and reporting."
        />

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
