<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    showHero: {
        type: Boolean,
        default: true,
    },
});
</script>

<template>
    <div class="flex min-h-screen">
        <!-- Branded hero panel -->
        <aside
            v-if="showHero"
            class="ts-hero-gradient relative hidden overflow-hidden lg:flex lg:w-[52%] xl:w-[55%]"
        >
            <div
                class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_20%_80%,rgba(247,148,28,0.18),transparent_55%)]"
            />

            <img
                src="/icon-detail.svg"
                alt=""
                class="pointer-events-none absolute -right-[18%] top-1/2 h-[115%] max-w-none -translate-y-[42%] opacity-[0.22] mix-blend-soft-light select-none"
                aria-hidden="true"
            />

            <div
                class="relative z-10 flex h-full w-full flex-col justify-between p-10 xl:p-14"
            >
                <Link :href="route('login')" class="inline-flex w-fit">
                    <img
                        src="/Logo.svg"
                        alt="TeamForge"
                        class="h-14 w-14 drop-shadow-lg"
                    />
                </Link>

                <div class="max-w-md space-y-4">
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-orange"
                    >
                        Team assignment
                    </p>
                    <h1
                        class="text-3xl font-bold leading-tight tracking-tight text-white xl:text-4xl"
                    >
                        Balanced teams,<br />
                        every session.
                    </h1>
                    <p class="text-base leading-relaxed text-white/75">
                        Define your roster and rules, generate fair lineups,
                        and share polished results with your group.
                    </p>
                </div>

                <p class="text-sm text-white/50">
                    © {{ new Date().getFullYear() }} TeamForge
                </p>
            </div>
        </aside>

        <!-- Form panel -->
        <main
            class="relative flex flex-1 flex-col items-center justify-center bg-brand-cream px-6 py-10 sm:px-10"
        >
            <div
                class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_90%_0%,rgba(29,133,191,0.08),transparent_50%)]"
            />

            <div class="relative z-10 w-full max-w-md">
                <Link
                    :href="route('login')"
                    class="mb-8 flex justify-center lg:hidden"
                >
                    <img
                        src="/Wordmark.svg"
                        alt="TeamForge"
                        class="h-9 w-auto"
                    />
                </Link>

                <div class="ts-auth-card">
                    <slot />
                </div>
            </div>
        </main>
    </div>
</template>
