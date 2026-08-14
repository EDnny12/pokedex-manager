import { nextTick } from 'vue';

export function useFocusInvalidField() {
    async function focusFirstInvalidField(): Promise<void> {
        await nextTick();

        document.querySelector<HTMLInputElement>('[aria-invalid="true"]')?.focus();
    }

    return { focusFirstInvalidField };
}
