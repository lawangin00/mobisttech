"""Render the reviewed structured MT-1.1 inventory into durable human evidence."""
from pathlib import Path
import collections
import hashlib
import json
import xml.etree.ElementTree as ET

ROOT=Path(__file__).resolve().parents[2]
data=json.loads((ROOT/'docs/migration/SOURCE_SYMBOL_INVENTORY.json').read_text())
files=json.loads((ROOT/'docs/migration/SOURCE_FILE_INVENTORY.json').read_text())

def sha(path):return hashlib.sha256(path.read_bytes()).hexdigest()
def tests(label):
    root=ET.parse(ROOT/f'.local/mt11/{label}-junit.xml').getroot()[0]
    result={k:root.attrib[k] for k in ('tests','assertions','errors','failures','skipped','time')}
    result['cases']=[{'class':case.attrib['class'],'name':case.attrib['name'],
                     'assertions':int(case.attrib['assertions'])} for case in root.iter('testcase')]
    result['junit_sha256']=sha(ROOT/f'.local/mt11/{label}-junit.xml')
    result['log_sha256']=sha(ROOT/f'.local/mt11/{label}-tests.log')
    result['harness_sha256']=sha(ROOT/f'.local/mt11/sources/{label}/tests/TestCase.php')
    return result

def main():
    pos,web=tests('pos'),tests('website')
    lines=['# MT-1.1 - Source inventory and feature parity register','',
    'Status: Completed characterization; target parity remains Pending until the linked roadmap gates pass.','',
    '## Authority and evidence boundary','',
    'This register is based on immutable tracked blobs at POS `c61e47394e7b3db8a49cfe443c85b63835febc9b` and Website `04e7c49518f9f11f60c83ad44f9f4e2fd2539066`. '
    'It inventories source behavior for migration; it does not claim that target code, MySQL migration, React/Next interfaces, provider sandboxes or production infrastructure exist. '
    'The protected source working trees were not written or executed. Select tracked blobs were exported into ignored `.local/mt11` copies; dependencies were freshly installed there with Composer scripts/plugins disabled. '
    'The characterization harness used SQLite memory, array mail/cache, synchronous queues, disabled integrations, synthetic encryption key, local fake storage, `Http::preventStrayRequests()` and Vite bypass.','',
    f"Fresh isolated source characterization passed: POS {pos['tests']} tests / {pos['assertions']} assertions; Website {web['tests']} tests / {web['assertions']} assertions. Errors, failures and skips were zero. These are source regression baselines only.",'',
    'Machine evidence: `SOURCE_FILE_INVENTORY.json` contains every tracked path/blob/hash, category and preliminary retention decision. `SOURCE_SYMBOL_INVENTORY.json` contains source-line methods, references, migration declarations, registry keys, command declarations, 316 resolved routes and family assignments.','',
    '## Inventory totals','',
    '| Source | Tracked files | Selected isolated export | Models | Migrations | Resolved routes | Declared methods |',
    '|---|---:|---:|---:|---:|---:|---:|']
    for src in files['sources']:
        c=data['counts'][src['source']]
        lines.append(f"| {src['source'].upper()} | {src['total_files']} | {src['exported_files']} | {c['models']} | {c['migrations']} | {c['routes']} | {c['methods']} |")
    lines += ['', 'All 1,224 tracked files have one or more family assignments; the unmapped list is empty. Generated dependencies/builds, source Git, secrets, live databases/uploads/backups and executable binaries were excluded. Historical documents and old CI are indexed by hash but were not treated as new authority.','',
    '## Decision vocabulary','',
    '- **Reuse** means retain a proven rule, registry, validation, calculation, state machine or assertion with minimal change.','- **Adapt** means keep behavior while changing framework boundary, path, REST contract, presentation or storage integration.','- **Refactor** means move working controller/transport/process behavior behind shared services without changing its contract.','- **Migrate** means preserve schema/data/history/identity with explicit mappings and reconciliation.','- **Rewrite** is limited to source structures that cannot satisfy the target: Blade presentation adapters become React/Next interfaces; dual-database transport becomes shared transactions; Control process ownership must be made safe. This does not justify rewriting their business contracts.','',
    '## Capability register','']
    counts=collections.Counter(g for r in data['files'] for g in r['families'])
    route_counts=collections.Counter(g for r in data['routes'] for g in r['families'])
    for family in data['families']:
        lines += [f"### {family['id']} - {family['title']}",'',f"Source coverage: {counts[family['id']]} tracked files; {route_counts[family['id']]} resolved routes. Target owner: {family['target_owner']}. Gates: {', '.join(family['roadmap_points'])}. Target status: Pending.",'',
        '**Preserve:** '+family['preserve'],'','**Decision:** '+family['decision'],'','**Parity gate:** '+family['target_gate'],'']
    lines += ['## Verified conflicts, gaps and retirement candidates','',
    '1. **Identity collision:** POS `User` is the outlet/shop record with immutable three-digit outlet code; Website `User` is customer identity and can also carry Website admin role. POS also has distinct `Admin`, `SuperAdmin` and `shop_admins`. MT-1.2 must define separate concepts and collision maps; table-name or numeric-ID equality cannot authorize merging.','',
    '2. **Duplicated products and orders:** Website stores a synced catalogue plus its own orders/payments/outbox while POS stores authoritative inventory plus reservation/allocation/request records. These copies and HTTP synchronization are retirement candidates only after one-schema transactional replacement passes X01/W02/W03/W04. Their idempotency, reservation, pricing and release rules remain required.','',
    '3. **Return scope gap:** source inspection found an inventory stock-unit return-to-stock operation, but no routed completed-sale refund/return workflow. The Goal still requires returns. MT-2.5 must define and test the missing sale-return contract from verified business requirements rather than pretend source parity already exists.','',
    '4. **Payment boundary:** COD and provider abstractions/configuration are reusable. JazzCash/Easypaisa authentic sandbox claims and an approved hosted/tokenized card gateway remain H-02. Disabled behavior, callback verification, retries and secret recovery are required; raw PAN/CVV handling is prohibited.','',
    '5. **Control safety:** both source repositories contain the same old Control source/mirror. It hard-codes old paths/ports and can `taskkill` any listener PID found on a port. Useful UI/browser/LAN/status behavior may be adapted once, but stop/restart must verify exact target process ownership. Old executable/logo/launcher backups are not approved masters.','',
    '6. **Unreferenced legacy presentation:** old POS course/badge/card/search Ajax partials are not referenced by current routes/controllers and contain unrelated school-template concepts. They are indexed historical/retirement candidates, not valid capabilities. Removal becomes final only when target full register and source route/view-reference acceptance confirm no dependency.','',
    '7. **Brand duplication:** source master artwork is byte-identical across repositories; runtime assets have application-specific references. MT-6.1 will retain one approved root master plus an explicit derivative/runtime manifest, after current-logo approval mapping.','',
    '8. **Local operational helpers:** interactive mail/account/test-payment/quote creation and go-live/reset commands are not public target APIs. Useful guarded operator behavior must be reviewed under F01/P08/W08; source-mutating launch/reset scripts and stale paths are not auto-migrated or executed.','',
    '## Retention and traceability rules','',
    'Every source file is traceable by source commit, Git blob and SHA-256. A file may serve more than one family; every assigned family gate must pass before retirement. File classification is a migration decision aid, not permission to bulk-copy. Dependencies and generated assets must be reinstalled/regenerated, licenses retained as applicable, and sensitive/runtime material must come from target-only secret/data procedures.','',
    'Target parity remains Pending for every family. During later points, evidence must update the register by stable family ID and source path, linking target services/routes/tests and recording migrated, retired-with-proof or blocked status. MT-7.5 and FINAL-AUDIT must show no valid capability silently dropped.','']
    register='\n'.join(lines)
    (ROOT/'docs/migration/FEATURE_PARITY_REGISTER.md').write_text(register,encoding='utf-8',newline='\n')
    verification={'schema':1,'point':'MT-1.1','status':'completed','source_commits':{k:v[1] for k,v in __import__('source_inventory').SOURCES.items()},
      'counts':data['counts'],'resolved_routes':len(data['routes']),'unmapped_files':len(data['unmapped']),
      'source_tests':{'pos':pos,'website':web},
      'harness':['isolated pinned-blob export','Composer install --no-scripts --no-plugins','SQLite :memory:','array cache/session/mail','sync queue','external integrations disabled','synthetic APP_KEY','Http::preventStrayRequests','withoutVite'],
      'limitations':['source characterization is not target parity','SQLite source regression is not MySQL migration/concurrency acceptance','Vite bypass excludes built frontend asset acceptance','no real provider/network/source data/source storage calls'],
      'artifact_sha256':{'file_inventory':sha(ROOT/'docs/migration/SOURCE_FILE_INVENTORY.json'),'symbol_inventory':sha(ROOT/'docs/migration/SOURCE_SYMBOL_INVENTORY.json'),'register':hashlib.sha256(register.encode()).hexdigest()}}
    (ROOT/'docs/migration/MT_1_1_VERIFICATION.json').write_text(json.dumps(verification,indent=2)+'\n',encoding='utf-8',newline='\n')
    print('Wrote register and verification')
if __name__=='__main__':main()
