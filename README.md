# DMS

Multi-tenant document management system for retail, distribution, and wholesale businesses.

Built with Laravel 13, PHP 8.3+, PostgreSQL, and Sanctum API authentication.

## Overview

DMS is an API-first backend that manages the full lifecycle of business operations — from inventory and purchasing to sales, delivery, and accounting. It is designed for organizations that operate across multiple branches, warehouses, and outlets, with role-based access control and policy-driven governance at every level.

The system is organized into 7 domains with 90+ service classes, 14 document types, and a complete double-entry accounting engine.

## Target Users

- **Multi-branch retail chains** — centralized inventory with per-location pricing, promotions, and POS sessions
- **Distribution companies** — warehouse-to-outlet transfers, batch/expiry tracking, rider-based last-mile delivery
- **Wholesale businesses** — credit-based sales, payment allocation, aging reports for receivables and payables
- **FMCG / Pharmaceuticals** — FIFO/FEFO batch tracking with expiry dates, recall support, storage conditions
- **Electronics / High-value goods** — serial number tracking with full lifecycle (available → reserved → sold → returned)
- **Any business needing governance controls** — role-based approval workflows, policy-driven stock flow restrictions, complete audit trail

## Features

### Multi-Tenancy & Access Control

- **Organization-scoped** — every record is tied to an organization; all queries are automatically scoped
- **User Contexts** — users operate within a specific context (organization + branch + warehouse + outlet + cash register) and can switch contexts at runtime
- **User Assignments** — polymorphic role assignments scoped to any level (organization, branch, warehouse, outlet) with start/end dates
- **RBAC** — Spatie Permission integration with custom ability-based middleware (`ability:vouchers.*`, `ability:inventory.*`, etc.)
- **Authority Policies** — per-role, per-subject, per-action, per-hierarchy-level authorization with allow/deny effect

### Inventory Management

- **Product Catalog** — products with variants, SKU/barcode, multi-unit support with conversion factors (e.g., 1 Carton = 24 PCS), brand/brand-model hierarchy, categories, images
- **Stock Locations** — polymorphic, hierarchical (warehouse → section → shelf → bin), types: storage, transit, virtual, mobile, customer
- **Inventory Balances** — current stock tracked per variant + location + batch + condition, with quantity, reserved quantity, min stock, reorder point, weighted average cost
- **Batch Tracking** — FIFO/FEFO-ready with batch number, manufacturing date, expiry date, MRP, warranty, recall support, storage conditions
- **Serial Number Tracking** — lifecycle: available → reserved → in_transit → sold → damaged → returned → scrapped
- **Stock Reservations** — hold stock for pending orders with priority, expiry, and status tracking
- **Inventory Conditions** — sellable/non-sellable classification (Good, Damaged, Obsolete, etc.)
- **Valuation Methods** — three strategies via Strategy Pattern, resolved per-product:
  - **FIFO** — First In, First Out (consume oldest batch first)
  - **FEFO** — First Expired, First Out (consume nearest-expiry batch first)
  - **Weighted Average (WAVG)** — rolling average cost across all stock
- **Stock Adjustments** — addition/reduction with approval workflow
- **Opening Stock** — single and bulk entry endpoints

### Sales & Purchase Workflow

14 document types with a full state machine workflow:

**Sale Flow:**
1. **Sale Order** — customer orders with line items, tax, discounts
2. **Invoice** — with POS session support, currency/exchange rate, paid/due tracking
3. **Delivery Note** — linked to sale order and/or invoice, with rider assignment and delivery lifecycle
4. **Credit Note** — sale returns, validated against original invoice amount

**Purchase Flow:**
5. **Purchase Order** — supplier orders with expected receipt date
6. **Purchase Bill** — supplier invoices with currency/exchange rate, paid/due tracking
7. **Receipt Note** — goods received with quality inspection workflow (received → inspected/rejected)
8. **Debit Note** — purchase returns, validated against original bill amount

**Financial Documents:**
9. **Receipt** — customer payments with journal creation, allocation to invoices
10. **Payment** — supplier payments with journal creation, allocation to purchase bills
11. **Expense Claim** — employee expense claims with line items and account linkage

**Inventory Documents:**
12. **Transfer Order** — inter-location stock transfers with dispatch/receive lifecycle
13. **Journal Entry** — double-entry bookkeeping with balanced debit/credit
14. **Stock Adjustment** — manual inventory corrections

**Workflow State Machine:**
```
draft → submitted → reviewed → approved → confirmed → posted
                    ↑           ↓
                    └── rejected (with field-level validation errors)
```

**Document Infrastructure:**
- Polymorphic line items, attachments, comments, and cross-document links
- Optimistic locking with 30-minute timeout
- Comprehensive audit trail (events, old/new values, IP, user agent)
- Full status history and rejection tracking with attempt numbers
- Race-condition-safe document numbering per organization + voucher type + financial year

### POS System

- **Cash Registers** — polymorphic, linked to outlets
- **POS Sessions** — shift management with opening/closing balances, linked to user and cash register
- **FBR Integration** — branch-level FBR POS fields, invoice-level FBR invoice number tracking

### Double-Entry Accounting

- **Chart of Accounts** — hierarchical with 5 types (Asset, Liability, Equity, Revenue, Expense), normal balance (Debit/Credit), code-based hierarchy
- **Journal Entries** — double-entry with journal headers and lines, branch/warehouse/outlet dimension tracking
- **Journal Reversal** — reversing entries support
- **Financial Year Management** — periods (yearly/quarterly/monthly) with status lifecycle (draft → open → closing → closed → archived), closing workflow with approval
- **Multi-Currency** — exchange rates with effective/expiry dates

### Pricing, Promotions & Coupons

- **Price Lists** — named price lists with currency, validity dates, priority, default flag
- **Quantity Tiers** — variant-level pricing with min_quantity breakpoints
- **Polymorphic Price Overrides** — per branch, outlet, or stock location
- **Promotions** — percentage/fixed with date ranges, priority, stackability, min order amount, usage limits
- **Coupons** — percentage/fixed with validity, global + per-customer usage limits, max discount
- **Cart Calculation** — endpoint for calculating cart totals with all pricing rules applied
- **Price Simulation** — endpoint for simulating prices before committing

### Governance & Policy Engine

- **Organization Policies** — high-level rules (hierarchy, workflow, numbering) as key-value JSON, lockable
- **Stock Flow Policies** — controls allowed stock movement paths (from location type → to location type), with allow/denied flag
- **Authority Policies** — who (role) can do what (action) on which subject (stock/voucher/user) at what hierarchy level (org/branch/warehouse)
- **Stock Control Policies** — quantitative rules (allow_negative_stock, enforce_fifo, valuation_method)
- **Policy Locking** — individual and global (organization-level) lock/unlock

### Logistics & Delivery

- **Rider Assignment** — assign delivery rider to a delivery note
- **Delivery Confirmation** — mark delivery as complete
- **Rider Stock** — view stock carried by a rider
- **Reverse Logistics** — initiate return of goods

### Reporting

**Stock Reports:**
- Stock Status Report (by location, variant, condition)
- Stock Movements Report (ledger-based with date range and transaction type filters)
- Stock Aging Report (0–30, 31–60, 61–90, 91–180, 181–365, 365+ day buckets)
- Low Stock Report (items at or below min_stock / reorder_point)

**Financial Reports:**
- Trial Balance
- Profit & Loss Statement
- Balance Sheet
- Accounts Receivable Aging Report
- Accounts Payable Aging Report

## Architecture

### Tech Stack

| Component | Technology |
|---|---|
| Framework | Laravel 13 |
| Language | PHP 8.3+ |
| Database | PostgreSQL |
| Authentication | Laravel Sanctum 4 (token-based) |
| RBAC | Spatie Permission 7 |
| Real-time | Laravel Reverb |
| Testing | Pest PHP 4 |

### Domain Structure

```
routes/api/
├── auth.php          # Authentication, RBAC, user management
├── config.php        # Core master data (orgs, branches, warehouses, taxes, financial years)
├── partner.php       # Customers and suppliers
├── inventory.php     # Products, stock, pricing, promotions, coupons
├── voucher.php       # All 14 document types with workflow
├── account.php       # Chart of accounts, journals, reports
├── logistics.php     # Rider assignment, delivery, reverse logistics
└── policies.php      # Governance and policy engine
```

### Service Layer

```
app/Services/
├── Auth/             # AuthService, AuthorizationService, ContextHierarchyResolver
├── Core/             # OrganizationService, FinancialYearService, TaxService
├── Partner/          # CustomerService, SupplierService
├── Inventory/        # 30+ services
│   ├── Core/         # StockMovementService, InventoryBalanceService, LocationService
│   ├── Valuation/    # FifoInventoryEngine, FefoInventoryEngine, WeightedAverageInventoryEngine
│   ├── Transfer/     # TransferOrderService, TransferDispatchService, TransferReceiveService
│   ├── Delivery/     # DeliveryService, RiderAccountabilityService, ReverseLogisticsService
│   └── Pricing/      # CartCalculationService, PriceResolverService, PromotionEngineService
├── Voucher/          # 14 services (one per document type) + VoucherWorkflowService
├── Accounts/         # AccountService, JournalEntryService, AgingService
├── Policy/           # OrganizationPolicyService, AuthorityPolicyService, StockFlowPolicyService
└── Audit/            # AuditLogger
```

## Getting Started

### Prerequisites

- PHP 8.3+
- PostgreSQL
- Composer
- Node.js & NPM (for frontend, if applicable)

### Installation

```bash
# Clone the repository
git clone <repository-url>
cd DMS_PHP

# Install PHP dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure database in .env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=dms_php
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Run migrations
php artisan migrate

# Seed the database (if seeders are available)
php artisan db:seed

# Start the development server
php artisan serve
```

### Environment Setup

Key environment variables:

```env
APP_NAME=DMS
APP_ENV=local
APP_URL=http://localhost:8080

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=dms_php
DB_USERNAME=
DB_PASSWORD=

SANCTUM_STATEFUL_DOMAINS=localhost,localhost:3000,127.0.0.1
```

## API Overview

All authenticated routes are under `api/v1/` and require a Sanctum bearer token.

| Route Group | Prefix | Ability Middleware |
|---|---|---|
| Authentication | `api/v1/auth/` | Public (login, forgot-password, reset-password) |
| Users & RBAC | `api/v1/users/`, `api/v1/rbac/` | `ability:user.*`, `ability:auth.*` |
| Context | `api/v1/switch-context`, `api/v1/available-contexts` | `auth:sanctum` |
| Core Master Data | `api/v1/` (branches, warehouses, taxes, etc.) | `ability:core.*` |
| Partners | `api/v1/customers/`, `api/v1/suppliers/` | `ability:partner.*` |
| Inventory | `api/v1/products/`, `api/v1/stock_locations/`, etc. | `ability:inventory.*` |
| Vouchers | `api/v1/sale-orders/`, `api/v1/invoices/`, etc. | `ability:vouchers.*` |
| Accounts | `api/v1/accounts/`, `api/v1/journals/` | `ability:accounts.*` |
| Logistics | `api/v1/logistics/` | `ability:inventory.*` |
| Policies | `api/v1/policies/` | `auth:sanctum` + `system.admin` |

### Authentication

```bash
# Login
POST /api/v1/auth/login
{ "email": "user@example.com", "password": "password" }

# Response includes a plainTextToken — use as Bearer token
Authorization: Bearer <token>
```

## License

The DMS framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
