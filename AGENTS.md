# Public plugin portability

- Keep this public plugin operable with its documented WordPress and PHP dependencies. Do not require a vendor browser, Node.js, a hosted evaluator, or a specific server filesystem layout for content operations.
- Resolve site URLs, paths, registered blocks, theme settings, and permissions through native WordPress Interfaces. Keep provider-specific behavior in optional owning Adapters outside this plugin; do not hardcode deployment or vendor assumptions in this plugin.
- Preserve native human editing. Do not add global persistence hooks that block ordinary WordPress saves on an external validation runtime.
- Verify portability changes with `php tools/check-portable-bootstrap.php` and the relevant existing content-write tests.
