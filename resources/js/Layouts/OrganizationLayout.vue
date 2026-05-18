<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import FlashToasts from '@/Components/FlashToasts.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import {
    CalendarDaysIcon,
    Cog6ToothIcon,
    HomeIcon,
    UsersIcon,
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
    '--org-primary': props.organization.brand_primary || '#1a6893',
    '--org-accent': props.organization.brand_accent || '#f7941c',
}));

const isAdmin = computed(() => props.organization.role === 'admin');
</script>

<template>
    <div :style="brandStyle" class="ts-page ts-org-branded">
        <nav
            class="border-b border-brand-mist bg-white/90 shadow-sm shadow-brand-navy/5 backdrop-blur-md md:sticky md:top-0 md:z-50"
        >
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
                                :alt="organization.name"
                                class="h-10 max-w-[140px] object-contain"
                            />
                            <span
                                v-else
                                class="truncate text-sm font-semibold text-brand-navy sm:text-base lg:max-w-xs"
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
                                :href="route('organizations.events.index', slug)"
                                :active="
                                    route().current('organizations.events.*')
                                "
                            >
                                <CalendarDaysIcon
                                    class="mr-1 inline h-4 w-4"
                                />
                                Events
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
                                        class="ts-org-nav-control inline-flex items-center rounded-lg border border-transparent bg-white px-3 py-2 text-sm font-medium transition"
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
                            class="ts-org-nav-control inline-flex items-center justify-center rounded-lg p-2 opacity-70 hover:bg-brand-mist"
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
                class="border-t border-brand-mist md:hidden"
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
                        :href="route('organizations.events.index', slug)"
                        :active="route().current('organizations.events.*')"
                        >Events</ResponsiveNavLink
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

        <!-- Optional sticky sub-nav bar (e.g. draft context bar). Sits just below the sticky main nav. -->
        <div
            v-if="$slots.subnav"
            class="border-b border-brand-mist bg-white/95 shadow-sm backdrop-blur-sm md:sticky md:top-16 md:z-40"
        >
            <div class="mx-auto max-w-7xl px-4 py-3 sm:px-6 lg:px-8">
                <slot name="subnav" />
            </div>
        </div>

        <header
            v-if="$slots.header"
            class="border-b border-brand-mist bg-white"
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
