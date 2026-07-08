# TicketStub — Event Ticket Booking System

A full-stack Event Ticket Booking System built with **PHP + MySQL + Bootstrap-free
custom CSS**, matching the stack and 3NF schema from the project documentation
(Section 3.4 and 3.9).

## What's included (Phase 1 + Phase 2)

- **Database**: full MySQL schema (`database/schema.sql`) matching the documented
  3NF relations — Roles, Staff, Customers, Venues, Seats, Events, Ticket_Tiers,
  Bookings, Transactions, QR_Code — plus seed data (`database/seed.sql`) with demo
  accounts and 3 sample events.
- **Accounts & roles**: Customers register/login separately from internal Staff
  accounts. Staff accounts carry a `Role_ID` (Admin / Organizer / Staff) that
  controls what they can access — enforced server-side in `includes/auth.php`
  (`require_login()`, `require_role()`, `require_customer()`).
- **Customer flow (fully working)**: browse published events → view event details
  → pick a ticket tier → pick a seat (for reserved-seating tiers) or just confirm
  (for General Admission) → mock payment → real QR code generated and shown on a
  ticket-stub-styled confirmation page → "My Tickets" dashboard.
- **Real-time availability**: ticket counts and seat availability are computed
  live from confirmed/pending bookings (`tier_remaining()` in
  `includes/functions.php`), and booking is wrapped in a DB transaction with a
  `SELECT ... FOR UPDATE` seat check to prevent two people booking the same seat.
- **Staff portal**: separate login at `/staff/login.php`. Admin and Organizer
  roles see a live dashboard (event counts, tickets sold, revenue — Organizers
  are scoped to only their own events).
- **Event & ticket tier management** (`staff/events.php`, `staff/event-form.php`,
  `staff/event-tiers.php`): Admins and Organizers can create/edit events
  (including a poster image upload and an inline "add new venue" widget),
  publish/unpublish/cancel them, and add, resize, or remove ticket tiers.
  Organizers can only manage events they created — Admins can manage all of
  them. Tiers already sold on cannot be deleted (only resized or the event
  cancelled), to protect existing bookings.
- **QR check-in scanner** (`staff/scan.php`): Admin, Organizer, and Staff roles
  can check attendees in by scanning (or typing) their ticket code. It
  validates the code exists, isn't expired, and hasn't already been used, then
  marks the booking `used`. Works with any USB "keyboard wedge" barcode/QR
  scanner, per the hardware requirements in the docs.
- **Staff account management** (`staff/manage-staff.php`, Admin only): create
  new Admin/Organizer/Staff accounts, edit their name/email/role, and
  suspend/reactivate existing ones. Suspended accounts are immediately blocked
  from logging in. Admins can't suspend or demote their own account (prevents
  accidental lockout).
- **Multi-ticket checkout**: customers can buy several tickets in one order —
  a quantity selector for General Admission, or picking multiple seats (up to
  6 per order) for reserved-seating tiers. One shared payment/transaction
  record covers the whole order; each ticket still gets its own QR code
  (`customer/confirmation.php` shows the full order, linking to each ticket).
- **Booking cancellation**: customers can cancel a confirmed, not-yet-started,
  not-yet-used booking from the ticket page. This frees up the seat/tier
  inventory immediately and invalidates its QR code so it can never be
  scanned in, even if someone tries afterward.
- **Customer profile editing & password change** (`customer/account.php`):
  update name/email/phone, or change password with current-password
  verification.
- **Self-service password reset** (`forgot-password.php` /
  `staff/forgot-password.php` + `reset-password.php`, shared by both account
  types): generates a single-use, 30-minute, SHA-256-hashed token
  (`Password_Resets` table). Since this project has no SMTP server
  configured, the reset link is shown directly on screen ("dev mode") *and*
  logged to `logs/mail.log` — swap in real SMTP via `config/mail.php` +
  `send_reset_email()` in `includes/functions.php` when you're ready.
- **Login rate limiting** (`Login_Attempts` table): 5 failed logins within 15
  minutes locks that username out for both customer and staff logins,
  independently. Successful logins clear the counter.
- **Camera-based QR scanning**: `staff/scan.php` now also supports scanning
  with a phone/webcam camera (via html5-qrcode), in addition to the original
  USB "keyboard wedge" scanner / manual entry.
- **Search bar** on the homepage, combined (AND) with the category filter chips.
- **Pagination** on `customer/my-tickets.php` and `staff/events.php`.
- **Booking confirmation emails**: like password resets, these run in the same
  dev-mode logging pattern — logged to `logs/mail.log` so you can see exactly
  what would be sent once real SMTP is configured.

## What's next (Phase 4)

- Real payment gateway integration (see the clearly marked INTEGRATION POINT in
  `customer/process-booking.php` — currently a mock "success" transaction)
- Real SMTP sending (see `config/mail.php` — currently dev-mode/logged only)
- Sales/reporting exports (CSV/PDF)

## Requirements

- PHP 8.1+ with the `pdo_mysql` extension
- MySQL 8.x or MariaDB 10.4+
- Any local server stack works: **XAMPP**, **Laragon**, **MAMP**, or PHP's
  built-in server

## Setup (XAMPP / Laragon)

1. Copy the `event-ticket-system` folder into your `htdocs` (XAMPP) or `www`
   (Laragon) directory.
2. Start Apache and MySQL from your control panel.
3. Open **phpMyAdmin**, and import the two files in this order:
   - `database/schema.sql`
   - `database/seed.sql`
4. If your MySQL root user has a password (default XAMPP/Laragon has none),
   update `config/database.php`:
   ```php
   define('DB_USER', 'root');
   define('DB_PASS', 'your_password_here');
   ```
5. Visit `http://localhost/event-ticket-system/index.php`

## Setup (PHP built-in server — quickest for testing)

```bash
mysql -u root < database/schema.sql
mysql -u root < database/seed.sql
cd event-ticket-system
php -S localhost:8000
```
Then open `http://localhost:8000/index.php`.

## Demo accounts

All demo accounts use the password: **`Password123!`**

| Role       | Username     | Where to log in         |
|------------|--------------|--------------------------|
| Customer   | `juandelacruz` | `/login.php`           |
| Admin      | `admin`        | `/staff/login.php`     |
| Organizer  | `organizer1`   | `/staff/login.php`     |
| Staff      | `staff1`       | `/staff/login.php`     |

## Project structure

```
event-ticket-system/
├── config/
│   ├── database.php               # PDO connection settings
│   └── mail.php                   # SMTP config (empty = dev mode / logged only)
├── database/
│   ├── schema.sql                 # 3NF schema, matches Section 3.4 of the docs
│   └── seed.sql                   # demo roles, accounts, venues, events, tiers, seats
├── logs/mail.log                  # dev-mode email log (reset links, booking confirmations)
├── includes/
│   ├── auth.php                   # session mgmt, RBAC, login rate limiting
│   ├── functions.php              # helpers: formatting, availability, pagination, email
│   ├── header.php / footer.php    # shared layout, role-aware nav
├── assets/css/style.css           # the "ticket stub" design system
├── assets/js/main.js              # nav toggle, flash auto-dismiss, search + category filter
├── index.php                      # homepage / event browsing + search
├── event-details.php              # single event + tiers
├── register.php / login.php       # customer auth
├── forgot-password.php / reset-password.php   # customer + staff password reset
├── logout.php                     # shared logout (customer + staff)
├── staff/
│   ├── login.php                  # internal-account login
│   ├── forgot-password.php        # staff password reset request
│   ├── dashboard.php              # role-aware dashboard (Admin/Organizer/Staff)
│   ├── events.php                 # event list (Admin: all, Organizer: own), paginated
│   ├── event-form.php             # create/edit event, poster upload, venue picker
│   ├── event-tiers.php            # manage ticket tiers per event
│   ├── api-add-venue.php          # AJAX endpoint: quick-add a venue
│   ├── scan.php                   # QR check-in scanner (manual + camera)
│   └── manage-staff.php           # Admin only: create/edit/suspend staff accounts
└── customer/
    ├── booking.php                 # tier + multi-seat/quantity selection
    ├── process-booking.php         # booking + mock payment + QR generation + email
    ├── confirmation.php            # multi-ticket order summary
    ├── cancel-booking.php          # booking cancellation
    ├── ticket.php                  # QR ticket display
    ├── my-tickets.php              # booking history, paginated
    └── account.php                 # profile edit + change password
```

## Design notes

The visual identity is a "ticket stub" motif: cards, booking confirmations, and
QR tickets are all shaped like physical admission tickets, complete with a
perforated, circle-notched divider between the event info and the price/QR
stub. Palette is a night-venue dark background with an amber marquee accent and
a violet stage-light accent; `Bebas Neue` is used for display type (marquee
energy), `Work Sans` for body copy, and `IBM Plex Mono` for ticket codes, seat
labels, and prices (a nod to barcode/ticket-printer typography).

## Security notes

- Passwords are hashed with `password_hash()` (bcrypt).
- All forms are protected with CSRF tokens (`includes/auth.php`).
- All SQL is parameterized via PDO prepared statements (no raw string
  concatenation).
- Seat booking is race-condition-safe via a DB transaction + `FOR UPDATE` lock,
  tested with two concurrent customers targeting the same seat.
