# Shipment freight calculation

Source-only change; not deployed. Rebuild EspoCRM in UAT when available to add
the new fields and refresh metadata. No backfill or production DB write is included.

## Behavior

Shipment has a pricing selector, two independent VND rates (per kg and per m³),
two read-only comparison amounts, and the applied basis:

- Weight: total weight × kg rate, even when volume is populated.
- Volume: total volume × m³ rate, even when weight is populated.
- Higher freight: maximum of the two amounts for the **whole shipment**.
  A tie uses kg. All parcels use that same winning basis, so their amounts sum
  to the shipment amount. It does not sum each parcel's individual maximum.

Example: 100 kg at 10,000 VND/kg and 2 m³ at 1,000,000 VND/m³ gives
1,000,000 VND by kg, 2,000,000 VND by m³, and 2,000,000 VND automatically.
Weight and volume are always retained. Missing measurements contribute zero;
manual mode never falls back to the other measure. The selected rate is required;
automatic mode requires both rates. An explicitly entered zero rate is allowed.

Saving rates/mode or adding, editing, moving, unlinking or deleting parcels
recalculates the affected shipment(s) and parcel amounts. Editing a measurement
can switch the automatic winner and therefore reprice other parcels in the lot.
The total payable remains freight + order balance + additional charges.

## Existing records

The empty option means “Not selected (existing calculation)”. It retains the
existing single-rate calculations until a user explicitly selects a new mode
and supplies the appropriate rates. Old rate values are not guessed or copied
into either new rate because their unit is ambiguous.

Legacy behavior is intentionally unchanged: the current source calculates the
shipment as `(totalWeight + totalVolume) × unitDeliveryPriceVnd`, while parcels
use weight when positive, otherwise volume. This existing inconsistency is NOT
used by the three new modes. No bulk historical repricing is performed.

Changes are available in full and quick-edit layouts, with Vietnamese, English,
and French labels. `templates/delivery-note.tpl` displays the new rates/applied
basis when available and retains the legacy tariff otherwise. If the active PDF
template lives in the database, its template body must also be updated in UAT.

## Checks

```powershell
& C:\xampp\php\php.exe tests/shipment-pricing.php
node --check data/espocrm-app/client/custom/src/views/c-shipment/record/detail.js
```

The PHP tests exercise actual calculators/hooks with in-memory ORM doubles;
they do not connect to a database. Browser forms, schema rebuild, real ORM/API
transactions, permissions for sales/warehouse roles, and PDF rendering still
require UAT acceptance testing. Simultaneous edits from independent clients
remain subject to the repository's existing concurrency behavior; bulk linking
from the custom screen is serialized to avoid overlapping repricing requests.
