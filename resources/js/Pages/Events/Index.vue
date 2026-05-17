<script setup>
import Badge from '@/Components/Badge.vue';
import ComboboxInput from '@/Components/ComboboxInput.vue';
import DateInput from '@/Components/DateInput.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormField from '@/Components/FormField.vue';
import ListboxInput from '@/Components/ListboxInput.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Toggle from '@/Components/Toggle.vue';
import Alert from '@/Components/Alert.vue';
import OrganizationLayout from '@/Layouts/OrganizationLayout.vue';
import { useToday } from '@/composables/useToday';
import { applyFormErrors, validateEventDetails } from '@/utils/formValidation';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';

const { todayStr } = useToday();

const props = defineProps({
    organization: Object,
    events: Array,
    eventTypes: Array,
    canManage: Boolean,
});

const showCreate = ref(false);

const upcomingEvents = computed(() =>
    [...(props.events || [])]
        .filter((e) => e.event_date >= todayStr.value)
        .sort((a, b) => a.event_date.localeCompare(b.event_date)),
);

const pastEvents = computed(() =>
    [...(props.events || [])]
        .filter((e) => e.event_date < todayStr.value)
        .sort((a, b) => b.event_date.localeCompare(a.event_date)),
);

const priorEventOptions = computed(() =>
    (props.events || [])
        .filter((e) => e.finalized)
        .map((e) => ({
            value: e.id,
            label: `${e.name} (${e.event_date})`,
        })),
);

const eventTypeOptions = computed(() =>
    (props.eventTypes || []).map((t) => ({
        value: t.id,
        label: t.name,
    })),
);

const createForm = useForm({
    name: '',
    description: '',
    event_date: '',
    event_type_id: null,
    uses_groups: false,
    previous_event_ids: [],
});

const openCreate = () => {
    createForm.clearErrors();
    createForm.reset();
    createForm.event_date = '';
    createForm.event_type_id = props.eventTypes?.[0]?.id ?? null;
    createForm.uses_groups = false;
    createForm.previous_event_ids = [];
    showCreate.value = true;
};

const closeCreate = () => {
    showCreate.value = false;
    createForm.clearErrors();
};

const focusCreateField = async (errors) => {
    const idByKey = {
        name: 'event_name',
        event_date: 'event_date',
        event_type_id: 'event_type_id',
        description: 'event_desc',
    };
    const key = Object.keys(errors)[0];
    if (!key) {
        return;
    }
    await nextTick();
    document.getElementById(idByKey[key] ?? 'event_name')?.focus();
};

const createFormHasErrors = computed(
    () => Object.keys(createForm.errors).length > 0 && !createForm.processing,
);

const submitCreate = () => {
    const result = validateEventDetails(createForm.data());
    if (!result.valid) {
        applyFormErrors(createForm, result.errors);
        focusCreateField(result.errors);

        return;
    }

    createForm.name = result.values.name;
    createForm.event_date = result.values.event_date;
    createForm.event_type_id = result.values.event_type_id;

    createForm.post(route('organizations.events.store', props.organization.slug), {
        preserveScroll: true,
        onSuccess: () => closeCreate(),
        onError: () => focusCreateField(createForm.errors),
    });
};
</script>

<template>
    <Head title="Events" />

    <OrganizationLayout :organization="organization">
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="ts-heading-page">Events</h1>
                    <p class="mt-1 text-sm text-brand-blue/70">
                        Plan rosters, rules, and team drafts per event.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <Link
                        :href="route('organizations.event-types.index', organization.slug)"
                        class="text-sm font-medium text-brand-blue hover:text-brand-navy"
                    >
                        Manage event types
                    </Link>
                    <PrimaryButton v-if="canManage" type="button" @click="openCreate">
                        New event
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <EmptyState
            v-if="!events?.length"
            title="No events yet"
            description="Create an event to invite members, attach rules, and generate team drafts."
            v-bind="canManage ? { onClick: openCreate } : {}"
        >
            <PrimaryButton v-if="canManage" type="button" @click.stop="openCreate">
                Create event
            </PrimaryButton>
        </EmptyState>

        <template v-else>
            <!-- Upcoming -->
            <section>
                <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-brand-blue/60">
                    Upcoming
                </h2>
                <p v-if="!upcomingEvents.length" class="rounded-xl border border-brand-mist bg-white px-4 py-4 text-sm text-brand-blue/60">
                    No upcoming events.
                </p>
                <div v-else class="space-y-3">
                    <Link
                        v-for="ev in upcomingEvents"
                        :key="ev.id"
                        class="block rounded-xl border border-brand-mist bg-white px-4 py-4 shadow-sm transition hover:border-brand-blue/30"
                        :href="route('organizations.events.show', { organization: organization.slug, event: ev.id })"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <span class="font-semibold text-brand-navy">{{ ev.name }}</span>
                                <p class="mt-1 text-sm text-brand-blue/70">
                                    {{ ev.event_type_name }} · {{ ev.event_date }}
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <Badge v-if="ev.finalized" variant="success">Finalized</Badge>
                                <Badge variant="success">{{ ev.rsvp_counts?.accepted ?? 0 }} accepted</Badge>
                                <Badge variant="warning">{{ ev.rsvp_counts?.pending ?? 0 }} pending</Badge>
                                <Badge variant="danger">{{ ev.rsvp_counts?.declined ?? 0 }} declined</Badge>
                            </div>
                        </div>
                    </Link>
                </div>
            </section>

            <!-- Past -->
            <section v-if="pastEvents.length" class="mt-8">
                <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-brand-blue/60">
                    Past events
                </h2>
                <div class="space-y-3">
                    <Link
                        v-for="ev in pastEvents"
                        :key="ev.id"
                        class="block rounded-xl border border-brand-mist bg-white px-4 py-4 shadow-sm transition hover:border-brand-blue/30 opacity-75"
                        :href="route('organizations.events.show', { organization: organization.slug, event: ev.id })"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <span class="font-semibold text-brand-navy">{{ ev.name }}</span>
                                <p class="mt-1 text-sm text-brand-blue/70">
                                    {{ ev.event_type_name }} · {{ ev.event_date }}
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <Badge v-if="ev.finalized" variant="success">Finalized</Badge>
                                <Badge variant="success">{{ ev.rsvp_counts?.accepted ?? 0 }} accepted</Badge>
                                <Badge variant="warning">{{ ev.rsvp_counts?.pending ?? 0 }} pending</Badge>
                                <Badge variant="danger">{{ ev.rsvp_counts?.declined ?? 0 }} declined</Badge>
                            </div>
                        </div>
                    </Link>
                </div>
            </section>
        </template>

        <p v-if="!canManage" class="mt-8 text-sm text-brand-blue/70">
            Only organizers can create or duplicate events.
        </p>

        <Modal :show="showCreate && canManage" @close="closeCreate">
            <form class="p-6" @submit.prevent="submitCreate">
                <h2 class="text-lg font-semibold text-brand-navy">Create event</h2>
                <p class="mt-1 text-sm text-brand-blue/70">
                    Prior events are optional; pick finalized events to link history for "avoid same members" rules.
                </p>

                <Alert v-if="createFormHasErrors" variant="error" class="mt-4" role="alert">
                    Please fix the errors below before creating the event.
                </Alert>

                <div v-if="!eventTypes?.length" class="mt-4">
                    <Alert variant="warning" role="alert">
                        Add an
                        <Link
                            :href="route('organizations.event-types.index', organization.slug)"
                            class="font-medium underline"
                        >
                            event type
                        </Link>
                        before creating events.
                    </Alert>
                </div>

                <div class="mt-6 space-y-5">
                    <FormField
                        label="Name"
                        name="event_name"
                        :error="createForm.errors.name"
                        hint="Required."
                        required
                    >
                        <TextInput
                            id="event_name"
                            v-model="createForm.name"
                            :error="!!createForm.errors.name"
                        />
                    </FormField>

                    <FormField label="Description" name="event_desc" :error="createForm.errors.description">
                        <textarea
                            id="event_desc"
                            v-model="createForm.description"
                            rows="3"
                            class="ts-input w-full resize-y rounded-lg py-2.5 text-sm"
                            :class="createForm.errors.description ? 'ts-input-error' : ''"
                        />
                    </FormField>

                    <FormField
                        label="Date"
                        name="event_date"
                        :error="createForm.errors.event_date"
                        hint="Required."
                        required
                    >
                        <DateInput id="event_date" v-model="createForm.event_date" :error="!!createForm.errors.event_date" />
                    </FormField>

                    <FormField
                        label="Event type"
                        name="event_type_id"
                        :error="createForm.errors.event_type_id"
                        hint="Required."
                        required
                    >
                        <ListboxInput
                            v-model="createForm.event_type_id"
                            :options="eventTypeOptions"
                            placeholder="Choose a type…"
                            :error="!!createForm.errors.event_type_id"
                        />
                    </FormField>

                    <div>
                        <Toggle v-model="createForm.uses_groups" label="Uses groups" />
                        <p class="mt-1 text-xs text-brand-blue/60">
                            Enables group-scoped rules when building teams.
                        </p>
                        <p v-if="createForm.errors.uses_groups" class="mt-1 text-sm text-red-600">
                            {{ createForm.errors.uses_groups }}
                        </p>
                    </div>

                    <div>
                        <span class="block text-sm font-medium text-brand-navy">Prior events</span>
                        <p class="mb-2 text-xs text-brand-blue/60">Optional — multi-select finalized events.</p>
                        <ComboboxInput
                            v-model="createForm.previous_event_ids"
                            :options="priorEventOptions"
                            multiple
                            placeholder="Search prior events…"
                            :error="!!createForm.errors.previous_event_ids"
                        />
                        <p v-if="createForm.errors.previous_event_ids" class="mt-1 text-sm text-red-600">
                            {{ createForm.errors.previous_event_ids }}
                        </p>
                    </div>
                </div>

                <div class="mt-8 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="closeCreate">Cancel</SecondaryButton>
                    <PrimaryButton
                        type="submit"
                        :disabled="createForm.processing || !eventTypes?.length"
                    >
                        {{ createForm.processing ? 'Creating…' : 'Create event' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </OrganizationLayout>
</template>
