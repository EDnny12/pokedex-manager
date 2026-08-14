<script setup lang="ts">
defineProps<{
    title: string;
    description: string;
}>();

defineSlots<{
    default(): unknown;
    footer?(): unknown;
}>();
</script>

<template>
    <div class="min-h-screen bg-canvas text-[#172033] antialiased dark:bg-[#10141f] dark:text-[#f8fafc]">
        <div class="grid min-h-screen lg:grid-cols-[minmax(0,1.05fr)_minmax(28rem,0.95fr)]">
            <aside
                class="relative hidden overflow-hidden bg-[#172033] px-12 py-14 text-white lg:flex lg:flex-col lg:justify-between xl:px-20"
            >
                <div aria-hidden="true" class="absolute -right-24 -top-24 size-80 rounded-full border border-white/10" />
                <div aria-hidden="true" class="absolute -right-8 top-16 size-52 rounded-full border border-white/10" />
                <div aria-hidden="true" class="absolute -bottom-40 -left-28 size-96 rounded-full border border-white/10" />

                <div class="relative flex items-center gap-3">
                    <div class="relative size-11 overflow-hidden rounded-full border-[3px] border-white bg-[#e63946]">
                        <div class="absolute inset-x-0 top-1/2 h-2 -translate-y-1/2 bg-white" />
                        <div class="absolute left-1/2 top-1/2 size-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-[3px] border-[#172033] bg-white" />
                    </div>
                    <div>
                        <p class="text-lg font-bold tracking-tight">Pokédex Manager</p>
                        <p class="font-mono text-xs uppercase tracking-[0.18em] text-white/55">Colección personal</p>
                    </div>
                </div>

                <div class="relative flex max-w-xl flex-col gap-8">
                    <div class="flex flex-col gap-4">
                        <p class="font-mono text-xs uppercase tracking-[0.24em] text-[#f4a4aa]">Tu colección, con contexto</p>
                        <h2 class="max-w-lg text-5xl font-bold leading-[1.05] tracking-[-0.04em] xl:text-6xl">
                            Cada Pokémon tiene una historia.
                        </h2>
                        <p class="max-w-md text-lg leading-relaxed text-white/65">
                            Organiza tu colección, registra lo que importa y descubre patrones en tus Pokémon.
                        </p>
                    </div>

                    <ul class="grid grid-cols-3 gap-6 border-t border-white/15 pt-6">
                        <li class="flex flex-col gap-1">
                            <span class="font-mono text-xs text-[#f4a4aa]">01</span>
                            <span class="text-sm font-semibold">Explora</span>
                        </li>
                        <li class="flex flex-col gap-1">
                            <span class="font-mono text-xs text-[#f4a4aa]">02</span>
                            <span class="text-sm font-semibold">Colecciona</span>
                        </li>
                        <li class="flex flex-col gap-1">
                            <span class="font-mono text-xs text-[#f4a4aa]">03</span>
                            <span class="text-sm font-semibold">Analiza</span>
                        </li>
                    </ul>
                </div>

                <p class="relative font-mono text-xs uppercase tracking-[0.18em] text-white/40">
                    Preparado para tu próxima captura
                </p>
            </aside>

            <main id="main-content" class="flex min-h-screen items-center justify-center px-5 py-10 sm:px-8 lg:px-12">
                <div class="flex w-full max-w-md flex-col gap-8">
                    <div class="flex items-center gap-3 lg:hidden">
                        <div class="relative size-10 overflow-hidden rounded-full border-[3px] border-[#172033] bg-[#e63946] dark:border-white">
                            <div class="absolute inset-x-0 top-1/2 h-2 -translate-y-1/2 bg-canvas dark:bg-[#10141f]" />
                            <div class="absolute left-1/2 top-1/2 size-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-[3px] border-[#172033] bg-canvas dark:border-white dark:bg-[#10141f]" />
                        </div>
                        <p class="font-bold tracking-tight">Pokédex Manager</p>
                    </div>

                    <section
                        class="flex flex-col gap-8 rounded-[1.75rem] bg-white p-6 shadow-[0_24px_70px_rgba(23,32,51,0.10)] sm:p-9 dark:bg-[#181e2b] dark:shadow-black/30"
                    >
                        <header class="flex flex-col gap-3">
                            <p class="font-mono text-xs font-semibold uppercase tracking-[0.2em] text-[#c62f3d] dark:text-[#f08b94]">
                                Acceso de entrenador
                            </p>
                            <div class="flex flex-col gap-2">
                                <h1 class="text-3xl font-bold leading-tight tracking-[-0.03em]">{{ title }}</h1>
                                <p class="text-pretty text-base leading-relaxed text-[#667085] dark:text-[#aeb7c7]">
                                    {{ description }}
                                </p>
                            </div>
                        </header>

                        <slot />

                        <footer v-if="$slots.footer" class="border-t border-line pt-6 text-center text-sm text-[#667085] dark:border-white/10 dark:text-[#aeb7c7]">
                            <slot name="footer" />
                        </footer>
                    </section>
                </div>
            </main>
        </div>
    </div>
</template>
