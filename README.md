# HerbaBloom

HerbaBloom is a PHP storefront and staff panel backed exclusively by the supplied `me_` MySQL schema.

## Local setup (Laragon)

1. Import the supplied schema into `herbabloom_db` with HeidiSQL. The schema script drops and recreates its tables; do not run it against a database containing data you need to keep. Run its seed section once after the schema.
2. Configure the web server to serve this directory and open `/web/index.php` for the store or `/admin/login.php` for staff.
3. Configure PHP with PDO MySQL, OpenSSL, and sessions enabled. PHPMailer is loaded from the project Composer dependencies.
4. Set the following environment variables for the PHP/web-server process:

   - `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`
   - `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASSWORD`, `SMTP_FROM`

   OTP email delivery is mandatory for staff sign-in. SMTP credentials are never stored in source code. Production deployments must use dedicated least-privilege database credentials and HTTPS.

## Application structure

- `db.php` creates the UTF-8 PDO connection, secure session settings, CSRF helpers, output escaping, and application URLs.
- `admin/` contains the staff panel. Role permissions are checked against the current database user on each request.
- `web/` contains the public storefront and customer order/contact flows.
- `api/` exposes the same backend as a JSON REST API. It uses the PHP session cookie and CSRF header; no API tokens or extra database tables are required.
- Stock is calculated from valid batches by `me_produktet_stoku`. Order reservations and cancellation restorations use transactions and record movements in `me_stoku_levizjet`.

The application does not create, migrate, or rename database tables.

Staff passwords created or reset in the panel must be 12–72 bytes. Newly created/reset accounts are required to change that password after OTP sign-in.

## REST API

Base URL in Laragon: `http://herbabloom.test/api` (or `http://localhost/herbabloom/api`).
Requests with a body must use `Content-Type: application/json`. Authenticated write requests also require the session cookie and `X-CSRF-Token`.

| Method | Endpoint | Input | Success | Main errors |
|---|---|---|---|---|
| GET | `/auth/csrf` | — | `200` with `csrf_token`; also creates a session cookie | `500` |
| POST | `/auth/login` | `{"username":"...","password":"..."}` | `202 OTP_REQUIRED` and sends OTP email | `401` invalid credentials, `422` invalid input, `429` rate limit, `503` OTP unavailable |
| POST | `/auth/verify-otp` | `{"otp_code":"123456"}` | `200` with signed-in user and role | `401` invalid/expired OTP, `422` malformed code |
| POST | `/auth/change-password` | `{"current_password":"...","new_password":"...","confirm_password":"..."}` | `200` | `401` unauthenticated, `422` invalid password |
| POST | `/auth/logout` | — | `200` and clears authenticated session | `401` unauthenticated, `419` CSRF failure |
| GET | `/products?q=tea&category=herbal&page=1&page_size=20` | Optional filters | `200` products and pagination | `422` invalid pagination |
| GET | `/orders?status=N%C3%AB%20pritje` | Optional status; staff session | `200` orders; courier only sees assigned deliveries | `401` unauthenticated, `403` forbidden, `422` invalid status |
| POST | `/orders` | Customer fields and `items:[{"product_id":1,"quantity":2}]`; admin/pharmacist | `201` order and tracking code; stock reserved in a transaction | `401`, `403`, `404` product missing, `409` insufficient stock, `422` invalid input |
| PATCH | `/orders/{id}` | `{"status":"Në përgatitje"}` or `{"courier_id":3}`; CSRF required | `200` updated status or assignment | `401`, `403`, `404` order/courier missing, `409` invalid transition, `422` invalid input |
| DELETE | `/orders/{id}` | `{"cancellation_reason":"..."}`; CSRF required | `200` cancelled order; stock restored to original batches | `401`, `403`, `404` missing/unassigned order, `409` invalid transition, `422` missing reason |
| GET | `/orders/track/{tracking_code}` | Public tracking code | `200` public order status and items | `404` order not found |

Example error response for a nonexistent order:

```json
{
  "success": false,
  "error": "ORDER_NOT_FOUND",
  "message": "Order does not exist."
}
```

### Try the API with a cookie-aware client

1. `GET /api/auth/csrf`, retaining the returned session cookie and token.
2. `POST /api/auth/login` with JSON credentials and header `X-CSRF-Token: <csrf_token>`. A `202` means the email OTP step is required.
3. `POST /api/auth/verify-otp` with the same cookie and CSRF header plus the six-digit code. Keep the renewed session cookie.
4. Call protected order endpoints with that cookie. For writes, include the current `X-CSRF-Token`.

Error responses use a consistent JSON shape: `success:false`, stable `error` code, and a human-readable `message`. Financial totals are returned by staff order endpoints only to the `admin` role.
