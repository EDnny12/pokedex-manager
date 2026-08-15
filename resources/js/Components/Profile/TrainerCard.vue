<script setup lang="ts">
import AppIcon from '@/Components/App/AppIcon.vue';
import PokeballMark from '@/Components/App/PokeballMark.vue';
import PokemonImage from '@/Components/Pokemon/PokemonImage.vue';
import PokemonTypeBadge from '@/Components/Pokemon/PokemonTypeBadge.vue';
import type { TrainerCardData } from '@/types/pokemon';
import { formatPokemonId } from '@/utils/pokemon';
import { Link } from '@inertiajs/vue3';
import { shallowRef, watch } from 'vue';
import { route } from '../../../../vendor/tightenco/ziggy';

const props = defineProps<{
    card: TrainerCardData;
}>();

const regenerating = shallowRef(false);

const localBio = shallowRef({
    headline: props.card.headline,
    description: props.card.description,
    is_ai_generated: props.card.is_ai_generated,
});

watch(
    () => props.card,
    (newCard) => {
        localBio.value = {
            headline: newCard.headline,
            description: newCard.description,
            is_ai_generated: newCard.is_ai_generated,
        };
    },
    { deep: true },
);

function xsrfToken(): string {
    if (typeof document === 'undefined') {
        return '';
    }

    const cookie = document.cookie
        .split('; ')
        .find((item) => item.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.split('=').slice(1).join('=')) : '';
}

async function regenerateBio(): Promise<void> {
    if (regenerating.value) {
        return;
    }

    regenerating.value = true;

    try {
        const response = await fetch(route('profile.bio.regenerate'), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (response.ok) {
            const data = (await response.json()) as {
                headline?: string;
                description?: string;
                is_ai_generated?: boolean;
            };

            if (data.headline && data.description) {
                localBio.value = {
                    headline: data.headline,
                    description: data.description,
                    is_ai_generated: Boolean(data.is_ai_generated),
                };
            }
        }
    } catch {
        // Keep current bio gracefully on error
    } finally {
        regenerating.value = false;
    }
}

const rankBadgeClasses: Record<string, string> = {
    'Maestro Pokémon': 'bg-amber-500/15 text-amber-600 dark:bg-amber-400/20 dark:text-amber-300 border-amber-500/30',
    'Líder': 'bg-purple-500/15 text-purple-600 dark:bg-purple-400/20 dark:text-purple-300 border-purple-500/30',
    'Experto': 'bg-blue-500/15 text-blue-600 dark:bg-blue-400/20 dark:text-blue-300 border-blue-500/30',
    'Entrenador': 'bg-emerald-500/15 text-emerald-600 dark:bg-emerald-400/20 dark:text-emerald-300 border-emerald-500/30',
    'Novato': 'bg-slate-500/15 text-slate-600 dark:bg-slate-400/20 dark:text-slate-300 border-slate-500/30',
};
</script>

<template>
    <section class="relative overflow-hidden rounded-[2rem] border border-line bg-gradient-to-br from-white via-surface to-surface-subtle shadow-sm dark:border-white/10 dark:from-[#192233] dark:via-[#131b29] dark:to-[#0f1622]">
        <!-- Holographic / Watermark accents -->
        <div class="pointer-events-none absolute -right-12 -top-12 size-64 opacity-5 dark:opacity-5">
            <PokeballMark size="md" class="size-full" />
        </div>

        <div class="relative flex flex-col gap-6 p-5 sm:p-7 xl:p-8">
            <!-- Top License Bar -->
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line/80 pb-4 dark:border-white/10">
                <div class="flex items-center gap-2.5">
                    <PokeballMark size="sm" />
                    <span class="font-mono text-xs font-bold uppercase tracking-[0.18em] text-[#8b91a0] dark:text-[#9aa5b5]">
                        Licencia de Entrenador Oficial
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="rounded-lg border border-line bg-white/80 px-2.5 py-1 font-mono text-xs font-bold tracking-wider text-[#505867] backdrop-blur-xs dark:border-white/10 dark:bg-white/5 dark:text-[#d6dbe4]">
                        {{ card.trainer_number }}
                    </span>
                    <span
                        class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-1 text-xs font-bold"
                        :class="rankBadgeClasses[card.rank] ?? rankBadgeClasses['Novato']"
                    >
                        <span class="size-1.5 rounded-full bg-current" />
                        {{ card.rank }}
                    </span>
                </div>
            </div>

            <!-- Profile Info & AI Identity -->
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)] lg:items-center">
                <div class="flex items-center gap-4 sm:gap-5">
                    <div class="relative size-16 shrink-0 sm:size-20">
                        <img
                            v-if="card.avatar_url"
                            :src="card.avatar_url"
                            :alt="`Avatar de ${card.trainer_name}`"
                            class="size-full rounded-2xl border-2 border-line object-cover shadow-xs dark:border-white/15"
                        />
                        <span
                            v-else
                            class="grid size-full place-items-center rounded-2xl bg-[#e9ded2] text-2xl font-black text-[#7b312e] shadow-xs sm:text-3xl dark:bg-[#3b2a30] dark:text-[#f2a6ac]"
                        >
                            {{ card.trainer_name.charAt(0).toUpperCase() }}
                        </span>
                    </div>

                    <div class="min-w-0 flex-1">
                        <h2 class="truncate text-2xl font-black tracking-tight sm:text-3xl">{{ card.trainer_name }}</h2>
                        <p class="mt-1 font-mono text-xs text-[#777f8f] dark:text-[#9aa5b5]">
                            Miembro desde {{ card.member_since }}
                        </p>
                    </div>
                </div>

                <!-- AI Trainer Identity Box -->
                <div
                    class="relative overflow-hidden rounded-2xl border border-line bg-white/80 p-4 shadow-xs backdrop-blur-xs transition-all dark:border-white/10 dark:bg-white/[0.04]"
                    :aria-busy="regenerating"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="inline-flex items-center gap-1.5 font-mono text-[0.65rem] font-bold uppercase tracking-wider text-[#9d3340] dark:text-[#f08f99]">
                            <AppIcon name="sparkles" class="size-3.5" :class="{ 'motion-safe:animate-spin': regenerating }" />
                            {{ regenerating ? 'Pika IA analizando…' : (localBio.is_ai_generated ? 'Identidad Pika IA' : 'Identidad de Entrenador') }}
                        </span>
                        <button
                            v-if="card.total_pokemon > 0"
                            type="button"
                            class="inline-flex min-h-7 items-center gap-1.5 rounded-lg px-2 py-1 text-[0.7rem] font-semibold text-[#697180] transition-colors hover:bg-surface-subtle hover:text-[#c62f3d] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] disabled:opacity-50 dark:text-[#9aa5b5] dark:hover:bg-white/5 dark:hover:text-[#f08f99]"
                            :disabled="regenerating"
                            :aria-label="'Regenerar descripción con IA'"
                            @click="regenerateBio"
                        >
                            <AppIcon name="refresh" class="size-3" :class="{ 'motion-safe:animate-spin': regenerating }" />
                            <span>{{ regenerating ? 'Generando…' : 'Regenerar' }}</span>
                        </button>
                    </div>

                    <!-- Shimmer / Skeleton Loading while regenerating -->
                    <div v-if="regenerating" class="mt-3 flex flex-col gap-2" role="status" aria-live="polite">
                        <div class="h-4.5 w-48 rounded-md bg-gradient-to-r from-[#edd8da] via-[#faeff0] to-[#edd8da] motion-safe:animate-pulse dark:from-[#3a2027] dark:via-[#4e2733] dark:to-[#3a2027]" />
                        <div class="space-y-1.5 pt-0.5">
                            <div class="h-3 w-full rounded-md bg-gradient-to-r from-[#edd8da] via-[#faeff0] to-[#edd8da] motion-safe:animate-pulse dark:from-[#3a2027] dark:via-[#4e2733] dark:to-[#3a2027]" />
                            <div class="h-3 w-3/4 rounded-md bg-gradient-to-r from-[#edd8da] via-[#faeff0] to-[#edd8da] motion-safe:animate-pulse dark:from-[#3a2027] dark:via-[#4e2733] dark:to-[#3a2027]" />
                        </div>
                        <span class="sr-only">Generando nueva identidad de entrenador…</span>
                    </div>

                    <!-- Static Bio Content -->
                    <template v-else>
                        <p class="mt-1.5 text-sm font-bold text-[#172033] dark:text-[#f7f4ed]">
                            {{ localBio.headline }}
                        </p>
                        <p class="mt-1 text-xs leading-5 text-[#505867] dark:text-[#aab4c4]">
                            {{ localBio.description }}
                        </p>
                    </template>
                </div>
            </div>

            <!-- Key Metrics Grid -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <div class="rounded-2xl border border-line bg-white/90 p-3.5 sm:p-4 dark:border-white/10 dark:bg-white/[0.02]">
                    <dt class="font-mono text-[0.65rem] font-bold uppercase tracking-[0.12em] text-[#7a8291] dark:text-[#96a1b2]">Colección</dt>
                    <dd class="mt-1 flex items-baseline gap-1">
                        <span class="text-2xl font-black">{{ card.total_pokemon }}</span>
                        <span class="text-xs text-[#7a8291] dark:text-[#96a1b2]">Pokémon</span>
                    </dd>
                </div>

                <div class="rounded-2xl border border-line bg-white/90 p-3.5 sm:p-4 dark:border-white/10 dark:bg-white/[0.02]">
                    <dt class="font-mono text-[0.65rem] font-bold uppercase tracking-[0.12em] text-[#7a8291] dark:text-[#96a1b2]">Favoritos</dt>
                    <dd class="mt-1 flex items-baseline gap-1">
                        <span class="text-2xl font-black text-[#c62f3d] dark:text-[#f08f99]">{{ card.favorites_count }}</span>
                        <span class="text-xs text-[#7a8291] dark:text-[#96a1b2]">marcados</span>
                    </dd>
                </div>

                <div class="col-span-2 rounded-2xl border border-line bg-white/90 p-3.5 sm:col-span-1 sm:p-4 dark:border-white/10 dark:bg-white/[0.02]">
                    <dt class="font-mono text-[0.65rem] font-bold uppercase tracking-[0.12em] text-[#7a8291] dark:text-[#96a1b2]">Especialidad</dt>
                    <dd class="mt-1 flex items-center gap-2">
                        <PokemonTypeBadge v-if="card.dominant_type" :type="card.dominant_type" />
                        <span v-else class="text-xs font-semibold text-[#7a8291] dark:text-[#96a1b2]">Por descubrir</span>
                    </dd>
                </div>
            </div>

            <!-- Party Section: 6 Slots Grid -->
            <div class="flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold tracking-tight">Equipo Activo (Party)</h3>
                        <p class="text-xs text-[#7a8291] dark:text-[#96a1b2]">Los 6 Pokémon destacados de tu colección</p>
                    </div>
                    <span v-if="card.signature_pokemon" class="inline-flex items-center gap-1 rounded-full border border-amber-500/30 bg-amber-500/10 px-2.5 py-0.5 font-mono text-[0.65rem] font-bold text-amber-700 dark:text-amber-300">
                        ⭐ {{ card.signature_pokemon.display_name }} (Insignia)
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    <!-- Render up to 6 party slots -->
                    <template v-for="index in 6" :key="index">
                        <div
                            v-if="card.party[index - 1]"
                            class="group relative flex flex-col items-center justify-between rounded-2xl border border-line bg-white p-3 text-center transition-all hover:border-[#c62f3d]/50 hover:shadow-md dark:border-white/10 dark:bg-white/[0.03] dark:hover:border-[#f08f99]/50"
                            :class="{ 'ring-2 ring-amber-500/40 dark:ring-amber-400/40': card.signature_pokemon && card.party[index - 1].id === card.signature_pokemon.id }"
                        >
                            <!-- Signature Badge -->
                            <span
                                v-if="card.signature_pokemon && card.party[index - 1].id === card.signature_pokemon.id"
                                class="absolute -top-2 left-1/2 -translate-x-1/2 rounded-full bg-amber-500 px-2 py-0.5 text-[0.6rem] font-black text-white shadow-xs"
                            >
                                INSIGNIA
                            </span>

                            <span class="self-end font-mono text-[0.6rem] font-bold text-[#8b91a0] dark:text-[#9aa5b5]">
                                {{ formatPokemonId(card.party[index - 1].id) }}
                            </span>

                            <div class="size-16 shrink-0">
                                <PokemonImage :src="card.party[index - 1].image" :alt="card.party[index - 1].display_name" class="size-full object-contain" />
                            </div>

                            <div class="mt-1 w-full">
                                <p class="truncate text-xs font-bold text-[#172033] dark:text-[#f7f4ed]">
                                    {{ card.party[index - 1].display_name }}
                                </p>
                                <div class="mt-1 flex justify-center gap-1">
                                    <PokemonTypeBadge
                                        v-for="t in card.party[index - 1].types.slice(0, 2)"
                                        :key="t"
                                        :type="t"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Empty Slot Placeholder -->
                        <div
                            v-else
                            class="flex min-h-36 flex-col items-center justify-center rounded-2xl border border-dashed border-line bg-surface-subtle/50 p-3 text-center dark:border-white/10 dark:bg-white/[0.01]"
                        >
                            <div class="size-8 opacity-20">
                                <PokeballMark size="sm" class="size-full" />
                            </div>
                            <span class="mt-2 font-mono text-[0.65rem] font-semibold text-[#8b91a0] dark:text-[#9aa5b5]">
                                Slot {{ index }}
                            </span>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Empty Collection Callout -->
            <div
                v-if="card.total_pokemon === 0"
                class="flex flex-col items-center justify-between gap-4 rounded-2xl border border-line bg-white/70 p-4 text-center sm:flex-row sm:text-left dark:border-white/10 dark:bg-white/[0.02]"
            >
                <div>
                    <h4 class="text-sm font-bold">¡Tu equipo está esperando!</h4>
                    <p class="text-xs text-[#697180] dark:text-[#aab4c4]">Explora la Pokédex y añade tus primeros Pokémon para desbloquear rangos e insignias.</p>
                </div>
                <Link
                    :href="route('pokedex.index')"
                    class="inline-flex min-h-10 shrink-0 items-center justify-center gap-2 rounded-xl bg-[#c62f3d] px-4 py-2 text-xs font-bold text-white shadow-xs transition-colors hover:bg-[#a82532] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d]"
                >
                    <AppIcon name="explore" class="size-4" />
                    Explorar Pokédex
                </Link>
            </div>
        </div>
    </section>
</template>
