<script setup lang="ts">
import AppIcon from '@/Components/App/AppIcon.vue';
import AssistantLauncher from '@/Components/Assistant/AssistantLauncher.vue';
import PokeballMark from '@/Components/App/PokeballMark.vue';
import type { AppPageProps } from '@/types/page';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from '../../../vendor/tightenco/ziggy';

defineSlots<{
    default(): unknown;
}>();

const page = usePage<AppPageProps>();
const user = computed(() => page.props.auth.user);

const navigation = [
    { label: 'Mi colección', shortLabel: 'Colección', routeName: 'dashboard', icon: 'collection' as const, cacheTags: 'collection' },
    { label: 'Explorar Pokédex', shortLabel: 'Explorar', routeName: 'pokedex.index', icon: 'explore' as const },
    { label: 'Análisis', shortLabel: 'Análisis', routeName: 'insights.index', icon: 'insights' as const },
    { label: 'Comparador', shortLabel: 'Comparar', routeName: 'compare.index', icon: 'compare' as const },
];

function isActive(routeName: string): boolean {
    if (routeName === 'dashboard') {
        return route().current('dashboard') || route().current('collection.*');
    }

    return route().current(routeName) ?? false;
}
</script>

<template>
    <div class="min-h-screen bg-canvas text-[#172033] dark:bg-[#0e1420] dark:text-[#f7f4ed]">
        <a
            href="#main-content"
            class="fixed left-4 top-3 z-[70] -translate-y-20 rounded-xl bg-[#172033] px-4 py-3 font-semibold text-white shadow-lg transition-transform focus:translate-y-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#f4cf4e]"
        >
            Saltar al contenido
        </a>

        <aside class="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col border-r border-line bg-white px-4 py-5 lg:flex dark:border-white/10 dark:bg-[#131b29]">
            <Link :href="route('dashboard')" class="flex items-center gap-3 rounded-xl px-2 py-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d]">
                <PokeballMark />
                <span class="text-[1.05rem] font-bold tracking-[-0.03em]">Pokédex Manager</span>
            </Link>

            <p class="mt-9 px-3 font-mono text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-[#8b91a0] dark:text-[#9aa5b5]">Navegación</p>
            <nav aria-label="Principal" class="mt-3 flex flex-col gap-1.5">
                <Link
                    v-for="item in navigation"
                    :key="item.routeName"
                    :href="route(item.routeName)"
                    prefetch
                    :cache-tags="item.cacheTags"
                    class="group flex min-h-12 items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d]"
                    :class="isActive(item.routeName) ? 'bg-[#172033] text-white shadow-sm dark:bg-[#f7f4ed] dark:text-[#172033]' : 'text-[#626979] hover:bg-surface-subtle hover:text-[#172033] dark:text-[#b3bccb] dark:hover:bg-white/5 dark:hover:text-white'"
                    :aria-current="isActive(item.routeName) ? 'page' : undefined"
                >
                    <AppIcon :name="item.icon" class="size-5" />
                    {{ item.label }}
                </Link>
            </nav>

            <div class="mt-auto border-t border-line pt-4 dark:border-white/10">
                <details class="group relative">
                    <summary class="flex min-h-12 cursor-pointer list-none items-center gap-3 rounded-xl px-2 py-2 transition-colors hover:bg-surface-subtle focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] dark:hover:bg-white/5 [&::-webkit-details-marker]:hidden">
                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-[#e9ded2] font-bold text-[#7b312e] dark:bg-[#3b2a30] dark:text-[#f2a6ac]">{{ user.name.charAt(0).toUpperCase() }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold">{{ user.name }}</span>
                            <span class="block truncate text-xs text-[#777f8f] dark:text-[#9aa5b5]">{{ user.email }}</span>
                        </span>
                        <AppIcon name="chevron-down" class="size-4 transition-transform group-open:rotate-180" />
                    </summary>
                    <div class="absolute bottom-14 left-0 right-0 z-20 flex flex-col gap-1 rounded-xl border border-line bg-white p-1.5 shadow-[0_18px_45px_rgba(23,32,51,0.16)] dark:border-white/10 dark:bg-[#192232]">
                        <Link :href="route('profile.show')" class="flex min-h-11 items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold hover:bg-surface-subtle focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] dark:hover:bg-white/5">
                            <AppIcon name="user" class="size-4" /> Perfil
                        </Link>
                        <Link :href="route('logout')" method="post" as="button" class="flex min-h-11 w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-semibold text-[#a92634] hover:bg-[#fff0f1] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] dark:text-[#f2a0a8] dark:hover:bg-[#c62f3d]/10">
                            <AppIcon name="logout" class="size-4" /> Cerrar sesión
                        </Link>
                    </div>
                </details>
            </div>
        </aside>

        <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-line/90 bg-white/95 px-4 backdrop-blur lg:hidden dark:border-white/10 dark:bg-[#131b29]/95">
            <Link :href="route('dashboard')" class="flex items-center gap-2 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d]">
                <PokeballMark size="sm" />
                <span class="font-bold tracking-[-0.03em]">Pokédex Manager</span>
            </Link>
            <Link :href="route('profile.show')" class="grid size-11 place-items-center rounded-full bg-[#e9ded2] font-bold text-[#7b312e] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] dark:bg-[#3b2a30] dark:text-[#f2a6ac]" :aria-label="`Abrir perfil de ${user.name}`">
                {{ user.name.charAt(0).toUpperCase() }}
            </Link>
        </header>

        <main id="main-content" class="min-h-screen pb-[calc(5.75rem+env(safe-area-inset-bottom))] lg:ml-64 lg:pb-0">
            <slot />
        </main>

        <nav aria-label="Principal" class="fixed inset-x-0 bottom-0 z-50 grid grid-cols-4 border-t border-line bg-white/95 px-2 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden dark:border-white/10 dark:bg-[#131b29]/95">
            <Link
                v-for="item in navigation"
                :key="item.routeName"
                :href="route(item.routeName)"
                prefetch
                :cache-tags="item.cacheTags"
                class="flex min-h-[4.5rem] flex-col items-center justify-center gap-1 rounded-lg px-1 text-[0.7rem] font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#c62f3d]"
                :class="isActive(item.routeName) ? 'text-[#b42534] dark:text-[#f28f99]' : 'text-[#777f8f] dark:text-[#9aa5b5]'"
                :aria-current="isActive(item.routeName) ? 'page' : undefined"
            >
                <AppIcon :name="item.icon" class="size-5" />
                {{ item.shortLabel }}
            </Link>
        </nav>

        <AssistantLauncher />
    </div>
</template>
