const baseUrl = process.env.LARAVEL_INTERNAL_URL ?? 'http://laravel.test/api/internal/assistant';

export async function callLaravel(
    contextToken: string,
    path: string,
    options: { method?: 'GET' | 'POST'; query?: Record<string, unknown>; body?: unknown } = {},
): Promise<Record<string, unknown>> {
    const url = new URL(`${baseUrl.replace(/\/$/, '')}/${path.replace(/^\//, '')}`);

    for (const [key, value] of Object.entries(options.query ?? {})) {
        if (value !== undefined && value !== null && value !== '') {
            url.searchParams.set(key, String(value));
        }
    }

    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), Number(process.env.LARAVEL_TIMEOUT_MS ?? 10_000));

    try {
        const response = await fetch(url, {
            method: options.method ?? 'GET',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                Authorization: `Bearer ${contextToken}`,
            },
            body: options.body === undefined ? undefined : JSON.stringify(options.body),
            signal: controller.signal,
        });
        const payload = await response.json() as Record<string, unknown>;

        if (!response.ok) {
            throw new Error(typeof payload.message === 'string' ? payload.message : `Laravel respondió ${response.status}.`);
        }

        return payload.data && typeof payload.data === 'object'
            ? payload.data as Record<string, unknown>
            : payload;
    } finally {
        clearTimeout(timeout);
    }
}
