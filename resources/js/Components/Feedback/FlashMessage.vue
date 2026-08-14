<script setup lang="ts">
import AppIcon from '@/Components/App/AppIcon.vue';
import type { AppPageProps } from '@/types/page';
import { usePage } from '@inertiajs/vue3';
import { computed, shallowRef, watch } from 'vue';

const page = usePage<AppPageProps>();
const dismissedMessage = shallowRef<string | null>(null);

const flash = computed(() => {
    if (page.props.flash.success) {
        return { type: 'success' as const, message: page.props.flash.success };
    }

    if (page.props.flash.error) {
        return { type: 'error' as const, message: page.props.flash.error };
    }

    return null;
});

const isVisible = computed(() => flash.value !== null && flash.value.message !== dismissedMessage.value);

watch(flash, () => {
    dismissedMessage.value = null;
});
</script>

<template>
    <div aria-live="polite" aria-atomic="true" class="pointer-events-none fixed inset-x-4 top-20 z-[60] flex justify-center lg:left-auto lg:right-6 lg:top-6">
        <Transition name="flash">
            <div
                v-if="isVisible && flash"
                role="status"
                class="pointer-events-auto flex w-full max-w-md items-start gap-3 rounded-2xl border bg-white p-4 shadow-[0_18px_50px_rgba(23,32,51,0.18)] dark:bg-[#192232]"
                :class="flash.type === 'success' ? 'border-emerald-200 dark:border-emerald-400/30' : 'border-red-200 dark:border-red-400/30'"
            >
                <span class="grid size-8 shrink-0 place-items-center rounded-full" :class="flash.type === 'success' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-200' : 'bg-red-100 text-red-700 dark:bg-red-400/15 dark:text-red-200'">
                    <AppIcon :name="flash.type === 'success' ? 'check' : 'refresh'" class="size-4" />
                </span>
                <p class="flex-1 pt-1 text-sm font-medium leading-5">{{ flash.message }}</p>
                <button type="button" class="grid size-10 shrink-0 place-items-center rounded-lg text-lg text-[#747c8d] hover:bg-[#f0ede6] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] dark:hover:bg-white/5" aria-label="Cerrar mensaje" @click="dismissedMessage = flash.message">×</button>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.flash-enter-active,
.flash-leave-active {
    transition: opacity 160ms ease, transform 160ms ease;
}

.flash-enter-from,
.flash-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}

@media (prefers-reduced-motion: reduce) {
    .flash-enter-active,
    .flash-leave-active {
        transition: none;
    }
}
</style>
