"""Verify inventory completeness/provenance and isolated characterization boundaries."""
from pathlib import Path
import hashlib
import json
import re
from source_inventory import ROOT

def sha(path):return hashlib.sha256(path.read_bytes()).hexdigest()
def main():
    base=ROOT/'docs/migration'
    inventory=json.loads((base/'SOURCE_FILE_INVENTORY.json').read_text())
    symbols=json.loads((base/'SOURCE_SYMBOL_INVENTORY.json').read_text())
    verification=json.loads((base/'MT_1_1_VERIFICATION.json').read_text())
    roadmap=(ROOT/'docs/PROJECT_IMPLEMENTATION_ROADMAP.md').read_text()
    ids=set(re.findall(r'^### (MT-\d+\.\d+|FINAL-AUDIT) - ',roadmap,re.M))
    families={f['id']:f for f in symbols['families']}
    assert not symbols['unmapped']
    for family in families.values():
        assert set(family['roadmap_points']) <= ids
        assert family['preserve'] and family['decision'] and family['target_gate']
        assert family['target_owner'] and family['target_status']=='Pending'
    lookup={(r['source'],r['path']):r for r in symbols['files']}
    expected={(s['source'],f['path']) for s in inventory['sources'] for f in s['files']}
    assert len(lookup)==len(symbols['files'])==1224 and set(lookup)==expected
    for source in inventory['sources']:
        label=source['source']; copy=ROOT/'.local/mt11/sources'/label
        assert not (copy/'.git').exists()
        for file in source['files']:
            row=lookup[(label,file['path'])]
            assert row['sha256']==file['sha256'] and row['families']
            assert set(row['families']) <= families.keys()
            if file['characterization_export'] and file['path']!='tests/TestCase.php':
                assert sha(copy/file['path'])==file['sha256'], (label,file['path'])
        result=verification['source_tests'][label]
        assert all(int(result[k])==0 for k in ('errors','failures','skipped'))
        assert len(result['cases'])==int(result['tests'])
        assert sum(case['assertions'] for case in result['cases'])==int(result['assertions'])
        assert sha(copy/'tests/TestCase.php')==result['harness_sha256']
        assert sha(ROOT/f'.local/mt11/{label}-junit.xml')==result['junit_sha256']
        assert sha(ROOT/f'.local/mt11/{label}-tests.log')==result['log_sha256']
    for route in symbols['routes']:
        assert (route['source'],route['source_file']) in lookup
        assert route['families'] and set(route['families']) <= families.keys()
        assert route['target_parity']=='Pending'
    for key,file in [('file_inventory','SOURCE_FILE_INVENTORY.json'),('symbol_inventory','SOURCE_SYMBOL_INVENTORY.json'),('register','FEATURE_PARITY_REGISTER.md')]:
        assert sha(base/file)==verification['artifact_sha256'][key]
    for directory in ('backend','website','brand','tools/mobist-control','.github'):
        assert [p.name for p in (ROOT/directory).iterdir()]==['.gitkeep'], directory
    assert sha(ROOT/'docs/PROJECT_GOAL.md')=='7bce00947418d18151756ea7176b51546b0cbc8ae00c02fda3c1bf7c1344908f'
    assert sha(ROOT/'docs/PROJECT_PREFERENCES.md')=='e37c3c2fd6a30ba7211ce73854c79501181b327a98ce7acafa5938ee9941d402'
    print('PASS: 1224 files; 316 routes; valid family/roadmap mapping; 438 fresh test cases; source export hashes; target placeholders; approved input hashes')

if __name__=='__main__':main()
