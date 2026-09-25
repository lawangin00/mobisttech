from __future__ import annotations
import argparse,collections,json,re
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
INVENTORY=ROOT/'docs/migration/SOURCE_SYMBOL_INVENTORY.json'
OUT=ROOT/'docs/audit/MT-7.5_H01_EXACT_ROUTE_EVIDENCE_2026-09-25.json'
ROUTE_DOCS={
'P01':'docs/audit/MT-7.5_P01_ROUTE_CROSSWALK.md',
'P02':'docs/audit/MT-7.5_P02_ROUTE_CROSSWALK.md',
'P03':'docs/audit/MT-7.5_H01_P03_INVENTORY_ROUTE_DISPOSITION_2026-09-25.md',
'P04':'docs/audit/MT-7.5_P04_SALES_RETURNS_PARITY.md',
'P05':'docs/audit/MT-7.5_P05_WARRANTY_CLAIMS_PARITY.md',
'P06':'docs/audit/MT-7.5_H01_P06_REPORTING_ROUTE_DISPOSITION_2026-09-25.md',
'P07':'docs/audit/MT-7.5_P07_SOURCE_CROSSWALK.md',
'P08':'docs/audit/MT-7.5_H01_P08_BACKUP_ROUTE_DISPOSITION_2026-09-25.md',
'W01':'docs/audit/MT-7.5_H01_W01_IDENTITY_ROUTE_DISPOSITION_2026-09-25.md',
'W02':'docs/audit/MT-7.5_H01_W02_CATALOGUE_ROUTE_DISPOSITION_2026-09-25.md',
'W03':'docs/audit/MT-7.5_H01_W03_CART_ORDER_ROUTE_DISPOSITION_2026-09-25.md',
'W04':'docs/audit/MT-7.5_W04_ROUTE_CROSSWALK.md',
'W05':'docs/audit/MT-7.5_W05_SOURCE_BEHAVIOR_DISPOSITION_2026-09-24.md',
'W06':'docs/audit/MT-7.5_W06_SOURCE_BEHAVIOR_DISPOSITION_2026-09-24.md',
'W07':'docs/audit/MT-7.5_W07_SOURCE_BEHAVIOR_DISPOSITION_2026-09-25.md',
'W08':'docs/audit/MT-7.5_H01_W08_ROUTE_DISPOSITION_2026-09-25.md',
'X01':'docs/audit/MT-7.5_X01_RESERVATION_PARITY.md',
}
def key(method,uri):
    uri=uri.lstrip('/') if len(uri)>1 else uri
    return (method.replace('/HEAD','|HEAD').replace('\\|','|'),uri)
def marked_rows(family,path):
    s=(ROOT/path).read_text(encoding='utf-8');pairs=[]
    if family in ('P04','P05','X01'):
        # P04 previously accepted eight route-specific lines, using GET shorthand instead of GET|HEAD.
        for m in re.finditer(r'^\| `((?:GET|POST|PATCH)) ([^` ]+)` \|',s,re.M):
            method=m.group(1)+'|HEAD' if m.group(1)=='GET' else m.group(1)
            pairs.append((key(method,m.group(2)),s[:m.start()].count('\n')+1))
    elif family=='W08':
        # These two audited rows have original method/URI but lack numbered table row (and include action).
        for match in re.finditer(r'^\| `((?:GET|POST)(?:\|HEAD)?) ([^` ]+)` \u2014 `([^`]+)` \|',s,re.M):
            pairs.append((key(match.group(1),match.group(2)),s[:match.start()].count('\n')+1))
    else:
        for n,line in enumerate(s.splitlines(),1):
            m=re.match(r'^\|\s*0*(\d+)\s*\|\s*`([^`]+)`\s*\|',line)
            if not m:continue
            r=re.match(r'^((?:GET|POST|PUT|PATCH|DELETE|HEAD)(?:\\?\|HEAD|/HEAD|\\?\|POST|\\?\|PUT|\\?\|PATCH|\\?\|DELETE|\\?\|OPTIONS)*?)\s+(.+)$',m.group(2))
            if not r:continue
            pairs.append((key(r.group(1),r.group(2)),n))
    return pairs

def main():
    parser=argparse.ArgumentParser();parser.add_argument('--check',action='store_true');a=parser.parse_args()
    inventory=json.loads(INVENTORY.read_text(encoding='utf-8'))
    routes=inventory['routes'];assert len(routes)==316
    by_family={}
    for f,doc in ROUTE_DOCS.items():
        expected=collections.Counter(key(r['method'],r['uri']) for r in routes if f in r['families'])
        rows=marked_rows(f,doc)
        got=collections.Counter(k for k,_ in rows)
        assert expected==got,(f,expected-got,got-expected)
        by_family[f]={k:line for k,line in rows}
    found=[];left=[]
    for i,r in enumerate(routes,1):
        k=key(r['method'],r['uri']);direct={f:{'doc':ROUTE_DOCS[f],'line':by_family[f][k]} for f in r['families'] if f in by_family}
        # Include source+uri in the immutable route locator; family doc may reference a duplicate original method+path under another source.
        row={'original_route_number':i,'source':r['source'],'method':r['method'],'uri':r['uri'],'action':r['action'],'families':r['families'],'prior_exact_route_disposition':direct,
          'remaining_evidence':'P01 independent family still OPEN' if 'P01' in r['families'] else 'family-level accepted; exact original route-to-target disposition NOT yet individually evidenced' if not direct else 'prior accepted family proof only; no new test'}
        (found if direct else left).append(row)
    assert len(found)+len(left)==316
    counts={'source_routes':316,'matched_preexisting_exact_rows':len(found),'still_only_group_family_disposition':len(left),
            'family_exact_doc_rows':{f:len(by_family[f]) for f in by_family},
            'pending_by_primary_family':dict(collections.Counter(next((f for f in r['families'] if f!='F01'),r['families'][0]) for r in left))}
    result={'schema':1,'purpose':'Reuse existing and bounded H01 original method/URI family review, WITHOUT re-executing accepted source/target tests or conflating 177 mapped routes with completed behavior parity',
        'counts':counts,'previous_exact_route_dispositions':found,'pending_exact_route_review':left,'first_pending':'All 316 source method/URI rows now have explicit original-to-target review with traceable evidence, but route identity is NOT full behavior equivalence. P08 local-backup operator UI is now locally accepted; first open original-behavior gap is W01 Website Admin activity log (50/page search/method under website.audit.view), then original combined dashboard CSV/KPI equivalence, P03 two product/private acquisition operator paths and W02 four catalogue presentation actions; link tests only for changed contracts. P01 family and Q01 milestone remain independently OPEN.'}
    text=json.dumps(result,ensure_ascii=False,indent=2)+'\n'
    if a.check:assert OUT.read_text(encoding='utf-8')==text,'H01 exact-route reuse evidence changed'
    else:OUT.write_text(text,encoding='utf-8',newline='\n')
    print('H01_EXACT_ROUTE_REUSE_'+('CHECK_' if a.check else '')+'PASS',json.dumps(counts,sort_keys=True))
if __name__=='__main__':main()
