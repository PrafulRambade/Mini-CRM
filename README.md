# Mini CRM — Leads & Customers

A Laravel 12 + MySQL CRM with role-based access, a lead pipeline that converts **Won** leads into customers, and a token-authenticated REST API.

## Stack

- PHP 8.2+, Laravel 12, MySQL / MariaDB
- Laravel Sanctum (API tokens)
- Blade + Bootstrap 5.3 with a custom admin theme (`public/css/app.css`, `public/js/app.js`); Chart.js for dashboard charts. All front-end libraries and the Inter font are self-hosted in `public/vendor/` (no CDNs, no Node build step)
- PHPUnit feature tests (108 tests)

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate

# create the databases (adjust credentials in .env if needed)
mysql -uroot -e "CREATE DATABASE crm_machine_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -uroot -e "CREATE DATABASE crm_machine_test_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

php artisan migrate --seed
php artisan serve          # http://localhost:8000
```

### Demo accounts (password `Password@123`)

| Role       | Email             |
|------------|-------------------|
| Admin      | admin@crm.test    |
| Sales User | sales1@crm.test   |
| Sales User | sales2@crm.test   |

### Tests

```bash
php artisan test
```

The tests run against the separate `crm_machine_test_testing` database (configured in `phpunit.xml`).

## Features

### Authentication and roles

- Session login and logout for the web UI. Login is throttled: an email + IP pair is locked out after 5 failed attempts, and each IP is limited to 10 login requests per minute.
- The session is regenerated on login to prevent session fixation, and invalidated on logout.
- Deactivated users (`is_active = false`) cannot log in. If a user is deactivated mid-session, their web session ends and their API token is revoked on their next request.
- `role` and `is_active` are not mass-assignable, so a request payload cannot change them.

| Permission                         | Admin | Sales User |
|------------------------------------|:-----:|:----------:|
| View leads                         | All   | Assigned to them only |
| Create leads                       | ✓ (assign to anyone) | ✓ (auto-assigned to self) |
| Edit leads / convert               | All   | Their own only |
| Reassign or unassign leads         | ✓     | ✗ |
| Delete leads (soft delete)         | ✓     | ✗ |
| View customers                     | All   | Converted from their leads |

These rules are enforced in `LeadPolicy` / `CustomerPolicy` and query scopes (`visibleTo`), so the web UI and the API apply them the same way.

### Leads

- Leads have list, create, view, edit and delete screens. Each lead has these fields: name, email, phone, company, source (Web/Ads/Referral), status (New/In Progress/Won/Lost), assigned to, follow-up date, notes, and the linked customer.
- The listing has search (name, email, phone, company), filters (status, source, assignee), sortable columns and pagination.
- **AJAX listings (Leads, Customers, Users, Activity log):** filtering, search-as-you-type, sorting and paging update the table without reloading the page. Filters are sent as a CSRF-protected `POST` to `/{listing}/table` and never appear in the address bar or server logs; the server returns escaped, server-rendered HTML. Back/Forward and Refresh keep the filter state (stored in `history.state`), older requests are cancelled, and the endpoints are rate-limited per user. Without JavaScript the same links still work as normal pages.
- Validation lives in the shared form requests (`StoreLeadRequest` / `UpdateLeadRequest`), which the web UI and the API both use:
  - email is RFC-valid and lowercased
  - phone must match a strict format
  - source and status must be valid enum values
  - the follow-up date cannot be in the past, though an existing past date can be kept on edit
  - the assignee must be an active user
  - notes are limited to 5,000 characters

### Lead → Customer conversion

- A lead converts automatically whenever its status becomes **Won**, on create or on edit, through the web UI or the API.
- There is also an explicit **Convert to Customer** action (`POST /leads/{id}/convert`).
- `LeadConversionService` is:
  - **atomic**: the customer and the lead link are written in one DB transaction
  - **idempotent**: converting an already converted lead returns its existing customer
  - **concurrency-safe**: it takes a row lock on the lead, and customers are deduplicated by email through a unique index plus `createOrFirst`
  - **deduplicating**: leads with the same email share one customer, and a soft-deleted customer is restored instead of duplicated
- After conversion the lead's status is locked at Won. Its other fields stay editable.

### Admin panel UI

- Collapsible sidebar (it collapses to icons on desktop and slides in on mobile), a sticky top bar with global lead search (press `/` to focus it), and a light/dark theme toggle. The sidebar and theme settings are remembered between visits.
- Dashboard with KPI tiles, a 30-day chart of leads created vs. converted, the pipeline by status, leads by source, upcoming and overdue follow-ups, recent conversions and team performance (admins only).
- Toast notifications, a confirmation dialog for destructive actions, protection against double submits, empty states and branded error pages (403/404/405/419/429/500/503).

### User management (admin) and profile

- Admins can list, search, create and edit users and activate or deactivate them. Passwords must be at least 8 characters with upper- and lowercase letters and a number.
- Admins cannot demote or deactivate themselves.
- Deactivating a user, or resetting their password, signs them out of every session and revokes their API tokens.
- Every user can edit their own name and email and change their password. Changing the password signs out their other sessions.

### Customers

- The customer screen is a listing only, with search, sort and pagination. Each row shows the lead(s) the customer was converted from.

## REST API

Base URL: `/api`. Every response is JSON, even when the client sends no `Accept` header. Authenticate with a Sanctum Bearer token. Tokens expire after `SANCTUM_TOKEN_EXPIRATION` minutes (default **60 minutes**); when a token expires the API returns 401 and the client simply calls `POST /api/login` again. A daily scheduled task prunes expired tokens. Each user can make 120 requests per minute.

| Method | Endpoint                     | Description |
|--------|------------------------------|-------------|
| POST   | `/api/login`                 | `{email, password, device_name?}` → `{access_token, token_type, expires_at, user}` |
| POST   | `/api/logout`                | Revoke the current token |
| GET    | `/api/me`                    | Current user |
| GET    | `/api/leads`                 | List (see query params) |
| POST   | `/api/leads`                 | Create (status `won` converts immediately) |
| GET    | `/api/leads/{id}`            | Show |
| PUT    | `/api/leads/{id}`            | Full update |
| PATCH  | `/api/leads/{id}`            | Partial update |
| DELETE | `/api/leads/{id}`            | Soft delete (admin) → 204 |
| POST   | `/api/leads/{id}/convert`    | Mark Won + convert to customer |
| GET    | `/api/customers`             | List |
| GET    | `/api/customers/{id}`        | Show |

**Listing query params:**

- `search`
- `sort`
  - leads: `name|email|company|status|source|follow_up_date|created_at`
  - customers: `name|email|company|created_at`
- `direction` (`asc|desc`)
- `per_page` (1–100, default 15)
- `page`
- leads only: `status`, `source`, `assigned_to` (`assigned_to` applies to admins only)

API requests with invalid parameters get a `422` response.

**Status codes:** `401` unauthenticated · `403` forbidden or deactivated · `404` not found · `405` method not allowed · `422` validation error (`{message, errors}`) · `429` rate limited.

### Example

```bash
TOKEN=$(curl -s -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@crm.test","password":"Password@123"}' | php -r 'echo json_decode(stream_get_contents(STDIN))->access_token;')

curl -H "Authorization: Bearer $TOKEN" "http://localhost:8000/api/leads?search=acme&status=new&per_page=10"

curl -X POST http://localhost:8000/api/leads -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"name":"Jane Doe","email":"jane@acme.com","phone":"+91 98765 43210","company":"Acme","source":"web","status":"won"}'
```

## Code structure

```
app/
  Enums/                 UserRole, LeadSource, LeadStatus
  Models/                User, Lead, Customer (+ Concerns/Searchable: LIKE-escaped search scope)
  Services/              LeadService (write rules), LeadConversionService (conversion)
  Policies/              LeadPolicy, CustomerPolicy
  Http/Controllers/      Web controllers + Api/ controllers (thin; delegate to services)
  Http/Requests/         Form requests shared by web and API
  Http/Resources/        API JSON transformers
  Http/Middleware/       EnsureUserIsActive, ForceJsonResponse, SecurityHeaders
database/                migrations (FKs, indexes, soft deletes), factories, seeder
tests/Feature/           Auth (web + API), Lead API, Customer API, conversion service, web UI
```

## Hardening notes

- CSRF protection is on for all web forms. Session payloads are encrypted (`SESSION_ENCRYPT=true`).
- Every response carries security headers: `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy` and `X-Permitted-Cross-Domain-Policies`. HTTPS responses also get HSTS, and `X-Powered-By` is removed.
- **Content-Security-Policy:** only same-origin resources load, and only `<script>` tags carrying a fresh per-request nonce run, so injected scripts are refused. The policy is sent as a header and repeated in a `<meta>` tag, because some hosts (e.g. Hostinger/LiteSpeed "Force HTTPS") overwrite the header.
- **CORS:** browsers on other websites cannot call the API (`CORS_ALLOWED_ORIGINS`, defaults to `APP_URL`).
- **Passwords:** at least 8 characters with mixed case and a number; in production, passwords found in known data breaches (Have I Been Pwned, k-anonymity) are rejected.
- **Audit log** (`/activity`, admins only): sign-ins, failed sign-ins and lockouts, user changes, and lead creation, edits, deletions and conversions, with user, IP and time. Append-only in the app, pruned after 180 days, and never stores passwords or note contents.
- **Demo-safe mode** (`DEMO_MODE=true`): demo accounts can use everything, but their password, email, role and status are locked. `php artisan crm:reset-demo --force` restores the demo data.
- `.htaccess` blocks hidden files (`.env`, `.git`), config/backup file types and directory listings.
- Login responses take about the same time whether or not the email exists, so response timing does not reveal valid emails.
- Search escapes LIKE wildcards. Sort columns come from an allow-list.
- System fields (`customer_id`, `converted_at`, `created_by`) cannot be mass-assigned.
- In production, HTTPS is forced and destructive artisan commands (such as `migrate:fresh`) are blocked. In development, `Model::shouldBeStrict()` turns lazy loading and silently dropped attributes into errors.
- For production, set `APP_ENV=production` and `APP_DEBUG=false`, use a dedicated DB user, and run `php artisan config:cache route:cache view:cache` plus the scheduler (`php artisan schedule:work` or cron).
