# BMM Registration — Test Suite

This plugin has two independent, dependency-light test suites that run without a
live WordPress install:

| Suite | Runner | Location | What it covers |
|-------|--------|----------|----------------|
| **PHP unit** | PHPUnit 11 | `tests/*.php` | Pure business logic — pricing, payment verification, the payment audit, bulk actions, CSV export, submission helpers |
| **JS unit** | Node's built-in test runner + jsdom | `tests/js/*.test.js` | Front-end form logic — seat collection, the "same for all" mirror, the three-way seat-mode toggle, sponsorships |

As of this writing: **85 PHP tests** (187 assertions) and **18 JS tests**.

## Running the tests

### PHP

```bash
composer install          # once, installs PHPUnit into vendor/
composer test             # or: vendor/bin/phpunit
vendor/bin/phpunit --testdox   # human-readable list of every case
```

Config lives in `phpunit.xml.dist`; it boots `tests/bootstrap.php` and runs
everything under `tests/`. `failOnWarning`/`failOnNotice` are on, so a PHP
notice fails the build.

### JavaScript

```bash
npm install               # once, installs jsdom
npm test                  # runs: node --test tests/js/*.test.js
```

Requires Node 18+ (uses the built-in `node:test` module — no Jest/Mocha).

## How these tests work without WordPress

The plugin's classes guard themselves with `defined( 'ABSPATH' ) || exit;` and
only call WordPress functions **at call time**, not when the class is defined.
That lets the tests load the class files directly and exercise their pure logic.

- **`tests/bootstrap.php`** defines `ABSPATH`, a `__()` shim, loads
  `tests/wp-stubs.php`, and `require_once`s the classes under test
  (`BMM_Form_Config`, `BMM_Pricing`, `BMM_Submission`, `BMM_Callback_Handler`,
  `BMM_CSV_Export`, `BMM_Admin`).
- **`tests/wp-stubs.php`** provides tiny **in-memory post and post-meta stores**
  plus shims for the WordPress functions the admin/mutation code calls
  (`get_post`, `wp_update_post`, `wp_trash_post`, `get_post_meta`,
  `update_post_meta`, `delete_post_meta`, the `sanitize_*` family, `wp_slash`/
  `wp_unslash`, `wp_json_encode`, …). Each shim is guarded with
  `function_exists()` so a real WordPress environment always wins. Helpers:
  - `__wp_reset_posts()` / `__wp_reset_meta()` — clear the stores (call in `setUp()`).
  - `__wp_seed_post( $id, $type, $status )` — seed a fake post.
  - `__wp_status( $id )` — read a post's current status for assertions.

The **guiding pattern**: where logic would otherwise be entangled with WordPress,
it is extracted into a **pure static method** that takes plain values and returns
plain values, and the thin WordPress wrapper delegates to it. Examples:
`BMM_Callback_Handler::payment_succeeded()`,
`BMM_Submission::resolve_list_status()`,
`BMM_Submission::completion_is_unverified()`,
`BMM_Submission::build_simulation_data()`,
`BMM_CSV_Export::build_row()`,
`BMM_Admin::apply_bulk_action()`. These are the seams the tests target.

## PHP test files

- **`PricingTest.php`** — `BMM_Pricing::calculate()` end to end: membership,
  the Horaat-Keva path (incl. the **₪0 order** with no extra seats), guest
  seats, three-way mutual exclusivity, sponsorships, and the per-holiday seat
  aggregates (`seats_for_holiday()`, and that `HOLIDAYS` partitions every
  davening).
- **`CallbackSuccessTest.php`** — `payment_succeeded()`: a submission is only
  "completed" for a real approved Nedarim payment (valid TransactionId / KevaId /
  explicit success), never for a declined or empty callback.
- **`SubmissionQueryTest.php`** — `resolve_list_status()` (the Submissions filter,
  default → Completed, plus `all` and the audit-only `unverified`),
  `completion_is_unverified()` and `record_looks_paid()` (the payment-audit rule,
  including re-evaluating a stored decline callback).
- **`BulkActionHandlerTest.php`** — `resolve_current_bulk_action()` and
  `apply_bulk_action()` against the in-memory store: Mark Completed/Pending/Failed
  and Move to Trash each change the right rows, foreign/missing posts are skipped.
- **`BulkActionTest.php`** — `bulk_action_new_status()` mapping.
- **`CsvExportTest.php`** — `build_row()` has exactly as many columns as
  `get_headers()` (guards silent field-shift), per-holiday aggregates land in the
  right columns, Yes/No flags render.
- **`SimulationDataTest.php`** — `build_simulation_data()`: the admin payment
  simulation carries the real name + seats, with dummy fallbacks for blanks.
- **`SubmissionEditTest.php`** — the admin edit round-trip: `update_fields()`
  persists edited name/seats/membership and `recalculate_pricing()` re-derives
  the price breakdown (incl. re-pricing an edit down to a ₪0 order), against the
  in-memory post-meta store.
- **`CheckoutModeTest.php`** — the money-critical rule (`checkout_mode()` + real
  pricing): a paid order must reach the payment screen; an order that comes to ₪0
  for the wrong reason (seats entered without a paid option) is `invalid`, never
  free; only a Horaat-Keva order with no extra seats is `free`.
- **`SanitizeDateTest.php`** — `BMM_Submission::sanitize_date()` accepts only
  valid `Y-m-d` dates (data-provider driven).

## JS test files

- **`_support.js`** — shared jsdom fixture (`loadForm`, `loadSeats`) that builds
  a representative registration DOM and loads a front-end module against it. Not
  a test file itself.
- **`collect-seats.test.js`** — `collectSeats()`/`collectStep(3)` read the correct
  gender's inputs (guards the men/women substring bug) and clamp bad values.
- **`same-for-all.test.js`** — the "Same for all davenings" toggle mirrors the
  shared value into every per-davening input and fires `bmm:seats-changed`.
- **`membership-toggle.test.js`** — three-way mutual exclusivity between
  Membership / Horaat Keva / Guest Seats, and that `collectStep(3)` records the
  Horaat-Keva flag.
- **`sponsorships.test.js`** — sponsorship selection/collection and the Kiddush
  Fund conditional fields.

## Adding a test

1. If the logic touches WordPress, first extract the decision into a **pure
   static method** and have the WP wrapper call it — then test the pure method.
2. PHP: add a `Test` class under `tests/` (PHPUnit discovers it automatically).
   Seed fake posts with the `__wp_*` helpers if you need `get_post` etc., and
   `__wp_reset_posts()` in `setUp()`.
3. JS: add `tests/js/<name>.test.js` using `node:test` and the `_support.js`
   loaders.
4. Run `composer test` and `npm test`; both must stay green (and every pushed
   change also bumps the plugin version — see `CLAUDE.md`).
