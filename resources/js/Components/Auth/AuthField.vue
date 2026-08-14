<script setup lang="ts">
import { useTemplateRef } from 'vue';

interface Props {
    id: string;
    label: string;
    type?: 'email' | 'password' | 'text';
    autocomplete?: string;
    error?: string;
    placeholder?: string;
    inputmode?: 'email' | 'numeric' | 'text';
    required?: boolean;
    autofocus?: boolean;
}

withDefaults(defineProps<Props>(), {
    type: 'text',
    autocomplete: undefined,
    error: undefined,
    placeholder: undefined,
    inputmode: undefined,
    required: false,
    autofocus: false,
});

const model = defineModel<string>({ required: true });
const input = useTemplateRef<HTMLInputElement>('input');

function focus(): void {
    input.value?.focus();
}

defineExpose({ focus });
</script>

<template>
    <div class="flex flex-col gap-2">
        <label :for="id" class="text-sm font-semibold text-[#273148] dark:text-[#e7ebf2]">
            {{ label }}
        </label>
        <input
            ref="input"
            :id="id"
            v-model="model"
            :name="id"
            :type="type"
            :autocomplete="autocomplete"
            :placeholder="placeholder"
            :inputmode="inputmode"
            :required="required"
            :autofocus="autofocus"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="error ? id + '-error' : undefined"
            class="min-h-12 w-full rounded-xl border border-[#d5d8df] bg-white px-4 py-3 text-base text-[#172033] outline-none transition-[border-color,box-shadow] placeholder:text-[#98a2b3] focus-visible:border-[#c62f3d] focus-visible:ring-4 focus-visible:ring-[#c62f3d]/15 disabled:cursor-not-allowed disabled:opacity-60 dark:border-white/15 dark:bg-[#10141f] dark:text-white dark:placeholder:text-[#6f7b8e] dark:focus-visible:border-[#f08b94] dark:focus-visible:ring-[#f08b94]/15"
        />
        <p v-if="error" :id="id + '-error'" class="text-sm leading-relaxed text-[#b42318] dark:text-[#fda29b]">
            {{ error }}
        </p>
    </div>
</template>
