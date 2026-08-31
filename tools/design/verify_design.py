"""Validate design artifacts only. Does not connect to or implement an application."""
from pathlib import Path
import copy
from decimal import Decimal
import hashlib
import itertools
import json
import re
import sys

ROOT=Path(__file__).resolve().parents[2]
sys.path.insert(0,str(ROOT/'.local/mt12/python-packages'))
from openapi_spec_validator import validate_spec
from jsonschema import Draft202012Validator, FormatChecker

def load(path):return json.loads((ROOT/path).read_text())
def sha(path):return hashlib.sha256((ROOT/path).read_bytes()).hexdigest()

def main():
    design=load('docs/design/SCHEMA_MAPPING.json')
    spec=load('docs/design/openapi.json')
    acceptance=load('docs/design/DESIGN_ACCEPTANCE_CASES.json')
    source=load('docs/migration/SOURCE_SYMBOL_INVENTORY.json')
    validate_spec(spec)
    assert design['source_inventory_sha256']==sha('docs/migration/SOURCE_SYMBOL_INVENTORY.json')
    expected_migrations={(x['source'],x['path']) for x in source['files'] if '/migrations/' in x['path']}
    assert {(x['source'],x['path']) for x in design['migration_files']}==expected_migrations
    expected_models={(x['source'],Path(x['path']).stem) for x in source['files'] if x['path'].startswith('app/Models/')}
    assert {(x['source'],x['model']) for x in design['models']}==expected_models
    expected_tables={(x['source'],table) for x in source['files'] for line in x.get('schema',[]) for table in re.findall(r"Schema::(?:create|table)\(['\"]([^'\"]+)",line['declaration'])}
    assert {(x['source'],x['table']) for x in design['tables']}==expected_tables
    assert all((x['source'],x['source_table']) in expected_tables for x in design['models'])
    expected_routes={(x['source'],x['method'],x['uri'],x['action']) for x in source['routes']}
    assert {(x['source'],x['method'],x['uri'],x['action']) for x in design['route_dispositions']}==expected_routes
    assert all(x['target_tables'] and x['mode'] for x in design['tables'])
    table_map={(x['source'],x['table']):x['target_tables'] for x in design['tables']}
    assert table_map['pos','users']==['outlets'] and table_map['website','users']==['users']
    assert table_map['website','products']==['products','product_listings']
    assert table_map['pos','website_orders']==['reservations']
    assert all(r['implementation_status']=='Pending' for r in design['route_dispositions'])
    ids=set(re.findall(r'^### (MT-\d+\.\d+|FINAL-AUDIT) - ',(ROOT/'docs/PROJECT_IMPLEMENTATION_ROADMAP.md').read_text(),re.M))
    assert all(c['implementation_gate'] in ids and c['status']=='Pending implementation test' for c in acceptance['cases'])
    assert len({c['id'] for c in acceptance['cases']})==len(acceptance['cases'])
    operations=[]
    for path,item in spec['paths'].items():
        for method,op in item.items():
            operations.append(op)
            assert op['x-target-status']=='Pending'
            declared={p['name'] for p in op['parameters'] if p['in']=='path' and p['required']}
            assert declared==set(re.findall(r'{(\w+)}',path))
            headers={p['name'] for p in op['parameters'] if p['in']=='header' and p.get('required')}
            if method!='get':
                assert op['x-csrf-required'] or op['x-provider-verification-required']
                if op['x-csrf-required']:assert 'X-XSRF-TOKEN' in headers
                if op['x-idempotency-required']:assert 'Idempotency-Key' in headers
            if op['x-policy'] not in ('public','guest','verified_provider'):assert op['security']
    assert len({op['operationId'] for op in operations})==len(operations)
    def validator(name):
        schema={'$schema':'https://json-schema.org/draft/2020-12/schema','$ref':'#/components/schemas/'+name,'components':spec['components']}
        return Draft202012Validator(schema,format_checker=FormatChecker())
    good_checkout={'cart_id':'cart-example','cart_version':1,'outlet_id':'outlet-example','lines':[{'product_id':'product-example','variant_key':'standard','quantity':1,'expected_unit_price':'100.00'}],
      'customer':{'name':'Synthetic Customer','mobile':'03000000000'},'delivery':{'city':'Test City','address':'Synthetic address only'},'gateway':'cod','currency':'PKR'}
    examples=[('CheckoutInput',good_checkout,True),('Money','0.30',True),('Money','99999999999999999.99',True),
      ('Money',0.30,False),('Money','1e2',False),('Money','0.001',False),('Money','-1.00',False),('Money','100000000000000000.00',False),
      ('CartLineInput',{'product_id':'p','variant_key':'standard','quantity':99},True),
      ('QuantityInput',{'quantity':100},False),('LoginInput',{'email':'bad-email','password':'example'},False)]
    for change in ('payment_status','currency','quantity','price','empty','more_lines','extra_role'):
        bad=copy.deepcopy(good_checkout)
        if change=='payment_status':bad['payment_status']='paid'
        elif change=='currency':bad['currency']='USD'
        elif change=='quantity':bad['lines'][0]['quantity']=21
        elif change=='price':bad['lines'][0]['expected_unit_price']=100
        elif change=='empty':bad['lines']=[]
        elif change=='more_lines':bad['lines']*=51
        else:bad['customer']['is_admin']=True
        examples.append(('CheckoutInput',bad,False))
    for name,example,valid in examples:
        errors=list(validator(name).iter_errors(example))
        assert bool(errors)!=valid,(name,example,errors)
    public_props=spec['components']['schemas']['Product']['properties']
    assert not set(public_props)&{'purchase_price','cost','imei','customer_cnic','credentials','password'}
    assert Decimal('0.10')+Decimal('0.20')==Decimal('0.30')
    # Explore the specification transition graph, including repeated/reordered terminal events.
    transitions=acceptance['reservation_transitions']; traces=0
    for start,events in [('active',('confirm','expire','release')),('held_cod',('collect','cancel','expire'))]:
        for sequence in itertools.product(events,repeat=5):
            state=start; consumed=0;released=0
            for event in sequence:
                destination=transitions[state].get(event)
                if destination is None:continue
                state=destination;consumed+=state=='consumed';released+=state in ('released','expired')
            assert consumed<=1 and released<=1 and not(consumed and released)
            if start=='held_cod' and set(sequence)=={'expire'}:assert state=='held_cod'
            traces+=1
    # Collision/import rules are structural design checks, not a data rehearsal.
    assert len({(x['source'],x['table']) for x in design['tables']})==len(design['tables'])
    for directory in ('backend','website','brand','tools/mobist-control','.github'):
        assert [p.name for p in (ROOT/directory).iterdir()]==['.gitkeep']
    assert sha('docs/PROJECT_GOAL.md')=='7bce00947418d18151756ea7176b51546b0cbc8ae00c02fda3c1bf7c1344908f'
    assert sha('docs/PROJECT_PREFERENCES.md')=='e37c3c2fd6a30ba7211ce73854c79501181b327a98ce7acafa5938ee9941d402'
    report={'point':'MT-1.2','result':'PASS','scope':'Specification validation only; no target runtime, MySQL concurrency, provider or migration acceptance claimed',
      'gates':{'openapi_specification':'OpenAPI 3.1.1 validated by openapi-spec-validator 0.7.2','source_tables':len(expected_tables),'source_models':len(expected_models),'source_migrations':len(expected_migrations),'source_routes':len(expected_routes),
        'api_operations':len(operations),'positive_negative_schema_examples':len(examples),'reservation_event_traces':traces,'pending_implementation_cases':len(acceptance['cases']),
        'source_qualified_identity_mapping':True,'unsafe_operation_security_metadata':True,'target_placeholders_unchanged':True,'approved_inputs_unchanged':True},
      'artifact_sha256':{str(p.relative_to(ROOT)).replace('\\','/'):hashlib.sha256(p.read_bytes()).hexdigest() for p in sorted((ROOT/'docs/design').iterdir()) if p.name not in ('DESIGN_VALIDATION.json','CHECKPOINT_VERIFICATION.json') and p.is_file()}}
    (ROOT/'docs/design/DESIGN_VALIDATION.json').write_text(json.dumps(report,indent=2)+'\n',encoding='utf-8',newline='\n')
    print(json.dumps(report['gates'],indent=2))

if __name__=='__main__':main()
