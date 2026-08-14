---
paths:
  - 'app/Models/PokemonCollectionItem.php,database/migrations/*pokemon_collection_items*'
---

# Migrations

## Persist only user-owned Pokémon collection metadata
PostgreSQL stores user_id, pokemon_id, nickname, notes, favorite state, and timestamps. PokéAPI remains the source of catalog names, images, types, abilities, dimensions, and stats; enforce uniqueness on user_id + pokemon_id.
