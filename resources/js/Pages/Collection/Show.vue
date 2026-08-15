<script setup lang="ts">
import AppIcon from '@/Components/App/AppIcon.vue';
import CollectionImpactPreview from '@/Components/Collection/CollectionImpactPreview.vue';
import ApiErrorBanner from '@/Components/Feedback/ApiErrorBanner.vue';
import ConfirmDialog from '@/Components/Feedback/ConfirmDialog.vue';
import FlashMessage from '@/Components/Feedback/FlashMessage.vue';
import PokemonDetail from '@/Components/Pokemon/PokemonDetail.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { CollectionImpact, CollectionPokemon } from '@/types/pokemon';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { shallowRef } from 'vue';
import { route } from '../../../../vendor/tightenco/ziggy';

const props = defineProps<{
    pokemon: CollectionPokemon;
    removalImpact: CollectionImpact;
    apiError: string | null;
}>();

const form = useForm({
    nickname: props.pokemon.nickname ?? '',
    notes: props.pokemon.notes ?? '',
    is_favorite: props.pokemon.is_favorite,
});

const deleteDialogOpen = shallowRef(false);
const deleting = shallowRef(false);

function save(): void {
    form.patch(route('collection.update', props.pokemon.collection_id), {
        preserveScroll: true,
        invalidateCacheTags: ['collection'],
        onSuccess: () => form.defaults(),
    });
}

function removePokemon(): void {
    deleting.value = true;
    router.delete(route('collection.destroy', props.pokemon.collection_id), {
        invalidateCacheTags: ['collection'],
        onFinish: () => {
            deleting.value = false;
        },
    });
}
</script>

<template>
    <AppLayout>
        <Head :title="pokemon.nickname || pokemon.display_name" />
        <FlashMessage />

        <div class="mx-auto flex w-full max-w-[96rem] flex-col gap-5 px-4 py-6 sm:px-6 sm:py-8 xl:px-9">
            <Link :href="route('dashboard')" class="inline-flex min-h-11 items-center gap-2 self-start rounded-lg pr-3 text-sm font-bold text-[#697180] hover:text-[#172033] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] dark:text-[#aab4c4] dark:hover:text-white">
                <AppIcon name="arrow-left" class="size-5" />
                Mi colección
            </Link>

            <ApiErrorBanner v-if="apiError" :message="apiError" />

            <PokemonDetail :pokemon="pokemon">
                <template v-if="pokemon.nickname" #eyebrow>
                    <p class="text-sm font-semibold text-[#697180] dark:text-[#aab4c4]">En tu colección como <strong class="text-[#172033] dark:text-white">{{ pokemon.nickname }}</strong></p>
                </template>

                <template #actions>
                    <Link :href="route('compare.index', { left: pokemon.name })" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-line-strong px-4 py-2.5 text-sm font-bold hover:bg-surface-subtle focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] dark:border-white/15 dark:hover:bg-white/5">
                        <AppIcon name="compare" class="size-4" /> Comparar
                    </Link>
                </template>

                <template #aside>
                    <form class="flex flex-col gap-5 rounded-[1.75rem] border border-line bg-surface p-5 sm:p-7 dark:border-white/10 dark:bg-[#161f2e]" @submit.prevent="save">
                        <div>
                            <p class="font-mono text-[0.68rem] font-bold uppercase tracking-[0.18em] text-[#9d3340] dark:text-[#f08f99]">Datos personales</p>
                            <h2 class="mt-1 text-2xl font-bold tracking-[-0.035em]">Mi Pokémon</h2>
                            <p class="mt-2 text-sm leading-6 text-[#697180] dark:text-[#aab4c4]">El apodo, las notas y el favorito se guardan solo en tu cuenta.</p>
                        </div>

                        <label class="flex flex-col gap-2">
                            <span class="text-sm font-bold">Apodo <span class="font-normal text-[#7b8392]">(opcional)</span></span>
                            <input v-model="form.nickname" type="text" maxlength="60" autocomplete="off" class="min-h-12 rounded-xl border-line-strong bg-white text-base focus:border-[#c62f3d] focus:ring-[#c62f3d] dark:border-white/10 dark:bg-[#111927] dark:text-white" placeholder="Ej. Chispitas" :aria-invalid="Boolean(form.errors.nickname)" :aria-describedby="form.errors.nickname ? 'nickname-error' : undefined" />
                            <span v-if="form.errors.nickname" id="nickname-error" class="text-sm font-medium text-[#b42534] dark:text-[#f3a0a8]">{{ form.errors.nickname }}</span>
                        </label>

                        <label class="flex flex-col gap-2">
                            <span class="text-sm font-bold">Notas <span class="font-normal text-[#7b8392]">(opcional)</span></span>
                            <textarea v-model="form.notes" rows="5" maxlength="2000" class="resize-y rounded-xl border-line-strong bg-white text-base leading-6 focus:border-[#c62f3d] focus:ring-[#c62f3d] dark:border-white/10 dark:bg-[#111927] dark:text-white" placeholder="¿Por qué es especial para ti?" :aria-invalid="Boolean(form.errors.notes)" :aria-describedby="form.errors.notes ? 'notes-error' : 'notes-help'" />
                            <span id="notes-help" class="text-xs text-[#7b8392]">{{ form.notes.length }}/2000 caracteres</span>
                            <span v-if="form.errors.notes" id="notes-error" class="text-sm font-medium text-[#b42534] dark:text-[#f3a0a8]">{{ form.errors.notes }}</span>
                        </label>

                        <label class="flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border border-line p-3 hover:bg-surface-subtle focus-within:ring-2 focus-within:ring-[#c62f3d] dark:border-white/10 dark:hover:bg-white/[0.03]">
                            <input v-model="form.is_favorite" type="checkbox" class="size-5 rounded border-[#bfc3cb] text-[#c62f3d] focus:ring-[#c62f3d]" />
                            <span class="flex-1 text-sm font-bold">Marcar como favorito</span>
                            <span class="text-xl text-amber-500" aria-hidden="true">★</span>
                        </label>

                        <button type="submit" :disabled="form.processing || !form.isDirty" class="min-h-12 rounded-xl bg-[#172033] px-5 py-3 text-sm font-bold text-white hover:bg-[#28344b] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-surface-subtle dark:text-[#172033] dark:hover:bg-white dark:focus-visible:ring-offset-[#161f2e]">
                            {{ form.processing ? 'Guardando…' : 'Guardar cambios' }}
                        </button>

                        <button type="button" class="min-h-12 rounded-xl px-4 py-3 text-sm font-bold text-[#a92634] hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] dark:text-[#f3a0a8] dark:hover:bg-[#c62f3d]/10" @click="deleteDialogOpen = true">
                            Eliminar de mi colección
                        </button>
                    </form>
                </template>
            </PokemonDetail>
        </div>

        <ConfirmDialog :open="deleteDialogOpen" :processing="deleting" :title="`¿Eliminar a ${pokemon.nickname || pokemon.display_name}?`" description="El apodo, las notas y el estado de favorito asociados también se eliminarán. Esta acción no se puede deshacer." confirm-label="Eliminar Pokémon" @close="deleteDialogOpen = false" @confirm="removePokemon">
            <template #details>
                <CollectionImpactPreview :impact="removalImpact" compact />
            </template>
        </ConfirmDialog>
    </AppLayout>
</template>
