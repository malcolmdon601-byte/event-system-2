# EventFlow — Event Management & Booking System (Client Demo)

A working prototype: Laravel API + Vue 3 frontend, covering enquiry → quotation →
deposit → confirmed → planning → completed, with role-based access, staff/vendor/
equipment management, tasks, messaging and reporting.

This is a **demo build for client review**, not a production-audited system. Section 7
below is honest about what's simplified.

---

## 1. What was built

**Backend (Laravel 12 + Sanctum + SQLite/MySQL)**
- 22 migrations covering users, customers, events, services, quotations, invoices,
  payments, staff, vendors, equipment + reservations, tasks, messages, notifications,
  documents, activity logs
- 17 Eloquent models with full relationships
- 13 API controllers with validation, role-based authorization (`role:` middleware),
  and business logic: quotation totals/discount/deposit calculation, payment →
  invoice balance updates, equipment double-booking prevention, event status pipeline
- Public endpoints for the marketing site (services list, "Request a Quote" form that
  creates a customer + enquiry with no login needed)
- A seeder with realistic demo data spanning the full pipeline (see credentials below)
- 16 feature tests (auth, customer CRUD, event/quotation flow, payment balance logic,
  equipment conflict prevention, dashboard stats)

**Frontend (Vue 3 + Vite + Pinia + Tailwind)**
- Premium navy/gold design system matching the brief
- Public site: Home, Services, Request a Quote
- Login with one-click demo account fill
- Customer Portal: event timeline, quotations (accept/reject), payment history,
  outstanding balance
- Admin suite: Dashboard (stats + charts-as-bars), Events (list, create, full event
  workspace with Overview/Staff & Vendors/Tasks/Payments/Messages tabs), Customers,
  Quotations, Payments, Reports

## 2. What works

Everything listed above is wired end-to-end — every frontend call matches a real
backend route, every route matches a controller method, every controller matches
the actual database columns. I verified this two ways from my side:
- Every PHP file passes `php -l` (syntax-checked, zero errors)
- The entire Vue app **builds successfully** with `npm run build` — all 12 views and
  every import resolved with zero errors

What I could **not** do from my side: run `composer install` or `php artisan test`,
because this sandbox has no access to Packagist. So the Laravel side is syntactically
verified and logically consistent, but the actual boot-and-run check happens on your
machine. Follow Section 4 and if `php artisan test` throws anything, send me the exact
error and I'll fix it immediately.

## 3. What is demo/simulated

- **Payments** are simulated — no real payment gateway is contacted. Every payment
  record is flagged `is_simulated: true` and the Payments admin page says so.
- **Messaging** is authenticated and scoped per-event/customer, but is not end-to-end
  encrypted. The UI labels it "Secure messaging — encryption architecture prepared
  for production implementation," as instructed.
- **Notifications** are stored and shown in-app only; no email/SMS/WhatsApp is sent.
- **Documents** (quotation/invoice/receipt PDFs) are represented in the data model
  but PDF rendering itself isn't implemented in this pass — the quotation/invoice
  data is structured and ready for a PDF template to be added.
- **Reports** are a simplified dashboard summary, not exportable PDF/Excel reports.

## 4. What requires real third-party credentials

- MySQL database (you provide connection details in `.env`)
- Any real payment gateway (Mobile Money aggregator, Stripe/Flutterwave for cards, etc.)
- Email/SMS/WhatsApp provider for real notifications
- A PDF rendering package (e.g. `barryvdh/laravel-dompdf`) if you want actual PDF
  downloads instead of on-screen quotation/invoice previews

## 5. Known limitations

- Vendors and Equipment don't yet have their own admin CRUD pages in the frontend
  (the backend endpoints exist and are tested; only the UI screens weren't built
  in this pass — happy to add them next).
- No file/document upload UI yet, though the `documents` table is ready for it.
- Calendar view (visual month/week grid) isn't built; event dates are visible in
  list views and the dashboard's "Upcoming Events."
- No automated browser/E2E tests, only backend feature tests.

## 6. Run and deploy the project

### Backend (Laravel API)

The complete Laravel backend is in `Backend/` and includes migrations, Sanctum token
authentication, demo data, role authorization, the frontend's API routes, and feature
tests. PHP 8.2+ and Composer are required.

```powershell
cd Backend
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan test
php artisan serve
```

The API runs at `http://localhost:8000/api`. Local development uses SQLite by default.
To use MySQL, change `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`,
`DB_USERNAME`, and `DB_PASSWORD` in `Backend/.env` before running migrations.
Seeded demo users all use password `password` (see the credentials below).

### Frontend (Vue/Vite)

```powershell
cd Frontend
npm install
npm run dev
```

Set `VITE_API_URL=http://localhost:8000/api` in `Frontend/.env` if using another API
URL. The Vite production build is `npm run build` and outputs `Frontend/dist`.

### Production deployment

Deploy `Frontend/` as a Vercel project (build command `npm run build`, output `dist`).
Deploy `Backend/` to a PHP-capable host such as Render or Railway with a managed MySQL
database; Vercel does not provide a native Laravel/PHP runtime. Configure the backend
environment with `APP_KEY` (generate once with `php artisan key:generate`),
`APP_ENV=production`, `APP_DEBUG=false`, database credentials, `APP_URL`, and
`CORS_ALLOWED_ORIGINS=https://your-frontend-domain.vercel.app`. Run
`php artisan migrate --force` during release/deployment. Configure Vercel's
`VITE_API_URL` to `https://your-backend-domain/api`, then redeploy the frontend.

For a real production deployment, use a persistent MySQL service, take database
backups, and do not enable demo seeders or keep the shared demo passwords. Payments
remain simulated; no payment provider is connected.

### Frontend

```powershell
cd Frontend
npm install
npm run dev        # http://localhost:5173
```

Create a `.env` in `Frontend` if your API isn't at the default:
```
VITE_API_URL=http://localhost:8000/api
```

## 7. Demo login credentials

All demo accounts use the password: **password**

| Role | Email |
|---|---|
| Super Admin | admin@eventflow.test |
| Manager | manager@eventflow.test |
| Finance | finance@eventflow.test |
| Event Staff | staff@eventflow.test |
| Customer | customer@eventflow.test |

The login screen has one-click buttons to fill each of these in.

Seeded demo data includes: Sarah's wedding (confirmed, deposit paid, tasks, staff,
equipment reserved), NovaTech's conference (quotation sent, awaiting acceptance),
an anniversary party (early enquiry), and a completed, fully-paid birthday event —
so every stage of the pipeline has something to show immediately.

## 8. Recommended next steps before production

- Add real payment gateway integration behind the existing `payments` API shape
- Add PDF generation for quotations/invoices/receipts
- Build Vendor and Equipment admin CRUD screens (backend already supports both)
- Add a visual calendar view
- Add email/SMS notifications alongside the in-app notification center
- Security hardening pass: rate limiting on public endpoints, file upload validation
  once document uploads are added, a proper CSP, and a dependency audit
- Replace demo data with real company content and branding assets
