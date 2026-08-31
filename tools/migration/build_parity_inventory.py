"""Build traceable MT-1.1 static indexes from pinned blobs, not modified test copies."""
from pathlib import Path
import collections
import hashlib
import json
import re
import subprocess
from source_inventory import ROOT, SOURCES, git_args

# Each family has an explicit preservation contract; matching is traceability, not proof of parity.
FAMILIES = [
('P01','POS identity and outlet access','pos',r'User|Admin|ShopAccess|OperatorContext|Login|Password|AccountSession|ShopController|Middleware', ['MT-2.2','MT-4.1'],
 'Preserve three guards, assigned-outlet operator context, immutable OUTLET3 identity, permission defaults, password recovery and session revocation.',
 'Migrate identities with explicit collision maps; adapt guards/controllers; reuse permission checks. Do not equate POS outlet User with Website customer.',
 'Cross-role and cross-outlet direct requests denied; credential/reset/session behavior and historical shop ownership retained.'),
('P02','POS products and master data','pos',r'Product|Category|MasterData|Variant|BusinessIdentifier', ['MT-2.3','MT-4.2'],
 'Preserve protected categories, variant key, brand/model/capacity/condition options, acquisition source, managed unit status and historical option usage.',
 'Reuse registries and validators; migrate IDs/options/usage; adapt persistence to shared schema. No category redesign.',
 'Category-aware validation, identifier uniqueness, disabled-option history and managed-option deletion safeguards match source assertions.'),
('P03','POS acquisition and stock','pos',r'Inventory|Stock|Imei|Reserved', ['MT-2.4','MT-4.2'],
 'Preserve acquisition, physical units and all required IMEI slots, quantity stock, pricing and stock-movement history.',
 'Reuse stock rules; refactor controller transactions into shared services; migrate units/movements. Preserve app-owned availability states.',
 'MySQL concurrent sale/reserve/return proves no oversell, duplicate IMEI or movement; rollback and inventory totals reconcile.'),
('P04','POS sales invoices and returns','pos',r'Sale|Invoice|PosController|BusinessProfile', ['MT-2.5','MT-4.2','MT-4.3'],
 'Preserve POS checkout, sale/invoice numbers, totals/discounts, customer snapshots, return/cancellation effects and outlet business identity.',
 'Reuse arithmetic and snapshots; refactor transactional controller work; migrate history; adapt React forms and print bindings.',
 'Legacy sale and negative flows, precise totals, returns, stock/accounting changes, historical invoices and permissions match.'),
('P05','POS warranty and claims','pos',r'Warranty|Claim', ['MT-2.6','MT-4.3'],
 'Preserve warranty jobs, durations, versioned clauses, claims and sale-time warranty snapshots.',
 'Reuse clause validation/snapshot services; migrate claim/history relations; adapt interface.',
 'Expiry boundaries, claim lifecycle and role denial, old/new clause snapshots and document output retained.'),
('P06','POS reports customer history and documents','pos',r'Report|Profit|Dashboard|Customer|Document|Communication|BusinessIdentity', ['MT-3.1','MT-4.3'],
 'Preserve outlet/customer history, dashboards, category reports, profit summaries, exports, invoice/warranty A4 and Thermal output and safe messaging.',
 'Reuse query/formatting/template contracts; adapt shared services and React screens. Preserve historical snapshot output.',
 'Role-scoped totals/CSV, filters and date boundaries; A4/80mm print/download; protected placeholders and safe communication links.'),
('P07','POS Dynamic Platform presentation','pos',r'PosSetting|PosTheme|PosBranding|SafePosMedia|PosMedia|PosConfiguration|Portal|ControlCenter', ['MT-3.2','MT-4.4'],
 'Preserve distinct POS settings, branding/theme, safe media, navigation/form/table/dashboard/report presentation, preview/publish/revisions/rollback.',
 'Reuse settings registries and sanitizers; adapt runtime presentation from Blade to Inertia; migrate revisions/media usage.',
 'Per-section and publish permission checks, draft isolation, safe upload/path rules, cache invalidation, audit and rollback parity.'),
('P08','POS backup audit and operations','pos',r'Backup|Restore|GoLive|PrepareLive|Audit|IntegrationStatus|routes/console', ['MT-3.3','MT-7.1','MT-7.4'],
 'Preserve backup manifests/history, download/delete rights, verify-only restore, code/key/target guards, secret-safe audit and operational health.',
 'Reuse manifest/redaction guards; adapt storage/process paths and shared-schema backup. Keep destructive reset separately guarded, never auto-run.',
 'Isolated backup/restore and failure tests; no secrets in audit/errors; ownership guards; recovery controls and scheduled job disablement.'),
('X01','Reservation confirmation release and reconciliation','both',r'WebsiteOrder|PosOrder|IntegrationRequest|IntegrationOutbox|OrderRelease|ReservedInventory|ExpireWebsite', ['MT-2.7','MT-3.3','MT-3.4'],
 'Preserve reservation expiry, allocations, idempotency, replay checks, confirmation-versus-release exclusion, retries and reconciliation.',
 'Reuse state machine/contract validations; refactor transport into shared Laravel transactions. Retire dual-database transport only after replacement acceptance, never its invariants.',
 'Same request replay, conflicting request hashes, expiry/confirm/release races, partial failure/retry and order-stock-payment reconciliation on MySQL.'),
('W01','Website customer and admin identity','website',r'User|Auth|Password|Account|WebsitePermission|WebsiteAdmin|EnsureCustomer|WebsiteOrderAccess', ['MT-2.2','MT-4.1','MT-4.4','MT-5.2'],
 'Preserve customer auth/account, owner/manager/content_editor/operations roles, permission matrix, admin avatar/profile/password, audit and signed order access.',
 'Reuse policy/validation and session boundaries; migrate identity mapping; adapt CMS administration into backend and public account into Next.js.',
 'Guest/customer/admin cross-role denial, account ownership, signed-link expiry, profile upload limits, last-owner safety and password reset parity.'),
('W02','Public catalogue and comparison','website',r'Catalog|Product.php|ProductVariant|PublicCatalog', ['MT-2.3','MT-3.4','MT-5.1'],
 'Preserve category catalogue, search/filter/sort/pagination, product/variant details, comparison, availability, zero-stock visibility and legacy redirects.',
 'Reuse query/presentation/public allowlist contracts; replace copied POS catalogue with shared-authority reads only after freshness parity; adapt Next pages.',
 'Same category/filter/variant/price results, private fields never public, zero stock handled correctly, POS writes reflected through APIs and redirects retained.'),
('W03','Cart orders and reviews','website',r'Cart|OrderController|Order.php|OrderItem|Review|OrderManagement|CommerceRecovery', ['MT-2.7','MT-3.4','MT-5.2'],
 'Preserve multi-line cart, quantity update/removal, order history/status/invoice access, review eligibility/moderation and admin order CSV/status operations.',
 'Reuse validation/ownership/review rules; migrate records and snapshots; adapt session cart and REST/Next interfaces.',
 'Guest-to-account/cart recovery, quantity/price changes, duplicate submit, ownership, signed access, eligible purchase review and moderation.'),
('W04','Payments COD hosted card and wallets','website',r'Payment|CashOnDelivery|Card|Easypaisa|JazzCash|Checkout|Credential', ['MT-2.7','MT-3.3','MT-5.3'],
 'Preserve payment manager/provider contracts, verified amount/currency/order/reference, COD collection, callback/webhook replay safety, retry/cancel and encrypted credentials.',
 'Reuse provider interfaces, verification and disabled-provider failures; adapt persistence/URLs; no invented gateway implementation or raw PAN/CVV collection.',
 'Amount/owner/reference tampering and replay denied; COD works; hosted gateway fakes prove contract only; authentic provider sandbox remains H-02.'),
('W05','Digital services requests and project quotes','website',r'DigitalService|ServiceRequest|ProjectQuote|ProjectPayment|CompanyPage', ['MT-3.2','MT-3.4','MT-5.4'],
 'Preserve digital services/content, request references/status, approved quote amounts/currency, secure payment tokens and paid quote linkage.',
 'Reuse quote/request validation and signed access; migrate service/quote/order relationships; adapt CMS and Next public flows.',
 'Approved quote amount cannot be changed by client, ownership/token expiry and request privacy hold, status/history and paid linkage preserved.'),
('W06','Website managed content navigation and SEO','website',r'Managed|Navigation|Homepage|HeaderFooter|Seo|Legal|Promotion|HomeController|SiteNavigation|SiteManaged|WebsiteAdminPage', ['MT-3.2','MT-4.4','MT-5.4'],
 'Preserve managed page templates/content/SEO, nested menus/destinations, homepage section registry, header/footer, promotions, legal pages and protected routes.',
 'Reuse registries/sanitization/revision services; migrate content; adapt admin and Next rendering. Keep publication and rollback semantics.',
 'Draft isolation, protected route collisions, safe markup/URLs/media, ordering/visibility, publication/rollback and cache invalidation match.'),
('W07','Website themes media settings and recovery','website',r'SiteSetting|SiteSecret|SiteMedia|SiteConfiguration|WebsiteSetting|WebsiteTheme|WebsiteBrand|WebsiteBusiness|WebsiteMedia|SafeWebsite|WebsiteConfiguration|WebsiteContentRevision|ConfigurationSecurity', ['MT-3.2','MT-3.3','MT-4.4'],
 'Preserve Website-specific setting keys, brand/theme tokens, safe upload/usage and replacements, secret envelopes, revisions and recovery.',
 'Reuse registries and recovery guards; migrate logical namespace and usage; adapt admin UI and public rendering.',
 'Secret masking/key mismatch, unsafe SVG/path rejection, usage-aware deletion/replacement, theme contrast/responsive fallbacks and revision recovery.'),
('W08','Website integration health and jobs','website',r'IntegrationHealth|IntegrationStatus|Reconcile|ProcessPos|routes/console|Audit', ['MT-3.3','MT-7.4'],
 'Preserve safe integration status, outbox/reconciliation job semantics, rate/retry limits and redacted audit; distinguish local test helpers from operational commands.',
 'Adapt health checks to one backend; retain recovery jobs where external I/O remains; retire obsolete polling only with recorded replacement evidence.',
 'No arbitrary endpoint/command execution, disabled integration makes no call, fake retry/failure tests and scheduler/queue recovery; local mark-paid never production API.'),
('B01','Canonical brand and runtime assets','both',r'Brandkit|BrandAsset|VisualSystem|runtime-assets', ['MT-6.1'],
 'Preserve approved editable logo/wordmark, fonts/icons/favicon/watermark/print/runtime references and license requirements.',
 'Deduplicate byte-identical masters into root brand later; retain runtime derivatives only when referenced. Do not migrate old Control logo as approved artwork.',
 'Master hashes and asset manifest, view/style/build references, fallback dimensions and print contrast verified in both target applications.'),
('C01','Windows Control and developer launch tooling','both',r'mobiST Control Center|legacy-operations-tooling', ['MT-6.2','MT-6.3'],
 'Preserve useful Start/Stop/Restart/Open/Status, LAN/QR, browser reuse and operator diagnostics; add required Start All/Stop All with current approved logo.',
 'Adapt useful C# UI; refactor process ownership and hard-coded paths. Port-only taskkill is unsafe; executable mirrors/launcher backup are not authoritative target source.',
 'Exact target PID/path ownership, occupied ports/stale PIDs, repeated all-actions, partial failure, no orphan/duplicate tabs, no source/unrelated process termination.'),
('Q01','Regression security performance and migration gates','both',r'AcceptanceTest|SecurityReview|Performance|MigrationRollback|Responsive|Consistency|ExampleTest|TestCase|Fixtures|Fakes', ['MT-7.1','MT-7.2','MT-7.3','MT-7.5'],
 'Retain source regression assertions and negative security/performance/rollback contracts as migration evidence, not proof of target completion.',
 'Adapt fixtures/tests for shared schema and new UI incrementally; reuse assertions. Replace source-text Blade checks with behavior plus target structure checks where appropriate.',
 'Fresh target PHPUnit/Pest, MySQL concurrency, builds and Playwright; no source tests quoted as target results; all registered capabilities independently close.'),
('F01','Framework schema and build foundation','both',r'framework-configuration|build-test-contracts|database/|Providers|Controller.php$|root-metadata|legacy-ci', ['MT-1.2','MT-1.3','MT-2.1','MT-7.3'],
 'Preserve Laravel configuration, middleware ordering, migration history/constraints, reproducible locks and application boot contracts.',
 'Reuse Laravel13 foundation, adapt shared migrations/config/CI, install dependencies fresh. React/Next presentation adapters are new; business logic rewrite is not justified.',
 'Shared schema collision/foreign key map, fresh setup and migration apply/rollback/reapply, exact toolchain locks, monorepo CI and environment isolation.'),
('H01','Historical documentation and metadata','both',r'historical-documentation', ['MT-7.5'],
 'Keep provenance of prior source acceptance and operational instructions without adopting old dual-database authority.',
 'Retain inventory/hash references; migrate only useful current runbooks. Source roadmap/old launch paths and duplicate documents are historical.',
 'Every required legacy behavior traced to target evidence; stale instructions cannot override Goal, Preferences or new registry.'),
]

OWNERS={
    **{f'P{i:02d}':'backend shared services and protected React/Inertia POS administration' for i in range(1,9)},
    'X01':'backend shared transactional services and REST contracts',
    **{f'W{i:02d}':'backend services/protected CMS plus website Next.js REST client' for i in range(1,8)},
    'W08':'backend integration jobs, audit and operational health',
    'B01':'brand canonical masters; generated backend/website runtime derivatives',
    'C01':'tools/mobist-control single Windows application',
    'Q01':'backend tests, website Playwright and .github monorepo CI',
    'F01':'backend foundation, website build contracts and .github',
    'H01':'docs migration evidence and operational runbooks',
}


def families(source, path, category):
    searchable=path+' '+re.sub(r'[-_]', '', path)+' '+category
    result = [f[0] for f in FAMILIES if f[2] in ('both',source) and re.search(f[3],searchable,re.I)]
    if category=='route-contracts' or path=='app/Http/Controllers/Controller.php': result.append('F01')
    if category.startswith('presentation/'):
        result.append('P07' if source=='pos' else 'W07')
        if source=='website':
            for pattern,group in [('order|invoice|cart|review','W03'),('service|quote|solutions','W05'),('home|about|contact|page|header|footer|nav','W06'),('product|shop|compare','W02'),('login|register|profile|admin/layout|components/admin','W01')]:
                if re.search(pattern,path):result.append(group)
        elif '/shop/pos.' in path:result.append('P04')
    if source=='pos' and 'PublicCatalog' in path:result += ['P02','X01']
    if 'ManagedAcquisition' in path or 'ManagedDevice' in path or 'ManagedUnit' in path:result.append('P02')
    if 'FormTablePresentation' in path:result.append('P07')
    if source=='pos' and 'IntegrationHealth' in path:result.append('P08')
    if source=='website' and 'BusinessMessaging' in path:result += ['W03','W05']
    return sorted(set(result))


def main():
    inventory = json.loads((ROOT/'docs/migration/SOURCE_FILE_INVENTORY.json').read_text())
    records, missing = [], []
    for source in inventory['sources']:
        label=source['source']; path, commit=SOURCES[label]
        proc=subprocess.Popen(git_args(path)+['cat-file','--batch'],stdin=subprocess.PIPE,stdout=subprocess.PIPE)
        for file in source['files']:
            name=file['path']; groups=families(label,name,file['category'])
            if not groups: missing.append(label+':'+name)
            row={'source':label,'path':name,'sha256':file['sha256'],'families':groups}
            if name.endswith(('.php','.cs','.bat','.js','.css')) and not name.startswith('public/'):
                proc.stdin.write((file['git_blob']+'\n').encode());proc.stdin.flush()
                header=proc.stdout.readline().decode().split()
                data=proc.stdout.read(int(header[2]));assert proc.stdout.read(1)==b'\n'
                content=data.decode('utf-8-sig',errors='replace')
                row['symbols']=[{'name':m.group(1),'line':content.count('\n',0,m.start())+1} for m in re.finditer(r'\bfunction\s+(\w+)\s*\(',content)]
                row['references']=sorted(set(re.findall(r'^use\s+(App\\[^;]+);',content,re.M)))
                row['view_references']=sorted(set(re.findall(r"(?:view|@include|@extends|@component)\s*\(\s*['\"]([^'\"]+)",content)))
                row['schema']=[{'line':i,'declaration':line.strip()} for i,line in enumerate(content.splitlines(),1) if 'Schema::' in line or '$table->' in line] if '/migrations/' in name else []
                row['commands']=[{'line':i,'declaration':line.strip()} for i,line in enumerate(content.splitlines(),1) if "Artisan::command(" in line or '$signature' in line or 'Schedule::' in line]
                if name.endswith('Registry.php') or name in ('app/Models/User.php','app/Models/Admin.php'):
                    row['declared_keys']=[{'key':m.group(1),'line':content.count('\n',0,m.start())+1} for m in re.finditer(r"['\"]([a-z][a-z0-9_.-]+)['\"]\s*=>",content)]
            records.append(row)
        proc.stdin.close();assert proc.wait()==0
    output={'schema':1,'point':'MT-1.1','method':'Pinned blob static declarations; line numbers are source lines. Multi-family assignments require all linked gates. No target parity is claimed.',
            'families':[dict(zip(('id','title','source','match','roadmap_points','preserve','decision','target_gate'),f)) for f in FAMILIES],
            'files':records,'unmapped':missing}
    for family in output['families']:
        family['target_owner']=OWNERS[family['id']]
        family['target_status']='Pending'
    routes=[]
    for label in SOURCES:
        data=(ROOT/f'.local/mt11/{label}-routes.json').read_text(encoding='utf-8-sig')
        for route in json.loads(data):
            action=route['action'].split('@')[0].replace('\\','/')
            file='app/'+action[4:]+'.php' if action.startswith('App/') else 'routes/web.php'
            groups=next((r['families'] for r in records if r['source']==label and r['path']==file),[])
            if not groups: groups=['F01']
            if label=='website' and file=='routes/web.php':
                groups=sorted(set(groups+(['W02'] if route['uri'].startswith('mobiles') else ['W06','W07'])))
            routes.append({'source':label,**route,'source_file':file,'families':groups,'target_parity':'Pending'})
    output['routes']=routes
    output['counts']={label:{'files':sum(r['source']==label for r in records),
        'routes':sum(r['source']==label for r in routes),
        'methods':sum(len(r.get('symbols',[])) for r in records if r['source']==label),
        'models':sum(r['source']==label and r['path'].startswith('app/Models/') for r in records),
        'migrations':sum(r['source']==label and '/migrations/' in r['path'] for r in records)} for label in SOURCES}
    (ROOT/'docs/migration/SOURCE_SYMBOL_INVENTORY.json').write_text(json.dumps(output,indent=2)+'\n',encoding='utf-8',newline='\n')
    print('Indexed',len(records),'files;',len(routes),'resolved non-vendor routes;',len(missing),'unmapped')
    for item in missing: print(item)


if __name__=='__main__':main()
