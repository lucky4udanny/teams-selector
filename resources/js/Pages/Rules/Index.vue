<script setup>
import OrganizationLayout from '@/Layouts/OrganizationLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    organization: Object,
    rules: Array,
    members: Array,
    ruleTypes: Array,
    ruleScopes: Array,
    canManage: Boolean,
});

const addForm = useForm({
    type: 'team_size',
    scope: 'team',
    weight: 100,
    sort_order: 0,
    config: { size: 2 },
});

watch(
    () => addForm.type,
    (t) => {
        if (t === 'team_size') addForm.config = { size: 2 };
        else if (t === 'group_size') addForm.config = { teams_per_group: 2 };
        else if (t === 'banned_pair')
            addForm.config = {
                member_a_id: props.members[0]?.id,
                member_b_id: props.members[1]?.id,
            };
        else if (t === 'repeat_pair') addForm.config = { window: 3 };
    },
);

const submitAdd = () => {
    addForm.post(route('organizations.rules.store', props.organization.slug), {
        preserveScroll: true,
        onSuccess: () => {
            addForm.reset();
            addForm.type = 'team_size';
            addForm.scope = 'team';
            addForm.weight = 100;
            addForm.config = { size: 2 };
        },
    });
};

const destroy = (id) => {
    if (!confirm('Delete this rule?')) return;
    router.delete(
        route('organizations.rules.destroy', [props.organization.slug, id]),
    );
};

const editingId = ref(null);
const editForm = useForm({
    type: '',
    scope: '',
    weight: 0,
    sort_order: 0,
    config: {},
});

const openEdit = (r) => {
    editingId.value = r.id;
    editForm.type = r.type;
    editForm.scope = r.scope;
    editForm.weight = r.weight;
    editForm.sort_order = r.sort_order;
    editForm.config = { ...r.config };
};

const saveEdit = () => {
    if (!editingId.value) return;
    editForm.put(
        route('organizations.rules.update', [
            props.organization.slug,
            editingId.value,
        ]),
        { preserveScroll: true, onSuccess: () => (editingId.value = null) },
    );
};

const cancelRuleEdit = () => {
    editingId.value = null;
};

const memberOptions = computed(() =>
    props.members.map((m) => ({ value: m.id, label: m.name })),
);
</script>

<template>
    <Head title="Rules" />

    <OrganizationLayout :organization="organization">
        <template #header>
            <h1 class="text-2xl font-bold text-brand-navy">Rules</h1>
        </template>

        <div
            v-if="canManage"
            class="mb-8 rounded-2xl border border-brand-mist bg-white p-6 shadow-sm"
        >
            <h2 class="mb-4 text-sm font-semibold text-brand-navy">Add rule</h2>
            <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submitAdd">
                <div>
                    <InputLabel value="Type" />
                    <select
                        v-model="addForm.type"
                        class="mt-1 block w-full rounded-md border-brand-mist shadow-sm"
                    >
                        <option v-for="t in ruleTypes" :key="t" :value="t">
                            {{ t }}
                        </option>
                    </select>
                    <InputError :message="addForm.errors.type" />
                </div>
                <div>
                    <InputLabel value="Scope" />
                    <select
                        v-model="addForm.scope"
                        class="mt-1 block w-full rounded-md border-brand-mist shadow-sm"
                    >
                        <option v-for="s in ruleScopes" :key="s" :value="s">
                            {{ s }}
                        </option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Weight (higher = more important)" />
                    <TextInput
                        v-model.number="addForm.weight"
                        type="number"
                        class="mt-1 block w-full"
                        min="0"
                    />
                    <InputError :message="addForm.errors.weight" />
                </div>
                <div>
                    <InputLabel value="Sort order" />
                    <TextInput
                        v-model.number="addForm.sort_order"
                        type="number"
                        class="mt-1 block w-full"
                        min="0"
                    />
                </div>
                <div v-if="addForm.type === 'team_size'" class="md:col-span-2">
                    <InputLabel value="Players per team" />
                    <TextInput
                        v-model.number="addForm.config.size"
                        type="number"
                        class="mt-1 block w-full"
                        min="1"
                    />
                </div>
                <div v-if="addForm.type === 'group_size'" class="md:col-span-2">
                    <InputLabel value="Teams per group" />
                    <TextInput
                        v-model.number="addForm.config.teams_per_group"
                        type="number"
                        class="mt-1 block w-full"
                        min="1"
                    />
                </div>
                <div
                    v-if="addForm.type === 'banned_pair'"
                    class="grid gap-4 md:col-span-2 md:grid-cols-2"
                >
                    <div>
                        <InputLabel value="Member A" />
                        <select
                            v-model.number="addForm.config.member_a_id"
                            class="mt-1 block w-full rounded-md border-brand-mist"
                        >
                            <option
                                v-for="o in memberOptions"
                                :key="'a-' + o.value"
                                :value="o.value"
                            >
                                {{ o.label }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Member B" />
                        <select
                            v-model.number="addForm.config.member_b_id"
                            class="mt-1 block w-full rounded-md border-brand-mist"
                        >
                            <option
                                v-for="o in memberOptions"
                                :key="'b-' + o.value"
                                :value="o.value"
                            >
                                {{ o.label }}
                            </option>
                        </select>
                    </div>
                </div>
                <div v-if="addForm.type === 'repeat_pair'" class="md:col-span-2">
                    <InputLabel value="Last N approved selections" />
                    <TextInput
                        v-model.number="addForm.config.window"
                        type="number"
                        class="mt-1 block w-full"
                        min="1"
                    />
                </div>
                <div class="md:col-span-2">
                    <PrimaryButton :disabled="addForm.processing">Add rule</PrimaryButton>
                </div>
            </form>
        </div>

        <div class="overflow-hidden rounded-2xl border border-brand-mist bg-white shadow-sm">
            <table class="min-w-full divide-y divide-brand-mist text-sm">
                <thead class="bg-brand-cream">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-brand-blue/70">
                            Type
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-brand-blue/70">
                            Scope
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-brand-blue/70">
                            Weight
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-brand-blue/70">
                            Config
                        </th>
                        <th
                            v-if="canManage"
                            class="px-4 py-3 text-right text-xs font-medium uppercase text-brand-blue/70"
                        >
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-mist">
                    <tr v-for="r in rules" :key="r.id">
                        <td class="px-4 py-3 font-mono text-xs">{{ r.type }}</td>
                        <td class="px-4 py-3">{{ r.scope }}</td>
                        <td class="px-4 py-3">{{ r.weight }}</td>
                        <td class="max-w-xs truncate px-4 py-3 font-mono text-xs text-brand-blue/80">
                            {{ JSON.stringify(r.config) }}
                        </td>
                        <td v-if="canManage" class="px-4 py-3 text-right">
                            <button
                                type="button"
                                class="text-brand-blue hover:underline"
                                @click="openEdit(r)"
                            >
                                Edit
                            </button>
                            <button
                                type="button"
                                class="ms-3 text-red-600 hover:underline"
                                @click="destroy(r.id)"
                            >
                                Delete
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="editingId && canManage"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="cancelRuleEdit"
        >
            <form
                class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl"
                @submit.prevent="saveEdit"
            >
                <h3 class="mb-4 font-semibold">Edit rule</h3>
                <div class="space-y-3">
                    <div>
                        <InputLabel value="Type" />
                        <select
                            v-model="editForm.type"
                            class="mt-1 block w-full rounded-md border-brand-mist"
                        >
                            <option v-for="t in ruleTypes" :key="t" :value="t">
                                {{ t }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Scope" />
                        <select
                            v-model="editForm.scope"
                            class="mt-1 block w-full rounded-md border-brand-mist"
                        >
                            <option v-for="s in ruleScopes" :key="s" :value="s">
                                {{ s }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Weight" />
                        <TextInput
                            v-model.number="editForm.weight"
                            type="number"
                            class="mt-1 w-full"
                        />
                    </div>
                    <template v-if="editForm.type === 'team_size'">
                        <InputLabel value="Size" />
                        <TextInput
                            v-model.number="editForm.config.size"
                            type="number"
                            class="mt-1 w-full"
                        />
                    </template>
                    <template v-if="editForm.type === 'group_size'">
                        <InputLabel value="Teams per group" />
                        <TextInput
                            v-model.number="editForm.config.teams_per_group"
                            type="number"
                            class="mt-1 w-full"
                        />
                    </template>
                    <template v-if="editForm.type === 'banned_pair'">
                        <InputLabel value="Member A" />
                        <select
                            v-model.number="editForm.config.member_a_id"
                            class="mt-1 block w-full rounded-md border-brand-mist"
                        >
                            <option
                                v-for="o in memberOptions"
                                :key="'ea-' + o.value"
                                :value="o.value"
                            >
                                {{ o.label }}
                            </option>
                        </select>
                        <InputLabel class="mt-2" value="Member B" />
                        <select
                            v-model.number="editForm.config.member_b_id"
                            class="mt-1 block w-full rounded-md border-brand-mist"
                        >
                            <option
                                v-for="o in memberOptions"
                                :key="'eb-' + o.value"
                                :value="o.value"
                            >
                                {{ o.label }}
                            </option>
                        </select>
                    </template>
                    <template v-if="editForm.type === 'repeat_pair'">
                        <InputLabel value="Window" />
                        <TextInput
                            v-model.number="editForm.config.window"
                            type="number"
                            class="mt-1 w-full"
                        />
                    </template>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton type="button" @click="cancelRuleEdit"
                        >Cancel</SecondaryButton
                    >
                    <PrimaryButton :disabled="editForm.processing">Save</PrimaryButton>
                </div>
            </form>
        </div>
    </OrganizationLayout>
</template>
