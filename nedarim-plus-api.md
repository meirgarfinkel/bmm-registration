# Nedarim Plus (Vows Plus) API Documentation

> This technical documentation is intended for developers who want to create tasks and interact with Vows Plus.
>
> For any questions or problems, contact support at **office@nedar.im**

---

## Table of Contents

- [General / Introduction](#introduction)
- [Credit (Clearing via Iframe, History, Cancellation, Credit, Callback, Excel Exports)](#credit)
- [Bit Clearing](#bit-clearing---vows-plus)
- [Credit Card Standing Orders](#credit-card-standing-orders)
- [Banker (Bank Standing Orders)](#banker)
- [External Revenues](#external-revenues)
- [Vows Card](#vows-card)
- [Receipt System](#receipt-system)
- [Donor Management](#donor-management)
- [Vows of Vow (Phone)](#vows-of-vow)
- [Matching System](#matching-system)
- [Messages to the Collector](#messages-to-the-collector)
- [Forms Department](#forms-department)

---

## Introduction

**General notes**

- All interface calls use the **HTTPS** protocol.
- Dates are in **`dd/mm/yyyy`** format.
- Each request must include an **institution identifier** parameter. The parameter name sometimes differs depending on the API type.
- Each request must include a **verification parameter** received from the office.
- To receive the verification ID, send an email to Nedarim Plus customer service from an email authorized by the institution and request a verification code.
- Required parameters are marked with an asterisk (\*).

---

## Credit

### Clearing via Iframe

The iframe solution uses simple technology that lets your page fully communicate with the Nedarim iframe. The most professional implementation displays a clean page without switching between windows.

**How it works**

1. The customer enters your payment page, which includes an iframe element.
2. The page commands the iframe to load: `https://www.matara.pro/nedarimplus/iframe/`
3. After loading, your page asks the iframe for its height.
4. You can re-check the height whenever the window resizes to keep the site responsive.
5. The iframe returns its height, which you immediately set in the iframe's `style`.
6. The customer fills in their details and credit card information, then clicks **Make Payment**.
7. Your page sends the iframe all transaction information (name, address, phone, etc.) entered on the page.
8. The iframe completes the clearing and notifies the page of the result.
9. You present the result to the customer.

**Example page:** `https://www.matara.pro/nedarimplus/iframe/sample2.html`

**Important**

- Communication with the iframe is done via `Post.Message`. You **must** embed all of the script from the example page into your page.
- At the end of the transaction, you can optionally receive a **CallBack** directly to your server, in addition to the iframe response. This prevents loss of information or communication interruptions caused by a client-side fault.
- If the institution is connected to a crowdfunding platform (e.g. Kuzmatch / Charidi / LiveRaiser), the Nedarim mechanism will **not** notify the platform of a new transaction, to prevent double updates on the campaign page — unless you send `'ForceUpdateMatching': '1'` when sending information to the iframe.
- If you generate receipts yourself, send `'ThirdPartyReceipt': '1'` so the system does not generate a receipt even if the customer is connected to the receipt service (neither during nor after the transaction; the transaction is simply marked as already having a receipt).

**Token creation**

- Create a token by setting `PaymentType` to `CreateToken`.
- For tokens, the iframe must be opened with `Tokef=Hide&CVV=Hide`, since this function only saves the credit card number. (You must save the validity in your own database. By law, the CVV may not be saved — not even by Nedarim.)
- Full link including token creation: `https://www.matara.pro/nedarimplus/iframe/?Tokef=Hide&CVV=Hide`

**Parameters to send to the iframe via `PostNedarim`** (all parameters must be listed, even if empty):

| Parameter | Value | Detail | Max chars |
|---|---|---|---|
| `Mosad` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) | 7 |
| `ApiValid` \* | xxxxxxx | Verification text (request from the office) | 10 |
| `Zeout` | | ID number | 9 |
| `FirstName` | | First name | 50 |
| `LastName` | | Last name | 50 |
| `Street` | | Street | 100 |
| `City` | | City | 100 |
| `Phone` | | Telephone (digits only) | 20 |
| `Mail` | | Email (no validity verification) | 50 |
| `PaymentType` \* | | Payment type: `Ragil` (regular transaction/payments) \| `HK` (direct debit) \| `CreateToken` (create a token) | |
| `Amount` \* | | `Ragil`: total transaction amount. `HK`: amount to charge each month | |
| `Tashlumim` \* | | `Ragil`: number of payments to divide into (1 or more). `HK`: number of months to charge (leave blank for unlimited) | |
| `Day` | | HK only. Billing day of the month: 1–31 | |
| `Currency` | | 1 (Shekel) \| 2 (Dollar) | |
| `Groupe` | | Category | 100 |
| `Comment` | | Notes | 300 |
| `Param1` | | Free text, for callback only. Not saved in the Nedarim database. | |
| `Param2` | | Free text, for callback only. Not saved in the Nedarim database. | |
| `CallBack` | | Address to receive an update on your server when the transaction completes. To prevent spoofing, verify the request comes from our IP: `18.194.219.73` | |
| `CallBackMailError` | | Email to notify on callback send error. If blank, all institution contacts are notified. | |

---

### Transaction History

> **Note:** This request is limited to **20 requests per hour**.

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Manage3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `GetHistoryJson` | Credit history withdrawal |
| `Mosad` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password for pulling data from this institution (ask the office) |
| `LastId` | | Show results from this transaction number onward (excluding). Defaults to the first transaction for this institution. |
| `MaxId` | | Maximum number of results. Limiting is strongly recommended to speed up the process and prevent overload. Cannot exceed 2000 results; loop requests if needed, sending the `LastId` of the last pull each time. |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `Shovar` | Voucher number at the credit company |
| `Zeout` | ID number |
| `ClientName` | Customer name |
| `Adresse` | Full address |
| `Phone` | Telephone |
| `Mail` | Email |
| `Amount` | Transaction amount |
| `Currency` | Currency (Shekel 1 \| Dollar 2) |
| `TransactionTime` | Transaction date and time |
| `Confirmation` | Approval number |
| `LastNum` | Last 4 digits |
| `TransactionType` | Transaction type (regular / payments / standing order) |
| `Groupe` | Category |
| `Comments` | Notes |
| `Tashloumim` | Number of payments (to divide) |
| `FirstTashloum` | First payment amount (payments transactions only) |
| `NextTashloum` | Amount of remaining payments |
| `CallId` | Call ID (for donations via Nedarim Phone) |
| `AsRecord` | 1 if the donor left a recording |
| `MasofId` | Position number (for synagogue donations) |
| `MasofName` | Position name |
| `TransactionId` | Transaction number |
| `CompagnyCard` | Brand (per Shva rules) |
| `Solek` | Acquirer (per Shva rules) |
| `Tayar` | Whether it's a tourist transaction (not reliable) |
| `KabalaId` | Receipt number |
| `KevaId` | Standing order ID (for standing-order transactions) |

---

### Transaction Cancellation

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Manage3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `DeletedAllowedTransaction` | Transaction cancellation |
| `Mosad` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `TransactionId` \* | xxxxxxx | Transaction ID in Vows Plus |

**Response (JSON / TEXT)**

> **Note:** In some responses the information is returned as text rather than JSON.

| Parameter | Detail |
|---|---|
| `Result` | OK (success) \| Error (error) |
| `Message` | Error text (in some errors the text appears without an additional parameter) |

---

### Transaction Credit (Refund)

- Per Income Tax guidelines, nonprofits must avoid partial crediting. To cancel a transaction, the **entire amount** must be canceled in full.
- The original transaction receipt cannot be canceled; the system issues a negative receipt in addition to the original.
- In payment transactions, the system credits the number of payments shown in the original transaction.
- A credit cannot be made twice, even for a partial credit.

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Manage3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `RefundTransaction` | Transaction credit |
| `Mosad` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `TransactionId` \* | xxxxxxx | Transaction ID in Vows Plus |
| `RefundAmount` \* | xxxxxxx | Amount to be credited |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `Result` | OK (success) \| Error (error) |
| `Message` | Error text |

---

### Callback / Webhook System

**How it works**

- After each credit transaction and/or the establishment of a standing order, you can receive an update on your servers.
- Send a URL to customer service and indicate whether to set it up for regular transactions and/or standing orders.
- The system is active for **credit only**, not banking.
- Data is sent via **POST** as **`application/json`**.
- If a transmission fault occurs, you'll be notified by email.
- The system will not retry transmission (only one attempt).
- The system is updated from time to time and new parameters are added. Existing parameter names will not change, but the order of parameters may change.

**Response — Standard Transaction / Payments (JSON)**

| Parameter | Detail |
|---|---|
| `TransactionId` | Transaction ID in Vows Plus |
| `ClientId` | Customer number (for users registered at synagogue locations) |
| `Zeout` | ID number |
| `ClientName` | Name |
| `Address` | Address |
| `Phone` | Phone |
| `Mail` | Email |
| `Amount` | Total transaction amount |
| `Currency` | Currency (1 Shekel \| 2 Dollar) |
| `TransactionTime` | Transaction date |
| `Confirmation` | Approval number from Shva. If absent, it's a temporary transaction. |
| `LastNum` | Last 4 digits |
| `Tokef` | Validity |
| `TransactionType` | Type (regular / payments / cash) |
| `Band` | Category |
| `Comments` | Notes |
| `Tashlumim` | Number of payments |
| `FirstTashloum` | First payment amount |
| `MosadNumber` | Nedarim Plus Institution Number |
| `CallId` | Phone caller ID (Vows Phone) |
| `MasofId` | Position number where the transaction was made. `Online` if not at a position. |
| `Shovar` | Voucher number from Shva |
| `CompagnyCard` | Brand number |
| `Solek` | Acquirer number |
| `Tayar` | 1 tourist transaction \| 0 regular (not recommended to rely on) |
| `Makor` | Transaction source |
| `KevaId` | Standing order ID (if payment is for a standing order) |
| `DebitIframe` | 1 (transaction via Nedarim iframe) \| 0 (regular transaction) |
| `ReceiptCreated` | Whether a receipt was created |
| `ReceiptData` | Link to the receipt document |
| `ReceiptDocNum` | Receipt ID |

**Response — Credit Standing Order Establishment (JSON)**

| Parameter | Detail |
|---|---|
| `KevaId` | Standing order ID |
| `ClientId` | Customer number (for users registered at synagogue positions) |
| `Zeout` | ID number |
| `ClientName` | Name |
| `Address` | Address |
| `Phone` | Phone |
| `Mail` | Email |
| `Amount` | Amount to charge each month |
| `Currency` | Currency (1 Shekel \| 2 Dollar) |
| `NextDate` | First billing date |
| `LastNum` | Last 4 digits |
| `Tokef` | Validity |
| `Band` | Category |
| `Comments` | Notes |
| `Tashlumim` | Number of months to bill (blank = unlimited) |
| `MosadNumber` | Institution number |
| `MasofId` | Position number where the instruction was established |
| `DebitIframe` | 1 (established via Nedarim iframe) \| 0 (regular) |

---

### Export Transaction History to Excel

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Manage3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `GetHistoryCSV` | Export transaction history to Excel |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `From` \* | `dd/mm/yyyy` | Export transactions from this date |
| `To` \* | `dd/mm/yyyy` | Export transactions up to this date |
| `ToMail` | | Email the Excel file: 1 (send to email) \| 0 (export now) |

---

### Export Transaction History to Excel — Business

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Manage3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `GetHistoryCSVAsakim` | Export transaction history to Excel — Business |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `From` \* | `dd/mm/yyyy` | Export transactions from this date |
| `To` \* | `dd/mm/yyyy` | Export transactions up to this date |
| `ToMail` | | Email the Excel file: 1 (send to email) \| 0 (export now) |

---

### Export Transaction History to Excel — Business 2

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Manage3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `GetHistoryCSVAsakim2` | Export transaction history to Excel — Business 2 |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `From` \* | `dd/mm/yyyy` | Export transactions from this date |
| `To` \* | `dd/mm/yyyy` | Export transactions up to this date |
| `ToMail` | | Email the Excel file: 1 (send to email) \| 0 (export now) |

---

### Export Transaction History to Excel — APT

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Manage3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `GetHistoryCSVApt` | Export transaction history to Excel — APT |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `From` \* | `dd/mm/yyyy` | Export transactions from this date |
| `To` \* | `dd/mm/yyyy` | Export transactions up to this date |
| `ToMail` | | Email the Excel file: 1 (send to email) \| 0 (export now) |

---

## Bit Clearing — Vows Plus

**How it works**

1. You contact Nedarim and establish the deal.
2. You receive a link to switch to Bit. (It's recommended to display it on the Nedarim payment page, e.g. `https://nedar.im/5773837`.)
3. If you supplied a callback, you'll receive a server update after the donor completes the transaction in the Bit app.
4. To show the customer a payment confirmation, you must use a callback.
5. During setup, send a unique identifier in `Param2`, then poll your server until the transaction with that unique parameter is received.
6. If the institution is connected to a crowdfunding platform (Kuzmatch / Charidi / LiveRaiser, etc.), Nedarim will not notify the platform of a new transaction, to prevent double updates — unless you complete the transaction without a `CallBack` parameter. (If there is a callback, Nedarim assumes you handle pushing information to your platform yourself and performs no additional updates.)

**API address (POST):** `https://matara.pro/nedarimplus/V6/Files/WebServices/DebitBit.aspx`

**Request (FORM)**

| Parameter | Value | Detail | Max chars |
|---|---|---|---|
| `Action` \* | `CreateTransaction` | Create Bit transaction | |
| `Mosad` \* | xxxxxxx | Nedarim Plus Institution Number (7 digits) | 7 |
| `ApiValid` \* | xxxxxxx | Verification text (request from the office) | 10 |
| `Zeout` | | ID number | 10 |
| `ClientName` \* | | Last name and first name | 50 |
| `Street` | | Street | 100 |
| `City` | | City | 100 |
| `Phone` \* | | Telephone (digits only) | 20 |
| `Mail` | | Email (no validity verification) | 50 |
| `Amount` \* | | Amount | |
| `Band` | | Category | 100 |
| `Comment` | | Notes | 300 |
| `Param2` | | Free text. For callback only. | 100 |
| `UrlSuccess` | | Link to thank-you page at transaction end | |
| `UrlFailure` | | Link to failure page at transaction end | |
| `CallBack` | | Address to receive a server update at transaction end. To prevent spoofing, verify the request comes from our IP: `18.194.219.73`. **If you use a transaction-level callback, you will not get updates** — neither to matching systems (if connected) nor to an institution-level callback (if defined). | 1000 |

---

## Credit Card Standing Orders

### All Standing Orders — As Shown in the Interface

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Manage3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `GetKevaNew` | Withdraw standing orders — credit |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |

**Response (JSON) — Totals**

| Parameter | Detail |
|---|---|
| `TotalMonth` | Monthly total (active) in shekels |
| `TotalMonth2` | Monthly total (active) in dollars |
| `TotalYear` | Total revenue forecast for the next 12 months — shekels |
| `TotalYear2` | Total revenue forecast for the next 12 months — dollars |

**Response — `data` (standing order list)**

| Field | Detail |
|---|---|
| `DT_RowId` | Direct debit ID |
| `2` | Full name |
| `3` | Full address and telephone |
| `4` | Amount to be charged |
| `5` | Category |
| `6` | Notes |
| `7` | Balance of charges |
| `8` | Number of charges made |
| `9` | Next billing date |
| `10` | Error while attempting to charge |
| `11` | Last 4 digits of the credit card |
| `12` | Validity (e.g. `1225` — first 2 digits month, last 2 year) |
| `14` | 1 \| There is a donor card in the system |

---

### List of Standing Orders

> **Note:** This request is limited to **20 requests per hour**.

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Manage3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `GetKevaJson` | Withdraw standing order transaction history — credit |
| `Mosad` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `LastId` | | Show results from this transaction number onward (excluding). Defaults to the first transaction. |
| `MaxId` | | Maximum results. Limiting recommended. Max 2000; loop with `LastId` if needed. |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `Zeout` | ID number |
| `ClientName` | Customer name |
| `Adresse` | Full address |
| `City` | City |
| `Phone` | Telephone |
| `Mail` | Email |
| `Amount` | Transaction amount |
| `Currency` | Currency (Shekel 1 \| Dollar 2) |
| `Itra` | Balance of charges |
| `Success` | Charges made |
| `LastNum` | Last 4 digits |
| `CreationDate` | Date of establishment |
| `NextDate` | Next billing date |
| `ErrorText` | Rejection error details |
| `Band` | Category |
| `Comments` | Notes |
| `MasofId` | Position number (for synagogue donations) |
| `Tokef` | Validity |
| `Enabled` | 1 active \| 0 inactive |
| `KevaId` | Vows Plus direct debit ID |

---

### Withdraw a Single Standing Order Detail

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Manage3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `GetKevaId` | Withdraw standing order details |
| `MosadId` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `KevaId` | xxxxxxx | Standing order ID |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `KevaId` | Direct debit ID |
| `KevaStatus` | Status: 1 (Active) \| 2 (Frozen) \| 3 (Deleted) |
| `KevaZeout` | ID number |
| `Name` | Customer name |
| `SpringAddress` | Full address |
| `KevaCity` | City |
| `KevaPhone` | Phone |
| `KevaMail` | Email |
| `KevaGroup` | Category |
| `KevaAvour` | Remark |
| `KevaAmount` | Monthly payment amount |
| `KevaCurrency` | Currency (Shekel 1 \| Dollar 2) |
| `KevaTashlumim` | Balance of charges |
| `KevaSuccess` | Charges made |
| `CreatedDate` | Date of establishment |
| `KevaAsToremCard` | Does the donor have a donor card? |
| `KevaObservation` | System notes |
| `NextDate` | Next billing date |
| `KevaFrequency` | Collection frequency: 1 (monthly) \| 2 (weekly) \| 3 (reminder) |
| `KevaLastNum` | Last 4 digits of the credit card |
| `KevaTokef` | Credit card expiration date |
| `KevaCVV` | CVV (3 digits on the back of the card) |
| `TotalHistoryAmount` | Amount charged so far |
| `HistoryCount` | Number of times charged/attempted |

**Response — `HistoryData` (billing history)**

| Parameter | Detail |
|---|---|
| `ID` | Action status: 1 (charged successfully) \| 2 (refused) \| 3 (canceled) |
| `Amount` | Billing amount. Blank if canceled/declined. A ❗ indicates no receipt was issued for the transaction. |
| `Date` | Billing date / billing attempt |
| `Name` | In whose name the charge was made |
| `LastNum` | Last 4 digits. Blank if canceled/declined. |
| `TransactionId` | Vows Plus billing ID. Blank if canceled/declined. |

---

### Making (Editing) a Standing Order

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Manage3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `UpdateKevaNew` | Edit standing order |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `KevaId` \* | xxxxxxx | Standing order ID |
| `Zeout` | | ID number |
| `ClientName` | | Customer name |
| `Address` | | Address |
| `City` | | City |
| `Phone` | | Phone |
| `Mail` | | Email |
| `Tashlumim` | | Balance of charges |
| `Groupe` | | Category |
| `Avour` | | Remark |
| `NextDate` \* | | Next billing date |
| `Frequency` | | Collection frequency: 1 (monthly — default) \| 2 (weekly) \| 3 (reminder) |
| `Amount` \* | | Amount to charge |
| `CreditCard` \* | | Credit card number: send either the last 4 digits of the card already saved on this standing order, or a new card number |
| `Tokef` \* | | Credit card expiration: month and year, e.g. `12/25` |
| `CVV` | | 3 digits on the back of the card |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `Result` | OK (success) \| Error (error) |
| `Message` | Error text \| success comes without text |

---

### Collecting a Single Payment from an Existing Standing Order (Credit)

**API address (POST):** `https://matara.pro/nedarimplus/Reports/Manage3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `TashlumBodedNew` | Collect a single payment from an existing standing order |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution Number (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `Currency` \* | | 1 (Shekel) \| 2 (Dollar) |
| `KevaId` \* | | Vows Plus direct debit ID |
| `Amount` \* | | Single charge amount |
| `Tashlumim` \* | | Payments (cannot be made in dollars) |
| `Band` | | Category (enter a category name to associate the transaction with it) |
| `Comments` | | Remark |
| `JoinToKevaId` | | `NoJoin` (do not attach to standing order — enters as a regular transaction, not in the standing order history) \| `Join` (records in standing order history; does **not** affect payment balance, next billing date, etc. — only adds one transaction to the "Executed" column) |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `Status` | OK (success) \| Error (error) |
| `Message` | Error text |

---

### Deleting a Standing Order (Credit)

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Manage3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `DeleteKeva` | Delete standing order |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `KevaId` \* | xxxxxxx | Vows Plus standing order ID |

**Response (TEXT):** `OK` (success) \| errors are returned as text only.

---

### Freezing a Standing Order (Credit)

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Manage3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `DisableKeva` | Freeze standing order |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `KevaId` \* | xxxxxxx | Vows Plus direct debit ID |

**Response (TEXT):** `OK` (success) \| errors are returned as text only.

---

### Activating a Frozen Standing Order (Credit)

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Manage3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `EnableKevaNew` | Activate a frozen standing order |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `KevaId` \* | xxxxxxx | Vows Plus direct debit ID |

**Response (TEXT):** `OK` (success) \| errors are returned as text only.

---

### Export Standing Orders to Excel (Credit)

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Manage3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `GetKevaCSV` | Export credit standing orders to Excel |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `ToMail` | | Email the Excel file: 1 (send to email) \| 0 (export now) |

---

### Export Standing Orders to Excel — Business (Credit)

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Manage3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `GetKevaCSVAsakim` | Export credit standing orders to Excel |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `ToMail` | | Email the Excel file: 1 (send to email) \| 0 (export now) |

---

### Export Refusals in Standing Orders to Excel (Credit)

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Manage3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `GetErrorLogsCSV` | Export refusals in credit standing orders to Excel |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `ToMail` | | Email the Excel file: 1 (send to email) \| 0 (export now) |

---

## Banker

### Create a New Standing Order

> After the transaction completes, a digitally signed form is emailed to you. The form must be faxed to the donor's bank branch.

**API address (POST):** `https://matara.pro/nedarimplus/Reports/Masav3.aspx`

**Request (FORM)**

| Parameter | Value | Detail | Max chars |
|---|---|---|---|
| `Action` \* | `NewMasavKeva` | Create a new standing order | |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) | 7 |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) | 10 |
| `ClientName` \* | | Full name | 50 |
| `ClientAdresse` | | Full address | 50 |
| `ClientZeout` \* | | ID card | 9 |
| `ClientPhone` | | Phone | 10 |
| `ClientMail` | | Email | |
| `Bank` \* | | Bank number | 2 |
| `Agency` \* | | Branch number | 3 |
| `Account` \* | | Account number | 9 |
| `NextDate` \* | | Collection day (1 / 5 / 10 / 15 / 20 / 25 / 28) | 2 |
| `Amount` \* | | Amount to charge (monthly) | |
| `Tashlumim` | | Balance of charges. Leave blank for unlimited charges. | |
| `Groupe` | | Category | 200 |
| `Comments` | | Remark | 300 |
| `Status` \* | | 1 (Form creation — digital signature) \| 2 (Physical form signed) \| 3 (Already approved by the bank) | |
| `SignImage` | | When `Status=1`, a signature file in base64 must be sent. | |
| `AjaxId` \* | | Idempotency guard against double-submission. Send the request timestamp in milliseconds (e.g. JavaScript `Date.now()`). | |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `Result` | OK (success) \| Error (error) |
| `Message` | Error text |
| `ID` | Standing order ID |
| `ClientName` | Customer name |
| `MasofName` | Name of the position where the transaction was made |
| `AsSign` | Was a signature received: 1 (received) \| 0 (not received) |
| `Account` | Bank account details |
| `NextDate` | Upcoming billing date |
| `Band` | Category |
| `Amount` | Amount to charge each month |
| `Tashlumim` | Number of payments remaining |
| `Alert` | Institution name |
| `FaxTo` | The bank's fax number |

---

### Editing an Existing Standing Order

**API address (POST):** `https://matara.pro/nedarimplus/Reports/Masav3.aspx`

**Request (FORM)**

| Parameter | Value | Detail | Max chars |
|---|---|---|---|
| `Action` \* | `EditMasavKeva` | Edit an existing standing order | |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) | 7 |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) | 10 |
| `KevaId` \* | | Bank direct debit ID | |
| `ClientName` \* | | Full name | 50 |
| `ClientAdresse` | | Full address | 50 |
| `ClientZeout` \* | | ID card | 9 |
| `ClientPhone` | | Phone | 10 |
| `ClientMail` | | Email | |
| `NextDate` \* | | Collection day (1 / 5 / 10 / 15 / 20 / 25 / 28) | 2 |
| `Amount` \* | | Amount to charge (monthly) | |
| `Tashlumim` | | Balance of charges. Leave blank for unlimited charges. | |
| `Band` | | Category | 200 |
| `Comments` | | Remark | 300 |
| `Bank` \* | | Bank number | 2 |
| `Agency` \* | | Branch number | 3 |
| `Account` \* | | Account number | 9 |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `Result` | OK (success) \| Error (error) |
| `Message` | Error text |
| `ID` | Standing order ID |
| `ClientName` | Customer name |
| `MasofName` | Name of the position where the transaction was made |
| `AsSign` | Was a signature received: 1 (received) \| 0 (not received) |
| `Account` | Bank account details |
| `NextDate` | Upcoming billing date |
| `Groupe` | Category |
| `Amount` | Amount to charge each month |
| `Tashlumim` | Number of payments remaining |

---

### Pulling the List of Standing Orders

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Masav3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `GetMasavKevaNew` | Pull the list of standing orders |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `Result` | Error |
| `Message` | Error text |

**Response — `data` (standing order list)**

| Field | Detail |
|---|---|
| `DT_RowId` | Direct debit ID |
| `2` | Customer name |
| `3` | Bank account details |
| `4` | Next charge |
| `5` | Balance of charges |
| `6` | Amount to charge (monthly) |
| `7` | Category |
| `8` | Remark |

---

### Withdrawing Standing Order History

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Masav3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `GetMasavHistoryNew` | Withdraw standing order history |
| `Mosad` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `From` \* | `dd/mm/yyyy` | Pull transactions from this date |
| `To` \* | `dd/mm/yyyy` | Pull transactions up to this date |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `Result` | OK (success) \| Error (error) |
| `Message` | Error text |
| `Maslul` | Track: blank (private) \| 3 (M.SH.G.B) |
| `TotalShidurim` | Total charges |
| `TotalAhzarot` | Total returns |
| `TotalAmlot` | Total fees |
| `TotalLezikuy` | Total net |

**Response — `data` (standing order list)**

| Field | Detail |
|---|---|
| `2` | Standing order ID |
| `3` | Customer name |
| `4` | Billing date |
| `5` | Amount |
| `6` | Movement type |
| `7` | Name of terminal (device) where the transaction was made |
| `8` | Category |

---

### Withdrawing Standing Order Data by Order ID

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Masav3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `GetMasavId` | Withdraw standing order data |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `MasavId` \* | xxxxxxx | Vows Plus direct debit ID |

**Response (JSON / TEXT)**

| Parameter | Detail |
|---|---|
| `Result` | OK (success) \| Error (error) |
| `Message` | Error text |
| `CodeMosad` | Institution code |
| `MasofName` | Name of the position where the transaction was made |
| `ClientName` | Customer name |
| `ClientAdresse` | Full address |
| `ClientZeout` | ID card |
| `ClientPhone` | Phone |
| `ClientMail` | Email |
| `Bank` | Bank |
| `Agency` | Branch |
| `Account` | Account |
| `BankData` | Bank information: branch address \| branch fax number |
| `Status` | Status code |
| `StatusText` | Status text |
| `NextDate` | Billing day of the month |
| `FullNextDate` | Next billing date (by billing day of the month) |
| `Amount` | Amount to pay each month |
| `Tashlumim` | Number of charges remaining |
| `Band` | Category |
| `Comments` | Remark |
| `AsSign` | Was a signature received: True (received) \| False (not received) |
| `MasavUpload` | Was the file uploaded: 2 (uploaded) \| 0 (not uploaded) |
| `Deleted` | Was the standing order deleted: 1 (deleted) \| 0 (not deleted) |

---

### Collecting a Single Payment from an Existing Standing Order (Bank)

- This transaction is recorded in the standing order history.
- It does **not** affect the payment balance, next billing date, etc.

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Masav3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `MasavBoded` | Collect a single payment |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `MasavId` \* | xxxxxxx | Vows Plus standing order ID |
| `Amount` \* | xxxxxxx | Amount to collect (in shekels) |
| `Date` \* | `dd/mm/yyyy` | Collection date |
| `AjaxId` | | Idempotency guard against double-submission. Send the request timestamp in milliseconds (e.g. JavaScript `Date.now()`). |

**Response (TEXT)**

| Parameter | Detail |
|---|---|
| `Result` | OK (success) \| Error (error) |
| `Message` | Error text |

---

### Change Status — Activate a Standing Order

> **Note:** Activating a standing order that has not been confirmed by the bank will cause unnecessary return fees.

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Masav3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `SetMasavStatus` | Activate standing order |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `MasavId` \* | xxxxxxx | Vows Plus standing order ID |
| `StatusNumber` \* | 1 | Status code to activate |
| `Comments` \* | `אני מאשר` | Mandatory; must contain the text "אני מאשר" ("I confirm") |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `Result` | OK (success) \| Error (error) |
| `Message` | Error text |

---

### Change Status — Freeze a Standing Order

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Masav3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `SetMasavStatus` | Freeze standing order |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `MasavId` \* | xxxxxxx | Vows Plus direct debit ID |
| `StatusNumber` \* | 7 | Status code to freeze |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `Result` | OK (success) \| Error (error) |
| `Message` | Error text |

---

### Change Status — Form Sent to the Bank

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Masav3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `SetMasavStatus` | Form sent to the bank |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `MasavId` \* | xxxxxxx | Vows Plus direct debit ID |
| `StatusNumber` \* | 4 | Status code: form sent to the bank |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `Result` | OK (success) \| Error (error) |
| `Message` | Error text |

---

### Change Status — Rejected by the Bank

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Masav3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `SetMasavStatus` | Rejected by the bank |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `MasavId` \* | xxxxxxx | Vows Plus standing order ID |
| `StatusNumber` \* | 10 | Status code |
| `Comments` \* | | Mandatory; specify the reason for rejection by the bank |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `Result` | OK (success) \| Error (error) |
| `Message` | Error text |

---

### Change Collection Month

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Masav3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `SetMasavStatus` | Change collection month |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `MasavId` \* | xxxxxxx | Vows Plus direct debit ID |
| `StatusNumber` \* | | 8 (roll back to a previous month) \| 9 (postpone to the next month — to delay the collection date) |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `Result` | OK (success) \| Error (error) |
| `Message` | Error text |

---

### Sending a Standing Order File to the Bank's Fax

> **Note:** Each fax costs 1.5 NIS.

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Masav3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `SendMasavPdf` | Send standing order file to the bank's fax |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `MasavId` \* | xxxxxxx | Vows Plus direct debit ID |

**Response (TEXT)**

| Parameter | Detail |
|---|---|
| `Result` | OK (success) \| Error (error) |
| `Message` | Error text |

---

### Export Standing Order History to Excel (Bank)

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Masav3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `GetMasavHistoryCSVNew` | Export standing order history to Excel |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `From` \* | `dd/mm/yyyy` | Export from this date |
| `To` \* | `dd/mm/yyyy` | Export up to this date |
| `ToMail` | | Email the Excel file: 1 (send to email) \| 0 (export now) |

---

### Export Standing Orders to Excel (Bank)

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Masav3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `GetMasavCSV` | Export standing orders to Excel |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `ToMail` | | Email the Excel file: 1 (send to email) \| 0 (export now) |

---

## External Revenues

### External Input (Add / Edit / Delete)

**API address (POST):** `https://matara.pro/nedarimplus/Reports/Manage3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `SaveAchnasot` | External income retention |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `ID` | | External income transaction ID. Required to edit or delete income. |
| `Type` \* | | Income type: 1 (Cash) \| 2 (Check) \| 3 (Bank transfer) \| 4 (External credit) \| 5 (External Masav) \| 6 (Other) |
| `Zeout` \* | | Donor ID card |
| `Amount` \* | | Transaction amount |
| `Date` \* | `dd/mm/yyyy` | Date |
| `Currency` \* | | 1 (Shekel) \| 2 (Dollar) |
| `Band` | | Category |
| `Avour` | | Notes |
| `Asmahta` | | Varies by income type — Check (2): check number; Bank transfer (3): reference; External credit (4): card type (Isracard / Visa / MasterCard / American Express, etc.); Other (6): payment type (free text). Mandatory depending on income type. |
| `Asmahta2` | | Varies by income type — Check (2) / Bank transfer (3) / External Masav (5): account details (bank-branch-account), e.g. `00-000-000000`; External credit (4): last 4 digits; Other (6): reference. Mandatory depending on income type. |
| `SpecialName` | | Name for receipt (to produce a receipt with a different name than the donor card). Ignored if the receipt is generated via the donor's Annual Generation button. |
| `SpecialAddress` | | Address for receipt (to produce a receipt with a different address than the donor card). Ignored if generated via Annual Generation. |
| `Delete` | | Whether to delete the entry: true (delete) \| false (do not delete) |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `Result` | OK (success) \| Error (error) |
| `Message` | Error text |
| `ID` | Income ID |
| `Mosad` | Nedarim Plus Institution ID |
| `Zeout` | Donor ID number |
| `TypeName` | Name of the income type |
| `Type` | Income type identifier (1 Cash \| 2 Check \| 3 Bank transfer \| 4 External credit \| 5 External Masav \| 6 Other) |
| `Amount` | Income amount |
| `Date` | Date |
| `Band` | Category |
| `Avour` | Remark |
| `SpecialName` | Receipt name |
| `SpecialAddress` | Receipt address |
| `Currency` | 1 (Shekel) \| 2 (Dollar) |
| `Asmahta` | (As above, varies by income type) |
| `Asmahta2` | (As above, varies by income type) |
| `Kabbalah` | If 0 appears, the transaction is marked as already having a receipt produced in another system |
| `InvoiceId` | EasyAccount receipt ID (for cases generated by EasyAccount) |

---

## Vows Card

### List of Families

**API address (GET):** `https://www.matara.pro/nedarimplus/Mechubad/Reports/ManageReports.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `GetClient_Table` | Pull a list of families |
| `Mosad` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `Result` | OK (success) \| Error (error) |
| `Message` | Error message |
| `Total` | In OK status only: total amount of money loaded on the families' cards |

**Response — `data` (list of families)**

| Parameter | Detail |
|---|---|
| `ClientId` | Family ID in the Nadir Card |
| `Zeout` | ID card |
| `FamilyName` | Last name |
| `FirstName` | First name |
| `Address` | Full address |
| `Phone` | Phone (up to 2 customer numbers, separated with `<br />`) |
| `Band` | Category |
| `External` | Family balance |
| `Tsad3Id` | Third party number |

---

### Family Card (Add / Edit / Delete)

**API address (GET):** `https://www.matara.pro/nedarimplus/Mechubad/Reports/ManageReports.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `SaveClientCard` | Edit family details |
| `Mosad` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `ClientId` | | Family ID in Vows Card. For editing or deleting a family. |
| `FamilyName` \* | | Last name |
| `FirstName` | | First name |
| `Zeout` | | ID card |
| `Address` | | Full address |
| `Phone1` | | Phone 1 |
| `Phone2` | | Phone 2 |
| `Email` | | Email |
| `Band` | | Category |
| `Comments` | | Remark |
| `Deleted` | | Whether to delete the family: 1 (delete) \| 0 (do not delete) |
| `Tsad3Id` | | Third party number |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `Result` | OK (success) \| Error (error) |
| `Message` | Error details. On add/update OK: the family ID added or edited. On delete OK: an empty parameter. |

---

### Pulling Family Data by ID

**API address (GET):** `https://www.matara.pro/nedarimplus/Mechubad/Reports/ManageReports.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `GetClientCard` | Pull family data by ID |
| `Mosad` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `ClientId` \* | | Family ID in Vows Card |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `Result` | OK (success) \| Error (error) |
| `Message` | Error text |
| `Zeout` | ID card |
| `FamilyName` | Last name |
| `FirstName` | First name |
| `Address` | Full address |
| `Phone1` | Phone 1 |
| `Phone2` | Phone 2 |
| `Email` | Email |
| `Band` | Category |
| `Comments` | Remark |
| `TotalFreeAmount` | Amount charged to the family |

**Response — `History` (transaction history)**

| Parameter | Detail |
|---|---|
| `HistoryId` | History line ID |
| `StoreName` | Store name |
| `Date` | Transaction date |
| `Amount` | Amount paid |
| `Comments` | Remark |
| `ProductId` | Product ID (relevant only to certain institutions; usually ignore) |

**Response — `Tlushim` (all uploads/recharges)**

| Parameter | Detail |
|---|---|
| `TlushId` | Loading ID |
| `KupaName` | Cashier who issued the recharge |
| `Date` | Recharge issuance date |
| `StoreName` | Store where charging is limited (mostly obsolete; relevant only for old uploads) |
| `LimitedStores` | Name of the group the upload is limited to |
| `Amount` | Original amount |
| `FreeAmount` | Amount remaining on the card ("Expired" if the recharge expired) |
| `Expiration` | Recharge expiration date |
| `ExpirationAmount` | Amount remaining that cannot be redeemed due to expiration |
| `ProductName` | Name of the product the loading is limited to (relevant only to certain institutions; usually ignore) |
| `Comments` | Remark |
| `AutoChargeId` | Cyclic charging identifier (if charged via cyclic charging) |

**Response — `AutoCharge` (cyclic charging)**

| Parameter | Detail |
|---|---|
| `ID` | Cyclic charging identifier |
| `Status` | 1 (Active) \| 2 (Frozen) |
| `Frequency` | 1 (Monthly Foreign) \| 2 (Monthly Hebrew) \| 3 (Weekly) |
| `FrequencyDay` | Day for charging — Foreign months: 1–28; Hebrew months: 1–29; Weekly: 1–7 |
| `FrequencyInterval` | Interval between charges — Foreign/Hebrew months: in months; Weekly: in weeks |
| `Amount` | Amount to reload each time |
| `Pulse` | Remaining loads to perform |
| `LimitedId` | Group ID for load restriction |
| `LimitedName` | Load limit group name |
| `ExpirationDays` | Number of days the recharge remains valid |
| `Campaign` | Campaign name |
| `Comments` | Remark |
| `NextCharge` | Foreign date of the next upload |
| `HebNextCharge` | Hebrew date of the next recharge |

**Response — `Cards` (magnetic cards)**

| Parameter | Detail |
|---|---|
| `CardId` | Magnetic card ID |
| `CardNumber` | Magnetic card number printed on the card |
| `MagneticCard` | Magnetic stripe card number |
| `AddedDate` | Date of card assignment to family |
| `RemovedDate` | Card disconnection date (if a value appears, the card is disconnected from the family) |

**Response — `Siruvim` (refusals)**

| Parameter | Detail |
|---|---|
| `StoreName` | Store name |
| `Date` | Refusal date |
| `Amount` | Amount |
| `Error` | Reason for refusal |

---

### Assigning a Magnetic Card to a Family (Add / Delete)

> Up to 3 cards can be assigned to a family.

**API address (GET):** `https://www.matara.pro/nedarimplus/Mechubad/Reports/ManageReports.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `SetClientMagneticCard` | Magnetic card storage |
| `Mosad` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `ClientId` \* | | Family ID in Vows Card |
| `MagneticCard` \* | | Card number / magnetic stripe |
| `CardId` | | Nadir Card ID. Leave blank to add a card; required only to delete a card. |
| `Remove` | | Delete the card? 1 (delete) \| 0 (do not delete — default) |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `Result` | OK (success) \| Error (error) |
| `Message` | Error text. If empty, the card was successfully deleted. |
| `CardId` | Magnetic card ID |
| `CardNumber` | Magnetic card number printed on the card |
| `MagneticCard` | Magnetic stripe card number |
| `AddedDate` | Date of card assignment to family |
| `RemovedDate` | Card disconnection date (always empty when deleting) |

---

### Adding a Family Recharge

**API address (GET):** `https://www.matara.pro/nedarimplus/Mechubad/Reports/ManageReports.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `AddTlush` | Add load to family |
| `Mosad` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `ClientId` \* | | Family ID in Vows Card |
| `Amount` \* | | Amount to load |
| `Expiration` | `dd/mm/yyyy` | Charge expiration date |
| `LimitedId` | | Store group ID for restriction |
| `Comments` | | Remark |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `Result` | OK (success) \| Error (error) |
| `Message` | Error details. On OK: the added loading ID. |

---

### Charging and Discharging

**API address (GET):** `https://www.matara.pro/nedarimplus/Mechubad/Reports/ManageReports.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `PrikatTlush` | Charging / discharging |
| `Mosad` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `TlushId` \* | | Nadir Card recharge ID |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `Result` | OK (success) \| Error (error) |
| `Message` | Error text |

---

### List of Stores in Agreement / Expired Agreement

**API address (GET):** `https://www.matara.pro/nedarimplus/Mechubad/Reports/ManageReports.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `GetStoresList` | Pull a list of stores |
| `Mosad` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `Result` | Error |
| `Message` | Error text |
| `StoreId` | Store ID |
| `StoreName` | Store name |
| `Enabled` | Is the agreement active: True (active) \| False (inactive) |

---

### Pulling Store Groups

**API address (GET):** `https://www.matara.pro/nedarimplus/Mechubad/Reports/ManageReports.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `GetLimitedStoresList` | Pull store groups |
| `MosadId` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `Result` | OK (success) \| Error (error) |
| `Message` | Error text |
| `ID` | Group ID |
| `ListName` | Group name |

**Response — `Stores` (list of stores)**

| Parameter | Detail |
|---|---|
| `StoreId` | Store ID |
| `StoreName` | Store name |

---

### Store Groups (Add / Edit / Delete)

**API address (GET):** `https://www.matara.pro/nedarimplus/Mechubad/Reports/ManageReports.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `SaveLimitedStores` | Group (Add / Edit / Delete) |
| `Mosad` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `ListName` \* | | Group name. When editing a group, the name cannot be changed. |
| `StoresId` \* | | Store IDs, comma-separated, e.g. `10,50,180` |
| `ListId` \* | | Group ID in Nadir Card. For editing or deleting a group. |
| `Delete` | | Whether to delete the group: 1 (delete) \| 0 (do not delete) |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `Result` | OK (success) \| Error (error) |
| `Message` | Error text |

---

### Making a Transaction (for Stores)

**API address (POST):** `https://matara.pro/NedarimPlus/Mechubad/ExternalPos.aspx`

**Request (FORM)**

| Parameter | Detail |
|---|---|
| `StoreId` \* | Store ID |
| `MagneticCard` \* | Magnetic stripe |
| `Amount` \* | Amount to redeem |
| `ForceDebit` | 1 (on) \| 0 (off). If the amount to charge is ₪500 but only ₪300 remains, the system charges ₪300 and does not show an insufficient-balance error. |
| `Comments` | Free field (max 300 chars). Usually for recording cash register / branch number for documentation and cross-checking. |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `Result` | OK (success) \| Error (error) |
| `Message` | Error text |
| `OperationId` | Unique identifier of the operation (in case of billing) |
| `Credit` | Current card balance (after redemption) |
| `KupaId` | Identifier of the institution that issued the card |
| `KupaName` | Name of the institution that issued the card |
| `DebitedAmount` | Amount actually charged |

**Example:**
```
https://matara.pro/NedarimPlus/Mechubad/ExternalPos.aspx?StoreId=1-m7sk0weoej&MagneticCard=000000000000000002=000000000025612788&Amount=5.5&Comments=Test
```

---

### Balance Inquiry (for Stores)

**API address (POST):** `https://matara.pro/NedarimPlus/Mechubad/ExternalPos.aspx`

**Request (FORM)**

| Parameter | Detail |
|---|---|
| `StoreId` \* | Store ID |
| `MagneticCard` \* | Magnetic stripe |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `Result` | OK (success) \| Error (error) |
| `Message` | Error text |
| `OperationId` | Unique identifier of the operation (in case of a charge) |
| `Credit` | Current card balance (after redemption) |
| `KupaId` | Identifier of the institution that issued the card |
| `KupaName` | Name of the institution that issued the card |

**Example:**
```
https://matara.pro/NedarimPlus/Mechubad/ExternalPos.aspx?StoreId=1-m7sk0weoej&MagneticCard=000000000000000002=000000000025612788
```

---

### Cancel a Transaction (for Stores)

**API address (POST):** `https://matara.pro/NedarimPlus/Mechubad/ExternalPos.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `CancelTransaction` | Transaction cancellation |
| `StoreId` \* | xxxx | Store ID |
| `OperationId` \* | xxxx | Transaction ID at this store |
| `Amount` \* | xxxx | Amount to cancel |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `Result` | OK (success) \| Error (error) |
| `Message` | Error text |

**Example:**
```
https://matara.pro/NedarimPlus/Mechubad/ExternalPos.aspx?Action=CancelTransaction&StoreId=1-m7sk0weoej&OperationId=123456&Amount=52
```

---

## Receipt System

### Generating a Receipt for a Credit Transaction

> When generating a receipt for a payment transaction, a receipt can be generated only from the first payment, not from the second payment onward.

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Tamal3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `TamalCreate` | Generate a receipt for credit transactions only |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `TransactionId` \* | xxxxxxx | Credit transaction ID in Vows Plus |
| `TamalType` \* | | Receipt type: 405 (donation receipt) \| 400 (regular receipt) \| 320 (tax invoice receipt). See dealer-type rules below. |

**Dealer-type rules (per EasyAccount configuration):**
- Licensed dealer: 400 (regular) \| 320 (tax invoice)
- Exempt dealer: 400 (regular)
- Ltd. Company: 400 (regular) \| 320 (tax invoice)
- Association: 400 (regular) \| 405 (donation)

**Response (TEXT):** `OK` (receipt issued successfully) \| error text.

---

### Generating a Centralized Receipt for a Standing Credit Instruction

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Tamal3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `CreateInvoice` | Receipt generation |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `ID` \* | xxxxxxx | Vows Plus direct debit ID |
| `Type` \* | `Keva` | Action type: Keva |
| `Tkufa` | `****` | If receipts haven't been produced for several years, specify which year to issue a receipt for. |
| `TamalType` \* | | Receipt type: 405 \| 400 \| 320 (see dealer-type rules above) |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `Result` | OK (success) \| Error (error) |
| `Message` | Error text |

---

### Centralized Receipt Generation for a Standing Bank Order

- The system generates a single receipt containing all transactions for which a receipt was not generated.
- **Caution!** Before producing receipts for standing bank orders, check the bank's website to ensure there have been no repeat standing orders.

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Tamal3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `CreateInvoice` | Receipt generation |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `ID` \* | xxxxxxx | Bank standing order ID in Vows Plus |
| `Type` \* | `Masav` | Action type: Masav |
| `Tkufa` | `****` | If receipts haven't been produced for several years, specify which year to issue for. |
| `TamalType` \* | | Receipt type: 405 \| 400 \| 320 (see dealer-type rules above) |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `Result` | OK (success) \| Error (error) |
| `Message` | Error text |

---

### Producing a Receipt for Income Outside of Nedarim Plus

> This API can create a receipt for external income: cash / check / bank transfer / external credit / external Masav / other payment method.

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Tamal3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `CreateInvoice` | Receipt generation |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `ID` \* | xxxxxxx | Transaction ID in Vows Plus |
| `Type` \* | `Achnasot` | Action type: Achnasot |
| `TamalType` \* | | Receipt type: 405 \| 400 \| 320 (see dealer-type rules above) |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `Result` | OK (success) \| Error (error) |
| `Message` | Error text |

---

### Centralized Receipt Generation by Donor Number

- An annual receipt can be issued only for a donor card with a valid ID number.
- The system generates a single receipt containing all transactions for which a receipt was not generated.
- The receipt includes all transactions (regular credit \| bank credit \| cash \| check \| bank transfer \| external credit \| external debit card \| other payment method).
- The receipt is created from the donor card details, **not** from the transactions themselves — make sure the donor's details are up to date.

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Tamal3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `CreateInvoice` | Receipt generation |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `ID` \* | xxxxxxx | Donor ID in Vows Plus |
| `Type` \* | `Torem` | Action type: Torem |
| `TamalType` \* | | Receipt type: 405 \| 400 \| 320 (see dealer-type rules above) |
| `Tkufa` | `****` | If receipts haven't been produced for several years, specify which year to issue for. |
| `Currency` | | If the donor has both shekel and dollar transactions: send 2 to issue the dollar transactions, 1 for the shekel transactions. |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `Result` | OK (success) \| Error (error) |
| `Message` | Error text |

---

### Withdrawing All Unproduced Receipts — by Period

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Tamal3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `CheckInvoiceTkufa` | Pull receipts pending production |
| `MosadId` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `Tkufa` \* | `yyyy/mm` | Annual: specify the desired year. Monthly: write month and year, e.g. `07/2024`. |
| `ToExcel` | | Whether to export a detailed Excel file: 1 (export) |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `Result` | OK (success) \| Error (error) |
| `Message` | Error text |
| `Ragil` | List of regular credit transaction IDs |
| `Keva` | List of credit standing order IDs |
| `Masav` | List of bank standing order IDs |
| `Achnasot` | List of external transaction IDs |

---

### Show Link to View Receipt

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Tamal3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `ShowInvoice` | Show receipt |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `TransactionId` | xxxxxxx | For a credit transaction, send the transaction ID here. **Note:** a direct debit ID cannot be sent, only the transaction ID. |
| `AchnasotId` | xxxxxxx | For other income from an external source, send the transaction ID here. **Note:** receipts for bank transactions cannot be displayed — log into EasyAccount to view bank receipts. |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `Result` | OK (success) \| Error (error) |
| `Message` | Error text \| link to view the receipt |

---

### Cancellation of Acceptance (Cancel Receipt)

- **Note:** Do not use this option if you have already manually canceled the receipt.
- Cancellation of a receipt is considered production of a document.

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Tamal3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `CancelInvoice` | Cancel receipt |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `TransactionId` | xxxxxxx | For a credit transaction, send the transaction ID here. **Note:** a direct debit ID cannot be sent, only the transaction ID. |
| `AchnasotId` | xxxxxxx | For other income from an external source, send the transaction ID here. **Note:** receipts for bank transactions cannot be canceled — log into EasyAccount to cancel bank receipts. |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `Result` | OK (success) \| Error (error) |
| `Message` | Error text |

---

## Donor Management

### Export to Excel — List of Donors

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Manage3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `GetTormimCsv` | Export list of donors to Excel |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `ToMail` | | Email the Excel file: 1 (send to email) \| 0 (export now) |

---

## Vows of Vow

### Export to Excel — Data Report (Vows Phone)

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Manage3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `GetCallReport` | Vows Phone — export data report to Excel |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `Type` \* | | `Day` (today) \| `Month` (current month) \| `LastMonth` (previous month) \| `Custom` (date range) |
| `FromDate` | `dd/mm/yyyy` | Export transactions from this date. Required when `Type=Custom`. |
| `ToDate` | `dd/mm/yyyy` | Export transactions up to this date. Required when `Type=Custom`. |

---

## Matching System

### Adding Offline Donations

> Only available when there is an active matching system.

**API address (POST):** `https://matara.pro/nedarimplus/Reports/Manage3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `MatchingOffLine` | Add OFFLINE donation |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution Number (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `MatrimId` \* | | Fundraiser (lift) ID. You can register a name instead of an ID, but this won't work if multiple lifts share the same name. |
| `ClientName` \* | | Donor name |
| `Amount` \* | | Amount before doubling. Enter a negative amount to lower the target. |
| `Comments` | | Notes |
| `AjaxId` | | Idempotency guard against double-submission. Send the request timestamp in milliseconds (e.g. JavaScript `Date.now()`). |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `Status` | OK (success) \| Error (error) |
| `Message` | Error text \| success text (operation completed successfully) |

---

### Export to Excel — Fundraisers (Lifts)

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Manage3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `ExportMatchingMatrix` | Matching system — export of lifts |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |

---

### Export to Excel — Offline Donations

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Manage3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `ExportMatchingOffLine` | Matching system — OFFLINE donations Excel export |
| `MosadNumber` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |

---

## Messages to the Collector

### Message History for the Collector

**API address (GET):** `https://matara.pro/nedarimplus/Reports/Manage3.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `GetUserMessage` | Pull message history to the collector |
| `Mosad` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |

**Response (JSON)**

| Parameter | Detail |
|---|---|
| `ID` | Row ID in Vows Plus |
| `SendDate` | Date the message was sent |
| `ClientName` | Name of the message sender |
| `ClientAdresse` | Full address including city |
| `ClientPhone` | Phone |
| `ClientId` | Donor ID in Vows Plus |
| `ClientZeout` | Message sender ID |
| `Message` | Message body |
| `Readed` | Was the message read by the collector? |
| `MasofName` | Name of the terminal the message was sent from |
| `RecordFile` | Recording ID. If the donor sent a voice message, a recording ID appears here (only present if there is a recording). Listen at: `https://images.matara.pro/IVR/Record/0_{RecordFile}.wav` |

---

## Forms Department

### Pulling Form Data

- To connect a form to this service, contact the Forms Department. Connecting involves a one-time cost per the Forms Department price list.
- Data display varies from form to form depending on the field names defined in it.
- If the form is changed by the Forms Department, ensure this function is updated accordingly.

**API address (GET):** `https://matara.pro/nedarimplus/Forms/Manage.aspx`

**Request (FORM)**

| Parameter | Value | Detail |
|---|---|---|
| `Action` \* | `GetJson` | Retrieve form data |
| `Mosad` \* | xxxxxxx | Nedarim Plus Institution ID (7 digits) |
| `ApiPassword` \* | xxxxxxx | API password (ask the office) |
| `TofesId` \* | xxxxxxx | Nedarim Plus Form ID |
| `LastId` | | Show results from this form number onward (excluding). Defaults to the first form submitted under that form. |
| `MaxId` | | Maximum results. Limiting recommended. Max 500; loop with `LastId` if needed. |
