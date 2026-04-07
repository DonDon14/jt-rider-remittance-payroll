# Mobile API Reference

Base URL: `/api`

Authentication:
- `POST /api/login`
- `POST /api/logout`
- `POST /api/logout-all`
- Send `username`, `password`, and optional `device_name`
- Use returned bearer token in `Authorization: Bearer <token>`

List endpoints:
- List responses return `data.items` and `data.meta`
- `page` and `per_page` query parameters are supported on paginated list endpoints
- `meta` includes `page`, `per_page`, `total`, `count`, and `has_more`

Rider endpoints:
- `GET /api/rider/profile`
- `GET /api/rider/dashboard?month=YYYY-MM`
- `GET /api/rider/payrolls?page=1&per_page=20`
- `GET /api/rider/submissions?page=1&per_page=20`
- `GET /api/rider/announcements`
- `GET /api/rider/remittance-accounts`
- `POST /api/rider/delivery-submissions`
  - `delivery_date`
  - `allocated_parcels`
  - `successful_deliveries`
  - `expected_remittance`
  - `remittance_account_id`
  - `notes` optional
- `POST /api/rider/payroll/{id}/confirm`
  - `received_notes` optional

Admin endpoints:
- `GET /api/admin/pending-submissions?page=1&per_page=20`
- `POST /api/admin/pending-submissions/{id}/approve`
  - `commission_rate`
- `POST /api/admin/pending-submissions/{id}/reject`
  - `rejection_note` optional
- `GET /api/admin/pending-remittances?page=1&per_page=20`
- `GET /api/admin/shortages?page=1&per_page=20`
- `GET /api/admin/payrolls?page=1&per_page=20&payroll_status=GENERATED|RELEASED|RECEIVED`
- `POST /api/admin/payrolls/{id}/release`
  - `payout_method`
  - `payout_reference` optional

Notes:
- API routes are token-based and are excluded from browser CSRF protection.
- Current mobile-ready scope covers rider dashboard, submissions, payroll receipt, and core admin queue actions.
