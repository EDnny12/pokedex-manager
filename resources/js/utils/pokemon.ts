import type { PokemonStatKey } from '@/types/pokemon';

export const pokemonTypeLabels: Record<string, string> = {
    normal: 'Normal',
    fire: 'Fuego',
    water: 'Agua',
    electric: 'Eléctrico',
    grass: 'Planta',
    ice: 'Hielo',
    fighting: 'Lucha',
    poison: 'Veneno',
    ground: 'Tierra',
    flying: 'Volador',
    psychic: 'Psíquico',
    bug: 'Bicho',
    rock: 'Roca',
    ghost: 'Fantasma',
    dragon: 'Dragón',
    dark: 'Siniestro',
    steel: 'Acero',
    fairy: 'Hada',
};

export const pokemonTypeClasses: Record<string, string> = {
    normal: 'border-stone-300 bg-stone-100 text-stone-700 dark:border-stone-500/40 dark:bg-stone-500/15 dark:text-stone-200',
    fire: 'border-orange-300 bg-orange-50 text-orange-800 dark:border-orange-400/40 dark:bg-orange-400/15 dark:text-orange-200',
    water: 'border-blue-300 bg-blue-50 text-blue-800 dark:border-blue-400/40 dark:bg-blue-400/15 dark:text-blue-200',
    electric: 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-400/40 dark:bg-amber-400/15 dark:text-amber-100',
    grass: 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-400/40 dark:bg-emerald-400/15 dark:text-emerald-200',
    ice: 'border-cyan-300 bg-cyan-50 text-cyan-800 dark:border-cyan-400/40 dark:bg-cyan-400/15 dark:text-cyan-200',
    fighting: 'border-red-300 bg-red-50 text-red-800 dark:border-red-400/40 dark:bg-red-400/15 dark:text-red-200',
    poison: 'border-fuchsia-300 bg-fuchsia-50 text-fuchsia-800 dark:border-fuchsia-400/40 dark:bg-fuchsia-400/15 dark:text-fuchsia-200',
    ground: 'border-yellow-300 bg-yellow-50 text-yellow-900 dark:border-yellow-400/40 dark:bg-yellow-400/15 dark:text-yellow-100',
    flying: 'border-indigo-300 bg-indigo-50 text-indigo-800 dark:border-indigo-400/40 dark:bg-indigo-400/15 dark:text-indigo-200',
    psychic: 'border-pink-300 bg-pink-50 text-pink-800 dark:border-pink-400/40 dark:bg-pink-400/15 dark:text-pink-200',
    bug: 'border-lime-300 bg-lime-50 text-lime-900 dark:border-lime-400/40 dark:bg-lime-400/15 dark:text-lime-100',
    rock: 'border-amber-400 bg-amber-100 text-amber-950 dark:border-amber-300/40 dark:bg-amber-300/15 dark:text-amber-100',
    ghost: 'border-violet-300 bg-violet-50 text-violet-800 dark:border-violet-400/40 dark:bg-violet-400/15 dark:text-violet-200',
    dragon: 'border-purple-300 bg-purple-50 text-purple-800 dark:border-purple-400/40 dark:bg-purple-400/15 dark:text-purple-200',
    dark: 'border-slate-400 bg-slate-100 text-slate-800 dark:border-slate-400/40 dark:bg-slate-400/15 dark:text-slate-200',
    steel: 'border-zinc-300 bg-zinc-100 text-zinc-800 dark:border-zinc-400/40 dark:bg-zinc-400/15 dark:text-zinc-200',
    fairy: 'border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-400/40 dark:bg-rose-400/15 dark:text-rose-200',
};

export const statLabels: Record<PokemonStatKey, string> = {
    hp: 'HP',
    attack: 'Ataque',
    defense: 'Defensa',
    'special-attack': 'At. especial',
    'special-defense': 'Def. especial',
    speed: 'Velocidad',
};

export const statKeys = Object.keys(statLabels) as PokemonStatKey[];

export function formatPokemonId(id: number): string {
    return `#${String(id).padStart(3, '0')}`;
}

export function pokemonTypeLabel(type: string): string {
    return pokemonTypeLabels[type] ?? type;
}

/**
 * Transforms external Pokemon image URLs to optimized, highly-cached WebP format via CDN.
 * Reduces 200KB PNGs to ~18KB WebPs and adds a 1-year immutable edge cache header.
 */
export function getOptimizedPokemonImageUrl(src: string | null, width = 360): string | null {
    if (!src) {
        return null;
    }

    if (src.includes('raw.githubusercontent.com/PokeAPI/sprites')) {
        return `https://wsrv.nl/?url=${encodeURIComponent(src)}&w=${width}&output=webp&q=85`;
    }

    return src;
}

