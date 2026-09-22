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

## API documentation

Base URL: `/api`. Authenticated routes require `Authorization: Bearer <token>`.

### Auth

| Method | Path | Auth | Roles | Description |
| ------ | ---- | ---- | ----- | ----------- |
| `POST` | `/api/auth/login` | none | — | Issue Sanctum token |

**Body:** `{ "email": string, "password": string }`

**200:** `{ "token", "token_type": "Bearer", "user": { "id", "email", "wms_role" } }`

**401:** invalid credentials (error envelope)

### Products (admin + warehouse_operator)

| Method | Path | Description |
| ------ | ---- | ----------- |
| `GET` | `/api/products` | List products (paginated) |
| `POST` | `/api/products` | Create product |
| `GET` | `/api/products/{id}` | Get product |
| `PUT` | `/api/products/{id}` | Update product |
| `DELETE` | `/api/products/{id}` | Delete product (409 if inventory exists) |

**Create/update body (fields):** `sku`, `name`, `unit` (required on create); optional `description`, `status` (`active`\|`inactive`), `low_stock_threshold`

### Warehouses

| Method | Path | Roles | Description |
| ------ | ---- | ----- | ----------- |
| `GET` | `/api/warehouses` | admin, operator | List warehouses |
| `GET` | `/api/warehouses/{id}` | admin, operator | Get warehouse |
| `POST` | `/api/warehouses` | **admin** | Create warehouse |
| `PUT` | `/api/warehouses/{id}` | **admin** | Update warehouse |
| `DELETE` | `/api/warehouses/{id}` | **admin** | Delete warehouse (409 if inventory exists) |

**Create/update body:** `code`, `name` (required on create); optional `address`, `status` (`active`\|`inactive`)

### Locations

| Method | Path | Roles | Description |
| ------ | ---- | ----- | ----------- |
| `GET` | `/api/warehouses/{id}/locations` | admin, operator | List locations in warehouse |
| `POST` | `/api/warehouses/{id}/locations` | **admin** | Create location |
| `GET` | `/api/locations/{id}` | admin, operator | Get location |
| `PUT` | `/api/locations/{id}` | **admin** | Update location |
| `DELETE` | `/api/locations/{id}` | **admin** | Delete location (409 if inventory exists) |

**Create/update body:** `code`, `name` (required on create); optional `status` (`active`\|`inactive`)

### Inventory

| Method | Path | Roles | Description |
| ------ | ---- | ----- | ----------- |
| `GET` | `/api/inventory` | admin, operator | List balances |
| `POST` | `/api/inventory/receive` | admin, operator | Receive stock |
| `POST` | `/api/inventory/transfer` | admin, operator | Transfer stock |
| `POST` | `/api/inventory/dispatch` | admin, operator | Dispatch stock |

**List query:** `product_id`, `location_id`, `per_page`, `page`

**Receive / dispatch body:**

```json
{ "product_id": 1, "location_id": 1, "quantity": 10, "reference": "optional" }
```

**Transfer body:**

```json
{
  "product_id": 1,
  "from_location_id": 1,
  "to_location_id": 2,
  "quantity": 5,
  "reference": "optional"
}
```

**200:** inventory payload + `reference_number`  
**409:** inactive product/location, insufficient stock, or other business conflict  
**422:** validation error

### Stock movements

| Method | Path | Roles | Description |
| ------ | ---- | ----- | ----------- |
| `GET` | `/api/stock-movements` | admin, operator | Paginated movement history |

**Query filters:** `product_id`, `warehouse_id` (matches source **or** destination location’s warehouse), `type` (`receive`\|`transfer`\|`dispatch`), `from`, `to`, `per_page`, `page`

### Errors

```json
{ "success": false, "message": "...", "errors": { } }
```

| Status | Meaning |
| ------ | ------- |
| **401** | Missing/invalid token or bad login |
| **403** | Authenticated but no / wrong `wms_role` |
| **404** | Resource not found |
| **409** | Business conflict (stock, inactive entity, delete blocked) |
| **422** | Validation failure |

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
