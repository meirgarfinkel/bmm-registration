# BMM Registration WordPress Plugin — Implementation Plan

## Context

BMM (bmm.org.il) needs a WordPress plugin for annual Yomim Noraim seat reservations and membership registration. Members fill out a multi-step form selecting membership, seats for each of 6 davening sessions (men's and women's sides), optional sponsorships, and personal/Hebrew-name info. The plugin calculates a locked total, saves a pending submission, then hands off to the **Nedarim Plus payment iframe** (postMessage-based). After payment, a server-side callback from Nedarim Plus marks the submission complete. Admins manage forms (draft/published/archived), browse submissions, and export CSV.

---

## Plugin Slug & Location

`bmm-registration/` inside the WordPress plugins directory.

---

## File Structure

```
bmm-registration/
├── bmm-registration.php              # Plugin header + bootstrap
├── uninstall.php                     # Clean up options/CPTs on delete
│
├── includes/
│   ├── class-bmm-plugin.php          # Singleton orchestrator
│   ├── class-bmm-post-types.php      # Register CPTs + custom statuses
│   ├── class-bmm-settings.php        # Global options (WP Settings API)
│   ├── class-bmm-form-config.php     # Value object wrapping per-form meta
│   ├── class-bmm-pricing.php         # Pure, stateless price calculator
│   ├── class-bmm-submission.php      # Submission CRUD + status transitions
│   ├── class-bmm-callback-handler.php# Nedarim Plus webhook processing
│   ├── class-bmm-csv-export.php      # CSV generation + streaming download
│   └── class-bmm-shortcode.php       # [bmm_registration form="slug"]
│
├── admin/
│   ├── class-bmm-admin.php           # Admin menus, capability checks
│   ├── class-bmm-form-editor.php     # Meta boxes for bmm_reg_form CPT
│   ├── class-bmm-submissions-list.php# WP_List_Table extension
│   └── views/
│       ├── form-editor.php
│       ├── submission-detail.php
│       ├── submissions-list-page.php
│       └── settings-page.php
│
├── public/
│   ├── class-bmm-form-renderer.php   # Resolves form slug → renders HTML
│   └── views/
│       ├── step-1-personal.php
│       ├── step-2-hebrew-names.php
│       ├── step-3-seats.php
│       ├── step-4-sponsorships.php
│       ├── step-5-notes-payment-type.php
│       └── step-6-summary-payment.php
│
├── rest-api/
│   ├── class-bmm-rest-price.php      # POST /bmm/v1/calculate-price
│   ├── class-bmm-rest-submit.php     # POST /bmm/v1/submit
│   └── class-bmm-rest-callback.php   # POST /bmm/v1/nedarim-callback
│
├── assets/
│   ├── css/
│   │   ├── bmm-form.css              # Public form (includes RTL rules)
│   │   └── bmm-admin.css
│   └── js/
│       ├── bmm-form.js               # Multi-step wizard + validation
│       ├── bmm-seats.js              # "Same for all davenings" toggle
│       ├── bmm-pricing.js            # Live price preview (calls REST)
│       └── bmm-nedarim.js            # Iframe lifecycle + postMessage
│
└── languages/
    └── bmm-registration.pot
```

No external Composer dependencies. Plain PHP 8.0+ and vanilla ES2017+ JS (no build step).

---

## Data Model

### CPT: `bmm_reg_form`
- `post_title` — form display name (e.g., "Yomim Noraim 5787")
- `post_name` — URL slug (unique link)
- `post_status` — `draft` | `published` | `archived`

**Post meta (prefix `_bmm_form_`):**

| Key | Type | Description |
|---|---|---|
| `membership_price` | int | NIS, flat membership fee |
| `membership_included_men` | int | Men's seats included in membership |
| `membership_included_women` | int | Women's seats included |
| `extra_seat_price` | int | NIS per additional seat |
| `sponsorships` | JSON | `[{id, label, amount, enabled}]` |
| `payment_options` | string | `ragil` \| `hk` \| `both` |
| `hk_months` | int | Months for standing order (0 = unlimited) |
| `ragil_tashlumim` | int | Installments for Ragil (default 1) |
| `mosad` | string | Per-form Nedarim institution ID (overrides global) |
| `api_valid` | string | Per-form ApiValid (overrides global) |

---

### CPT: `bmm_submission`
- `post_title` — registrant full name
- `post_date` — submission timestamp
- `post_status` — `pending` \| `completed` \| `failed`
- `post_parent` — ID of parent `bmm_reg_form` post

**Post meta (prefix `_bmm_sub_`):**

| Key | Type | Description |
|---|---|---|
| `first_name`, `last_name` | string | Latin name |
| `email`, `phone` | string | |
| `city`, `address` | string | |
| `zeout` | string | Israeli ID (optional) |
| `hebrew_name` | string | Registrant (placeholder: פלוני אלמוני בן אבא) |
| `tribe` | string | `kohen` \| `levi` \| `yisrael` |
| `wife_hebrew_name` | string | Optional |
| `children_hebrew_names` | JSON | Array of strings |
| `wants_membership` | int | 0/1 |
| `seats_men` | JSON | Array of 6 ints (one per davening) |
| `seats_women` | JSON | Array of 6 ints |
| `sponsorships_selected` | JSON | Array of sponsorship IDs |
| `notes` | string | |
| `payment_type` | string | `Ragil` \| `HK` |
| `price_membership` | int | Computed at submit |
| `price_extra_men` | int | |
| `price_extra_women` | int | |
| `price_sponsorships` | int | |
| `price_total` | int | **Locked total sent to Nedarim** |
| `callback_token` | string | 32-char random token for callback URL |
| `nedarim_transaction_id` | string | From callback |
| `nedarim_keva_id` | string | For HK submissions |
| `nedarim_confirmation` | string | Approval number from Shva |
| `nedarim_last_num` | string | Last 4 card digits |
| `nedarim_raw_callback` | JSON | Full raw callback payload (audit) |
| `payment_completed_at` | string | ISO datetime |

---

## Pricing Logic

Implemented in `BMM_Pricing` — pure, stateless, no I/O.

```
membership_fee    = wants_membership ? form.membership_price : 0

extra_men         = max(0, max(seats_men[]) - form.membership_included_men)
                    × form.extra_seat_price
                    (if !wants_membership: included seats = 0)

extra_women       = max(0, max(seats_women[]) - form.membership_included_women)
                    × form.extra_seat_price

sponsorships_fee  = sum of selected sponsorship amounts

total             = membership_fee + extra_men + extra_women + sponsorships_fee
```

**Seat pricing uses the maximum across all 6 davenings** (not per-davening sum). This is the most practical model for synagogue use.

The `BMM_REST_Price` endpoint exposes this calculation for live preview (no state written). The authoritative total is always computed server-side at submit time and stored in `_bmm_sub_price_total` before the iframe is initialized.

---

## The 6 Davenings

Stored as indices 0–5 in `seats_men[]` / `seats_women[]`:
- 0: Rosh Hashana Night 1
- 1: Rosh Hashana Day 1
- 2: Rosh Hashana Night 2
- 3: Rosh Hashana Day 2
- 4: Yom Kippur Night
- 5: Yom Kippur Day

---

## Registration Form (6 Steps)

**Step 1 — Personal Info**
First name, last name, phone, email, city, street address, optional Israeli ID (ת.ז.)

**Step 2 — Hebrew Names**
- Registrant's Hebrew name (RTL input, placeholder: `פלוני אלמוני בן אבא`)
- Tribe: כהן / לוי / ישראל (radio)
- Wife's Hebrew name (optional)
- Children's Hebrew names — dynamic add/remove list

**Step 3 — Membership & Seats**
- Membership checkbox with price displayed
- Seat grid: 12 inputs (6 davenings × men/women)
- "Same for all davenings" toggle — syncs all 6 to a single men's and single women's input (UX convenience; server still receives array of 6)
- Live price preview updates as values change (calls `GET /bmm/v1/calculate-price`)

**Step 4 — Sponsorships**
- Checkboxes for each enabled sponsorship with NIS amount
- Default sponsorships (admin-configurable): Kiddush Fund (350), Avos Ubanim (300), Shalosh Seudos (250)

**Step 5 — Notes & Payment Type**
- Optional notes textarea
- Payment type: Ragil (one-time) / Horaat Keva (standing order) — shown based on form config

**Step 6 — Summary & Payment**
- Read-only itemized summary
- Nedarim Plus iframe (dynamically loaded)
- Amount pre-filled and locked from server

---

## Payment Flow

1. User completes Steps 1–5 (state held in `window.bmmFormState`, also persisted to `sessionStorage` so refresh doesn't lose data).
2. On reaching Step 6: JS calls `POST /wp-json/bmm/v1/submit` with all data.
3. Server validates, calculates total via `BMM_Pricing`, creates `bmm_submission` (status: `pending`), generates 32-char `callback_token`, returns `{submission_id, total, itemized, mosad, api_valid, payment_type, tashlumim, callback_url}`.
4. JS creates `<iframe src="https://www.matara.pro/nedarimplus/iframe/">`.
5. On iframe `message` event signaling readiness, JS calls `PostNedarim()` (Nedarim's required script embedded in page) with:
   ```js
   {
     Mosad, ApiValid,
     FirstName, LastName, Phone, Mail, City, Street, Zeout,
     PaymentType,  // "Ragil" or "HK"
     Amount,       // from server response — never client-calculated
     Tashlumim,
     Comment,      // short registration summary ≤300 chars
     Param1: submission_id,
     CallBack: callback_url  // includes token as query param
   }
   ```
6. User completes payment in iframe.
7. **Client side**: iframe postMessages result. On success: show confirmation. On failure: show error with retry (reuses same `submission_id`, re-initializes iframe only).
8. **Server side**: Nedarim POSTs to `/wp-json/bmm/v1/nedarim-callback?token={callback_token}` from IP `18.194.219.73`.

---

## Callback Handler Security

`BMM_Callback_Handler` (`class-bmm-callback-handler.php`):

1. Verify source IP = `18.194.219.73` (with configurable `BMM_TRUST_PROXY` constant for load-balanced setups).
2. Verify `token` query param matches `_bmm_sub_callback_token` on the submission.
3. Look up submission by `Param1` (post ID). Verify it's a `bmm_submission` post whose parent is a valid `bmm_reg_form`.
4. If already `completed`: return `200 OK` (idempotent).
5. Branch on payload: if `KevaId` present and no `TransactionId` → HK establishment; else regular transaction.
6. Store `nedarim_transaction_id`, `nedarim_keva_id`, `nedarim_confirmation`, `nedarim_last_num`, `nedarim_raw_callback`, `payment_completed_at`.
7. Cross-check `Amount` in callback vs `_bmm_sub_price_total`; if mismatch, set `_bmm_sub_amount_mismatch = 1` for admin review.
8. Transition post status to `completed`.
9. Fire `do_action('bmm_payment_completed', $submission_id, $payload)`.
10. Return `200 OK`.

---

## REST API Endpoints (namespace `bmm/v1`)

| Method | Path | Auth | Purpose |
|---|---|---|---|
| POST | `/calculate-price` | Public + nonce | Live price preview |
| POST | `/submit` | Public + nonce | Create pending submission, return locked total |
| POST | `/nedarim-callback` | IP + token | Nedarim Plus payment webhook |

---

## Admin UI

**Menu structure**: "BMM Registration" (top-level) → Forms, Submissions, Settings.

**Forms list**: Standard CPT list table with status column and "Copy link" action for published forms.

**Form editor**: Meta boxes on the `bmm_reg_form` edit screen covering all `_bmm_form_*` fields. Sponsorships section is a repeater (add/remove rows with JS).

**Submissions list**: `WP_List_Table` with columns: Name, Date, Form, Total (NIS), Payment Status. Filterable by status. Bulk "Export CSV" action.

**Submission detail**: Read-only admin page showing all submitted data + payment info. Manual status override dropdown.

**Settings page**: Global Mosad, ApiValid, ApiPassword (used as fallback when not set per-form).

---

## CSV Export

UTF-8 with BOM (`\xEF\xBB\xBF`) so Excel opens Hebrew correctly. Streamed via `fputcsv` to `php://output`. Triggered by nonce-protected `admin-post.php` action, restricted to `manage_options`.

**Columns** (in order):
Submission ID, Date, Payment Status, Form Name, First Name, Last Name, Email, Phone, City, Address, Israeli ID, Hebrew Name, Tribe, Wife Hebrew Name, Children Hebrew Names (semicolon-separated), Membership Purchased, Seats Men RH Night 1–2 / Day 1–2 / YK Night / YK Day (6 columns), Seats Women (6 columns), Sponsorships Selected, Notes, Payment Type, Membership Fee, Extra Men Seats Fee, Extra Women Seats Fee, Sponsorships Fee, Total (NIS), Nedarim Transaction ID, Standing Order ID, Approval Number, Card Last 4, Payment Completed At.

---

## Form Access

Each published form is accessible at `/?bmm_form={slug}` (query var) **and** via shortcode `[bmm_registration form="{slug}"]` on any WP page.

Archived forms return a "registration is closed" message on the public URL. Draft forms are only visible to logged-in admins.

---

## Key Assumptions to Confirm During Build

- **Seat pricing**: max-across-davenings model confirmed.
- **ID number**: optional field, passed as `Zeout` to Nedarim iframe when provided.
- **RTL**: Hebrew inputs use `dir="rtl"`; form overall direction follows site locale.
- **HK months**: configurable per form (`hk_months`); 0 means unlimited.
- **No user login required**: submissions are anonymous.
- **Nedarim credentials**: Mosad + ApiValid come from admin-configured settings (global or per-form). ApiPassword (for history/export APIs) lives only in global settings.

---

## Implementation Sequence

1. Plugin bootstrap (`bmm-registration.php`, `BMM_Plugin`, `BMM_Post_Types`)
2. Global settings page (`BMM_Settings`)
3. Form editor meta boxes (`BMM_Form_Config`, `BMM_Form_Editor`)
4. Pricing engine (`BMM_Pricing`) — test manually before wiring to UI
5. REST price endpoint (`BMM_REST_Price`)
6. Form renderer + 6 step views (static HTML first)
7. `bmm-form.js` wizard navigation + `bmm-seats.js` toggle
8. `bmm-pricing.js` live preview
9. Submit endpoint (`BMM_REST_Submit`, `BMM_Submission`)
10. `bmm-nedarim.js` iframe + postMessage handling
11. Callback handler (`BMM_REST_Callback`, `BMM_Callback_Handler`)
12. Submissions list table + detail view (`BMM_Submissions_List`)
13. CSV export (`BMM_CSV_Export`)
14. RTL + responsive CSS polish
15. `uninstall.php`

---

## Verification

- Register a test form in WP admin → confirm unique URL resolves.
- Complete all 6 form steps → verify submission created in DB with correct meta.
- Use the Nedarim Plus test environment to fire a real iframe payment → confirm callback marks submission `completed`.
- Export CSV → open in Excel, verify Hebrew renders correctly with BOM.
- Archive a form → verify public URL shows "closed" message.
- Test "same for all davenings" toggle → verify all 6 seat values sync.
- Test IP mismatch on callback → verify 403 returned and submission stays `pending`.
