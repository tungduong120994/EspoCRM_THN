# Logistics and customer balances — 2026-09-14

The initial source-only stage has progressed to isolated UAT after DNS was supplied
on 2026-09-16. See [live verification](../deploy/uat/VERIFIED-20260916.md).
Production application/data/permissions remain unchanged.

## Confirmed by the user

1. Tracking records become Delivered when their shipment becomes fully paid.
   Removing a tracking record from a shipment or cancelling the shipment returns
   the record to Undelivered. Linking to an unpaid shipment alone is insufficient.
2. Receipt into the Vietnam warehouse is the tracking record's CRM creation
   timestamp. Delivery date means delivery to the customer. Inventory counts
   tracking records, not packageCount; all physical packages on a tracking record
   move together. Existing `returned` records count as inventory.
3. Add a separate Logistics → Inventory page, grouped by Account. Include
   customers with zero current inventory. Show tracking receipts in a date range,
   current tracking stock, weight and volume, with drill-down into tracking rows.
   Do not replace the existing parcel page.
4. XLSX export is required. Sales: assigned customers only. Accounting:
   Management Team access. Warehouse: inventory data only, with no access to
   orders/financial pages. Enforce access on backend endpoints and exports, not
   only menu visibility. Exact existing role memberships need checking in UAT;
   a team name alone does not grant an administrative role.
5. Formal-import orders have actual and declared values. Actual value is the
   service fee base. Declared value is the entrustment/import-tax/VAT base.
   User-editable rates are needed; example rates are not exhaustive restrictions.
   User's requested formulas (not statutory tax advice):
   - Service fee = actual value × service percentage / 100.
   - Item/document fee: manual VND amount (examples 0 / 300,000 / 500,000).
   - Entrustment = declared value × entrustment percentage / 100.
   - Import tax = declared value × import-tax percentage / 100.
   - VAT = declared value × VAT percentage / 100.
   - Existing additional charges remain separate manual amounts.
6. Support partial payment, one receipt allocated to multiple shipments, and
   carried-forward customer credit/overpayments. A shipment belongs to exactly
   one customer. Only confirmed shipments enter receivables. Printed historical
   old balances must stay as they were when the slip was prepared. Multiple
   orders on one shipment are requested.
7. Search orders by customer code and customer name, with an Account filter.

## Source findings and compatibility requirements

- COrder's existing service fee is already in its total. Its rate is stored as
  a fraction (`0.02` means 2%). Do NOT reinterpret existing stored values as
  whole percentages, or add the same service fee again on the shipment.
- COrder's actual VND total currently includes domestic shipping and is converted
  from CNY using the order exchange rate. Preserve that existing basis unless
  the user changes its definition.
- Shipment currently has a boolean `paid`, a single `order` link and no separate
  confirmation/cancellation status. Adding confirmed receivables needs an explicit
  workflow; marking Delivered must still follow full payment as requested.
- Current order/shipment links are one-to-one. Multi-order shipment support needs
  a deliberate migration preserving existing links, account consistency checks,
  updated recalculation hooks and PDF detail rows. Do not silently discard links.
- Retain per-order original value, service fees and deposits. Aggregate remaining
  order charges only once on a shipment. A linked order must not be charged again
  on another active shipment without explicit split-order rules.
- `returned` already exists in CParcel metadata (Vietnamese: Đã trả lại).
  A negative amount alone does not identify which tracking records returned.
- `createdAt` can supply historical receipt dates. Historical delivery timestamps
  cannot be recovered accurately from current status alone; leave unknown until
  an approved source/backfill is available, never substitute deployment date.
- Account `name` is used as its code; `cName` is the customer display name.
  The existing order Account filter is already present. Quick search now includes
  order name, account.name and account.cName using Espo's normal query/ACL pipeline.

## Accepted implementation direction (user: "ok bạn làm nha")

1. Confirm a separate Draft → Confirmed → Cancelled shipment workflow, with
   payment status derived separately. Confirmed-but-unpaid shipments accrue debt,
   while their tracking records remain inventory until fully paid per the user's
   stated rule. Reversal of payment after delivery needs an explicit policy before
   automation, since a payment reversal does not physically return goods.
2. Confirm a dedicated return document selecting exact tracking records and
   credit amounts, rather than treating every negative financial slip as a return.
   A return needs to release tracking records for later dispatch while preserving
   the original shipment/payment audit history.
3. Confirm VND as the valuation base and whether declared-value fees belong to
   each order or to the whole shipment. Proposal: per order, aggregated once on
   the shipment, reusing the existing actual-value service fee.

The proposals above are now the implementation basis; no further business-rule
confirmation is pending for this source work.

## Payment design constraints

- Keep a shipment's own receivable separate from the customer's old balance and
  the printed requested-payment total. Never sum rolled-forward printed totals.
- Receipts and allocations must be separate records: one receipt can pay multiple
  same-customer shipments; a shipment can receive multiple payments. Reject
  cross-customer allocation and allocation beyond available receipt/credit.
- Customer credit is unapplied money and opening credit, not a negative tracking
  count. Cancelling a paid slip releases/reverses allocations, with an audit trail;
  it must not silently delete received cash.
- Snapshot invoice lines, rates, old balance and requested total at the agreed
  posting/printing event. Reprinting must use that stored snapshot, not live debts.
- Use transactions/locking for posting, payment allocation, reversal and stock
  transitions. Retried requests must not duplicate receipts, allocations or dates.
- Permissions must be verified with actual Sales, Warehouse and Management Team
  users in UAT, including direct API access and XLSX export.

## Implementation status

Source implementation now includes inventory and finance pages, backend account
ACL checks, inventory XLSX export, per-order declared fees, multiple orders per
shipment, invoice confirmation/snapshots, receipts/allocations/opening balances,
cancellation and tracking-specific returns. Generic financial/stock edits are
guarded; posted amounts are frozen. Order changes refresh affected draft shipments,
including shipments that reference the order in additionalOrders.

The delivery-note source template now prefers immutable invoiceSnapshotHtml.
Existing database PDF templates still require updating in UAT. Legacy slips without
a snapshot retain their prior print path. No historical receivable migration has
been executed: existing balances must be reconciled before switching accounting to
the new ledger. Legacy slips cannot be cancelled through the new ledger without
reconciliation; this avoids silently losing previously received money.

Local checks on 2026-09-15: 6 Lead tests, 14 freight tests, 35 money/fee/ledger/snapshot
assertions, 18 inventory SQL/access assertions and 4 client retry/template tests
passed. Custom PHP and JavaScript syntax checks passed. Domain tests use doubles;
inventory queries run against isolated SQLite. These do not validate MariaDB row
locking, Espo dependency injection/hooks, real role merging, PDF rendering or browser
flows. Local PHP is 8.1; the installed Espo dependency set requires >=8.2.

See [UAT activation and acceptance](logistics-uat-checklist.md) for remaining runtime
verification. No production or hosting changes were made during this source work.
