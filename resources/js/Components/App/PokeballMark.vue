<script setup lang="ts">
withDefaults(defineProps<{ size?: 'sm' | 'md' }>(), {
    size: 'md',
});
</script>

<template>
    <span
        class="relative inline-block shrink-0 overflow-hidden rounded-full border-[3px] border-current bg-[#c62f3d]"
        :class="size === 'sm' ? 'size-8' : 'size-10'"
        aria-hidden="true"
    >
        <span class="absolute inset-x-0 top-1/2 h-1.5 -translate-y-1/2 bg-white" />
        <span class="absolute left-1/2 top-1/2 size-3.5 -translate-x-1/2 -translate-y-1/2 rounded-full border-[3px] border-current bg-white" />
    </span>
</template>
