<script setup>
import OrganizationLayout from '@/Layouts/OrganizationLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    organization: Object,
    drafts: Array,
});

const form = useForm({
    name: '',
});

const create = () => {
    form.post(route('organizations.drafts.store', props.organization.slug), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <Head title="Drafts" />

    <OrganizationLayout :organization="organization">
        <template #header>
            <h1 class="text-2xl font-bold text-slate-900">Drafts</h1>
        </template>

        <form
            class="mb-8 flex flex-wrap items-end gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
            @submit.prevent="create"
        >
            <div>
                <InputLabel for="name" value="Name (optional)" />
                <TextInput
                    id="name"
                    v-model="form.name"
                    class="mt-1 block w-full min-w-[200px]"
                />
            </div>
            <PrimaryButton :disabled="form.processing">New draft</PrimaryButton>
        </form>

        <ul class="space-y-2">
            <li v-for="d in drafts" :key="d.id">
                <Link
                    :href="
                        route('organizations.drafts.show', [
                            organization.slug,
                            d.id,
                        ])
                    "
                    class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm transition hover:border-indigo-200"
                >
                    <span>
                        <span class="font-medium text-slate-900">{{
                            d.name || 'Draft #' + d.id
                        }}</span>
                        <span class="ms-2 text-xs text-slate-500">{{
                            d.updated_at
                        }}</span>
                    </span>
                    <span
                        v-if="d.total_penalty != null"
                        class="text-xs text-amber-700"
                        >Penalty {{ d.total_penalty }}</span
                    >
                </Link>
            </li>
        </ul>
    </OrganizationLayout>
</template>
