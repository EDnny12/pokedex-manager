---
paths:
  - '{app/Actions/PaginateAssistantMessages.php,app/Http/Controllers/AssistantConversationController.php,resources/js/Components/Assistant/**,resources/js/composables/useAssistantChat.ts,database/migrations/*assistant*}'
---

# Composables Migrations

## Paginate assistant history with a stable cursor
Fetch assistant messages newest-first with cursor pagination ordered by created_at DESC, id DESC, backed by the (conversation_id, created_at, id) index. Reverse each page only when serializing it for the chronological UI; never restore OFFSET pagination or oldest()->limit() for history.
