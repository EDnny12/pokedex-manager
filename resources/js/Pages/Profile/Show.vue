<script setup lang="ts">
import AppIcon from '@/Components/App/AppIcon.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { AppPageProps } from '@/types/page';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from '../../../../vendor/tightenco/ziggy';

const page = usePage<AppPageProps>();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <AppLayout>
        <Head title="Perfil" />

        <div class="mx-auto flex w-full max-w-3xl flex-col gap-5 px-4 py-6 sm:px-6 sm:py-8 xl:px-9">
            <Link :href="route('dashboard')" class="inline-flex min-h-11 items-center gap-2 self-start rounded-lg pr-3 text-sm font-bold text-[#697180] hover:text-[#172033] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] dark:text-[#aab4c4] dark:hover:text-white">
                <AppIcon name="arrow-left" class="size-5" /> Mi colección
            </Link>

            <header class="flex flex-col gap-2">
                <p class="font-mono text-[0.68rem] font-bold uppercase tracking-[0.2em] text-[#9d3340] dark:text-[#f08f99]">Cuenta</p>
                <h1 class="text-3xl font-bold tracking-[-0.045em] sm:text-4xl">Perfil</h1>
                <p class="text-sm leading-6 text-[#697180] dark:text-[#aab4c4]">La información básica de la cuenta con la que guardas tu colección.</p>
            </header>

            <section class="flex flex-col gap-6 rounded-[1.75rem] border border-line bg-surface p-5 sm:p-7 dark:border-white/10 dark:bg-[#161f2e]">
                <div class="flex items-center gap-4">
                    <span class="grid size-16 shrink-0 place-items-center rounded-full bg-[#e9ded2] text-2xl font-bold text-[#7b312e] dark:bg-[#3b2a30] dark:text-[#f2a6ac]">{{ user.name.charAt(0).toUpperCase() }}</span>
                    <div class="min-w-0">
                        <h2 class="truncate text-xl font-bold">{{ user.name }}</h2>
                        <p class="truncate text-sm text-[#697180] dark:text-[#aab4c4]">{{ user.email }}</p>
                    </div>
                </div>

                <dl class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl border border-line bg-white p-4 dark:border-white/10 dark:bg-white/[0.03]">
                        <dt class="font-mono text-[0.65rem] font-bold uppercase tracking-[0.12em] text-[#7a8291]">Nombre</dt>
                        <dd class="mt-2 font-semibold">{{ user.name }}</dd>
                    </div>
                    <div class="rounded-2xl border border-line bg-white p-4 dark:border-white/10 dark:bg-white/[0.03]">
                        <dt class="font-mono text-[0.65rem] font-bold uppercase tracking-[0.12em] text-[#7a8291]">Correo electrónico</dt>
                        <dd class="mt-2 break-all font-semibold">{{ user.email }}</dd>
                    </div>
                </dl>

                <Link :href="route('logout')" method="post" as="button" class="inline-flex min-h-12 items-center justify-center gap-2 self-stretch rounded-xl border border-line-strong px-5 py-3 text-sm font-bold text-[#a92634] hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] sm:self-start dark:border-white/15 dark:text-[#f3a0a8] dark:hover:bg-[#c62f3d]/10">
                    <AppIcon name="logout" class="size-4" /> Cerrar sesión
                </Link>
            </section>
        </div>
    </AppLayout>
</template>
