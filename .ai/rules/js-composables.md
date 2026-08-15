---
paths:
  - '{app/Services/Assistant/**,app/Actions/**,resources/js/Components/Assistant/**,resources/js/composables/useAssistantChat.ts,services/**}'
---

# Js Composables

## Laravel conserva la autoridad del asistente
No expongas identidad ni credenciales al modelo. Gemini solo puede consultar datos o preparar solicitudes: agregar, eliminar y editar apodo/notas/favorito requieren una tarjeta de confirmación explícita. Laravel valida el contexto opaco firmado, la autorización y ejecuta cada acción de forma idempotente en PostgreSQL.
