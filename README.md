# Caroline's Party Helper — Booking System

A multi-page PHP website. Customers request a booking, verify their email, and wait for Caroline's approval. Caroline gets a Gmail notification and approves or rejects from an admin dashboard; the customer is emailed the decision.

**Stack:** HTML/CSS/JS · PHP 8+ · MySQL (phpMyAdmin) · PHPMailer + Gmail SMTP · Composer · vlucas/phpdotenv

**A booking is never confirmed automatically.** Status flow: `pending_email` → (customer verifies) → `pending_review` → (admin) → `approved` / `rejected`.

---

## 1. Local setup with XAMPP (Windows/Mac)

1. **Install XAMPP** (PHP 8.0+). Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. **Copy this folder** to `C:\xampp\htdocs\party-booking-system` (Mac: `/Applications/XAMPP/htdocs/party-booking-system`).
3. **Install Composer** from https://getcomposer.org, then open a terminal in the project folder:
   ```
   cd C:\xampp\htdocs\party-booking-system
   composer install
   ```
   This creates the `vendor/` folder (PHPMailer + dotenv).
4. **Create the database:** open http://localhost/phpmyadmin → **Import** tab → choose `database/schema.sql` → **Go**. (It creates the `party_booking` database and all tables.)
5. **Create your settings file:** copy `.env.example` to `.env` and edit it (see section 3).
6. **Create your admin account** in the terminal:
   ```
   C:\xampp\php\php.exe scripts\create-admin.php
   ```
   (If `php` works in your terminal, `php scripts/create-admin.php` is enough.)
7. **Test the setup:**
   ```
   php scripts/test-connections.php
   php scripts/test-connections.php youraddress@gmail.com   (also sends a real test email)
   ```
8. Open **http://localhost/party-booking-system/** — and the admin at **http://localhost/party-booking-system/admin/**.

## 2. Gmail App Password

Gmail will not accept your normal password for apps.
1. Sign in to the Gmail account that will send/receive booking emails.
2. Open https://myaccount.google.com/security and turn on **2-Step Verification** (required).
3. Open https://myaccount.google.com/apppasswords, create an app password named e.g. "Party Booking", and copy the 16-character code.
4. Put it in `.env` as `MAIL_PASSWORD` (spaces optional). Never share it or commit `.env`.

## 3. `.env` settings

```
DB_HOST=localhost
DB_PORT=3306
DB_NAME=party_booking
DB_USER=root
DB_PASS=

MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=yourgmail@gmail.com
MAIL_PASSWORD=your_google_app_password
MAIL_FROM_NAME="Caroline's Party Helper"

ADMIN_EMAIL=yourgmail@gmail.com
APP_URL=http://localhost/party-booking-system
APP_ENV=local
APP_TIMEZONE=America/New_York
```
`APP_URL` must match where the site really lives (no trailing slash) — it is used for links in emails. Set `APP_ENV=production` when live.

**XAMPP on Windows, "SSL certificate" errors when sending mail?** In `C:\xampp\php\php.ini` set `openssl.cafile` to a downloaded `cacert.pem` (https://curl.se/ca/cacert.pem) and restart Apache.

## 4. Testing checklist

| Test | How |
|---|---|
| Database + Gmail | `php scripts/test-connections.php` |
| Navigation | Click every menu item on desktop and a narrow window (menu ☰). Each opens its own page; the current one is highlighted; the logo returns Home. |
| Form validation | On `booking.php` try: end date before start, same day with end ≤ start, a past date, empty fields. Errors appear and submit is blocked. Disable JavaScript and repeat — the server rejects them too. |
| Booking submit | Submit a valid request (e.g. Nov 25 2026 8:00 AM → Nov 26 2026 5:00 PM). You see "Booking Request Submitted" with dates/times, status **Waiting for email verification**. |
| Verification email | Check the inbox (and spam) for "Verify Your Booking Email Address". Click the button → success page. |
| Admin notification | Caroline's Gmail gets "New Verified Booking Request - Name", marked *Email Verified - Pending Administrator Approval*. Press **Reply** — the To: field is the customer. |
| Expired link | In phpMyAdmin set `verification_expires_at` of an unverified booking to a past time, click the link → "Verification link expired" with a resend option. |
| Resend | Use the resend page (reference + email). The old link stops working. |
| Duplicates | Submit the same email + same dates twice → blocked. Click Submit rapidly → only one booking. |
| Admin login | `/admin/` → log in. Wrong password 5 times → temporarily blocked. |
| Approve | Open a *Pending review* booking → Approve. Customer receives "Your Party Booking Request Has Been Approved!". |
| Reject | Reject with an optional message → customer receives "Update Regarding Your Party Booking Request" (private notes are never included). |
| Overlap prevention | Approve one booking, then try to approve another that overlaps its time range → blocked with a conflict message. |
| Unauthorized access | Log out, then open `/admin/dashboard.php` or `/admin/booking-details.php?id=1` → sent to login. POSTing to `approve-booking.php` without a session or CSRF token fails. Also `/.env`, `/config/`, `/vendor/` return 403. |

## 5. File guide

**Public pages (project root)**
- `index.php` Home · `services.php` · `about.php` · `gallery.php` · `booking.php` (the form) · `contact.php` (info + message form)

**`booking/`**
- `submit-booking.php` receives the form: CSRF, honeypot, single-use form token, rate limits, server validation, duplicate check, saves the booking with a hashed token, sends the verification email.
- `booking-submitted.php` confirmation page with the full summary.
- `verify-email.php` checks the token (exists / not expired / not used), sets `email_verified=1` and `pending_review`, erases the token, emails Caroline.
- `resend-verification.php` secure "send a new link" (needs reference + email; rate limited).

**`contact/submit-inquiry.php`** emails the contact-form message to Caroline (Reply-To = visitor).

**`admin/`** `login.php`, `logout.php`, `dashboard.php` (stats, search, filters, pagination, recent activity), `booking-details.php` (details, history, conflict warning, approve/reject/notes), `approve-booking.php`, `reject-booking.php`, `save-notes.php`.

**`includes/`**
- `bootstrap.php` loads Composer, `.env`, time zone, secure session, headers — every page includes it.
- `functions.php` helpers: escaping, URLs, flash messages, rate limiting, date/time parsing, booking validation.
- `bookings.php` all booking database logic (create, verify, approve with lock + conflict check, reject, search, history).
- `csrf.php` CSRF tokens and single-use form tokens · `auth.php` admin login/session.
- `mail-templates.php` HTML emails · `notifications.php` the four booking emails.
- `header.php` / `footer.php` shared public layout and navigation · `admin-header.php` / `admin-footer.php` admin layout.

**`config/`** `database.php` (PDO connection) · `mailer.php` (PHPMailer + Gmail; reports success only if PHPMailer confirms).

**`database/schema.sql`** tables: `administrators`, `bookings`, `booking_logs`, `email_logs`, `rate_limits`.
**`scripts/`** `create-admin.php`, `test-connections.php` (command line only).
**`assets/`** `css/style.css` (your original design, unchanged), `css/pages.css` (additions), `css/admin.css`, `js/main.js`, `js/booking.js`, `js/admin.js`.
**Other:** `.env.example`, `.gitignore` (excludes `.env` and `vendor/`), `.htaccess` (blocks private folders), `composer.json`.

## 6. Security summary
PDO prepared statements everywhere · all output escaped with `htmlspecialchars` · 32-byte random tokens, only the SHA-256 hash stored, 30-minute expiry, erased after use · CSRF tokens on every POST · password_hash/password_verify, session ID regenerated on login, 30-minute idle timeout · rate limiting on bookings, resend, verification and login · honeypot fields · approval re-checked server-side inside a transaction with a MySQL lock (no double approval or double booking) · credentials only in `.env` · technical errors go to the PHP error log, never to visitors.

## 7. Going live
Upload everything (including `vendor/` or run `composer install --no-dev` on the server), create a production `.env` with `APP_ENV=production` and your `https://` `APP_URL`, import `schema.sql`, create the admin, and enable the HTTPS redirect lines in `.htaccess`. Never upload your local `.env` to a public repository.

## Notes
- The customer calendar and time lists are built from the availability you set in Admin > Availability (see section 8).
- Gallery and hero photos load from Unsplash, so they need an internet connection. The logo is the emoji design from the original site.

---

## 8. Availability & calendar management (update 2)

**You control which dates and times customers can book.** The booking page shows an interactive calendar. Days you are closed, blocked, in the past, or fully booked are greyed out and cannot be clicked; the time lists only contain genuinely free times. The server checks everything again on submit, so it cannot be bypassed.

### How availability works
1. **Weekly hours** (Admin > Availability): which weekdays you work and when. A day can have two ranges (e.g. a lunch break).
2. **Date changes** (Admin > Calendar > click a day, or "Block dates"): block a date, or give it custom hours (this can also open a day that is normally closed).
3. **Approved bookings** automatically block their exact time. Two bookings can share a day if their times do not overlap.
4. **Pending requests never block the calendar.** When you approve one, the system re-checks it, so two overlapping pending requests can never both be approved.
5. Changes are live immediately (the booking page reads MySQL on every request, nothing is cached).
6. **Time steps** (15 / 30 / 60 minutes) are set in Availability. A booking can last up to 14 days (`MAX_BOOKING_DAYS` in `includes/availability.php`). For multi-day bookings every day in between must be open, and the first/last times must fall inside opening hours.

### Update your database with phpMyAdmin
- **Existing database (you already imported schema.sql earlier):** open http://localhost/phpmyadmin → click the `party_booking` database → **Import** → choose `database/migration-002-availability.sql` → **Go**. It only adds three new tables (`weekly_hours`, `availability_overrides`, `settings`); your bookings, admin account and logs are untouched. Run it **once**.
- **Brand-new install:** just import `database/schema.sql` (it already contains everything).
- The migration inserts **starting** weekly hours of every day 8:00 AM – 8:00 PM. Change them right away in Admin > Availability.
- Check with `php scripts/test-connections.php`.
- **No new Composer packages** are needed. No `composer install` is needed again if `vendor/` already exists.

### New admin pages
Dashboard (overview) · Bookings (search by customer name/reference/email, event date, status) · Calendar (month view, approved = green, pending = amber, blocked = red stripes, closed = grey, custom hours = blue) · Calendar day (edit one date) · Availability (weekly hours, block dates, time steps, upcoming changes) · Customers · History (every action and email). Booking details also has "Email the customer" for status updates.

### How to test (do these in order)
**Availability + customer page**
1. Admin > Availability: confirm the weekly hours. Untick **Sunday**, save.
2. Open `booking.php` (private window): Sundays and past dates are greyed and cannot be clicked. Pick a Monday: you see "This date is available!" and the start times.
3. Admin > Calendar > click a future Tuesday > "Unavailable all day" > Save. Refresh/reopen the booking page (or just click to another month and back): that Tuesday is now disabled. Click "Follow the weekly hours" to reopen it.
4. Block a whole week with "Block dates", confirm those days are disabled; remove them under "Upcoming changes".
5. Click a day, then **End Date** and pick a later day for a multi-day booking; the end-time list updates.
6. Admin > Calendar > a day > "Custom available hours" 10:00–12:00 and 13:00–15:00: the customer sees only those times (the lunch gap is not offered).

**Approval and overlaps**
7. Submit two requests for overlapping times on the same day (use two different email addresses) and verify both emails. Both appear as *Pending review* on the calendar (amber) and do **not** block the date.
8. Approve the first: the calendar day turns green and the customer gets "Your Party Booking Request Has Been Approved!" with date, start/end time and reference.
9. Open the second request: it shows a **Schedule conflict** warning and the Approve button is disabled. On the booking page, the first booking's time is no longer offered.
10. Reject the second with a reason: the customer receives the polite rejection email. The request stays in Bookings and History.
11. Try a request, then block its date in Availability *before* approving: approval is refused with an explanation.
12. Back-to-back bookings (one ends at 12:00 PM, another starts at 12:00 PM) are allowed.

**Server-side protection**
13. With the browser dev tools, edit a hidden `start_date` value to a blocked date and submit: the server rejects it with "Sorry, ... is not available".

### New files
`includes/availability.php` (the availability engine) · `api/availability.php` (JSON for the calendar) · `database/migration-002-availability.sql` · `admin/bookings.php`, `calendar.php`, `calendar-day.php`, `availability.php`, `availability-action.php`, `customers.php`, `history.php`, `send-update.php` · updated `booking.php`, `assets/js/booking.js`, `assets/css/*`, `includes/bookings.php`, `booking/submit-booking.php`.
#   c a r o l i n e s - w e b s i t e  
 