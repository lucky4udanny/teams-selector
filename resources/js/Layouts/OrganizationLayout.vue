<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import FlashToasts from '@/Components/FlashToasts.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import {
    ClipboardDocumentListIcon,
    Cog6ToothIcon,
    HomeIcon,
    UserGroupIcon,
    UsersIcon,
    WrenchScrewdriverIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    organization: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const showingNavigationDropdown = ref(false);

const slug = computed(() => props.organization.slug);
const brandStyle = computed(() => ({
    '--brand-primary': props.organization.brand_primary || '#4f46e5',
    '--brand-accent': props.organization.brand_accent || '#6366f1',
}));

const isAdmin = computed(() => props.organization.role === 'admin');
</script>

<template>
    <div :style="brandStyle" class="min-h-screen bg-slate-50">
        <nav class="border-b border-slate-200 bg-white shadow-sm">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 justify-between">
                    <div class="flex min-w-0 flex-1 items-center gap-6">
                        <Link
                            :href="route('organizations.show', slug)"
                            class="flex shrink-0 items-center gap-3"
                        >
                            <img
                                v-if="organization.logo_url"
                                :src="organization.logo_url"
                                alt=""
                                class="h-10 max-w-[140px] object-contain"
                            />
                            <ApplicationLogo
                                v-else
                                class="block h-9 w-auto fill-current text-slate-700"
                            />
                            <span
                                class="hidden truncate text-sm font-semibold text-slate-900 sm:inline lg:max-w-xs"
                                >{{ organization.name }}</span
                            >
                        </Link>
                        <div
                            class="hidden items-center gap-1 md:flex md:flex-wrap lg:gap-2"
                        >
                            <NavLink
                                :href="route('organizations.show', slug)"
                                :active="
                                    route().current('organizations.show')
                                "
                            >
                                <HomeIcon class="mr-1 inline h-4 w-4" />
                                Home
                            </NavLink>
                            <NavLink
                                :href="route('organizations.members.index', slug)"
                                :active="
                                    route().current('organizations.members.*')
                                "
                            >
                                <UsersIcon class="mr-1 inline h-4 w-4" />
                                Members
                            </NavLink>
                            <NavLink
                                :href="route('organizations.rules.index', slug)"
                                :active="
                                    route().current('organizations.rules.*')
                                "
                            >
                                <WrenchScrewdriverIcon
                                    class="mr-1 inline h-4 w-4"
                                />
                                Rules
                            </NavLink>
                            <NavLink
                                :href="route('organizations.drafts.index', slug)"
                                :active="
                                    route().current('organizations.drafts.*')
                                "
                            >
                                <ClipboardDocumentListIcon
                                    class="mr-1 inline h-4 w-4"
                                />
                                Drafts
                            </NavLink>
                            <NavLink
                                :href="
                                    route('organizations.selections.index', slug)
                                "
                                :active="
                                    route().current('organizations.selections.*')
                                "
                            >
                                <UserGroupIcon class="mr-1 inline h-4 w-4" />
                                Selections
                            </NavLink>
                            <NavLink
                                v-if="isAdmin"
                                :href="route('organizations.users.index', slug)"
                                :active="
                                    route().current('organizations.users.*')
                                "
                            >
                                <Cog6ToothIcon class="mr-1 inline h-4 w-4" />
                                Users
                            </NavLink>
                        </div>
                    </div>
                    <div class="hidden shrink-0 sm:ms-6 sm:flex sm:items-center">
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <span class="inline-flex rounded-md">
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium text-slate-600 transition hover:text-slate-900"
                                    >
                                        {{ page.props.auth.user.name }}
                                        <svg
                                            class="-me-0.5 ms-2 h-4 w-4"
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 20 20"
                                            fill="currentColor"
                                        >
                                            <path
                                                fill-rule="evenodd"
                                                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                clip-rule="evenodd"
                                            />
                                        </svg>
                                    </button>
                                </span>
                            </template>
                            <template #content>
                                <DropdownLink :href="route('organizations.index')">
                                    All organizations
                                </DropdownLink>
                                <DropdownLink :href="route('profile.edit')">
                                    Profile
                                </DropdownLink>
                                <DropdownLink
                                    :href="route('logout')"
                                    method="post"
                                    as="button"
                                >
                                    Log out
                                </DropdownLink>
                            </template>
                        </Dropdown>
                    </div>
                    <div class="-me-2 flex items-center md:hidden">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-md p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                            @click="
                                showingNavigationDropdown =
                                    !showingNavigationDropdown
                            "
                        >
                            <svg
                                class="h-6 w-6"
                                stroke="currentColor"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    :class="{
                                        hidden: showingNavigationDropdown,
                                        'inline-flex':
                                            !showingNavigationDropdown,
                                    }"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 6h16M4 12h16M4 18h16"
                                />
                                <path
                                    :class="{
                                        hidden: !showingNavigationDropdown,
                                        'inline-flex':
                                            showingNavigationDropdown,
                                    }"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
            <div
                :class="{
                    block: showingNavigationDropdown,
                    hidden: !showingNavigationDropdown,
                }"
                class="border-t border-slate-100 md:hidden"
            >
                <div class="space-y-1 px-2 pb-3 pt-2">
                    <ResponsiveNavLink
                        :href="route('organizations.show', slug)"
                        :active="route().current('organizations.show')"
                        >Home</ResponsiveNavLink
                    >
                    <ResponsiveNavLink
                        :href="route('organizations.members.index', slug)"
                        :active="route().current('organizations.members.*')"
                        >Members</ResponsiveNavLink
                    >
                    <ResponsiveNavLink
                        :href="route('organizations.rules.index', slug)"
                        :active="route().current('organizations.rules.*')"
                        >Rules</ResponsiveNavLink
                    >
                    <ResponsiveNavLink
                        :href="route('organizations.drafts.index', slug)"
                        :active="route().current('organizations.drafts.*')"
                        >Drafts</ResponsiveNavLink
                    >
                    <ResponsiveNavLink
                        :href="route('organizations.selections.index', slug)"
                        :active="
                            route().current('organizations.selections.*')
                        "
                        >Selections</ResponsiveNavLink
                    >
                    <ResponsiveNavLink
                        v-if="isAdmin"
                        :href="route('organizations.users.index', slug)"
                        :active="route().current('organizations.users.*')"
                        >Users</ResponsiveNavLink
                    >
                </div>
            </div>
        </nav>

        <FlashToasts />

        <header
            v-if="$slots.header"
            class="border-b border-slate-100 bg-white"
        >
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <slot name="header" />
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <slot />
        </main>
    </div>
</template>
