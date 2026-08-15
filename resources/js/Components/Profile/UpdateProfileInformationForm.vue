<script setup lang="ts">
import AppIcon from '@/Components/App/AppIcon.vue';
import type { AuthenticatedUser } from '@/types/page';
import { router, useForm } from '@inertiajs/vue3';
import { ref, shallowRef } from 'vue';
import { route } from '../../../../vendor/tightenco/ziggy';

const props = defineProps<{
    user: AuthenticatedUser;
}>();

const form = useForm({
    _method: 'PUT',
    name: props.user.name,
    email: props.user.email,
    photo: null as File | null,
});

const photoPreview = shallowRef<string | null>(null);
const photoInput = ref<HTMLInputElement | null>(null);

function selectNewPhoto(): void {
    photoInput.value?.click();
}

function updatePhotoPreview(): void {
    const photo = photoInput.value?.files?.[0];
    if (!photo) {
        return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
        photoPreview.value = e.target?.result as string;
    };
    reader.readAsDataURL(photo);
}

function deletePhoto(): void {
    router.delete(route('current-user-photo.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            photoPreview.value = null;
            clearPhotoFileInput();
        },
    });
}

function clearPhotoFileInput(): void {
    if (photoInput.value?.value) {
        photoInput.value.value = '';
    }
}

function updateProfileInformation(): void {
    if (photoInput.value?.files?.[0]) {
        form.photo = photoInput.value.files[0];
    }

    form.post(route('user-profile-information.update'), {
        errorBag: 'updateProfileInformation',
        preserveScroll: true,
        onSuccess: () => clearPhotoFileInput(),
    });
}
</script>

<template>
    <section class="flex flex-col gap-6 rounded-[1.75rem] border border-line bg-surface p-5 sm:p-7 dark:border-white/10 dark:bg-[#161f2e]">
        <header>
            <h3 class="text-xl font-bold tracking-tight">Información de la cuenta</h3>
            <p class="mt-1 text-sm text-[#697180] dark:text-[#aab4c4]">
                Actualiza el nombre de tu entrenador, correo electrónico y foto de perfil.
            </p>
        </header>

        <form class="flex flex-col gap-5" @submit.prevent="updateProfileInformation">
            <!-- Profile Photo -->
            <div class="flex flex-col gap-3">
                <label class="font-mono text-xs font-bold uppercase tracking-wider text-[#7a8291] dark:text-[#96a1b2]">
                    Foto de perfil
                </label>

                <input
                    ref="photoInput"
                    type="file"
                    class="hidden"
                    accept="image/jpeg,image/png,image/jpg"
                    @change="updatePhotoPreview"
                />

                <div class="flex items-center gap-4">
                    <!-- Current / Preview Photo -->
                    <div class="relative size-16 shrink-0 sm:size-20">
                        <img
                            v-if="photoPreview"
                            :src="photoPreview"
                            :alt="`Vista previa de foto de ${user.name}`"
                            class="size-full rounded-2xl border-2 border-line object-cover shadow-xs dark:border-white/15"
                        />
                        <img
                            v-else-if="user.profile_photo_url"
                            :src="user.profile_photo_url"
                            :alt="`Foto actual de ${user.name}`"
                            class="size-full rounded-2xl border-2 border-line object-cover shadow-xs dark:border-white/15"
                        />
                        <span
                            v-else
                            class="grid size-full place-items-center rounded-2xl bg-[#e9ded2] text-2xl font-black text-[#7b312e] shadow-xs sm:text-3xl dark:bg-[#3b2a30] dark:text-[#f2a6ac]"
                        >
                            {{ user.name.charAt(0).toUpperCase() }}
                        </span>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-line bg-white px-4 py-2 text-xs font-bold text-[#172033] shadow-xs transition-colors hover:border-line-strong hover:bg-surface-subtle focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] dark:border-white/10 dark:bg-white/5 dark:text-[#f7f4ed] dark:hover:bg-white/10"
                            @click.prevent="selectNewPhoto"
                        >
                            <AppIcon name="image" class="size-4" />
                            Seleccionar nueva foto
                        </button>

                        <button
                            v-if="user.profile_photo_url || photoPreview"
                            type="button"
                            class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-transparent px-3 py-2 text-xs font-bold text-[#a92634] hover:bg-[#fff0f1] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] dark:text-[#f3a0a8] dark:hover:bg-[#c62f3d]/10"
                            @click.prevent="deletePhoto"
                        >
                            <AppIcon name="trash" class="size-4" />
                            Quitar foto
                        </button>
                    </div>
                </div>

                <p v-if="form.errors.photo" class="text-xs font-bold text-[#c62f3d] dark:text-[#f08f99]">
                    {{ form.errors.photo }}
                </p>
            </div>

            <!-- Name -->
            <div class="flex flex-col gap-1.5">
                <label for="name" class="font-mono text-xs font-bold uppercase tracking-wider text-[#7a8291] dark:text-[#96a1b2]">
                    Nombre de entrenador
                </label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    required
                    autocomplete="name"
                    class="min-h-12 w-full rounded-xl border border-line bg-white px-4 py-2.5 text-sm font-semibold transition-colors focus:border-[#c62f3d] focus:outline-none focus:ring-2 focus:ring-[#c62f3d]/20 dark:border-white/10 dark:bg-white/5 dark:focus:border-[#f08f99] dark:focus:ring-[#f08f99]/20"
                />
                <p v-if="form.errors.name" class="text-xs font-bold text-[#c62f3d] dark:text-[#f08f99]">
                    {{ form.errors.name }}
                </p>
            </div>

            <!-- Email -->
            <div class="flex flex-col gap-1.5">
                <label for="email" class="font-mono text-xs font-bold uppercase tracking-wider text-[#7a8291] dark:text-[#96a1b2]">
                    Correo electrónico
                </label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    autocomplete="username"
                    class="min-h-12 w-full rounded-xl border border-line bg-white px-4 py-2.5 text-sm font-semibold transition-colors focus:border-[#c62f3d] focus:outline-none focus:ring-2 focus:ring-[#c62f3d]/20 dark:border-white/10 dark:bg-white/5 dark:focus:border-[#f08f99] dark:focus:ring-[#f08f99]/20"
                />
                <p v-if="form.errors.email" class="text-xs font-bold text-[#c62f3d] dark:text-[#f08f99]">
                    {{ form.errors.email }}
                </p>
            </div>

            <!-- Submit Button & Feedback -->
            <div class="flex items-center justify-between pt-2">
                <p v-if="form.recentlySuccessful" class="text-xs font-bold text-emerald-600 dark:text-emerald-400">
                    ✓ Cambios guardados correctamente.
                </p>
                <span v-else />

                <button
                    type="submit"
                    class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-[#172033] px-6 py-3 text-sm font-bold text-white shadow-xs transition-colors hover:bg-[#25334d] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] disabled:opacity-50 dark:bg-[#f7f4ed] dark:text-[#172033] dark:hover:bg-white"
                    :disabled="form.processing"
                >
                    <AppIcon name="check" class="size-4" />
                    {{ form.processing ? 'Guardando…' : 'Guardar cambios' }}
                </button>
            </div>
        </form>
    </section>
</template>
