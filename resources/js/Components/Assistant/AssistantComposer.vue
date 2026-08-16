<script setup lang="ts">
import AppIcon from '@/Components/App/AppIcon.vue';
import { onBeforeUnmount, shallowRef, useTemplateRef, watch } from 'vue';

const props = defineProps<{ sending: boolean }>();
const emit = defineEmits<{ submit: [message: string, images: File[]] }>();
const message = defineModel<string>({ default: '' });
const images = defineModel<File[]>('images', { required: true });
const imageInput = useTemplateRef<HTMLInputElement>('imageInput');
const previews = shallowRef<Array<{ file: File; url: string }>>([]);
const attachmentError = shallowRef('');
const allowedTypes = new Set(['image/jpeg', 'image/png', 'image/webp']);
const maximumImages = 2;
const maximumBytes = 5 * 1024 * 1024;

watch(images, (nextImages) => {
    previews.value.forEach((preview) => {
        if (!nextImages.includes(preview.file)) {
            URL.revokeObjectURL(preview.url);
        }
    });
    previews.value = nextImages.map((file) => {
        const existing = previews.value.find((preview) => preview.file === file);

        return existing ?? { file, url: URL.createObjectURL(file) };
    });
}, { immediate: true });

onBeforeUnmount(() => {
    previews.value.forEach((preview) => URL.revokeObjectURL(preview.url));
});

function submit(): void {
    const content = message.value.trim();

    if ((!content && images.value.length === 0) || content.length > 2000) {
        return;
    }

    emit('submit', content, [...images.value]);
}

const textarea = useTemplateRef<HTMLTextAreaElement>('textarea');

function openImagePicker(): void {
    if (props.sending || images.value.length >= maximumImages) {
        return;
    }

    imageInput.value?.click();
}

function focus(): void {
    textarea.value?.focus();
}

defineExpose({ openImagePicker, focus });

function selectImages(event: Event): void {
    const input = event.target as HTMLInputElement;
    const selectedImages = Array.from(input.files ?? []);
    const nextImages = [...images.value];
    attachmentError.value = '';

    for (const image of selectedImages) {
        if (nextImages.length >= maximumImages) {
            attachmentError.value = 'Puedes adjuntar hasta 2 imágenes por mensaje.';
            break;
        }

        if (!allowedTypes.has(image.type)) {
            attachmentError.value = 'Usa imágenes JPG, PNG o WebP.';
            continue;
        }

        if (image.size > maximumBytes) {
            attachmentError.value = 'Cada imagen puede pesar hasta 5 MB.';
            continue;
        }

        const duplicate = nextImages.some((item) => (
            item.name === image.name
            && item.size === image.size
            && item.lastModified === image.lastModified
        ));

        if (!duplicate) {
            nextImages.push(image);
        }
    }

    images.value = nextImages;
    input.value = '';
}

function removeImage(image: File): void {
    images.value = images.value.filter((item) => item !== image);
    attachmentError.value = '';
}

function handleKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        submit();
    }
}
</script>

<template>
    <form class="border-t border-line bg-white p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] dark:border-white/10 dark:bg-[#131b29]" @submit.prevent="submit">
        <div v-if="previews.length > 0" class="mb-2 flex gap-2 overflow-x-auto px-1 pb-1" aria-label="Imágenes listas para enviar">
            <figure v-for="preview in previews" :key="`${preview.file.name}-${preview.file.lastModified}`" class="relative size-20 shrink-0">
                <img :src="preview.url" :alt="`Vista previa de ${preview.file.name}`" class="size-full rounded-xl border border-line-strong object-cover dark:border-white/15" />
                <button
                    type="button"
                    class="absolute -right-1.5 -top-1.5 grid size-8 place-items-center rounded-full border border-line bg-white text-[#303849] shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] disabled:opacity-60 dark:border-white/15 dark:bg-[#172033] dark:text-white"
                    :aria-label="`Quitar ${preview.file.name}`"
                    :disabled="sending"
                    @click="removeImage(preview.file)"
                >
                    <AppIcon name="close" class="size-4" />
                </button>
            </figure>
        </div>
        <label for="assistant-message" class="sr-only">Escribe tu mensaje para Pika IA</label>
        <div class="flex items-end gap-2 rounded-2xl border border-line-strong bg-surface-subtle p-1.5 focus-within:border-[#c62f3d] focus-within:ring-1 focus-within:ring-[#c62f3d] dark:border-white/15 dark:bg-[#0e1420]">
            <label
                for="assistant-images"
                class="grid size-11 shrink-0 cursor-pointer place-items-center rounded-xl text-[#5f6878] transition-colors hover:bg-white hover:text-[#172033] focus-within:ring-2 focus-within:ring-[#c62f3d] dark:text-[#aab4c4] dark:hover:bg-white/10 dark:hover:text-white"
                :class="sending || images.length >= maximumImages ? 'pointer-events-none opacity-50' : ''"
                title="Adjuntar imágenes"
            >
                <AppIcon name="image" class="size-5" />
                <span class="sr-only">Adjuntar imágenes</span>
                <input
                    id="assistant-images"
                    ref="imageInput"
                    type="file"
                    class="sr-only"
                    accept="image/jpeg,image/png,image/webp"
                    multiple
                    :disabled="sending || images.length >= maximumImages"
                    @change="selectImages"
                />
            </label>
            <textarea
                id="assistant-message"
                ref="textarea"
                v-model="message"
                rows="1"
                maxlength="2000"
                class="max-h-32 min-h-11 flex-1 resize-none border-0 bg-transparent px-3 py-2.5 text-base leading-6 placeholder:text-[#818897] focus:ring-0 dark:text-white dark:placeholder:text-[#7f8999] sm:text-sm"
                placeholder="Pregunta o adjunta una imagen…"
                :disabled="sending"
                @keydown="handleKeydown"
            />
            <button
                type="submit"
                class="grid size-11 shrink-0 place-items-center rounded-xl bg-[#c62f3d] text-white transition-[background-color,transform] hover:bg-[#aa2634] active:scale-[0.96] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-[#c7cbd2] dark:focus-visible:ring-offset-[#0e1420]"
                :disabled="sending || (!message.trim() && images.length === 0)"
                aria-label="Enviar mensaje"
            >
                <AppIcon name="send" class="size-5" />
            </button>
        </div>
        <p v-if="attachmentError" class="mt-1.5 px-2 text-xs leading-4 text-[#a12835] dark:text-[#f2a0a8]" role="alert">{{ attachmentError }}</p>
        <p v-else class="mt-1.5 px-2 text-[0.68rem] leading-4 text-[#777f8f] dark:text-[#9aa5b5]">Hasta 2 imágenes · 5 MB cada una · Enter para enviar</p>
    </form>
</template>
