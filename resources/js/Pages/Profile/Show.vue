<script setup lang="ts">
import AppIcon from '@/Components/App/AppIcon.vue';
import TrainerCard from '@/Components/Profile/TrainerCard.vue';
import UpdateProfileInformationForm from '@/Components/Profile/UpdateProfileInformationForm.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { AppPageProps } from '@/types/page';
import type { TrainerCardData } from '@/types/pokemon';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from '../../../../vendor/tightenco/ziggy';

defineProps<{
    trainerCard: TrainerCardData;
}>();

const page = usePage<AppPageProps>();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <AppLayout>
        <Head title="Perfil de Entrenador" />

        <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 px-4 py-6 sm:px-6 sm:py-8 xl:px-9">
            <Link :href="route('dashboard')" class="inline-flex min-h-11 items-center gap-2 self-start rounded-lg pr-3 text-sm font-bold text-[#697180] hover:text-[#172033] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] dark:text-[#aab4c4] dark:hover:text-white">
                <AppIcon name="arrow-left" class="size-5" /> Mi colección
            </Link>

            <header class="flex flex-col gap-2">
                <p class="font-mono text-[0.68rem] font-bold uppercase tracking-[0.2em] text-[#9d3340] dark:text-[#f08f99]">Identidad & Cuenta</p>
                <h1 class="text-3xl font-bold tracking-[-0.045em] sm:text-4xl">Perfil de Entrenador</h1>
                <p class="text-sm leading-6 text-[#697180] dark:text-[#aab4c4]">Tu licencia oficial de la Liga Pokémon y la configuración de tu cuenta.</p>
            </header>

            <!-- 1. Trainer Card Hero -->
            <TrainerCard :card="trainerCard" />

            <!-- 2. Account & Profile Photo Form -->
            <UpdateProfileInformationForm :user="user" />

            <!-- 3. Logout Area -->
            <section class="flex flex-col items-start justify-between gap-4 rounded-[1.75rem] border border-line bg-surface p-5 sm:flex-row sm:items-center sm:p-7 dark:border-white/10 dark:bg-[#161f2e]">
                <div>
                    <h3 class="text-sm font-bold text-[#172033] dark:text-[#f7f4ed]">Sesión actual</h3>
                    <p class="text-xs text-[#697180] dark:text-[#aab4c4]">Conectado como <strong class="font-semibold">{{ user.email }}</strong></p>
                </div>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-line-strong px-5 py-2.5 text-xs font-bold text-[#a92634] hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] dark:border-white/15 dark:text-[#f3a0a8] dark:hover:bg-[#c62f3d]/10"
                >
                    <AppIcon name="logout" class="size-4" />
                    Cerrar sesión
                </Link>
            </section>
        </div>
    </AppLayout>
</template>
