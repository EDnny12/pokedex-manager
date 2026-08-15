import { getCurrentInstance, onUnmounted, shallowRef } from 'vue';

export interface TypewriterOptions {
    speedMs?: number;
    chunkSize?: number;
}

export function useTypewriter(options?: TypewriterOptions) {
    const speedMs = options?.speedMs ?? 16;
    const chunkSize = options?.chunkSize ?? 3;

    const displayedTexts = shallowRef<Record<string, string>>({});
    const typingStatus = shallowRef<Record<string, boolean>>({});
    const activeTimers = new Map<string, number>();

    function prefersReducedMotion(): boolean {
        if (typeof window === 'undefined' || !window.matchMedia) {
            return false;
        }

        return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function typeMessage(
        id: string,
        fullText: string,
        onProgress?: () => void,
        onComplete?: () => void,
    ): void {
        if (activeTimers.has(id)) {
            window.clearInterval(activeTimers.get(id));
            activeTimers.delete(id);
        }

        if (prefersReducedMotion() || !fullText) {
            displayedTexts.value = { ...displayedTexts.value, [id]: fullText };
            typingStatus.value = { ...typingStatus.value, [id]: false };
            onProgress?.();
            onComplete?.();
            return;
        }

        displayedTexts.value = { ...displayedTexts.value, [id]: '' };
        typingStatus.value = { ...typingStatus.value, [id]: true };

        let currentIndex = 0;
        const timer = window.setInterval(() => {
            currentIndex = Math.min(currentIndex + chunkSize, fullText.length);
            displayedTexts.value = {
                ...displayedTexts.value,
                [id]: fullText.slice(0, currentIndex),
            };
            onProgress?.();

            if (currentIndex >= fullText.length) {
                window.clearInterval(timer);
                activeTimers.delete(id);
                typingStatus.value = { ...typingStatus.value, [id]: false };
                onComplete?.();
            }
        }, speedMs);

        activeTimers.set(id, timer);
    }

    function isMessageTyping(id: string): boolean {
        return typingStatus.value[id] === true;
    }

    function getDisplayedContent(id: string, fallback: string): string {
        return displayedTexts.value[id] ?? fallback;
    }

    function completeAll(): void {
        activeTimers.forEach((timer) => window.clearInterval(timer));
        activeTimers.clear();
        typingStatus.value = {};
    }

    if (getCurrentInstance()) {
        onUnmounted(() => {
            completeAll();
        });
    }

    return {
        displayedTexts,
        typingStatus,
        typeMessage,
        isMessageTyping,
        getDisplayedContent,
        completeAll,
    };
}
