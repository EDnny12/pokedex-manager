---
paths:
  - '{app/Actions/**,app/Services/PokeApi/**,app/Services/Assistant/**}'
---

# Assistant

## Keep external I/O outside database locks
Resolve PokéAPI and AI-agent network work before entering PostgreSQL transactions. Transactions that use lockForUpdate must contain only authorization rechecks and database mutations, use bounded deadlock retries, and remain short.
