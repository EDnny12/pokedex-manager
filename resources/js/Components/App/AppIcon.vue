<script setup lang="ts">
defineProps<{
    name:
        | 'collection'
        | 'explore'
        | 'insights'
        | 'compare'
        | 'search'
        | 'plus'
        | 'chevron-down'
        | 'arrow-left'
        | 'trash'
        | 'sparkles'
        | 'user'
        | 'logout'
        | 'refresh'
        | 'check'
        | 'chat'
        | 'image'
        | 'send'
        | 'close'
        | 'menu';
}>();
</script>

<template>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <template v-if="name === 'collection'">
            <path d="M4 7.5 12 3l8 4.5-8 4.5-8-4.5Z" />
            <path d="M4 12.5 12 17l8-4.5M4 17l8 4 8-4" />
        </template>
        <template v-else-if="name === 'explore'">
            <circle cx="12" cy="12" r="9" />
            <path d="m15.5 8.5-2 5-5 2 2-5 5-2Z" />
        </template>
        <template v-else-if="name === 'insights'">
            <path d="M4 19V9m5 10V5m5 14v-7m5 7V3" />
        </template>
        <template v-else-if="name === 'compare'">
            <path d="M7 7h13m0 0-3-3m3 3-3 3M17 17H4m0 0 3-3m-3 3 3 3" />
        </template>
        <template v-else-if="name === 'search'">
            <circle cx="10.8" cy="10.8" r="6.8" />
            <path d="m16 16 4 4" />
        </template>
        <template v-else-if="name === 'plus'">
            <path d="M12 5v14M5 12h14" />
        </template>
        <template v-else-if="name === 'chevron-down'">
            <path d="m7 10 5 5 5-5" />
        </template>
        <template v-else-if="name === 'arrow-left'">
            <path d="m15 18-6-6 6-6" />
        </template>
        <template v-else-if="name === 'trash'">
            <path d="M4 7h16M9 7V4h6v3m3 0-1 13H7L6 7m4 4v5m4-5v5" />
        </template>
        <template v-else-if="name === 'sparkles'">
            <path d="m12 3 1.2 3.8L17 8l-3.8 1.2L12 13l-1.2-3.8L7 8l3.8-1.2L12 3ZM5 14l.8 2.2L8 17l-2.2.8L5 20l-.8-2.2L2 17l2.2-.8L5 14Zm13-1 1 2 2 1-2 1-1 2-1-2-2-1 2-1 1-2Z" />
        </template>
        <template v-else-if="name === 'user'">
            <circle cx="12" cy="8" r="4" />
            <path d="M4 21a8 8 0 0 1 16 0" />
        </template>
        <template v-else-if="name === 'logout'">
            <path d="M10 5H5v14h5m4-3 4-4-4-4m4 4H9" />
        </template>
        <template v-else-if="name === 'refresh'">
            <path d="M20 7v5h-5M4 17v-5h5" />
            <path d="M6.1 8A7 7 0 0 1 18 6l2 6M18 16a7 7 0 0 1-11.9 2L4 12" />
        </template>
        <template v-else-if="name === 'chat'">
            <path d="M5 18.5 3.5 21l3.8-1.1a9 9 0 1 0-2.3-1.4Z" />
            <path d="M8 11.5h8M8 15h5" />
        </template>
        <template v-else-if="name === 'image'">
            <rect x="3" y="4" width="18" height="16" rx="2" />
            <circle cx="8.5" cy="9" r="1.5" />
            <path d="m4 17 4.5-4.5 3.5 3 2.5-2.5 5.5 5" />
        </template>
        <template v-else-if="name === 'send'">
            <path d="m3 11 18-8-7.5 18-2.2-7.3L3 11Z" />
            <path d="m11.3 13.7 4.2-4.2" />
        </template>
        <template v-else-if="name === 'close'">
            <path d="m6 6 12 12M18 6 6 18" />
        </template>
        <template v-else-if="name === 'menu'">
            <path d="M4 7h16M4 12h16M4 17h16" />
        </template>
        <template v-else>
            <path d="m5 12 4 4L19 6" />
        </template>
    </svg>
</template>
