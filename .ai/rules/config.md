---
paths:
  - config/fortify.php
---

# Config

## Keep public password reset disabled
Forgotten-password recovery is intentionally out of scope. Do not enable Features::resetPasswords or restore forgot/reset UI unless the product decision changes. Authenticated password updates remain enabled.
