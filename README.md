# WMS Backend

Laravel Warehouse Management System REST API (unit `wms-api`) on the existing Fortify/Teams/Inertia app. WMS auth uses Sanctum bearer tokens and a separate `users.wms_role` (`admin` | `warehouse_operator`).

## Setup

```bash
composer install
cp .env.example .env   # if needed
php artisan key:generate
php artisan migrate
php artisan serve
```

Assign a WMS role (tinker or seeder):

```php
$user->wms_role = \App\Enums\WmsRole::Admin;
$user->save();
```

Login:

```bash
curl -s -X POST http://localhost:8000/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"you@example.com","password":"password"}'
```

Use `Authorization: Bearer <token>` on subsequent `/api/*` calls.

### Docker Compose (assignment-faithful path)

`docker-compose.yml` runs app + PostgreSQL + Redis + a queue worker.

```bash
docker compose up -d
# configure .env for pgsql/redis as in the compose file, then:
php artisan migrate
php artisan queue:work redis   # or use the compose `queue` service
```

Local SQLite/MySQL is fine for development; Compose is the production-minded stack (PostgreSQL + Redis).

## Architecture

| Layer  | Location                                                              |
| ------ | --------------------------------------------------------------------- |
| HTTP   | `routes/api.php`, `app/Http/Controllers/Api/*`                        |
| Authz  | Sanctum + `EnsureWmsRole`                                             |
| Domain | `app/Services/Wms/InventoryService`, `StockAuditService`              |
| Async  | `ProcessLowStockAlert` job (Redis queue in Compose)                   |
| Models | Product, Warehouse, Location, Inventory, StockMovement, LowStockAlert |

### Concurrency

Stock receive / transfer / dispatch run in a single DB transaction. Inventory rows are locked with `SELECT … FOR UPDATE`. Missing balances are inserted at quantity `0` under the unique `(product_id, location_id)` constraint; on unique conflict the service re-reads and locks the winning row (first-touch safety). Transfers lock location IDs in ascending order to avoid deadlocks. Insufficient stock aborts with HTTP **409**.

### Redis

Used for the queue connection that runs low-stock alerts after commit so inventory never rolls back if notification fails. Mock/log notification is written in `ProcessLowStockAlert`.

## API surface (prefix `/api`)

- `POST /auth/login`
- `CRUD /products` (admin + operator)
- Warehouses/locations: list/show for WMS roles; mutate **admin only**
- `GET /inventory`, `POST /inventory/receive|transfer|dispatch`
- `GET /stock-movements` (filters: `product_id`, `warehouse_id`, `type`, `from`, `to`)

Errors use `{ "success": false, "message": "...", "errors": { ... } }` with 401 / 403 / 409 / 422 as appropriate.

## Tests

```bash
php artisan test --compact tests/Feature/Wms
```

## Design decisions / limits

- No WMS UI (Inertia/Fortify left for the product shell only).
- Transfer history is **two** `stock_movements` rows sharing one `reference_number`.
- `warehouse_id` on movements matches when **source or destination** location belongs to that warehouse.
- Hard delete of master data with inventory is refused (409); deactivate instead.
- Known limits: no multi-warehouse transfer atomicity beyond two locations; notification is log/mock only.
