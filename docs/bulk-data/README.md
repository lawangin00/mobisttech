# MT-2.17 Validated Bulk Data Workflows

This point adds operational CSV and XLSX-style bulk workflows for catalogue, price, managed master-data and inventory data.

## Boundary

- The service is operational only. Historical/private migration remains owned by the dedicated offline migration and rehearsal importers.
- Catalogue and price mutations call `ProductDefinitions`; master-data calls `MasterDataAdministration`; inventory calls `InventoryOperations`.
- Bulk inventory never writes `products.qty`, stock-unit status, held quantities or other stock counters directly.
- Catalogue, price and inventory workflows require the existing outlet-scoped `shop.inventory` authority. Master-data requires `config.master-data.manage`.

## Validation and recovery

- CSV payloads are bounded to 2 MiB, 1000 data rows and 64 unique non-empty columns.
- XLSX-style input is an explicit cell matrix under the same row/column bounds; spreadsheet formula execution is not supported.
- Formula-leading cells are rejected on import and escaped on export to prevent spreadsheet formula injection.
- Preview returns normalized rows plus row-level validation errors before any mutation.
- `whole_batch` recovery commits all valid rows atomically or rolls the complete batch back on a late failure.
- `row` recovery commits each valid row independently and retains invalid/failed row evidence for correction and retry.
- Batch and row idempotency use existing `idempotency_requests`; inventory also retains the authoritative inventory-operation idempotency key.

## Export privacy

Exports use dataset-specific allowlists. Inventory exports derived on-hand/held/available values only. Acquisition seller CNIC, phone/address, private file paths, credentials and unrelated identity fields are not exported.
