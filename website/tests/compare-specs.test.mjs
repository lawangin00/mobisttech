import test from 'node:test';
import assert from 'node:assert/strict';
import { publicCompareEligible, publicCompareSpec, resolveCompareCategory, sameCategoryProducts } from '../src/lib/compare-specs.ts';

const phone = { category: { code: 'mobile_phone' }, device: {ram_gb:8,storage_gb:128,sim:'Single SIM'}, variants: [
  {color:'Black',condition:'used',pta_status:'pta_approved'},
  {color:'Blue',condition:'brand_new',pta_status:'non_pta'},
  {color:'Black',condition:'used',pta_status:'pta_approved'},
]};

test('compare safely formats POS-managed hardware and distinct public variant attributes', () => {
  assert.equal(publicCompareSpec(phone,'ram'),'8 GB');
  assert.equal(publicCompareSpec(phone,'storage'),'128 GB');
  assert.equal(publicCompareSpec(phone,'sim'),'Single SIM');
  assert.equal(publicCompareSpec(phone,'condition'),'used / brand_new');
  assert.equal(publicCompareSpec(phone,'pta'),'pta_approved / non_pta');
  assert.equal(publicCompareSpec(phone,'colors'),'Black / Blue');
});

test('missing hardware and unknown or absent public variant attributes show safe placeholders', () => {
  const accessory = {category:{code:'accessory'},device:{ram_gb:null,storage_gb:null,sim:null},variants:[{color:null,condition:'Unknown',pta_status:'N/A'}]};
  for (const field of ['ram','storage','sim','condition','pta','colors']) assert.equal(publicCompareSpec(accessory,field),'—');
  assert.equal(publicCompareEligible(accessory),true);
  assert.equal(publicCompareEligible({category:{code:'tablet'}}),true);
  assert.equal(publicCompareEligible({category:{code:'unrecognized'}}),false);
});

// Original Website source only selected mobiles/tablets. Existing target accessory
// comparison is retained as a documented additive product-scope difference.
test('explicit comparison eligibility retains mobiles tablets and previously accepted accessories only', () => {
  for (const code of ['mobile_phone', 'tablet', 'accessory'])
    assert.equal(publicCompareEligible({category:{code}}), true, code);
  for (const code of ['software', 'digital_service', 'unknown', ''])
    assert.equal(publicCompareEligible({category:{code}}), false, code);
});

test('owner category policy forbids mobile/tablet/accessory cross-comparison in all selected slots', () => {
  const selected = [{slug:'mobile-1',category:{code:'mobile_phone'}},
    {slug:'accessory-1',category:{code:'accessory'}}, {slug:'tablet-1',category:{code:'tablet'}},
    {slug:'mobile-2',category:{code:'mobile_phone'}}, {slug:'accessory-2',category:{code:'accessory'}}];
  assert.equal(resolveCompareCategory(undefined, selected), 'mobile_phone');
  assert.deepEqual(sameCategoryProducts(selected, 'mobile_phone').map(p=>p.slug), ['mobile-1','mobile-2']);
  assert.deepEqual(sameCategoryProducts(selected, 'accessory').map(p=>p.slug), ['accessory-1','accessory-2']);
  assert.deepEqual(sameCategoryProducts(selected, 'tablet').map(p=>p.slug), ['tablet-1']);
  assert.deepEqual(sameCategoryProducts(selected, null), []);
  assert.equal(resolveCompareCategory('accessory', selected), 'accessory');
  assert.equal(resolveCompareCategory('unsupported', selected), 'mobile_phone');
  assert.equal(resolveCompareCategory(undefined, []), null);
});
