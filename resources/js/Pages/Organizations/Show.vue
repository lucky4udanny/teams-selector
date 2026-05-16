<script setup>
import OrganizationLayout from '@/Layouts/OrganizationLayout.vue';
import ColorInput from '@/Components/ColorInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    CalendarDaysIcon,
    UsersIcon,
} from '@heroicons/vue/24/outline';
import { computed } from 'vue';

const props = defineProps({
    organization: Object,
    events: {
        type: Array,
        default: () => [],
    },
});

const isAdmin = computed(() => props.organization.role === 'admin');

const settingsForm = useForm({
    brand_primary: props.organization.brand_primary || '#1a6893',
    brand_accent: props.organization.brand_accent || '#f7941c',
    logo: null,
    remove_logo: false,
});

const pickLogo = (e) => {
    const f = e.target.files?.[0];
    settingsForm.logo = f || null;
};

const saveSettings = () => {
    settingsForm.post(
        route('organizations.settings.update', props.organization.slug),
        { forceFormData: true, preserveScroll: true },
    );
};
</script>

<template>
    <Head :title="organization.name" />

    <OrganizationLayout :organization="organization">
        <template #header>
            <h1 class="ts-heading-page">
                {{ organization.name }}
            </h1>
        </template>

        <div class="grid gap-6 md:grid-cols-2">
            <Link
                :href="route('organizations.members.index', organization.slug)"
                class="ts-card-interactive"
            >
                <div class="ts-icon-tile-blue">
                    <UsersIcon class="h-7 w-7" />
                </div>
                <div>
                    <p class="font-semibold text-brand-navy">Members</p>
                    <p class="text-sm text-brand-blue/70">
                        {{ organization.counts.members }} people
                    </p>
                </div>
            </Link>
            <Link
                :href="route('organizations.events.index', organization.slug)"
                class="ts-card-interactive"
            >
                <div class="ts-icon-tile-orange">
                    <CalendarDaysIcon class="h-7 w-7" />
                </div>
                <div>
                    <p class="font-semibold text-brand-navy">Events</p>
                    <p class="text-sm text-brand-blue/70">
                        {{ events.length }} scheduled
                    </p>
                </div>
            </Link>
        </div>

        <section
            v-if="events.length"
            class="ts-card-padded mt-8"
        >
            <h2 class="ts-heading-section mb-4">Upcoming &amp; recent</h2>
            <ul class="divide-y divide-brand-mist">
                <li
                    v-for="ev in events"
                    :key="ev.id"
                    class="py-3 first:pt-0"
                >
                    <Link
                        class="flex flex-wrap items-baseline justify-between gap-2 hover:text-brand-navy"
                        :href="
                            route('organizations.events.show', {
                                organization: organization.slug,
                                event: ev.id,
                            })
                        "
                    >
                        <span class="font-medium text-brand-navy">{{
                            ev.name
                        }}</span>
                        <span class="text-sm text-brand-blue/70">
                            {{ ev.event_type_name }} · {{ ev.event_date }}
                            <span v-if="ev.finalized" class="ml-2 text-emerald-700"
                                >Finalized</span
                            >
                        </span>
                    </Link>
                    <p class="mt-1 text-xs text-brand-blue/60">
                        RSVP: {{ ev.rsvp_counts.accepted }} accepted,
                        {{ ev.rsvp_counts.pending }} pending,
                        {{ ev.rsvp_counts.declined }} declined
                    </p>
                </li>
            </ul>
        </section>

        <section
            v-if="isAdmin"
            class="ts-card-padded mt-10"
        >
            <h2 class="ts-heading-section mb-4">Branding</h2>
            <form class="space-y-4" @submit.prevent="saveSettings">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="brand_primary" value="Primary" />
                        <ColorInput
                            id="brand_primary"
                            v-model="settingsForm.brand_primary"
                            placeholder="#1a6893"
                        />
                        <InputError
                            class="mt-2"
                            :message="settingsForm.errors.brand_primary"
                        />
                    </div>
                    <div>
                        <InputLabel for="brand_accent" value="Accent" />
                        <ColorInput
                            id="brand_accent"
                            v-model="settingsForm.brand_accent"
                            placeholder="#f7941c"
                        />
                        <InputError
                            class="mt-2"
                            :message="settingsForm.errors.brand_accent"
                        />
                    </div>
                </div>
                <div
                    class="flex flex-wrap items-center gap-3 rounded-lg border border-brand-mist bg-brand-cream/60 px-4 py-3"
                    aria-hidden="true"
                >
                    <span class="text-xs font-medium text-brand-blue/70"
                        >Preview</span
                    >
                    <span
                        class="inline-flex rounded-lg px-3 py-1.5 text-sm font-semibold text-white shadow-sm"
                        :style="{
                            backgroundColor: settingsForm.brand_primary,
                        }"
                        >Primary</span
                    >
                    <span
                        class="inline-flex rounded-lg px-3 py-1.5 text-sm font-semibold text-white shadow-sm"
                        :style="{
                            backgroundColor: settingsForm.brand_accent,
                        }"
                        >Accent</span
                    >
                </div>
                <div>
                    <InputLabel for="logo" value="Logo" />
                    <input
                        id="logo"
                        type="file"
                        accept="image/png,image/jpeg,image/gif,image/webp"
                        class="mt-1 block w-full text-sm text-brand-blue/80"
                        @change="pickLogo"
                    />
                    <InputError
                        class="mt-2"
                        :message="settingsForm.errors.logo"
                    />
                </div>
                <label class="flex items-center gap-2 text-sm text-brand-navy/90">
                    <input
                        v-model="settingsForm.remove_logo"
                        type="checkbox"
                        class="rounded border-brand-mist"
                    />
                    Remove current logo
                </label>
                <div class="flex gap-2">
                    <PrimaryButton :disabled="settingsForm.processing"
                        >Save</PrimaryButton
                    >
                    <SecondaryButton
                        type="button"
                        @click="settingsForm.reset('logo')"
                        >Clear file</SecondaryButton
                    >
                </div>
            </form>
        </section>
    </OrganizationLayout>
</template>
