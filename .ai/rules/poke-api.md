---
paths:
  - 'app/Services/PokeApi/**'
---

# Poke Api

## PokéAPI stays behind a cached server-side boundary
Consume PokéAPI only through App\Contracts\PokemonCatalog. Keep the base URL and timeout/cache values in config/services.php, cache normalized responses, and never add an API key because PokéAPI is public.
