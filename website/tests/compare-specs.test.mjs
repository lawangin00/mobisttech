import test from 'node:test';
import assert from 'node:assert/strict';
import { publicCompareEligible, publicCompareSpec } from '../src/lib/compare-specs.ts';

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
