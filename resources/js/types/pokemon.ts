export type PokemonStatKey =
    | 'hp'
    | 'attack'
    | 'defense'
    | 'special-attack'
    | 'special-defense'
    | 'speed';

export interface PokemonAbility {
    name: string;
    is_hidden: boolean;
}

export interface Pokemon {
    id: number;
    name: string;
    display_name: string;
    image: string | null;
    cry_url?: string | null;
    types: string[];
    height_m: number | null;
    weight_kg: number | null;
    abilities: PokemonAbility[];
    stats: Partial<Record<PokemonStatKey, number>>;
}

export interface CollectionPokemon extends Pokemon {
    collection_id: number;
    nickname: string | null;
    notes: string | null;
    is_favorite: boolean;
    added_at: string | null;
    updated_at: string | null;
}

export interface CatalogPokemon extends Pokemon {
    collection_id: number | null;
}

export interface PaginationMeta {
    current_page: number;
    from: number;
    last_page: number;
    per_page: number;
    to: number;
    total: number;
}

export interface PaginatedPokemon<TPokemon extends Pokemon = Pokemon> {
    data: TPokemon[];
    meta: PaginationMeta;
}

export interface TypeDistribution {
    name: string;
    count: number;
}

export interface TopStat {
    key: PokemonStatKey;
    label: string;
    pokemon_name: string;
    pokemon_id: number;
    collection_id: number;
    value: number;
}

export interface CollectionInsights {
    total: number;
    favorites: number;
    represented_types: number;
    total_types: number;
    type_distribution: TypeDistribution[];
    dominant_type: TypeDistribution | null;
    missing_types: string[];
    top_stats: TopStat[];
}

export type CollectionImpactStatus =
    | 'starts_collection'
    | 'expands'
    | 'reinforces'
    | 'empties_collection'
    | 'reduces'
    | 'keeps_coverage';

export interface CollectionImpactChange {
    key: PokemonStatKey;
    label: string;
    before: number | null;
    after: number | null;
}

export interface CollectionImpact {
    mode: 'add' | 'remove';
    status: CollectionImpactStatus;
    is_partial: boolean;
    total: { before: number; after: number };
    represented_types: { before: number; after: number };
    new_types: string[];
    reinforced_types: string[];
    lost_types: string[];
    stat_changes: CollectionImpactChange[];
}
