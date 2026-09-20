import test from 'node:test';
import assert from 'node:assert/strict';
import { normalizeCatalogueFilters } from '../src/lib/catalogue-legacy-filters.ts';

test('legacy public filter aliases map to current POS-authoritative API fields', () => {
  assert.deepEqual(normalizeCatalogueFilters({category:'mobile_phone',pta:'pta_approved',ram:'8',storage:'128'}),
    {category:'mobile_phone',pta:'pta_approved',ram:'8',storage:'128',pta_status:'pta_approved',ram_gb:'8',storage_gb:'128'});
});
test('modern explicit filters win over legacy aliases, including cleared values', () => {
  assert.deepEqual(normalizeCatalogueFilters({pta:'non_pta',pta_status:'pta_approved',ram:'8',ram_gb:'',storage:'128',storage_gb:'256'}),
    {pta:'non_pta',pta_status:'pta_approved',ram:'8',ram_gb:'',storage:'128',storage_gb:'256'});
});
test('malformed repeated old aliases cannot become a scalar filter', () => {
  assert.deepEqual(normalizeCatalogueFilters({pta:['non_pta','pta_approved'],ram:['8','16'],storage:' '}),
    {pta:['non_pta','pta_approved'],ram:['8','16'],storage:' '});
});
