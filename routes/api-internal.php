<?php

declare(strict_types=1);

/*
| Session JSON API, prefix /app/api, route names `api.*`.
| Middleware (bootstrap/app.php): web, auth, tenant.active, tenant.resolve, branch.selected, locale.
| Endpoints (notifications, search, preferences) arrive in later steps.
*/
