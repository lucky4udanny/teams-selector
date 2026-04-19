<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { BuildingOffice2Icon } from '@heroicons/vue/24/outline';

defineProps({
    organizations: Array,
});

const form = useForm({
    name: '',
});

const submit = () => {
    form.post(route('organizations.store'));
};
</script>

<template>
    <Head title="Organizations" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Organizations
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <form
                    class="mb-10 overflow-hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                    @submit.prevent="submit"
                >
                    <h3 class="mb-4 flex items-center gap-2 text-sm font-semibold text-slate-800">
                        <BuildingOffice2Icon class="h-5 w-5 text-indigo-600" />
                        New organization
                    </h3>
                    <div>
                        <InputLabel for="name" value="Name" />
                        <TextInput
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="mt-1 block w-full"
                            required
                            autofocus
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>
                    <div class="mt-4 flex justify-end">
                        <PrimaryButton :disabled="form.processing">
                            Create
                        </PrimaryButton>
                    </div>
                </form>

                <ul class="space-y-3">
                    <li v-for="o in organizations" :key="o.id">
                        <Link
                            :href="route('organizations.show', o.slug)"
                            class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-indigo-200 hover:shadow"
                        >
                            <span class="flex items-center gap-3">
                                <img
                                    v-if="o.logo_url"
                                    :src="o.logo_url"
                                    alt=""
                                    class="h-10 w-10 rounded-lg object-cover"
                                />
                                <span
                                    v-else
                                    class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-xs font-medium text-slate-500"
                                    >{{ o.name.charAt(0) }}</span
                                >
                                <span>
                                    <span class="block font-medium text-slate-900">{{
                                        o.name
                                    }}</span>
                                    <span class="text-xs text-slate-500">{{
                                        o.role
                                    }}</span>
                                </span>
                            </span>
                            <span class="text-slate-400">→</span>
                        </Link>
                    </li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
