import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useTypewriter } from './useTypewriter';

describe('useTypewriter', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        Object.defineProperty(window, 'matchMedia', {
            writable: true,
            value: undefined,
        });
    });

    afterEach(() => {
        vi.restoreAllMocks();
        vi.useRealTimers();
        Object.defineProperty(window, 'matchMedia', {
            writable: true,
            value: undefined,
        });
    });

    it('revela el texto de forma progresiva a intervalos definidos', () => {
        const { typeMessage, getDisplayedContent, isMessageTyping } = useTypewriter({
            speedMs: 10,
            chunkSize: 2,
        });

        typeMessage('msg-1', 'Pikachu');

        expect(isMessageTyping('msg-1')).toBe(true);
        expect(getDisplayedContent('msg-1', 'fallback')).toBe('');

        vi.advanceTimersByTime(10);
        expect(getDisplayedContent('msg-1', 'fallback')).toBe('Pi');

        vi.advanceTimersByTime(10);
        expect(getDisplayedContent('msg-1', 'fallback')).toBe('Pika');

        vi.advanceTimersByTime(20);
        expect(getDisplayedContent('msg-1', 'fallback')).toBe('Pikachu');
        expect(isMessageTyping('msg-1')).toBe(false);
    });

    it('completa inmediatamente si la persona prefiere movimiento reducido', () => {
        Object.defineProperty(window, 'matchMedia', {
            writable: true,
            value: vi.fn().mockImplementation((query: string) => ({
                matches: query.includes('prefers-reduced-motion: reduce'),
                media: query,
                onchange: null,
                addListener: vi.fn(),
                removeListener: vi.fn(),
                addEventListener: vi.fn(),
                removeEventListener: vi.fn(),
                dispatchEvent: vi.fn(),
            })),
        });

        const { typeMessage, getDisplayedContent, isMessageTyping } = useTypewriter();

        typeMessage('msg-2', 'Texto completo');

        expect(isMessageTyping('msg-2')).toBe(false);
        expect(getDisplayedContent('msg-2', 'fallback')).toBe('Texto completo');
    });

    it('devuelve el fallback cuando un mensaje no ha sido tipiado', () => {
        const { getDisplayedContent } = useTypewriter();

        expect(getDisplayedContent('unknown-id', 'Contenido original')).toBe('Contenido original');
    });

    it('permite cancelar o completar todas las animaciones activas', () => {
        const { typeMessage, completeAll, isMessageTyping } = useTypewriter({ speedMs: 10 });

        typeMessage('msg-3', 'Mensaje largo');
        expect(isMessageTyping('msg-3')).toBe(true);

        completeAll();
        expect(isMessageTyping('msg-3')).toBe(false);
    });
});
