---
paths:
  - '{app/Services/Assistant/**,app/Actions/Assistant/**,resources/js/Components/Assistant/**,resources/js/composables/useAssistantChat.ts,services/**}'
---

# Composables

## Laravel conserva la autoridad del asistente
No exponer user_id ni credenciales al modelo. Gemini solo puede solicitar acciones; agregar o eliminar de la colección requiere confirmación explícita en la UI y ejecución idempotente mediante contexto opaco firmado. Laravel valida identidad, autorización y persistencia PostgreSQL.
