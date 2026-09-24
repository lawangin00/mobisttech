from __future__ import annotations
import collections,json,re
from pathlib import Path
root=Path(__file__).resolve().parents[2]
read=lambda relative:(root/relative).read_text(encoding='utf-8')
trace=json.loads(read('docs/audit/MT-7.5_H01_SOURCE_TRACE_2026-09-25.json'))
routes=json.loads(read('docs/audit/MT-7.5_H01_EXACT_ROUTE_EVIDENCE_2026-09-25.json'))
old=json.loads(read('docs/audit/MT-7.5_H01_LEGACY_DOC_PROVENANCE_2026-09-25.json'))
inv=json.loads(read('docs/migration/SOURCE_SYMBOL_INVENTORY.json'))
assert (len(trace['files']),len(trace['routes']))==(1224,316)
assert len({(r['source'],r['original_path']) for r in trace['files']})==1224
assert len({(r['source'],r['original_method'],r['original_uri'],r['original_action']) for r in trace['routes']})==316
assert all(f['family_ids'] for f in trace['files']) and all(r['family_ids'] for r in trace['routes'])
assert len(routes['previous_exact_route_dispositions'])==252 and len(routes['pending_exact_route_review'])==64
assert len(old['source_documents'])==45
assert old['summary']['pinned_blob_read_only_verified']==8 and old['summary']['pinned_local_read_only_verified']==37
assert all('source' in row and 'original_path' in row and 'pinned_sha256' in row and row['audited_content_origin'] in ('matching_local_source_file_read_only','pinned_git_blob') for row in old['source_documents'])
assert all(item['local_read_only_sha_matches_pinned']==(item['audited_content_origin']=='matching_local_source_file_read_only') for item in old['source_documents'])
assert len([r for r in inv['routes'] if 'P08' in r['families']])==19
assert 'P08 exact route map identifies backend-only per-backup history/download/delete' in routes['first_pending']
assert 'exact source' in read('docs/audit/MT-7.5_H01_P08_BACKUP_ROUTE_DISPOSITION_2026-09-25.md').lower() or 'original POS P08 has **19 source routes**' in read('docs/audit/MT-7.5_H01_P08_BACKUP_ROUTE_DISPOSITION_2026-09-25.md')
assert 'historical initial-setup checkpoint' in read('README.md')
assert 'Historical MT-1.3 foundation checkpoint' in read('docs/foundation/WINDOWS_SETUP.md')
assert '19/27 DONE, 8 OPEN' in read('docs/PROJECT_IMPLEMENTATION_STATUS.md')
assert '21/H01 OPEN' in read('docs/audit/MT-7.5_H01_FINITE_ACCEPTANCE_2026-09-25.md')
assert 'P01' in trace['status_boundary'] and 'Q01' in trace['status_boundary'] and 'OPEN' in trace['status_boundary']
assert len(re.findall(r'^\|\s*\d+\s*\|',read('docs/audit/MT-7.5_P01_ROUTE_CROSSWALK.md'),re.M))==54
print('H01_DOCUMENT_AUTHORITY_AND_TRACE_GUARD_PASS 1224 files 316 source routes 252 exact route rows 64 pending 45 verified provenance originals P01/Q01 OPEN')
