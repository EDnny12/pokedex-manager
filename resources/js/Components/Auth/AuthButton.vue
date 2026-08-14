<script setup lang="ts">
withDefaults(
    defineProps<{
        processing?: boolean;
    }>(),
    {
        processing: false,
    },
);

defineSlots<{
    default(): unknown;
}>();
</script>

<template>
    <button
        type="submit"
        :disabled="processing"
        :aria-busy="processing"
        class="flex min-h-12 w-full items-center justify-center gap-3 rounded-xl bg-[#c62f3d] px-5 py-3 text-base font-semibold text-white shadow-[0_10px_24px_rgba(198,47,61,0.22)] transition-[background-color,transform,box-shadow] hover:bg-[#ad2432] active:scale-[0.96] disabled:cursor-wait disabled:opacity-70 disabled:active:scale-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] focus-visible:ring-offset-2 dark:bg-[#e54c59] dark:hover:bg-[#f05d68] dark:focus-visible:ring-[#f08b94] dark:focus-visible:ring-offset-[#181e2b]"
    >
        <svg
            v-if="processing"
            aria-hidden="true"
            class="size-5 animate-spin motion-reduce:animate-none"
            viewBox="0 0 24 24"
            fill="none"
        >
            <circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" />
            <path class="opacity-90" d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
        </svg>
        <slot />
    </button>
</template>
