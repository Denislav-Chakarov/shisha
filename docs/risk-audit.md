# Risk Audit

This register captures the highest-impact correctness, security, and performance risks in current production-like operations.

## Critical Risks

### 1) Missing role-based authorization
- **Type:** Security + correctness
- **Risk:** Any authenticated user can execute sensitive mutations (delete products, close bills, change table status).
- **Reproduction:**
  1. Log in as a low-privilege staff account.
  2. Submit requests to protected mutation routes like `/dashboard/products/{id}` or `/dashboard/orders/close`.
  3. Observe action succeeds due to `auth`-only route protection.
- **Expected:** Only authorized roles can perform sensitive operations.
- **Impact:** Privilege abuse, accidental or malicious business data corruption.
- **Primary files:** `routes/web.php`, `app/Http/Controllers/DashboardController.php`

### 2) Double-close race can over-restore hookah stock
- **Type:** Data integrity
- **Risk:** Two concurrent close requests can both process the same open order.
- **Reproduction:**
  1. Ensure table has open order with hookah items.
  2. Send two near-simultaneous `POST /dashboard/orders/close` requests for same table.
  3. Watch hookah stock be incremented twice in edge timing.
- **Expected:** One request succeeds; second is rejected or idempotent.
- **Impact:** Inventory drift and reconciliation issues.
- **Primary files:** `app/Http/Controllers/DashboardController.php`

## High Risks

### 3) No explicit login throttling
- **Type:** Security
- **Risk:** Brute-force attempts against `/login`.
- **Reproduction:** Repeated failed `POST /login` attempts from same source; no cooldown/limit applied.
- **Expected:** Rate limiting or lockout behavior.
- **Impact:** Account compromise risk.
- **Primary files:** `routes/web.php`, `app/Http/Controllers/Auth/LoginController.php`

### 4) Multiple open orders per table in concurrency window
- **Type:** Data integrity
- **Risk:** Concurrent first-item add can create duplicate open orders for same table.
- **Reproduction:**
  1. Use table with no open order.
  2. Trigger two concurrent add-item or AI-add requests.
  3. Observe potential duplicate `orders` rows with `status='open'`.
- **Expected:** Exactly one open order per table.
- **Impact:** Billing confusion and fragmented ticket state.
- **Primary files:** `app/Http/Controllers/DashboardController.php`, `database/migrations/2026_04_26_100000_create_shisha_core_tables.php`

### 5) Reservations can be created against active service
- **Type:** Operational correctness
- **Risk:** Reservation conflict logic checks only reservation overlap, not open-order occupancy.
- **Reproduction:**
  1. Keep table actively serving with open order.
  2. Create reservation for overlapping window.
  3. Reservation may pass.
- **Expected:** Reservation blocked when table currently occupied for service.
- **Impact:** Double-booking and host-floor conflicts.
- **Primary files:** `app/Http/Controllers/DashboardController.php`

## Medium-High Risks

### 6) Product delete can throw unhandled server error
- **Type:** Reliability
- **Risk:** Deleting product referenced by `order_items` may fail on FK restriction.
- **Reproduction:**
  1. Create order item with product.
  2. Attempt delete from products page.
  3. DB exception path can surface as 500 instead of user-safe message.
- **Expected:** Friendly validation-style rejection.
- **Impact:** Operator interruption and confusion during service.
- **Primary files:** `app/Http/Controllers/DashboardController.php`, `database/migrations/2026_04_26_100000_create_shisha_core_tables.php`

## Medium Risks

### 7) Filesystem-heavy image suggestion endpoint
- **Type:** Performance
- **Risk:** Per-query recursive scans can slow product editor UX at scale.
- **Reproduction:**
  1. Populate many files in `public/storage/products` or `public/assets`.
  2. Type quickly in product name field.
  3. Observe increased response time from `/dashboard/products/image-suggestions`.
- **Expected:** Predictable low-latency suggestions.
- **Impact:** Slower admin workflow.
- **Primary files:** `app/Http/Controllers/DashboardController.php`, `resources/views/products.blade.php`

### 8) Status model inconsistencies (`reserved` handling)
- **Type:** Correctness + UX consistency
- **Risk:** `reserved` is written from reservation flow but not uniformly accepted/rendered across all paths.
- **Reproduction:** Compare manual status endpoint allowed values with reservation-induced table status and table rendering logic.
- **Expected:** Single status contract across write/read paths.
- **Impact:** Confusing table state behavior.
- **Primary files:** `app/Http/Controllers/DashboardController.php`, `resources/views/dashboard.blade.php`, `resources/views/tables.blade.php`

## Recommended Mitigation Order
1. Authorization controls + auth throttling.
2. Concurrency hardening for order close and open-order creation.
3. Reservation/business-rule alignment.
4. Graceful product delete failure handling.
5. Performance optimization for image suggestions.

