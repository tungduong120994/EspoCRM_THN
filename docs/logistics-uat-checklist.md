# Logistics UAT activation and acceptance

Update 2026-09-16: isolated UAT deployment and several live checks are complete;
see [dated results](../deploy/uat/VERIFIED-20260916.md). The original checklist
below remains the acceptance reference, not a current statement that all work is
pending. Interactive UI/PDF visual checks and historical reconciliation remain.

Source preparation only. Do not execute against production. UAT deployment remains
on hold until the user supplies DNS information. All runtime checks below remain
pending; local unit tests are not a substitute for these checks.

## Activate on isolated UAT

1. Verify UAT has its own database, database user, file storage and application URL.
   Database credentials must have no grants on production. Disable copied email,
   scheduled integrations, webhooks and external writes before booting the clone.
2. Back up the UAT database and customized files. Use the Espo-supported PHP runtime
   (the current dependency set requires PHP >=8.2). Copy custom PHP, custom client
   files and metadata; rebuild the application/schema and clear its cache.
3. Verify the new ledger, operation, shipment-charge and stock-event tables, the
   unique requestKey index, shipment snapshot fields and repository overrides.
4. Add CInventory (Tồn kho) under Logistics in the navigation configuration and
   CFinance (Thu tiền và công nợ) for the appropriate users. Direct routes are
   #CInventory and #CFinance. Preserve the existing parcel/order navigation.
5. Update the UAT CShipment PDF template body from templates/delivery-note.tpl.
   Confirm the stored HTML renders through the PDF engine and fits the page with
   realistic Vietnamese names, many orders, and many trackings.

## Role configuration

Espo combines user and team roles. Audit effective permissions, not only one newly
created role. A restrictive role does not undo access granted by another role.

| Role | CInventory read/export | CFinance read/edit | Other data |
| --- | --- | --- | --- |
| Sales | own / own | own / no | Existing sales access; customer must be assigned to user |
| Warehouse | all / all | no / no | No COrder, COrderItem, CShipment or financial access |
| Management Team accounting | all / all | all / all | Account and shipment access required for allocations |

Do not grant generic read/create/edit/delete/export on CLogisticsEntry,
CLogisticsOperation, CShipmentCharge or CStockEvent to ordinary roles. These are
internal audit entities exposed through checked custom operations. Ensure Sales
has permission to read the orders and edit its shipments for confirmation.
Test the existing native exports independently: their record ownership/field ACL
must not allow customer data outside the user's assigned accounts. The new custom
inventory export enforces Account.assignedUserId on the server.

## Historical accounting cutover

- Keep the original Lead name components; the single-name editor only clears old
  components when the name itself changes. Do not bulk concatenate or rewrite names.
- Preserve old freight mode/rates and service-fee fractions. A fraction of 0.02 is
  displayed as 2 percent; it must not become 0.02 percent or a second fee.
- Reconcile a cutover report per customer: unpaid legacy PXKs, deposits, receipts,
  credits and cancellations. No script currently guesses these figures from paid.
- The new ledger includes only explicitly confirmed new-workflow invoices plus
  recorded opening balances. It is not yet a complete historical debt report.
- Opening debt/credit can carry reconciled legacy balances. Do not also confirm
  those same legacy slips and count their debt twice. If historical invoice-level
  allocations are required, implement and review a separate migration before use.
- Leave unknown historical delivery dates blank. Never backfill them with the
  deployment date. Source snapshots preserve new confirmed slips; old slips cannot
  recover historical values that were never stored.

## Acceptance scenarios

1. As Sales, search code/name, see assigned customers only, drill down and export
   XLSX. Try another customer's ID directly through every API. As Warehouse, repeat
   export and verify order/finance URLs and native APIs are forbidden.
2. Filter a Vietnam calendar day across both midnight boundaries. Count tracking
   records independently of packageCount; include returned stock and zero-stock
   accounts. Match XLSX values to the displayed result and retain leading zeros.
3. Create an order with actual and declared values, 1.5% service fee and editable
   declared percentages. Verify each fee once, with surcharge separate. Edit an old
   order without changing its service rate and confirm the prior rate is preserved.
4. Add several same-customer orders to a draft PXK; edit an additional order and
   verify draft totals refresh. Reject cross-customer and duplicate active billing.
5. Confirm the PXK: accrue only its own charge, retain inventory until fully paid,
   and store old debt/requested total. Later receipts must not alter the reprint.
6. Record one receipt across two PXKs and multiple receipts against one PXK. Keep
   unused cash as credit. Reject excess allocation and another customer's PXK.
7. Full allocation delivers every linked tracking. Partial allocation leaves stock.
   Retry after a lost response and verify exactly one receipt/allocation/event.
8. Return selected delivered trackings only; restore their stock and release excess
   allocation. Cancel afterwards and verify cash is retained exactly once. Reusing
   a returned tracking must not change the original invoice's printed snapshot.
9. Race two allocations/confirmations in separate sessions. Verify locking prevents
   spending the same credit or billing an order twice. Force failure midway through
   a return and verify the transaction rolls back ledger, stock and links together.
10. Try ordinary PUT, mass update, relationship endpoints and deletes against posted
    records and audit entities. They must not bypass the supported workflow.

Do not promote to production until these checks and opening-balance reconciliation
have passed on UAT with actual Sales, Warehouse and Management Team accounts.
