# Offline Musical Instrument POS — System Design

## 1. Project Overview

This project is an offline-first Point of Sale (POS) system designed for a musical instrument and equipment retail business.

The system must remain fully usable without an Internet connection while providing reliable cloud synchronization and end-of-day backup when connectivity is available.

The system will support:

- Product and inventory management
- Point-of-sale transactions
- Payments and mixed payments
- Refunds
- Product exchanges
- Bulk product and stock imports
- Serial-number tracking for selected instruments
- Customer management
- Supplier management
- Warranty tracking
- Stock movement history
- User roles and permissions
- Audit logging
- Sales and inventory reports
- Automatic cloud synchronization
- Daily cloud backups
- Local recovery and disaster recovery

---

# 2. Core Architectural Principles

The system will follow these principles:

### 2.1 Offline-first

The POS must not depend on an active Internet connection to perform normal business operations.

Normal transactions occur against the local database first.

```text
Cashier
   ↓
Flutter POS
   ↓
Local SQLite Database
   ↓
Transaction completed
   ↓
Receipt printed
```

Synchronization with the cloud happens separately.

```text
Local Database
   ↓
Sync Queue
   ↓
Internet
   ↓
Backend API
   ↓
PostgreSQL
```

### 2.2 Transactions over direct state modification

Business events must be recorded as transactions rather than silently changing historical records.

For example, a refund must not modify or delete the original sale.

### 2.3 Stock ledger instead of direct stock editing

Inventory is represented by stock movements.

Examples:

```text
PURCHASE             +10
OPENING_STOCK        +100
BULK_IMPORT           +50
SALE                   -1
REFUND                +1
EXCHANGE_RETURN       +1
EXCHANGE_SALE         -1
DAMAGE                -1
ADJUSTMENT            -1
TRANSFER              -5
```

Current stock is derived from the movement ledger.

```text
Current Stock = SUM(all applicable stock movements)
```

A cached stock balance may be maintained for performance, but the movement ledger remains the authoritative history.

### 2.4 Immutable transaction history

Completed sales, refunds, exchanges, and stock movements should not be silently deleted.

Corrections should be represented by new transactions.

### 2.5 Cloud is for synchronization and recovery

The POS must continue working locally when the cloud is unavailable.

Cloud infrastructure provides:

- Synchronization
- Centralized data
- Disaster recovery
- Daily backups
- Optional remote reporting

---

# 3. High-Level Architecture

```text
                    ┌─────────────────────────────┐
                    │          CLOUD              │
                    │                             │
                    │        NestJS API           │
                    │             │               │
                    │             ▼               │
                    │        PostgreSQL           │
                    │             │               │
                    │             ▼               │
                    │     Backup/Object Storage    │
                    └─────────────▲───────────────┘
                                  │
                           Internet / Sync
                                  │
┌─────────────────────────────────┴──────────────────────────────┐
│                         FLUTTER POS                            │
│                                                               │
│  UI → State Management → Domain Logic → Repositories          │
│                                                   │            │
│                                                   ▼            │
│                                                 Drift          │
│                                                   │            │
│                                                   ▼            │
│                                                 SQLite         │
│                                                               │
│  Sync Engine → Sync Queue → Backend                           │
└───────────────────────────────────────────────────────────────┘
```

---

# 4. Recommended Technology Stack

## 4.1 Desktop Client

### Flutter + Dart

Flutter is the recommended framework for the POS application.

Reasons:

- Windows desktop support
- Good performance
- Touchscreen support
- Barcode scanner integration
- Receipt printer integration
- SQLite support
- Future Android support
- Single codebase

---

## 4.2 Local Database

### SQLite

SQLite will be the local source used by the POS during normal operation.

### Drift

Drift will provide the typed database layer between Flutter and SQLite.

Architecture:

```text
Flutter UI
   ↓
Riverpod
   ↓
Controllers / Domain Services
   ↓
Repositories
   ↓
Drift
   ↓
SQLite
```

---

## 4.3 State Management

### Riverpod

Riverpod will be used for:

- Application state
- Authentication state
- Cart state
- Inventory state
- Sales state
- Sync state
- Settings
- Reactive database queries

---

## 4.4 Backend

### NestJS + TypeScript

NestJS will provide:

- REST API
- Authentication
- Authorization
- Synchronization
- Product management
- Sales synchronization
- Inventory synchronization
- Import processing
- Reporting
- Audit functionality
- Backup coordination

Potential API domains:

```text
/auth
/users
/products
/categories
/inventory
/sales
/payments
/refunds
/exchanges
/customers
/suppliers
/imports
/sync
/reports
/backups
/audit
```

---

## 4.5 Cloud Database

### PostgreSQL

PostgreSQL will be the primary cloud database.

It is appropriate because the system contains strongly related entities:

```text
Sale
 ├── Sale Items
 ├── Payments
 └── Customer

Product
 ├── Stock Movements
 ├── Serial Numbers
 └── Supplier
```

---

## 4.6 Cloud Storage

Use S3-compatible object storage for:

- Database backups
- Exported reports
- Import files
- Error reports
- Other business documents where required

---

## 4.7 Deployment

Use Docker for backend deployment.

Recommended production structure:

```text
Internet
   ↓
Nginx / Caddy
   ↓
NestJS API
   ↓
PostgreSQL
   ↓
Object Storage
```

HTTPS must be enabled for all cloud communication.

---

# 5. Flutter Application Structure

Recommended project structure:

```text
lib/
│
├── core/
│   ├── database/
│   ├── networking/
│   ├── sync/
│   ├── errors/
│   ├── security/
│   └── utils/
│
├── features/
│   ├── auth/
│   ├── dashboard/
│   ├── products/
│   ├── inventory/
│   ├── sales/
│   ├── payments/
│   ├── refunds/
│   ├── exchanges/
│   ├── customers/
│   ├── suppliers/
│   ├── imports/
│   ├── reports/
│   ├── users/
│   └── settings/
│
└── main.dart
```

Each major feature should be separated into:

```text
data/
domain/
presentation/
```

where appropriate.

---

# 6. Backend Structure

```text
src/
│
├── auth/
├── users/
├── products/
├── categories/
├── inventory/
├── sales/
├── payments/
├── refunds/
├── exchanges/
├── customers/
├── suppliers/
├── imports/
├── sync/
├── reports/
├── backups/
├── audit/
│
├── database/
├── common/
└── main.ts
```

---

# 7. Core Database Entities

The exact schema will be finalized before implementation, but the core entities are:

```text
users
roles
permissions

products
categories
brands
suppliers

customers

sales
sale_items
payments

refunds
refund_items

exchanges

stock_movements
stock_adjustments

serial_numbers
warranties

import_batches
import_rows

sync_queue
audit_logs

settings
devices
```

---

# 8. Products

Suggested `products` fields:

```text
id
sku
barcode
name
category_id
brand_id
description
cost_price
selling_price
tax_rate
reorder_level
tracking_type
is_active
created_at
updated_at
```

`tracking_type`:

```text
QUANTITY
SERIALIZED
```

Example:

```text
SKU: YAM-PSR-E473
Name: Yamaha PSR-E473
Category: Keyboards
Cost Price: 4500
Selling Price: 5800
Tracking Type: SERIALIZED
```

---

# 9. Categories

Examples:

```text
Keyboards
Guitars
Bass Guitars
Drums
Percussion
Microphones
Speakers
Amplifiers
Mixers
Effects
Cables
Accessories
Music Stands
```

Table:

```text
categories

id
name
description
created_at
updated_at
```

---

# 10. Customers

Customers are optional for ordinary walk-in sales.

Suggested fields:

```text
id
name
phone
email
address
created_at
updated_at
```

A sale may therefore either have:

```text
customer_id = NULL
```

or reference a customer.

---

# 11. Sales

Sales should be separated from sale items.

### `sales`

```text
id
receipt_number
customer_id
cashier_id
subtotal
discount
tax
total
payment_status
sale_status
created_at
updated_at
```

### `sale_items`

```text
id
sale_id
product_id
quantity
unit_price
discount
tax
subtotal
serial_number_id
```

A completed sale creates corresponding stock movements.

```text
SALE
quantity = -sold_quantity
```

---

# 12. Payments

Payments must be stored separately from sales.

### `payments`

```text
id
sale_id
payment_method
amount
reference
created_at
```

Supported methods:

```text
CASH
MOBILE_MONEY
CARD
BANK_TRANSFER
MIXED
```

Mixed payments can contain multiple payment records for one sale.

Example:

```text
Sale Total: GHS 2,625

Cash:          GHS 1,000
Mobile Money:  GHS 1,625
```

---

# 13. Inventory and Stock Ledger

The system must not rely on manually changing a product's stock quantity.

### `stock_movements`

```text
id
product_id
movement_type
quantity
reference_type
reference_id
user_id
reason
created_at
```

Movement types include:

```text
OPENING_STOCK
PURCHASE
BULK_IMPORT
SALE
REFUND
EXCHANGE_RETURN
EXCHANGE_SALE
DAMAGE
ADJUSTMENT
TRANSFER_IN
TRANSFER_OUT
```

Example:

```text
10 units received
       ↓
PURCHASE +10

1 unit sold
       ↓
SALE -1

1 unit refunded
       ↓
REFUND +1
```

Current stock:

```text
SUM(stock_movements.quantity)
```

---

# 14. Refund Architecture

A refund must never delete or rewrite the original sale.

Example:

```text
Original Sale #1001
Yamaha Guitar
GHS 2,500
```

Refund:

```text
Refund #2001
Original Sale: #1001
Yamaha Guitar
Quantity: 1
Refund Amount: GHS 2,500
```

Stock movement:

```text
REFUND
+1
```

The original sale remains intact.

Refunds should be permission-controlled and auditable.

---

# 15. Exchange Architecture

An exchange is represented as a return plus a replacement sale.

Example:

```text
Original:
Yamaha Guitar
GHS 2,500
```

Customer exchanges it for:

```text
Fender Guitar
GHS 3,000
```

System creates:

```text
EXCHANGE_RETURN
Yamaha Guitar +1

EXCHANGE_SALE
Fender Guitar -1
```

Difference:

```text
Customer pays GHS 500
```

Exchange records should link:

```text
Exchange
 ├── Original Sale
 ├── Return
 └── Replacement Sale
```

Do not edit the original sale's product.

---

# 16. Manual Stock Adjustments

Manual adjustments are allowed only for legitimate inventory reconciliation.

Example:

```text
System Stock: 15
Physical Count: 14

Adjustment: -1
Reason: Physical stock count
Approved By: Manager
```

This creates:

```text
ADJUSTMENT
-1
```

It does not directly overwrite stock.

---

# 17. Serial Number Tracking

Selected products should support serial numbers.

Example:

```text
Yamaha Keyboard

Serial Numbers:
KBD001
KBD002
KBD003
```

### `serial_numbers`

```text
id
product_id
serial_number
status
created_at
updated_at
```

Possible statuses:

```text
IN_STOCK
SOLD
RETURNED
DAMAGED
WARRANTY
```

Serialized products should have individual serial records rather than relying only on quantity.

---

# 18. Warranty Management

### `warranties`

```text
id
sale_item_id
serial_number_id
warranty_start
warranty_end
warranty_terms
status
created_at
updated_at
```

This allows the system to search by serial number and identify:

- Sale date
- Customer
- Product
- Warranty period
- Warranty status

---

# 19. Bulk Product and Stock Import

The POS must support CSV and Excel imports.

Example:

```text
SKU | Product Name | Category | Brand | Cost | Selling | Quantity | Serial Number
```

Import flow:

```text
Upload Excel/CSV
       ↓
Read File
       ↓
Validate Rows
       ↓
Preview
       ↓
User Confirms
       ↓
Create Import Batch
       ↓
Create/Update Products
       ↓
Create Stock Movements
       ↓
Add Sync Events
```

The importer must not directly set product stock.

---

# 20. Import Batches

### `import_batches`

```text
id
batch_number
import_type
file_name
total_rows
successful_rows
failed_rows
created_by
created_at
status
```

Import types:

```text
PRODUCT_IMPORT
OPENING_STOCK
BULK_STOCK
```

Example:

```text
Import #IMP-00045

File: January_Stock.xlsx

Rows: 1,250
Successful: 1,237
Failed: 13
Status: Completed
```

---

# 21. Bulk Import Modes

The system should clearly distinguish:

### Add Stock

```text
Current: 5
Import: +10
Result: 15
```

Creates:

```text
BULK_IMPORT +10
```

### Opening Inventory

Used during initial setup.

```text
Current: 0
Opening: +10
Result: 10
```

Creates:

```text
OPENING_STOCK +10
```

### Inventory Adjustment

Used when physical stock differs from recorded stock.

```text
System: 15
Physical: 10
Adjustment: -5
```

Creates:

```text
ADJUSTMENT -5
```

These operations must not be confused.

---

# 22. Import Validation

The system should validate:

- Required fields
- SKU uniqueness
- Valid categories
- Valid numerical values
- Valid prices
- Valid quantities
- Serial number uniqueness
- Product tracking type
- Existing product conflicts

Example error:

```text
Row 42
SKU: YAM-001
Error: Selling price is missing
```

The user should receive an import error report.

---

# 23. Import Preview

Before committing an import, display:

```text
IMPORT PREVIEW

Products found:       1,250
New products:           840
Existing products:      410
Valid rows:            1,237
Errors:                   13

Stock to be added:     4,820 units

[View Errors] [Cancel] [Import]
```

The import should require explicit confirmation.

---

# 24. Offline Synchronization

Every local transaction should be saved locally and added to a synchronization queue.

### `sync_queue`

```text
id
entity_type
entity_id
operation
payload
created_at
synced_at
status
retry_count
last_error
```

Example:

```text
entity_type: sale
entity_id: SALE-1001
operation: CREATE
status: PENDING
```

Synchronization:

```text
SQLite
   ↓
Sync Queue
   ↓
Sync Manager
   ↓
REST API
   ↓
PostgreSQL
```

---

# 25. UUIDs and Idempotency

Transactions should use globally unique identifiers.

Example:

```text
550e8400-e29b-41d4-a716-446655440000
```

The backend must be idempotent.

If the client retries a transaction because of a network failure, the server must recognize that the transaction already exists and avoid creating a duplicate.

Example:

```text
POS sends SALE-123
       ↓
Server receives it
       ↓
Network fails before response
       ↓
POS retries SALE-123
       ↓
Server recognizes SALE-123
       ↓
Returns existing result
```

This is essential for reliable offline synchronization.

---

# 26. End-of-Day Cloud Backup

The client specifically requires daily cloud backup.

The system should automate this.

Example:

```text
8:00 PM
   ↓
Check Internet
   ↓
Sync pending transactions
   ↓
Verify synchronization
   ↓
Create/verify cloud backup
   ↓
Record backup status
```

If Internet is unavailable:

```text
Internet unavailable
       ↓
Keep all data locally
       ↓
Retry synchronization later
```

The POS must not delete local data simply because synchronization failed.

---

# 27. Synchronization vs Backup

These are separate systems.

### Synchronization

```text
Local POS
   ↓
Cloud Database
```

Moves business transactions to the cloud.

### Backup

```text
PostgreSQL
   ↓
Cloud Object Storage
```

Creates recoverable snapshots.

Recommended retention:

```text
Daily backups
Weekly backups
Monthly archives
```

Exact retention can be configured based on the client's needs.

---

# 28. Recovery

If the POS computer fails:

```text
Install POS
     ↓
Authenticate
     ↓
Select Business
     ↓
Download latest cloud data
     ↓
Rebuild local database
     ↓
Resume operation
```

The system should aim to make computer failure a recoverable event rather than a business-data disaster.

---

# 29. Local Backup

In addition to cloud backup, the application may support local backup/export.

```text
POS
 ├── SQLite
 ├── Local Backup
 │     └── USB / External Storage
 │
 └── Cloud Sync
       └── PostgreSQL
             └── Cloud Backups
```

This gives the business multiple recovery options.

---

# 30. Multi-POS Consideration

A single POS terminal is significantly easier to synchronize.

If multiple terminals are required:

```text
POS 1
POS 2
POS 3
   ↓
Cloud Sync
   ↓
PostgreSQL
```

Offline multi-terminal operation introduces conflicts.

Example:

```text
Initial stock: 1 guitar

POS 1 offline → sells guitar
POS 2 offline → sells same guitar
```

Both terminals could temporarily believe the item is available.

Therefore, multi-terminal and multi-branch support must be explicitly designed rather than added later.

Possible future solutions include:

- Terminal-specific inventory allocation
- Branch inventory ownership
- Conflict detection
- Server reconciliation
- Reserved stock
- Online synchronization policies

---

# 31. User Roles

Recommended roles:

```text
OWNER
ADMIN
MANAGER
CASHIER
INVENTORY_MANAGER
```

Example permissions:

| Action | Cashier | Manager | Owner |
|---|---:|---:|---:|
| Make Sale | ✓ | ✓ | ✓ |
| Refund | Limited | ✓ | ✓ |
| Exchange | ✓ | ✓ | ✓ |
| Change Price | ✗ | ✓ | ✓ |
| Stock Adjustment | ✗ | ✓ | ✓ |
| Delete Product | ✗ | ✓ | ✓ |
| Reports | Limited | ✓ | ✓ |
| System Configuration | ✗ | Limited | ✓ |

Refunds, stock adjustments, price changes, and sensitive administrative operations should require appropriate permissions.

---

# 32. Audit Log

### `audit_logs`

```text
id
user_id
action
entity_type
entity_id
old_value
new_value
timestamp
device_id
```

Important operations to audit:

```text
LOGIN
SALE
REFUND
EXCHANGE
PRICE_CHANGE
STOCK_ADJUSTMENT
PRODUCT_CREATE
PRODUCT_UPDATE
PRODUCT_DELETE
IMPORT
USER_CREATE
USER_PERMISSION_CHANGE
BACKUP
RESTORE
```

The audit trail should make it possible to determine:

- Who performed an action
- What happened
- When it happened
- Which record was affected
- Which device performed it

---

# 33. Authentication and Security

Authentication should use:

```text
JWT access token
+
Refresh token
```

Passwords must be securely hashed.

Sensitive communication between the POS and backend must use HTTPS.

The POS should not store plaintext passwords.

Local sensitive data should be protected where practical using OS-level secure storage/encryption.

---

# 34. Reporting

The system should eventually provide:

### Sales

- Daily sales
- Weekly sales
- Monthly sales
- Sales by product
- Sales by category
- Sales by cashier
- Sales by payment method

### Inventory

- Current stock
- Low stock
- Stock movement history
- Inventory value
- Stock adjustments
- Damaged items
- Serialized inventory

### Refunds

- Refund totals
- Refunds by cashier
- Refunds by product
- Refund reasons

### Financial reconciliation

```text
Total Sales
Cash
Mobile Money
Card
Bank Transfer
Refunds
Net Sales
```

---

# 35. POS Transaction Flow

Normal sale:

```text
Cashier scans/selects products
          ↓
Cart
          ↓
Calculate totals
          ↓
Select payment
          ↓
Validate payment
          ↓
SQLite Transaction
          │
          ├── Create Sale
          ├── Create Sale Items
          ├── Create Payment
          ├── Create Stock Movements
          ├── Create Audit Event
          └── Create Sync Events
          ↓
Commit
          ↓
Print Receipt
```

All database changes should be committed atomically.

---

# 36. Refund Flow

```text
Find Original Sale
       ↓
Select Items to Refund
       ↓
Validate Refund Permissions
       ↓
Calculate Refund
       ↓
SQLite Transaction
       │
       ├── Create Refund
       ├── Create Refund Items
       ├── Create Payment Reversal/Refund Record
       ├── Create REFUND Stock Movement
       ├── Create Audit Event
       └── Create Sync Events
       ↓
Commit
       ↓
Print Refund Receipt
```

---

# 37. Exchange Flow

```text
Find Original Sale
       ↓
Select Product to Return
       ↓
Select Replacement Product
       ↓
Calculate Difference
       ↓
Return Old Product
       ↓
Create Replacement Sale
       ↓
Create Stock Movements
       ↓
Calculate Additional Payment / Refund
       ↓
Commit Transaction
       ↓
Print Exchange Receipt
```

---

# 38. Bulk Import Flow

```text
Upload File
     ↓
Parse
     ↓
Validate
     ↓
Preview
     ↓
Confirm
     ↓
Create Import Batch
     ↓
Create/Update Products
     ↓
Create Stock Movements
     ↓
Create Audit Records
     ↓
Create Sync Events
     ↓
Commit
```

---

# 39. Database Transaction Rules

Critical operations must use database transactions.

For a sale:

```text
BEGIN

INSERT sale
INSERT sale_items
INSERT payments
INSERT stock_movements
INSERT sync_queue
INSERT audit_log

COMMIT
```

If any critical step fails:

```text
ROLLBACK
```

This prevents partially completed sales.

The same principle applies to:

- Refunds
- Exchanges
- Stock imports
- Stock adjustments
- Other critical inventory operations

---

# 40. Recommended Development Order

Build the system in phases.

## Phase 1 — Foundation

```text
Flutter project
SQLite + Drift
Riverpod
Database migrations
Authentication
User roles
Basic navigation
```

## Phase 2 — Product Management

```text
Products
Categories
Brands
SKUs
Barcodes
Pricing
Product search
```

## Phase 3 — Inventory

```text
Stock ledger
Stock movements
Opening stock
Stock adjustments
Low-stock alerts
Serial numbers
```

## Phase 4 — POS

```text
Product search/scanning
Cart
Discounts
Taxes
Payments
Receipt generation
Sales history
```

## Phase 5 — Refunds and Exchanges

```text
Refunds
Exchange workflow
Refund permissions
Audit trail
Return stock movements
```

## Phase 6 — Bulk Import

```text
CSV
Excel
Templates
Validation
Preview
Import batches
Error reports
Opening inventory
Bulk stock
```

## Phase 7 — Cloud Backend

```text
NestJS
PostgreSQL
Authentication API
Product API
Sales API
Inventory API
```

## Phase 8 — Synchronization

```text
Sync queue
Push
Pull
Retry
Idempotency
Conflict handling
Sync status
```

## Phase 9 — Backup and Recovery

```text
Automated cloud backups
Backup verification
Restore process
Local backup
Disaster recovery
```

## Phase 10 — Reporting and Polish

```text
Sales reports
Inventory reports
Financial reconciliation
Dashboard
Performance optimization
Security hardening
Deployment
```

---

# 41. Key Design Decisions

The following decisions should remain fixed unless requirements change:

1. The POS is offline-first.
2. SQLite is the local database.
3. Drift manages SQLite access.
4. PostgreSQL is the cloud database.
5. NestJS is the backend.
6. Flutter is the POS client.
7. Riverpod manages application state.
8. Stock is represented through a movement ledger.
9. Refunds never modify/delete the original sale.
10. Exchanges are represented as return + replacement sale.
11. Bulk imports create stock movements rather than directly editing stock.
12. Every important operation is auditable.
13. Local transactions are committed before cloud synchronization.
14. Synchronization is asynchronous.
15. Sync operations are idempotent.
16. Cloud synchronization and backup are separate processes.
17. The system must support recovery after POS computer failure.
18. Multi-terminal support must be explicitly designed before implementation.

---

# 42. Core System Model

The fundamental business model is:

```text
                         PRODUCT
                            │
                            ▼
                      STOCK LEDGER
                            │
        ┌───────────────────┼───────────────────┐
        │                   │                   │
        ▼                   ▼                   ▼
     PURCHASE             SALE                REFUND
       +10                 -1                  +1
        │                   │                   │
        └───────────────────┼───────────────────┘
                            │
                            ▼
                     CURRENT STOCK
```

Transactions:

```text
SALE
REFUND
EXCHANGE
PURCHASE
ADJUSTMENT
BULK_IMPORT
OPENING_STOCK
```

are business events.

Stock is the resulting state of those events.

---

# 43. Final Architecture

```text
┌─────────────────────────────────────────────────────────────┐
│                         POS CLIENT                           │
│                                                             │
│ Flutter + Dart                                              │
│ Riverpod                                                    │
│                                                             │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │                    Application                          │ │
│ │                                                         │ │
│ │ Sales     Products     Inventory     Refunds             │ │
│ │ Exchanges Customers   Suppliers    Imports              │ │
│ │ Reports   Users       Settings                          │ │
│ └──────────────────────────┬──────────────────────────────┘ │
│                            │                                 │
│                     Domain / Repositories                    │
│                            │                                 │
│                         Drift                               │
│                            │                                 │
│                         SQLite                              │
│                            │                                 │
│                       Sync Queue                            │
└────────────────────────────┬────────────────────────────────┘
                             │
                       HTTPS / REST
                             │
┌────────────────────────────▼────────────────────────────────┐
│                         BACKEND                              │
│                                                             │
│ NestJS + TypeScript                                         │
│                                                             │
│ Auth | Products | Sales | Inventory | Refunds | Exchanges   │
│ Imports | Sync | Reports | Audit | Backups                  │
└────────────────────────────┬────────────────────────────────┘
                             │
                  ┌──────────┴──────────┐
                  │                     │
                  ▼                     ▼
            PostgreSQL            Object Storage
            Cloud Database        Backups / Files
```

## Primary rule

**The local POS is the operational system. The cloud is the synchronization and recovery system.**

The POS must remain capable of selling, refunding, exchanging products, importing stock, printing receipts, and recording inventory movements without Internet access.
