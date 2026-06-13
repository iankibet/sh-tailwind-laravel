# Pius Videos

A full-stack application template built on **Laravel 13** + **Vue 3**, using the **Streamline** convention as the API transport instead of conventional controllers and route files. The frontend is a Vue SPA served from a single Blade view; everything else is data fetched over Streamline.

---

## Table of contents

- [Architecture at a glance](#architecture-at-a-glance)
- [Tech stack](#tech-stack)
- [Getting started](#getting-started)
- [Project structure](#project-structure)
- [The Streamline pattern](#the-streamline-pattern)
- [Authentication](#authentication)
- [Roles & permissions](#roles--permissions)
- [Notifications](#notifications)
- [Activity logging](#activity-logging)
- [Frontend](#frontend)
- [Artisan commands](#artisan-commands)
- [Testing](#testing)
- [Best practices](#best-practices)

---

## Architecture at a glance

```
Browser (Vue SPA)
   │  POST /api/streamline   { stream, action, params }
   ▼
Streamline router  ──►  App\Streams\**\*Stream   (the "controllers")
   │                         │
   │                         ├─ #[Permission] / #[Validate] attributes
   │                         ├─ Eloquent models (app/Models)
   │                         └─ App\Services (Notifier, GoogleIdentity, …)
   ▼
JSON response  ──►  sh-core / sh-tailwind render it
```

- **No `routes/api.php`, no resource controllers.** Each business action is a public method on a *Stream* class, addressed as `folder/class:method`.
- **Bearer-token SPA.** Sanctum personal access tokens, stored in `localStorage` by the frontend.
- **Everything is a convention.** Routing, validation, permission checks, and list pagination are derived from class attributes and method signatures.

---

## Tech stack

### Backend (`composer.json`)

| Package | Purpose |
| --- | --- |
| `laravel/framework` ^13.8 | Application framework (PHP ^8.3). |
| `iankibet/laravel-streamline` ^1.2 | The Stream transport — turns `App\Streams\*` classes into API endpoints. See [The Streamline pattern](#the-streamline-pattern). |
| `iankibet/shbackend` ^2.1 | Roles/permissions, activity logging (`ShRepository`), `SearchRepo`/`tableResponse`, and the base notification tables. **Not auto-discovered** (see `extra.laravel.dont-discover`). |
| `laravel/sanctum` ^4.3 | API token authentication. |
| `laravel/passkeys` ^0.2 | WebAuthn / passkey login. |
| `google/apiclient` ^2.18 | Verifies Google Identity tokens for "Sign in with Google". |
| `laravel/tinker` ^3.0 | REPL. |

Dev: `pint` (formatting), `phpunit`, `pail` (log viewer), `pao`, `collision`, `mockery`, `fakerphp/faker`.

### Frontend (`package.json`)

| Package | Purpose |
| --- | --- |
| `vue` ^3.5 | UI framework (Composition API, `<script setup>`). |
| `vue-router` ^4.6 | SPA routing with an auth guard. |
| `pinia` ^3.0 | State stores (e.g. notifications). |
| `@iankibetsh/sh-core` ^1.0 | Streamline client (`useStreamline`, `shApis`), auth strategy, `useUserStore`, toasts (`shRepo`), `formatDate`. |
| `@iankibetsh/sh-tailwind` ^0.1 | `ShForm`, `ShTable`, `ShDialogForm`, dialogs — the building blocks for forms and listings. |
| `@laravel/passkeys` ^0.2 | Passkey registration/login on the client. |
| `@lucide/vue` ^1.18 | Icon set. |
| `tailwindcss` ^4 + `@tailwindcss/vite` | Styling (CSS-first config in `resources/css/app.css`). |
| `vite` ^8 + `laravel-vite-plugin` | Build tooling. |

---

## Getting started

Requirements: **PHP 8.3+**, **Composer**, **Node 18+**. The default database is **SQLite** and mail is sent to the **log** driver.

```bash
# 1. Install + scaffold (.env, key, migrate, npm build)
composer setup

# 2. Initialise permission modules into storage (see Roles & permissions)
php artisan sh:init

# 3. Create the first administrator (+ a department with full admin permissions)
php artisan sh:add-admin

# 4. Run everything (server + queue worker + log tail + Vite) in one command
composer dev
```

`composer dev` runs, via `concurrently`:

- `php artisan serve` — HTTP server
- `php artisan queue:listen` — processes queued jobs/notifications (queue driver is `database`)
- `php artisan pail` — live log output
- `npm run dev` — Vite dev server with HMR

For production assets only: `npm run build`.

Key `.env` values: `DB_CONNECTION=sqlite`, `QUEUE_CONNECTION=database`, `MAIL_MAILER=log`.

---

## Project structure

```
app/
  Streams/              ← the API surface (controllers replacement)
    Auth/               ← guest + authenticated user streams (auth/guest, auth/user, auth/notifications)
    Admin/              ← admin-only streams (admin/admins, admin/departments, admin/notifications, …)
  Models/
    User.php
    Core/               ← Department, DepartmentPermission, Log, NotificationMessage
  Services/             ← framework-agnostic logic (Notifier, NotificationDefinitions, GoogleIdentity, Recaptcha)
  Notifications/        ← GeneralNotification + Channels/ (Sms, Whatsapp)
  Http/Controllers/     ← only PasskeyController (passkeys can't go through Streamline)
  Console/Commands/     ← sh:init, sh:add-admin
  Providers/            ← AppServiceProvider (Gate::before → isAllowedTo)
config/
  streamline.php        ← stream namespace, route prefix, guest streams, middleware
  notifications.php     ← default notification definitions (see Notifications)
resources/js/
  app.js                ← Vue bootstrap + ShTailwind config
  router.js             ← routes + auth guard
  components/           ← DashboardLayout, SidebarMenu, …
  views/                ← page components
  stores/               ← Pinia stores
  navigation/menu.js    ← sidebar menu (permission-gated)
routes/web.php          ← passkey routes + SPA catch-all (no api.php)
storage/app/permissions ← runtime permission module/rule JSON
```

---

## The Streamline pattern

A **Stream** is a class in `App\Streams` whose public methods are callable actions. Configuration lives in `config/streamline.php`:

```php
'class_namespace' => 'App\\Streams',
'class_postfix'   => 'Stream',
'route'           => 'api/streamline',
'middleware'      => ['auth:sanctum'],   // default for every stream
'guest_streams'   => ['auth/guest'],     // unauthenticated allow-list
```

### Addressing

The frontend posts `{ stream, action, params }` to `/api/streamline`. The endpoint string is written as **`folder/class:method`**:

| Endpoint string | Resolves to |
| --- | --- |
| `auth/guest:login` | `App\Streams\Auth\GuestStream::login()` |
| `admin/admins:list` | `App\Streams\Admin\AdminsStream::list()` |
| `auth/notifications:read` | `App\Streams\Auth\NotificationsStream::read($id)` |

`params` are passed **positionally** to the method (`read(string $id)` receives `params[0]`); any object argument is also merged into the request body, so `Validator::validate(request()->all(), …)` works.

### Anatomy of a Stream

```php
#[Permission('admins')]                       // class-level gate (every method)
class AdminsStream extends Stream
{
    public ?User $user = null;                // public props are returned by the implicit onMounted()

    #[Permission('admins.list')]              // method-level gate
    public function list(): Response
    {
        return User::query()
            ->whereIn('role', ['admin', 'super_admin'])
            ->select(['id', 'name', 'email'])
            ->tableResponse(['name', 'email']);   // paginated payload for ShTable
    }

    #[Permission('admins.update')]
    public function update(?int $id = null): array
    {
        $data = Validator::validate(request()->all(), [
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users')->ignore($id)],
        ]);
        $user = User::findOrFail($id);
        $user->update($data);
        ShRepository::storeLog('admin_updated', "Updated {$user->name}", $user);

        return ['status' => 'success', 'user' => $user->fresh()];
    }
}
```

Conventions:

- **Validation** — either the `#[Validate([...])]` attribute (auto-runs before the method) or inline `Validator::validate(request()->all(), …)`. Return field-keyed errors so `ShForm` can map them.
- **Permissions** — `#[Permission('slug')]` on the class and/or method. Checked against `request()->user()->can($slug)` (see [Roles & permissions](#roles--permissions)).
- **Listings** — return `->tableResponse([...searchable columns])` (a Laravel paginator shaped for `ShTable`).
- **Single results / mutations** — return a plain array, conventionally `['status' => 'success', …]`.
- **Initial page data** — public properties set in the constructor/`onMounted` are serialized into `props` on the client.

---

## Authentication

Three entry points, all on the guest stream `App\Streams\Auth\GuestStream`:

- `auth/guest:login` — email + password (+ reCAPTCHA), returns a Sanctum token.
- `auth/guest:register` — creates a `client` user, returns a token, and fires the `welcome` notification.
- `auth/guest:google` — verifies a Google Identity credential via `App\Services\GoogleIdentity`; first-time users are created and welcomed.

**Passkeys** can't ride Streamline (they need browser WebAuthn ceremonies), so they live in `routes/web.php` via `PasskeyController` and the `@laravel/passkeys` client.

The client stores the bearer token in `localStorage` (`authMode: 'bearer'`, `tokenStorage: 'local'` in `app.js`) and attaches it to every Streamline call. `auth/user:current` returns the authenticated user with resolved permissions.

---

## Roles & permissions

Permissions are **slug-based** and resolved per request through `iankibet/shbackend`:

- **Where they're defined** — JSON "permission modules" in `storage/app/permissions/modules/*.json` (plus an optional `rules.json`). `php artisan sh:init` copies the package defaults into storage so you can edit them.
- **Roles** — users have a `role` (`client`, `admin`, `super_admin`). `admin`/`super_admin` are **department-scoped**: their effective permissions come from the `department_permissions` granted to their `department_id`. Other roles get permissions straight from their role definition.
- **Granting** — admins manage a department's module permissions through the **Departments** UI (`admin/departments` + the `PermissionTree` component). `php artisan sh:add-admin` bootstraps the first admin with a department granted *all* admin-role permissions.
- **Enforcement** — `AppServiceProvider` registers `Gate::before(fn ($user, $ability) => $user->isAllowedTo($ability))`, so `#[Permission('…')]` on a Stream and `$user->can('…')` both flow through the same check. On the client, `useUserStore().isAllowedTo(slug)` and the `v-if-user-can` directive gate UI.

> To make a **new admin feature** grantable, add a permission module JSON for it (matching the `module` segment of its `#[Permission('module.action')]` slugs) so it appears in the permission tree.

---

## Notifications

A complete, reusable notification system. Definitions are config-driven with optional per-message admin overrides.

### Definitions (`config/notifications.php`)

Keyed by **slug**; each entry carries `subject`, `mail`, `sms`, `whatsapp`, `channels`, `action_label`, `action_url`, and a documented `placeholders` list. Every text field supports `{placeholder}` tokens.

```php
'welcome' => [
    'subject'      => 'Welcome to Pius Videos, {name}',
    'mail'         => "Hi {name},\n\nYour account is ready.",
    'sms'          => 'Hi {name}, welcome to Pius Videos.',
    'whatsapp'     => 'Hi {name}, welcome to Pius Videos.',
    'channels'     => ['database', 'mail'],
    'action_label' => 'Go to dashboard',
    'action_url'   => '/profile',
    'placeholders' => ['name'],
],
```

### Channels

`database` (in-app dropdown), `mail` (email), `sms`, `whatsapp`. The SMS/WhatsApp channels (`app/Notifications/Channels/`) are **wired but inert** — they log instead of sending. Swap the `Log::debug` call for a real gateway when ready.

### Overrides (admins can edit defaults)

The DB stores **overrides only**. `App\Services\NotificationDefinitions` resolves a slug to its DB row if one exists, otherwise the config default. Admins edit definitions at **`/notifications`** (`admin/notifications` stream); saving creates a `notification_messages` row, and **Reset** deletes it to fall back to the default.

### Sending

```php
use App\Services\Notifier;

Notifier::send('welcome', $user, ['name' => $user->name]);
//             slug       notifiable(s)   placeholder values
```

`Notifier` substitutes `{placeholders}`, then dispatches `App\Notifications\GeneralNotification` (a queued notification) across the definition's channels. `notifiables` may be a model, a collection, or an array.

### Reading (the user side)

- The bell dropdown in `DashboardLayout` shows **unread only**, with a "You're all caught up" empty state and a **View all** link.
- The full history lives at **`/notifications/all`** (`AllNotificationsView`).
- Both read from `App\Streams\Auth\NotificationsStream` (`list` = unread, `all` = history, `read` = mark one & return its `action_url`, `markAllRead`). Clicking a notification marks it read and redirects to its action URL if set. Client state is kept in `resources/js/stores/notifications.js`.

---

## Activity logging

Use `ShRepository::storeLog($slug, $message, $model = null)` for audit entries (writes to the `logs` table, captures the actor, IP, and device). It's used throughout the streams (`admin_updated`, `user_login`, `notification_updated`, …) and surfaced in the **Access logs** view.

---

## Frontend

- **Bootstrap** (`resources/js/app.js`) — registers Pinia, the router, and `ShTailwind` with API/auth config (`baseApiUrl: '/api/'`, `streamlineUrl: 'streamline'`, bearer tokens, table caching, theme overrides).
- **Routing** (`resources/js/router.js`) — `createWebHistory` SPA; `beforeEach` redirects unauthenticated users (`meta.auth`) to `/login` and authenticated users away from `meta.guest` pages. Page `meta` carries `title` and breadcrumb data consumed by `DashboardLayout`.
- **Data access** — `useStreamline('folder/class')` returns `{ service, props, loading }`; call `service.method(payload)` for actions. For one-off calls use `shApis.doPost('folder/class:method', body)`. Forms use `ShForm` / `ShDialogForm` with `action="folder/class:method"`; listings use `ShTable` with `endpoint="folder/class:list"`.
- **State** — `useUserStore` (current user + permissions) and feature stores under `resources/js/stores/`.
- **Navigation** — `resources/js/navigation/menu.js` defines the sidebar; each item carries a `permission` slug and is hidden when the user lacks it.
- **Styling** — Tailwind v4 with a `brand`/`ink` palette defined in `resources/css/app.css`. Reuse the rounded-card + shadow patterns seen across the views.

---

## Artisan commands

| Command | What it does |
| --- | --- |
| `php artisan sh:init` | Copies the shbackend default permission modules into `storage/app/permissions/modules`. Run once after install. |
| `php artisan sh:add-admin` | Interactively creates an administrator and a department granted all admin-role permissions. |
| `php artisan migrate` | Runs migrations (app + shbackend-provided notification/permission tables). |
| `php artisan queue:listen` | Processes queued notifications/jobs (bundled into `composer dev`). |

---

## Testing

Feature tests live in `tests/Feature` and exercise the streams directly (auth, admins/profile, departments, access logs, passkeys, Streamline routing).

```bash
composer test          # config:clear + php artisan test
php artisan test --filter=DepartmentsStreamTest
```

Format code with Pint before committing: `./vendor/bin/pint`.

---

## Best practices

**Do**

- Add new server actions as **Stream methods**, named by intent (`list`, `get`, `create`, `update`, `reset`). Gate them with `#[Permission(...)]`.
- Keep reusable, framework-agnostic logic in `app/Services`; keep Streams thin.
- Validate with the `#[Validate]` attribute or `Validator::validate(request()->all(), …)`, and return **field-keyed** errors for `ShForm`.
- Return `->tableResponse([...])` for every listing so `ShTable` gets a consistent paginator.
- Send user-facing messages through `Notifier::send(slug, …)` and define the content in `config/notifications.php` — never hard-code copy in the trigger.
- Record meaningful actions with `ShRepository::storeLog(...)`.
- Gate UI with `useUserStore().isAllowedTo(slug)` / `v-if-user-can` and add a `permission` to menu items.
- Build forms with `ShForm`/`ShDialogForm` and lists with `ShTable`.

**Don't**

- Don't add `routes/api.php` or conventional resource controllers — use Streams. (`PasskeyController` is the one sanctioned exception, because WebAuthn can't go through Streamline.)
- Don't put business copy or channel logic in callers — use the notification definitions and `Notifier`.
- Don't bypass the permission system; rely on `#[Permission]` + `Gate::before`.
- Don't reuse the shbackend `templates/` classes verbatim — they are examples from other projects.

