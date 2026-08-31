"""Compile reviewed source schemas into conventional, frozen Laravel migrations.

Source JSON contains schema only. Never reads source rows, credentials or databases.
Re-run only while this migration is unpublished; later schema changes need new migrations.
"""
from pathlib import Path
import copy
import hashlib
import json
import re

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'docs/schema'
RENAMES = {'pos': {'users': 'outlets', 'shop_admins': 'outlet_admins',
    'website_orders': 'reservations', 'website_order_lines': 'reservation_lines',
    'website_order_allocations': 'reservation_allocations', 'integration_requests': 'legacy_integration_requests'},
    'website': {'products': 'product_listings', 'pos_integration_outbox': 'legacy_integration_events'}}
RUNTIME = {'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions',
           'password_reset_tokens', 'personal_access_tokens'}
EXISTING = RUNTIME - {'password_reset_tokens', 'personal_access_tokens'}
tables = {}
manifest = []


def rename(source, table, column):
    if source == 'pos':
        if column == 'shop_id': return 'outlet_id'
        if column == 'website_order_id': return 'reservation_id'
        if column == 'website_order_line_id': return 'reservation_line_id'
        if table == 'users' and column in {'password', 'remember_token'}: return 'legacy_' + column
    if source == 'website':
        if table in {'order_items', 'product_reviews'} and column == 'product_id': return 'product_listing_id'
        if table == 'products' and column in {'online_price', 'stock_quantity', 'availability_status', 'synced_at'}:
            return 'legacy_' + column
        if table == 'project_quotes' and column == 'secure_token': return 'legacy_secure_token'
    return column


def target_name(source, name): return RENAMES[source].get(name, name)
def seq(value): return list(value.values()) if isinstance(value, dict) else value


for source in ('pos', 'website'):
    schema = json.loads((OUT / f'source_{source}.json').read_text(encoding='utf-8'))
    for name, source_table in schema['tables'].items():
        target = target_name(source, name)
        row = {'source': source, 'table': name, 'target_table': target, 'columns': []}
        cols = []
        for original in source_table['columns']:
            c = copy.deepcopy(source_table['declarations'][original['name']])
            c['name'] = rename(source, name, original['name'])
            c['nullable'] = original['nullable']
            if c['name'] == 'legacy_secure_token':
                c['nullable'] = True  # New quotes use hashed capabilities; old token evidence is optional/inactive.
            c['autoIncrement'] = original['auto_increment']
            # SQLite is authoritative for surviving columns/defaults; Blueprint retains lengths and precision.
            default = original['default']
            c.pop('default', None)
            if default is not None:
                c['default'] = default[1:-1].replace("''", "'") if default.startswith("'") else default
            if c['type'] in {'timestamp', 'dateTime'}: c.update(type='dateTime', precision=6)
            if c['type'] == 'decimal' and c['name'] != 'aspect_ratio': c.update(total=19, places=2)
            if c['name'] in {'currency', 'outlet_code'}: c.update(type='char', length=3)
            if c['type'] in {'string', 'char', 'uuid'} and re.search(r'(code|key|hash|token|reference|external_id|imei|slug|^source$|^gateway$|^domain$|^guard$)', c['name']):
                c['binary'] = True
            disposition = 'preserve'
            transform = 'Preserve value; remap internal IDs through source-qualified identity map. Validate type, bounds and nullability before insert.'
            if name in RUNTIME:
                disposition = 'reset-runtime'
                transform = 'Do not import live credentials, sessions, tokens, cache or jobs. Retain required failed-job evidence only in a separately authorized redacted archive.'
            elif c['name'].startswith('legacy_'):
                disposition = 'retain-inactive-history'
                transform = 'Retain as restricted historical evidence only; never use as authentication, current price or available stock.'
            elif c['name'] != original['name']:
                disposition = 'rename-and-map'
            elif original['name'] == 'id':
                disposition = 'map-identity'
            if original['type_name'] == 'datetime': transform += ' Convert using declared source timezone to UTC DATETIME(6); reject ambiguous dates.'
            if c['type'] == 'decimal': transform += ' Use exact decimal strings; reject excess scale/overflow, never round during import.'
            if name == 'account_sessions': transform += ' Security history only; invalidate source sessions, remap account_id by guard realm.'
            if target.startswith('legacy_'): transform += ' Archive-only table; no transport worker may replay these rows.'
            row['columns'].append({'source_column': original['name'], 'destination': target+'.'+c['name'],
                'disposition': disposition, 'transform': transform, 'target_type': c})
            cols.append(c)
        manifest.append(row)
        if name in EXISTING or target in tables: continue
        indexes = []
        for idx in seq(source_table['indexes']):
            if idx['primary'] and idx['columns'] == ['id']: continue
            # One order may have multiple attempts; holding uniqueness is generated below.
            if target == 'reservations' and idx['columns'] == ['source', 'website_order_number']: continue
            indexes.append({'columns': [rename(source, name, c) for c in idx['columns']],
                            'kind': 'primary' if idx['primary'] else ('unique' if idx['unique'] else 'index')})
        fks = []
        for fk in source_table['foreign_keys']:
            fks.append({'columns': [rename(source, name, c) for c in fk['columns']],
                        'table': target_name(source, fk['foreign_table']),
                        'references': fk['foreign_columns']})
        tables[target] = {'columns': cols, 'indexes': indexes, 'foreign_keys': fks, 'checks': []}


def column(name, kind='string', nullable=False, **kw):
    return {'name': name, 'type': kind, 'nullable': nullable, **kw}


def add(table, *cols): tables[table]['columns'].extend(cols)
def unique(table, *cols): tables[table]['indexes'].append({'columns': list(cols), 'kind': 'unique'})
def index(table, *cols): tables[table]['indexes'].append({'columns': list(cols), 'kind': 'index'})
def fk(table, column_name, other, refs=None):
    names = [column_name] if isinstance(column_name, str) else column_name
    if not any(f['columns'] == names for f in tables[table]['foreign_keys']):
        tables[table]['foreign_keys'].append({'columns': names, 'table': other, 'references': refs or ['id']})
def check(table, expression): tables[table]['checks'].append(expression)
def ident(name, nullable=False): return column(name, 'bigInteger', nullable, unsigned=True)
def textcol(name, nullable=False): return column(name, 'text', nullable)
def key(name, length=100, nullable=False): return column(name, nullable=nullable, length=length, binary=True)
def dt(name, nullable=True): return column(name, 'dateTime', nullable, precision=6)
def money(name, nullable=False): return column(name, 'decimal', nullable, total=19, places=2)
def currency(): return column('currency', 'char', length=3, default='PKR', binary=True)
def new(table, *cols):
    tables[table] = {'columns': [column('id', 'bigInteger', autoIncrement=True, unsigned=True), *cols],
                     'indexes': [], 'foreign_keys': [], 'checks': []}
def public(table):
    add(table, column('public_id', 'uuid', binary=True), column('version', 'bigInteger', unsigned=True, default=1))
    unique(table, 'public_id')


for t in ('outlets', 'admins', 'super_admins', 'users', 'products'):
    add(t, dt('archived_at'))
for t in ('outlets', 'products', 'stock_units', 'invoices', 'claims', 'orders', 'payments', 'product_listings', 'project_quotes', 'service_requests'):
    public(t)
new('customers', ident('website_user_id', True), column('display_name', length=255), column('email', nullable=True, length=255), column('mobile', nullable=True, length=40), dt('archived_at'), dt('created_at'), dt('updated_at'))
public('customers'); unique('customers', 'website_user_id'); fk('customers', 'website_user_id', 'users')
new('customer_source_links', ident('customer_id'), key('source_repository', 32), key('source_table', 64), key('source_primary_key', 191), key('verification_kind', 64), key('verified_actor_type', 40), ident('verified_actor_id'), dt('verified_at', False), textcol('verification_reason'))
unique('customer_source_links', 'source_repository', 'source_table', 'source_primary_key'); fk('customer_source_links', 'customer_id', 'customers')
add('product_listings', ident('product_id')); unique('product_listings', 'product_id'); fk('product_listings', 'product_id', 'products')
unique('products', 'id', 'outlet_id'); unique('outlet_admins', 'outlet_id', 'admin_id')
new('active_imeis', key('imei', 32), ident('stock_unit_id'), column('slot_no', 'smallInteger', unsigned=True))
tables['active_imeis']['columns'] = tables['active_imeis']['columns'][1:]
tables['active_imeis']['indexes'].append({'kind': 'primary', 'columns': ['imei']})
unique('active_imeis', 'stock_unit_id', 'slot_no'); fk('active_imeis', 'stock_unit_id', 'stock_units'); check('active_imeis', 'slot_no > 0')
for t in ('invoices', 'orders'): add(t, ident('customer_id', True)); fk(t, 'customer_id', 'customers')
add('invoices', ident('order_id', True), currency()); unique('invoices', 'order_id'); fk('invoices', 'order_id', 'orders')
add('order_items', ident('product_id', True), ident('outlet_id', True), ident('project_quote_id', True))
fk('order_items', 'product_id', 'products'); fk('order_items', 'outlet_id', 'outlets'); fk('order_items', 'project_quote_id', 'project_quotes')
fk('orders', 'stock_return_confirmed_by', 'users')
add('reservations', ident('order_id'), column('attempt', 'integer', unsigned=True, default=1), ident('outlet_id'))
add('reservations', column('holding_order_id', 'bigInteger', True, unsigned=True, storedAs="CASE WHEN state IN ('active', 'held_cod') THEN order_id ELSE NULL END"))
unique('reservations', 'order_id', 'attempt'); unique('reservations', 'holding_order_id'); fk('reservations', 'order_id', 'orders'); fk('reservations', 'invoice_id', 'invoices'); fk('reservations', 'outlet_id', 'outlets')
add('reservation_lines', ident('order_item_id'), ident('product_id'), ident('outlet_id'))
fk('reservation_lines', 'order_item_id', 'order_items'); fk('reservation_lines', 'product_id', 'products'); fk('reservation_lines', 'outlet_id', 'outlets')
unique('reservation_lines', 'reservation_id', 'order_item_id')
add('reservation_allocations', column('active_stock_unit_id', 'bigInteger', True, unsigned=True, storedAs='CASE WHEN released_at IS NULL THEN stock_unit_id ELSE NULL END'))
unique('reservation_allocations', 'active_stock_unit_id')
unique('stock_units', 'id', 'product_id')
unique('invoices', 'id', 'outlet_id')
unique('reservation_lines', 'id', 'product_id')
for t in ('product_imeis', 'stock_movements', 'claims', 'reservation_allocations'):
    fk(t, ['stock_unit_id', 'product_id'], 'stock_units', ['id', 'product_id'])
for t in ('sales', 'claims'):
    fk(t, ['invoice_id', 'outlet_id'], 'invoices', ['id', 'outlet_id'])
fk('reservation_allocations', ['reservation_line_id', 'product_id'], 'reservation_lines', ['id', 'product_id'])
for t in ('sales', 'claims', 'stock_acquisitions', 'stock_movements', 'reservation_lines', 'order_items'):
    fk(t, ['product_id', 'outlet_id'], 'products', ['id', 'outlet_id'])
check('order_items', '(product_id IS NULL AND outlet_id IS NULL) OR (product_id IS NOT NULL AND outlet_id IS NOT NULL)')
for t in ('products',):
    check(t, 'qty >= 0 AND sold_qty >= 0 AND price >= 0 AND purchase_price >= 0 AND sale_price >= 0')
for t in ('sales', 'claims', 'stock_acquisitions', 'order_items', 'reservation_lines', 'reservation_allocations'):
    check(t, 'quantity > 0')
check('reservation_lines', 'allocated_quantity >= 0 AND allocated_quantity <= quantity')
check('reservations', 'attempt > 0')
fk('site_managed_pages', 'social_image_media_id', 'site_media_assets')
add('payments', key('merchant', 100), key('mode', 20), key('attempt_key', 100))
unique('payments', 'order_id', 'attempt_key')
# Legacy gateway/reference lengths retained; binary composite fits 3072-byte InnoDB key.
unique('payments', 'gateway', 'merchant', 'mode', 'transaction_reference')
new('payment_receipts', ident('payment_id'), key('gateway', 255), key('merchant', 100), key('mode', 20), key('event_id', 255), key('transaction_reference', 255, True), money('amount'), currency(), key('payload_hash', 64), dt('received_at', False), dt('verified_at', False))
unique('payment_receipts', 'gateway', 'merchant', 'mode', 'event_id'); unique('payment_receipts', 'gateway', 'merchant', 'mode', 'transaction_reference'); fk('payment_receipts', 'payment_id', 'payments')
new('returns', ident('invoice_id'), ident('order_id', True), key('actor_type', 40), ident('actor_id'), textcol('reason'), key('status', 40), key('idempotency_key', 100), dt('created_at'), dt('updated_at'))
public('returns'); unique('returns', 'actor_type', 'actor_id', 'idempotency_key'); fk('returns', 'invoice_id', 'invoices'); fk('returns', 'order_id', 'orders')
new('return_lines', ident('return_id'), ident('sale_id'), ident('stock_unit_id', True), column('quantity', 'integer', unsigned=True), key('condition', 100), key('disposition', 40), dt('accepted_at'))
fk('return_lines', 'return_id', 'returns'); fk('return_lines', 'sale_id', 'sales'); fk('return_lines', 'stock_unit_id', 'stock_units'); unique('return_lines', 'return_id', 'stock_unit_id'); check('return_lines', 'quantity > 0')
new('refunds', ident('payment_id'), ident('return_id', True), money('amount'), currency(), key('status', 40), key('operation_key', 100), key('provider', 100), key('provider_reference', 255, True), key('evidence_hash', 64, True), dt('created_at'), dt('updated_at'))
public('refunds'); unique('refunds', 'operation_key'); fk('refunds', 'payment_id', 'payments'); fk('refunds', 'return_id', 'returns'); check('refunds', 'amount > 0')
new('idempotency_requests', key('actor_scope', 191), key('operation', 100), key('key', 191), key('request_hash', 64), key('resource_type', 80, True), ident('resource_id', True), key('status', 40), column('response', 'json', True), dt('response_expires_at'), dt('created_at'), dt('updated_at'))
unique('idempotency_requests', 'actor_scope', 'operation', 'key')
new('domain_events', key('aggregate_type', 80), key('aggregate_id', 100), ident('aggregate_version'), key('event_type', 100), column('payload', 'json'), key('operation_key', 191), dt('lease_until'), key('lease_token', 64, True), column('attempts', 'integer', unsigned=True, default=0), dt('next_attempt_at'), dt('completed_at'), dt('failed_at'), key('last_error_code', 100, True), dt('created_at'))
tables['domain_events']['columns'][0] = column('id', 'uuid', binary=True)
tables['domain_events']['indexes'].append({'kind': 'primary', 'columns': ['id']})
unique('domain_events', 'operation_key'); index('domain_events', 'completed_at', 'failed_at', 'next_attempt_at', 'lease_until')
new('publication_versions', key('domain', 80), column('version', 'bigInteger', unsigned=True, default=1), dt('updated_at'))
tables['publication_versions']['columns'] = tables['publication_versions']['columns'][1:]
tables['publication_versions']['indexes'].append({'kind': 'primary', 'columns': ['domain']})
new('document_sequences', key('namespace', 80), ident('outlet_id'), column('business_date', 'date'), column('next_sequence', 'bigInteger', unsigned=True, default=1))
unique('document_sequences', 'namespace', 'outlet_id', 'business_date'); fk('document_sequences', 'outlet_id', 'outlets'); check('document_sequences', 'next_sequence > 0')
new('resource_capabilities', key('resource_type', 80), ident('resource_id'), key('operation', 80), key('token_hash', 64), dt('expires_at', False), dt('revoked_at'), dt('created_at'))
unique('resource_capabilities', 'token_hash')
new('migration_runs', key('input_manifest_hash', 64), key('code_hash', 64), key('schema_hash', 64), key('target_identity', 191), key('status', 40), dt('started_at', False), dt('completed_at'))
new('migration_identity_map', key('source_repository', 32), key('source_table', 64), key('source_primary_key', 191), key('target_table', 64), key('target_id', 191), ident('run_id'), key('row_digest', 64), key('outcome', 40), textcol('reviewed_merge_reference', True))
unique('migration_identity_map', 'source_repository', 'source_table', 'source_primary_key', 'target_table'); index('migration_identity_map', 'target_table', 'target_id'); fk('migration_identity_map', 'run_id', 'migration_runs')
check('migration_identity_map', "outcome <> 'merged' OR reviewed_merge_reference IS NOT NULL")
new('migration_quarantine', ident('run_id'), key('source_repository', 32), key('source_table', 64), key('source_primary_key', 191), textcol('reason'), textcol('evidence_reference'), textcol('resolution', True))
new('migration_reconciliation', ident('run_id'), key('control_name', 191), textcol('source_value'), textcol('target_value'), key('result', 40), key('evidence_hash', 64))
new('migration_source_history', ident('run_id'), key('source_repository', 32), key('source_migration', 255), key('source_hash', 64), column('source_batch', 'integer', unsigned=True))
unique('migration_source_history', 'run_id', 'source_repository', 'source_migration')
for t in ('migration_quarantine', 'migration_reconciliation', 'migration_source_history'): fk(t, 'run_id', 'migration_runs')
for t in ('orders', 'payments', 'payment_receipts', 'reservations', 'invoices', 'refunds', 'project_quotes'):
    check(t, "currency = 'PKR'")
for t in ('payments', 'payment_receipts'): check(t, 'amount >= 0')


def php(value):
    if value is None: return 'null'
    if isinstance(value, bool): return 'true' if value else 'false'
    if isinstance(value, (int, float)): return str(value)
    if isinstance(value, list): return '[' + ', '.join(php(x) for x in value) + ']'
    return "'" + str(value).replace('\\', '\\\\').replace("'", "\\'") + "'"


def declaration(c):
    kind = c['type']; args = [php(c['name'])]
    if c.get('autoIncrement') and c['name'] == 'id': return "$table->id();"
    if kind in {'string', 'char'}: args.append(str(c.get('length', 255)))
    if kind == 'decimal': args += [str(c['total']), str(c['places'])]
    if kind == 'dateTime': args.append('6')
    if kind == 'enum': args.append(php(c['allowed']))
    statement = '$table->'+kind+'('+', '.join(args)+')'
    if c.get('unsigned'): statement += '->unsigned()'
    if c.get('binary'): statement += "->collation('utf8mb4_bin')"
    if c.get('storedAs'): return statement+'->storedAs('+php(c['storedAs'])+');'
    if c.get('nullable'): statement += '->nullable()'
    if 'default' in c:
        statement += '->useCurrent()' if c['default'] == 'CURRENT_TIMESTAMP' else '->default('+php(c['default'])+')'
    return statement+';'


header = '''<?php

// MT-2.1: adapted from pinned source schemas; no source data or backfills execute here.
use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
'''
lines = [header]
for name, table in tables.items():
    lines += [f"        Schema::create('{name}', function (Blueprint $table) {{", "            $table->engine = 'InnoDB';", "            $table->charset = 'utf8mb4';", "            $table->collation = 'utf8mb4_unicode_ci';"]
    lines += ['            '+declaration(c) for c in table['columns']]
    for n, idx in enumerate(table['indexes']):
        lines.append('            $table->'+idx['kind']+'('+php(idx['columns'])+', '+php(f'mt21_{name}_{n}')+');')
    lines += ['        });', '']
lines += ['    }', '', '    public function down(): void', '    {']
lines += [f"        Schema::dropIfExists('{name}');" for name in reversed(tables)]
lines += ['    }', '};', '']
migration_dir = ROOT / 'backend/database/migrations'
(migration_dir/'2026_08_31_100000_create_shared_schema.php').write_text('\n'.join(lines), encoding='utf-8', newline='\n')
lines = [header]; reverse = []
for name, table in tables.items():
    for n, relation in enumerate(table['foreign_keys']):
        label = f'mt21_fk_{name}_{n}'
        lines += [f"        Schema::table('{name}', function (Blueprint $table) {{", '            $table->foreign('+php(relation['columns'])+', '+php(label)+')->references('+php(relation['references'])+')->on('+php(relation['table'])+")->restrictOnDelete()->restrictOnUpdate();", '        });']
        reverse.append(f"        Schema::table('{name}', fn (Blueprint $table) => $table->dropForeign('{label}'));")
    for n, constraint in enumerate(table['checks']):
        label = f'mt21_check_{name}_{n}'
        lines.append('        DB::statement('+php(f'ALTER TABLE `{name}` ADD CONSTRAINT `{label}` CHECK ({constraint})')+');')
        reverse.append('        DB::statement('+php(f'ALTER TABLE `{name}` DROP CHECK `{label}`')+');')
lines += ['    }', '', '    public function down(): void', '    {', *reversed(reverse), '    }', '};', '']
(migration_dir/'2026_08_31_100100_constrain_shared_schema.php').write_text('\n'.join(lines), encoding='utf-8', newline='\n')
for row in manifest:
    for c in row['columns']:
        c['target_type'] = {k: v for k, v in c['target_type'].items() if k in {'name','type','length','total','places','precision','unsigned','nullable','binary'}}
(OUT/'COLUMN_DESTINATIONS.json').write_text(json.dumps({'point': 'MT-2.1', 'source_tables': len(manifest), 'source_columns': sum(len(t['columns']) for t in manifest), 'policy': 'Fail closed on unknown columns and invalid values. This manifest is not an importer or authorization for live data. Runtime fields are deliberately reset; inactive history never grants access.', 'tables': manifest}, indent=2)+'\n', encoding='utf-8')
(OUT/'TARGET_SCHEMA.json').write_text(json.dumps({'point': 'MT-2.1', 'tables': tables}, indent=2)+'\n', encoding='utf-8')
print(f'Compiled {len(manifest)} source tables / {sum(len(t["columns"]) for t in manifest)} columns into {len(tables)} new shared tables and two migrations.')
