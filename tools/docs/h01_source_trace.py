from __future__ import annotations
import argparse, collections, hashlib, json
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
SYMBOL=ROOT/'docs/migration/SOURCE_SYMBOL_INVENTORY.json'
ORIGINAL=ROOT/'docs/migration/SOURCE_FILE_INVENTORY.json'
OUTPUT=ROOT/'docs/audit/MT-7.5_H01_SOURCE_TRACE_2026-09-25.json'
EVIDENCE={
'P01':'docs/audit/MT-7.5_P01_ROUTE_CROSSWALK.md',
'P02':'docs/audit/MT-7.5_P02_ROUTE_CROSSWALK.md',
'P03':'docs/audit/MT-7.5_P03_STOCK_PARITY.md',
'P04':'docs/audit/MT-7.5_P04_SALES_RETURNS_PARITY.md',
'P05':'docs/audit/MT-7.5_P05_WARRANTY_CLAIMS_PARITY.md',
'P06':'docs/audit/MT-7.5_P06_REPORTING_DOCUMENTS_PARITY.md',
'P07':'docs/audit/MT-7.5_P07_SOURCE_CROSSWALK.md',
'P08':'docs/deploy/MT-7.4_VERIFICATION.md',
'X01':'docs/audit/MT-7.5_X01_RESERVATION_PARITY.md',
'W01':'docs/audit/MT-7.5_CLOSURE_GATES.md',
'W02':'docs/audit/MT-7.5_W02_ROUTE_PARITY.md',
'W03':'docs/audit/MT-7.5_W03_CART_ORDER_REVIEW_PARITY.md',
'W04':'docs/audit/MT-7.5_W04_SOURCE_ROUTE_DISPOSITION.md',
'W05':'docs/audit/MT-7.5_W05_DEVELOPMENT_CLOSURE_2026-09-24.md',
'W06':'docs/audit/MT-7.5_W06_DEVELOPMENT_CLOSURE_2026-09-25.md',
'W07':'docs/audit/MT-7.5_W07_DEVELOPMENT_CLOSURE_2026-09-25.md',
'W08':'docs/deploy/MT-7.4_VERIFICATION.md',
'B01':'docs/brand/MT-6.1_VERIFICATION.md',
'C01':'docs/control/MT-6.3_VERIFICATION.md',
'F01':'docs/schema/README.md',
'Q01':'docs/audit/MT-7.5_FAST_TRACK_REVIEW.md',
'H01':'docs/migration/FEATURE_PARITY_REGISTER.md',
}
OPEN={'P01','Q01','H01'}

def disposition(x):
    c=x['category']; d=x['decision']
    if c=='historical-documentation' and x['path']=='NOTICE.md':return 'preserve original third-party attribution provenance for G-L release notice review; source NOTICE cannot supply target application license or operative runbook'
    if c=='historical-documentation':return 'provenance only; retire source document as current target instructions; check binding target authorities and retain any separately required licenses/notices'
    if c=='characterization-tests':return 'retain assertion provenance; target tests/adapted behavior only; source PHPUnit is not executable target acceptance'
    if d=='inspect-runtime-dependencies':return 'source runtime dependency is reference only; install target pinned dependencies fresh, do not copy vendor/node_modules'
    if d=='deduplicate-to-root-brand':return 'use approved target brand master/derivative manifest; source asset is not an approved new master'
    if d=='adapt-single-control':return 'canonical target Control with exact owned PID/path; retire duplicate source executables/launchers'
    if d in {'adapt-isolated-configuration','adapt-pinned-toolchain','adapt-monorepo-ci'}:return 'adapt to independent target environment/configuration/CI; do not reuse source secrets, database, remotes or deploy commands'
    return 'reuse/adapt required business contract through approved family target; retire original source transport/route only against family evidence'

def build():
    raw=ORIGINAL.read_bytes();sy=SYMBOL.read_bytes(); old=json.loads(raw);sym=json.loads(sy)
    refs={ (item['source'],item['path']):item for group in old['sources'] for item in group['files'] }
    fs=sym['files'];rs=sym['routes'];families={f['id']:f for f in sym['families']}
    assert set(families)==set(EVIDENCE) and len(fs)==len(refs)==1224 and len(rs)==316
    assert len({(x['source'],x['path']) for x in fs})==1224
    assert not sym['unmapped']
    assert sum(x['source']=='pos' for x in fs)==824 and sum(x['source']=='website' for x in fs)==400
    assert all((ROOT/path).is_file() for path in EVIDENCE.values())
    seen={};ff=[]
    for x in fs:
        key=x['source'],x['path'];o=refs[key];assert x['sha256']==o['sha256'] and x['families'] and all(f in families for f in x['families']);seen[key]=1
        ff.append({'source':x['source'],'original_path':x['path'],'pinned_sha256':x['sha256'],
            'pinned_git_blob':o['git_blob'],'category':o['category'],'original_inventory_decision':o['decision'],
            'target_disposition':disposition(o),'family_ids':x['families'],
            'unclosed_family_gates':[f for f in x['families'] if f in OPEN]})
    rr=[]
    for i,x in enumerate(rs,1):
        assert x['families'] and all(f in families for f in x['families']);assert (x['source'],x['source_file']) in refs
        assert isinstance(x['uri'],str) and isinstance(x['method'],str)
        rr.append({'source_route_index':i,'source':x['source'],'original_method':x['method'],
            'original_uri':x['uri'],'original_action':x['action'],'original_middleware':x['middleware'],
            'original_source_file':x['source_file'],'family_ids':x['families'],
            'unclosed_family_gates':[f for f in x['families'] if f in OPEN]})
    assert len(ff)==1224 and len(rr)==316 and len({(r['source'],r['original_method'],r['original_uri'],r['original_action']) for r in rr})==316
    summary={'source_files':len(ff),'source_routes':len(rr),'per_source_files':dict(collections.Counter(x['source'] for x in ff)),
      'per_source_routes':dict(collections.Counter(x['source'] for x in rr)),
      'source_files_with_P01_open':sum('P01' in x['unclosed_family_gates'] for x in ff),
      'source_routes_with_P01_open':sum('P01' in x['unclosed_family_gates'] for x in rr),
      'source_files_with_Q01_open':sum('Q01' in x['unclosed_family_gates'] for x in ff),
      'source_files_with_H01_provenance':sum('H01' in x['unclosed_family_gates'] for x in ff),
      'unmapped_files':0,'unmapped_routes':0}
    return {'schema':1,'scope':'H01 frozen source-path/family trace; NOT independent file-by-file behavior acceptance',
        'source_inventory_sha256':hashlib.sha256(raw).hexdigest(),'source_symbol_sha256':hashlib.sha256(sy).hexdigest(),
        'source_commits':{x['source']:x['commit'] for x in old['sources']},
        'family_target_contracts':{fid:{'target_owner':fam['target_owner'],'target_gate':fam['target_gate'],'decision':fam['decision'],'prior_evidence_reference':EVIDENCE[fid],'family_gate':'OPEN' if fid in OPEN else 'PRIOR_DEVELOPMENT_ACCEPTED'} for fid,fam in families.items()},
        'item_evidence_limit':'Each item maps to its original category, frozen target decision, every family and family-level evidence pointer; it is NOT newly verified source-to-target behavior. H01 cannot retire unfinished P01 or substitute for final Q01.',
        'status_boundary':'H01 OPEN; P01 OPEN and Q01 final acceptance OPEN; all other previously accepted family evidence reused unchanged',
        'summary':summary,'files':ff,'routes':rr}

def main():
    ap=argparse.ArgumentParser();ap.add_argument('--check',action='store_true');args=ap.parse_args()
    payload=build();encoded=json.dumps(payload,ensure_ascii=False,indent=2)+'\n'
    if args.check:
        assert OUTPUT.read_text(encoding='utf-8')==encoded, 'H01 source trace does not match pinned inventory or approved family owners/evidence'
    else:OUTPUT.write_text(encoded,encoding='utf-8',newline='\n')
    print('H01_PINNED_FILE_ROUTE_TRACE_'+('CHECK_' if args.check else '')+'PASS',json.dumps(payload['summary'],sort_keys=True),'bytes',len(encoded.encode('utf-8')))
if __name__=='__main__':main()
