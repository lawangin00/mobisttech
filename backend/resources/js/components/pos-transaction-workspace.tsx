import { useEffect, useMemo, useRef, useState } from 'react';
import PosStockControlWorkspace from './pos-stock-control-workspace';
import {tabSearchOptedIn,tabSearchCategory,tabSearchConsent,tabSearchSaveCategory} from './pos-tab-search-memory';
import { DocumentActions } from './pos-customer-reporting-workspace';

type Unit = { id: string; code: string; status: string; version: number; imeis: string[] };
type Product = { website_listing?:{slug:string;description:string;is_online:boolean;version:number}|null; category_master_data_id?:number|null;subcategory_master_data_id?:number|null;brand_master_data_id?:number|null;ram_master_data_id?:number|null;storage_master_data_id?:number|null;sim_master_data_id?:number|null;warranty_type?:string|null;warranty_unit?:number|null;warranty_duration?:number|null; id: string; code: string; name: string; category?: string; model?: string | null; brand_snapshot?: string | null; brand_display?: string | null; purchase_price: string; sale_price: string; qty: number; track_imei: boolean; version?: number; units: Unit[]; acquisitions?:Array<{source_type:string;quantity:number;unit_purchase_price:string;acquired_at:string}>; movements?:Array<{type:string;quantity_change:number;stock_before:number;stock_after:number;created_at:string}>; subcategory_display?: string | null; ram_display?: string | null; storage_display?: string | null; sim_display?: string | null };
type Destination = { public_id: string; method: string; display_name: string };
type Master = { id: number; list_key: string; code: string; label: string; metadata: Record<string, unknown> };
type InventoryPaging = { page:number;pages:number;total:number;per_page:number;q:string;category:string;options:string[];auto_focus_search:boolean;remember_search:boolean;density:'comfortable'|'compact';sort:string;filter:string;columns:Array<{id:string;label:string;visible:boolean;order:number}> };
type Catalogue = { products: Product[]; page: number; has_more: boolean; pagination: InventoryPaging | null; payment_destinations: Destination[]; master_data: Master[]; can_send_documents: boolean };
type Payment = { method: string; destination_id: string; amount: string; transaction_reference?: string; cash_tendered?: string };
type Quote = { payable: string; payments_total: string; remaining: string; cash_change: string };

async function csrf() {
    const response = await fetch('/internal/admin/auth/csrf-cookie', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    const body = await response.json() as { data?: { csrf_token?: string } };
    if (!response.ok || !body.data?.csrf_token) throw new Error('Secure request token is unavailable.');
    return body.data.csrf_token;
}

async function api<T>(url: string, init?: RequestInit): Promise<T> {
    const method = init?.method ?? 'GET';
    const headers: Record<string, string> = { Accept: 'application/json' };
    if (method !== 'GET') {
        headers['Content-Type'] = 'application/json';
        headers['X-CSRF-TOKEN'] = await csrf();
        headers['Idempotency-Key'] = crypto.randomUUID();
    }
    const response = await fetch(url, { ...init, credentials: 'same-origin', headers });
    const body = await response.json().catch(() => ({})) as { data?: T; message?: string; errors?: Record<string, string[]> };
    if (!response.ok) throw new Error(body.errors ? Object.values(body.errors).flat()[0] : (body.message ?? 'Request failed.'));
    return body.data as T;
}

export default function PosTransactionWorkspace({ area, memoryScope }: { area: 'inventory' | 'sales'; memoryScope: string }) {
    const [catalogue, setCatalogue] = useState<Catalogue | null>(null);
    const inventorySearchRef = useRef<HTMLInputElement>(null);
    const [query, setQuery] = useState('');
    const [category, setCategory] = useState('');
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState('');
    const [page, setPage] = useState(1);
    const [tabConsent,setTabConsent] = useState(false);
    const load = async (value = '', requestedPage = 1, selectedCategory = category) => {
        const params = new URLSearchParams({q:value,page:String(requestedPage),...(area === 'inventory' ? {mode:'inventory',...(selectedCategory?{category:selectedCategory}:{})}:{})});
        const data = await api<Catalogue>('/internal/admin/pos/catalogue?' + params.toString());
        const enabled = area === 'inventory' && data.pagination?.remember_search === true;
        setTabConsent(tabSearchOptedIn(memoryScope,enabled));
        const remembered = data.pagination ? tabSearchCategory(memoryScope,enabled,'inventory',data.pagination.options) : null;
        if (enabled && value === '' && requestedPage === 1 && !selectedCategory && remembered && remembered !== data.pagination?.category) return load('',1,remembered);
        setCatalogue(data);
        setPage(data.page);
        if (data.pagination) setCategory(data.pagination.category);
    };
    useEffect(() => { void load(); }, [area]);
    useEffect(() => { if (area === 'inventory' && catalogue?.pagination?.auto_focus_search) inventorySearchRef.current?.focus(); }, [area, catalogue?.pagination?.auto_focus_search]);
    const run = async (task: () => Promise<void>) => {
        setBusy(true); setMessage('');
        try { await task(); } catch (error) { setMessage(error instanceof Error ? error.message : 'Request failed.'); } finally { setBusy(false); }
    };
    const lookup = () => run(async () => {
        const found = await api<{ product: Product; unit: Unit | null }>('/internal/admin/pos/lookup?q=' + encodeURIComponent(query));
        setCatalogue((old) => old ? { ...old, products: [found.product], pagination: null, has_more: false } : old);
        setMessage(found.unit ? 'Matched unit ' + found.unit.code : 'Matched product ' + found.product.code);
    });
    const density=catalogue?.pagination?.density??'comfortable';
    return <div data-testid={'mt43-'+area} data-density={density} className={'mt-6 grid min-w-0 '+(density==='compact'?'gap-3':'gap-5')}>
        <section className="min-w-0 rounded-2xl border border-slate-200 bg-white p-4">
            <div className="flex min-w-0 flex-col gap-2 sm:flex-row">
                <input data-testid="pos-lookup" ref={inventorySearchRef} value={query} onChange={(e) => setQuery(e.target.value)}
                    onKeyDown={(e) => { if (e.key === 'Enter') void lookup(); }}
                    placeholder="Scan barcode / QR / IMEI, or search product" className="min-w-0 flex-1 rounded-lg border px-3 py-2 text-sm" />
                <button disabled={busy} onClick={() => void lookup()} className="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white">Lookup</button>
                <button disabled={busy} onClick={() => void run(() => load(query, 1, category))} className="rounded-lg border px-4 py-2 text-sm font-semibold">Search</button>
            </div>
            {area === 'inventory' && catalogue?.pagination?.remember_search && <label className="mt-2 flex items-center gap-2 text-xs"><input data-testid="inventory-tab-opt-in" type="checkbox" checked={tabConsent} onChange={e=>{tabSearchConsent(memoryScope,true,e.target.checked);setTabConsent(tabSearchOptedIn(memoryScope,true));if(e.target.checked)tabSearchSaveCategory(memoryScope,true,'inventory',category,catalogue.pagination?.options??[]);}}/>Remember search category in this tab only (never search text)</label>}
            {area === 'inventory' && catalogue?.pagination && <label className="mt-2 flex items-center gap-2 text-sm">Search category <select data-testid="inventory-category" value={category} onChange={(e)=>{setCategory(e.target.value);tabSearchSaveCategory(memoryScope,catalogue.pagination?.remember_search===true,'inventory',e.target.value,catalogue.pagination?.options??[]);}} className="rounded border px-2 py-1">{catalogue.pagination.options.map((option)=><option key={option} value={option}>{option.replaceAll('_',' ')}</option>)}</select></label>}
            {message && <p role="alert" className="mt-3 text-sm">{message}</p>}
            <div className="mt-3 flex items-center justify-end gap-2 text-xs">
                <button disabled={busy || page <= 1} onClick={() => void run(() => load(query, page - 1, category))} className="rounded border px-2 py-1 disabled:opacity-40">Previous</button>
<span data-testid="inventory-page">Page {page}{catalogue?.pagination ? `/${catalogue.pagination.pages} · ${catalogue.pagination.total} products · ${catalogue.pagination.per_page} per page · ${catalogue.pagination.sort.replaceAll('_',' ')} · ${catalogue.pagination.filter.replaceAll('_',' ')}` : ""}</span>
                <button disabled={busy || !catalogue?.has_more} onClick={() => void run(() => load(query, page + 1, category))} className="rounded border px-2 py-1 disabled:opacity-40">Next</button>
            </div>
        </section>
        {area === 'inventory'
            ? <><Inventory catalogue={catalogue} busy={busy} run={run} reload={() => load(query, page, category)} /><PosStockControlWorkspace /></>
            : <Sales catalogue={catalogue} busy={busy} run={run} />}
    </div>;
}

function Inventory({ catalogue, busy, run, reload }: { catalogue: Catalogue | null; busy: boolean; run: (task: () => Promise<void>) => Promise<void>; reload: () => Promise<void> }) {
    const column=(id:string)=>catalogue?.pagination?.columns.find(item=>item.id===id)??{id,label:id,visible:true,order:999};
    const [productId, setProductId] = useState('');
    const [quantity, setQuantity] = useState('1');
    const [cost, setCost] = useState('');
    const [source, setSource] = useState('');
    const [party, setParty] = useState('');
    const [phone, setPhone] = useState('03');
    const [cnic, setCnic] = useState('');
    const [address, setAddress] = useState('');
    const [unitId, setUnitId] = useState('');
    const [imei1, setImei1] = useState('');
    const [imei2, setImei2] = useState('');
    const [editingId, setEditingId] = useState('');
    const [editingVersion, setEditingVersion] = useState<number|null>(null);
    const [editingWarranty, setEditingWarranty] = useState<{type:string;unit:number|null;duration:number|null}>({type:'no_warranty',unit:null,duration:null});
    const [websiteProduct, setWebsiteProduct] = useState<Product|null>(null);
    const [websiteSlug, setWebsiteSlug] = useState('');
    const [websiteDescription, setWebsiteDescription] = useState('');
    const openWebsiteEditor = (p:Product) => {
        setWebsiteProduct(p);
        setWebsiteSlug(p.website_listing?.slug ?? p.name.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,''));
        setWebsiteDescription(p.website_listing?.description ?? 'Product information for '+p.name+'.');
    };
    const [newName, setNewName] = useState('');
    const [categoryId, setCategoryId] = useState('');
    const [brandId, setBrandId] = useState('');
    const [subcategoryId, setSubcategoryId] = useState('');
    const [ramId, setRamId] = useState('');
    const [storageId, setStorageId] = useState('');
    const [simId, setSimId] = useState('');
    const [newModel, setNewModel] = useState('');
    const [newPurchase, setNewPurchase] = useState('');
    const [newSale, setNewSale] = useState('');
    const [newTrack, setNewTrack] = useState(false);
    const [adjustType, setAdjustType] = useState('correction_in');
    const [adjustQty, setAdjustQty] = useState('1');
    const [adjustReason, setAdjustReason] = useState('POS stock correction');
    const [conditionId, setConditionId] = useState('');
    const [ptaId, setPtaId] = useState('');
    const product = catalogue?.products.find((p) => p.id === productId);
    const unit = product?.units.find((u) => u.id === unitId);
    const sources = catalogue?.master_data.filter((m) => m.list_key === 'acquisition_source_type') ?? [];
    const categories = catalogue?.master_data.filter((m) => m.list_key === 'product_category') ?? [];
    const brands = catalogue?.master_data.filter((m) => m.list_key === 'product_brand') ?? [];
    const selectedCategory = categories.find((m) => String(m.id) === categoryId);
    const subcategories = catalogue?.master_data.filter((m) => m.list_key === 'product_subcategory' && m.metadata.parent_category_code === selectedCategory?.code) ?? [];
    const isDevice = selectedCategory?.code === 'mobile_phone' || selectedCategory?.code === 'tablet';
    const rams = catalogue?.master_data.filter((m) => m.list_key === 'device_ram_gb') ?? [];
    const storages = catalogue?.master_data.filter((m) => m.list_key === 'device_storage_gb') ?? [];
    const simConfigurations = catalogue?.master_data.filter((m) => m.list_key === 'device_sim_configuration') ?? [];
    const conditions = catalogue?.master_data.filter((m) => m.list_key === 'unit_condition') ?? [];
    const ptaStatuses = catalogue?.master_data.filter((m) => m.list_key === 'unit_pta_status') ?? [];
    const resetDefinition=()=>{setEditingId('');setEditingVersion(null);setNewName('');setNewModel('');setNewPurchase('');setNewSale('');setBrandId('');setSubcategoryId('');setRamId('');setStorageId('');setSimId('');setCategoryId('');setNewTrack(false);setEditingWarranty({type:'no_warranty',unit:null,duration:null});};
    const editDefinition=(p:Product)=>{
        if(!p.category_master_data_id||!p.warranty_type||!p.version)throw new Error('Product definition is not complete; historical import review is required.');
        setEditingId(p.id);setEditingVersion(p.version);setEditingWarranty({type:p.warranty_type,unit:p.warranty_unit??null,duration:p.warranty_duration??null});
        setProductId(p.id);setNewName(p.name);setCategoryId(String(p.category_master_data_id));setBrandId(String(p.brand_master_data_id??''));
        setSubcategoryId(String(p.subcategory_master_data_id??''));setRamId(String(p.ram_master_data_id??''));setStorageId(String(p.storage_master_data_id??''));setSimId(String(p.sim_master_data_id??''));
        setNewModel(p.model??'');setNewPurchase(p.purchase_price);setNewSale(p.sale_price);setNewTrack(p.track_imei);
    };
    const printLabel = async (kind: string, id: string) => {
        const label = await api<Record<string, unknown>>('/internal/admin/pos/labels/' + kind + '/' + id);
        const popup = window.open('', '_blank', 'width=520,height=420');
        if (!popup) throw new Error('Print window was blocked.');
        popup.document.write('<html><body style="font-family:Arial;padding:28px"><h2>' + String(label.name ?? 'mobiST') + '</h2><pre>' + JSON.stringify(label, null, 2) + '</pre><script>window.print()<\/script></body></html>');
        popup.document.close();
    };
    return <div className="grid min-w-0 gap-5 xl:grid-cols-2">
        <section className="min-w-0 rounded-2xl border bg-white p-5"><h3 className="font-semibold">Inventory</h3>
            <div className="mt-3 grid gap-2">{catalogue?.products.map((p) => <div key={p.id} className="flex flex-col rounded-xl border p-3">
                <button style={{order:column('product').order}} className="w-full text-left" onClick={() => { setProductId(p.id); setCost(p.purchase_price); setUnitId(p.units[0]?.id ?? ''); }}><strong>{p.name}</strong><span className="block text-xs text-slate-500">{p.code}</span></button><p hidden={!column('purchase_cost').visible} style={{order:column('purchase_cost').order}} className="mt-1 text-xs text-slate-600">Purchase PKR {p.purchase_price}</p><p hidden={!column('sale_price').visible} style={{order:column('sale_price').order}} className="mt-1 text-xs text-slate-600">Sale PKR {p.sale_price}</p><p hidden={!column('in_stock').visible} style={{order:column('in_stock').order}} className="mt-1 text-xs text-slate-600">Qty {p.qty}</p><p hidden={!column('imei_tracking').visible} style={{order:column('imei_tracking').order}} className="mt-1 text-xs text-slate-600">IMEI tracking {p.track_imei?'enabled':'disabled'}</p>
                {p.brand_snapshot && <p data-testid={'inventory-brand-history-'+p.id} className="mt-1 text-xs text-slate-600">Original brand: {p.brand_snapshot} · Current brand: {p.brand_display}</p>}
                {(p.subcategory_display || p.ram_display || p.storage_display || p.sim_display) && <p data-testid={'inventory-variant-'+p.id} hidden={!column('variant').visible} style={{order:column('variant').order}} className="mt-1 text-xs text-slate-600">{[p.subcategory_display, p.ram_display, p.storage_display, p.sim_display].filter(Boolean).join(' · ')}</p>}
                <div style={{order:column('actions').order}}><button data-testid={'product-edit-'+p.id} onClick={()=>void run(async()=>{editDefinition(p);})} className="mt-2 mr-2 rounded border px-2 py-1 text-xs">Edit definition</button>
                <button data-testid={'product-website-'+p.id} onClick={()=>openWebsiteEditor(p)} className="mt-2 mr-2 rounded border px-2 py-1 text-xs">Website listing</button>
                <button onClick={() => void run(() => printLabel('product', p.id))} className="mt-2 rounded border px-2 py-1 text-xs">Print product label</button></div>
                {p.units.length > 0 && <p hidden={!column('unit_details').visible} style={{order:column('unit_details').order}} className="mt-2 text-xs text-slate-500">{p.units.map((u) => u.code + (u.imeis.length ? ' (' + u.imeis.join(', ') + ')' : '')).join(' · ')}</p>}
                <div data-testid={'inventory-history-'+p.id} hidden={!column('history').visible} style={{order:column('history').order}} className="mt-3 grid gap-2 text-xs text-slate-600 sm:grid-cols-2">
                    <div><strong className="text-slate-800">Acquisition history</strong>{p.acquisitions?.length
                        ? <ul className="mt-1 space-y-1">{p.acquisitions.map((row,index)=><li key={index}>{row.source_type} · {row.quantity} @ PKR {row.unit_purchase_price}</li>)}</ul>
                        : <p className="mt-1">No acquisition recorded.</p>}</div>
                    <div><strong className="text-slate-800">Stock movement history</strong>{p.movements?.length
                        ? <ul className="mt-1 space-y-1">{p.movements.map((row,index)=><li key={index}>{row.type.replaceAll('_',' ')} · {row.quantity_change>0?'+':''}{row.quantity_change} · {row.stock_before} → {row.stock_after}</li>)}</ul>
                        : <p className="mt-1">No movement recorded.</p>}</div>
                </div>
            </div>)}</div>
        </section>
        {websiteProduct && <section data-testid="website-listing-editor" className="min-w-0 rounded-2xl border bg-white p-5"><h3 className="font-semibold">Website listing: {websiteProduct.name}</h3>
            <p className="mt-1 text-xs">Publishing makes this product public when the Website commerce mode is active. Stock and prices remain POS-authoritative.</p>
            <input data-testid="website-listing-slug" value={websiteSlug} onChange={e=>setWebsiteSlug(e.target.value)} placeholder="Public product slug" className="mt-3 w-full rounded border p-2 text-sm" />
            <textarea data-testid="website-listing-description" value={websiteDescription} onChange={e=>setWebsiteDescription(e.target.value)} rows={3} placeholder="Public description" className="mt-2 w-full rounded border p-2 text-sm" />
            <div className="mt-2 flex gap-2"><button data-testid="website-listing-publish" disabled={busy||websiteSlug.length<3||websiteDescription.trim().length<10} onClick={()=>void run(async()=>{
                await api('/internal/admin/pos/inventory/products/'+websiteProduct.id+'/website-listing',{method:'POST',body:JSON.stringify({slug:websiteSlug,description:websiteDescription,is_online:true,expected_version:websiteProduct.website_listing?.version??0})});
                setWebsiteProduct(null);await reload();
            })} className="rounded bg-slate-950 px-3 py-2 text-sm text-white disabled:opacity-40">Publish listing</button>
            {websiteProduct.website_listing?.is_online&&<button data-testid="website-listing-hide" disabled={busy} onClick={()=>void run(async()=>{
                await api('/internal/admin/pos/inventory/products/'+websiteProduct.id+'/website-listing',{method:'POST',body:JSON.stringify({slug:websiteSlug,description:websiteDescription,is_online:false,expected_version:websiteProduct.website_listing?.version??0})});
                setWebsiteProduct(null);await reload();
            })} className="rounded border px-3 py-2 text-sm">Hide listing</button>}
            <button onClick={()=>setWebsiteProduct(null)} className="rounded border px-3 py-2 text-sm">Cancel</button></div>
        </section>}
        <div className="grid gap-5">
            <section className="min-w-0 rounded-2xl border bg-white p-5"><h3 className="font-semibold">Product definition</h3>
                {editingId&&<div data-testid="product-edit-mode" className="mt-2 flex items-center justify-between gap-2 text-xs"><span>Editing existing product {editingId}; stock, sales and historical records remain linked.</span><button onClick={resetDefinition} className="rounded border px-2 py-1">Cancel edit</button></div>}
                <div className="mt-3 grid gap-2">
                    <input value={newName} onChange={(e) => setNewName(e.target.value)} placeholder="Product name" className="w-full min-w-0 rounded border p-2 text-sm" />
                    <div className="grid grid-cols-2 gap-2">
                        <select data-testid="product-category" value={categoryId} onChange={(e) => { setCategoryId(e.target.value); setSubcategoryId(''); setRamId(''); setStorageId(''); setSimId(''); }} className="w-full min-w-0 rounded border p-2 text-sm"><option value="">Category</option>{categories.map((m) => <option key={m.id} value={m.id}>{m.label}</option>)}</select>
                        <select data-testid="product-brand" value={brandId} onChange={(e) => setBrandId(e.target.value)} className="w-full min-w-0 rounded border p-2 text-sm"><option value="">Brand (if device)</option>{brandId&&!brands.some(m=>String(m.id)===brandId)&&<option value={brandId}>Previously selected brand (inactive)</option>}{brands.map((m) => <option key={m.id} value={m.id}>{m.label}</option>)}</select>
                    </div>
                    <select data-testid="product-subcategory" value={subcategoryId} onChange={(e) => setSubcategoryId(e.target.value)} disabled={!categoryId} className="w-full min-w-0 rounded border p-2 text-sm"><option value="">Subcategory (optional)</option>{subcategoryId&&!subcategories.some(m=>String(m.id)===subcategoryId)&&<option value={subcategoryId}>Previously selected subcategory (inactive)</option>}{subcategories.map((m) => <option key={m.id} value={m.id}>{m.label}</option>)}</select>
                    <input value={newModel} onChange={(e) => setNewModel(e.target.value)} placeholder="Model" className="w-full min-w-0 rounded border p-2 text-sm" />
                    {isDevice && <fieldset className="grid grid-cols-2 gap-2 rounded border p-2"><legend className="text-xs font-semibold">Device configuration</legend>
                        <select data-testid="product-ram" aria-label="RAM" value={ramId} onChange={e=>setRamId(e.target.value)} className="rounded border p-2 text-sm"><option value="">RAM (optional)</option>{ramId&&!rams.some(m=>String(m.id)===ramId)&&<option value={ramId}>Previously selected option (inactive)</option>}{rams.map(m=><option key={m.id} value={m.id}>{m.label}</option>)}</select>
                        <select data-testid="product-storage" aria-label="Storage" value={storageId} onChange={e=>setStorageId(e.target.value)} className="rounded border p-2 text-sm"><option value="">Storage (optional)</option>{storageId&&!storages.some(m=>String(m.id)===storageId)&&<option value={storageId}>Previously selected option (inactive)</option>}{storages.map(m=><option key={m.id} value={m.id}>{m.label}</option>)}</select>
                        <select data-testid="product-sim" aria-label="SIM configuration" value={simId} onChange={e=>setSimId(e.target.value)} className="col-span-2 rounded border p-2 text-sm"><option value="">SIM configuration (optional)</option>{simId&&!simConfigurations.some(m=>String(m.id)===simId)&&<option value={simId}>Previously selected option (inactive)</option>}{simConfigurations.map(m=><option key={m.id} value={m.id}>{m.label}</option>)}</select>
                    </fieldset>}
                    <div className="grid grid-cols-2 gap-2"><input value={newPurchase} onChange={(e) => setNewPurchase(e.target.value)} placeholder="Purchase price" className="w-full min-w-0 rounded border p-2 text-sm" /><input value={newSale} onChange={(e) => setNewSale(e.target.value)} placeholder="Sale price" className="w-full min-w-0 rounded border p-2 text-sm" /></div>
                    <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={newTrack} onChange={(e) => setNewTrack(e.target.checked)} /> Track IMEI</label>
                    <div className="grid gap-2 rounded border p-2 sm:grid-cols-3" data-testid="product-warranty-editor">
                        <select aria-label="Warranty type" value={editingWarranty.type} onChange={e=>setEditingWarranty({type:e.target.value,unit:e.target.value==='no_warranty'?null:0,duration:e.target.value==='no_warranty'?null:30})} className="rounded border p-2 text-sm"><option value="no_warranty">No warranty</option><option value="shop_warranty">Shop warranty</option><option value="brand_warranty">Brand warranty</option></select>
                        {editingWarranty.type!=='no_warranty'&&<><select aria-label="Warranty unit" value={editingWarranty.unit??0} onChange={e=>setEditingWarranty(old=>({...old,unit:Number(e.target.value)}))} className="rounded border p-2 text-sm"><option value="0">Days</option><option value="1">Months</option><option value="2">Years</option></select><input aria-label="Warranty duration" type="number" min="1" step="1" value={editingWarranty.duration??''} onChange={e=>setEditingWarranty(old=>({...old,duration:e.target.value?Number(e.target.value):null}))} className="rounded border p-2 text-sm" /></>}
                    </div>
                    <button data-testid="product-save" disabled={busy || !newName || !categoryId || !newPurchase || !newSale || (isDevice && (!brandId || !newModel.trim())) || (editingWarranty.type!=='no_warranty' && (!Number.isInteger(editingWarranty.duration) || (editingWarranty.duration??0)<1))} onClick={() => void run(async () => {
                        const category = categories.find((m) => String(m.id) === categoryId);
                        if (!category) throw new Error('Choose a valid category.');
                        await api('/internal/admin/pos/inventory/products', { method: 'POST', body: JSON.stringify({
                            ...(editingId?{product_id:editingId,expected_version:editingVersion}:{}),
                            name: newName, category: category.code, category_master_data_id: Number(categoryId),
                            brand_master_data_id: brandId ? Number(brandId) : null, model: newModel || null,
                            subcategory_master_data_id: subcategoryId ? Number(subcategoryId) : null,
                            ram_master_data_id: isDevice && ramId ? Number(ramId) : null,
                            storage_master_data_id: isDevice && storageId ? Number(storageId) : null,
                            sim_master_data_id: isDevice && simId ? Number(simId) : null,
                            purchase_price: newPurchase, sale_price: newSale, track_imei: newTrack,
                            warranty_type: editingWarranty.type,
                            ...(editingWarranty.type!=='no_warranty'?{warranty_unit:editingWarranty.unit,warranty_duration:editingWarranty.duration}:{}),
                        }) });
                        resetDefinition(); await reload();
                    })} className="rounded bg-slate-950 px-3 py-2 text-sm font-semibold text-white disabled:opacity-40">{editingId?'Update product':'Save product'}</button>
                </div>
            </section>
            <section className="min-w-0 rounded-2xl border bg-white p-5"><h3 className="font-semibold">Receive stock</h3>
                <div className="mt-3 grid gap-2">
                    <select value={productId} onChange={(e) => setProductId(e.target.value)} className="w-full min-w-0 rounded border p-2 text-sm"><option value="">Product</option>{catalogue?.products.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}</select>
                    <div className="grid grid-cols-2 gap-2"><input value={quantity} onChange={(e) => setQuantity(e.target.value)} placeholder="Qty" className="w-full min-w-0 rounded border p-2 text-sm" /><input value={cost} onChange={(e) => setCost(e.target.value)} placeholder="Unit cost" className="w-full min-w-0 rounded border p-2 text-sm" /></div>
                    <select value={source} onChange={(e) => setSource(e.target.value)} className="w-full min-w-0 rounded border p-2 text-sm"><option value="">Source type</option>{sources.map((s) => <option key={s.id} value={s.id}>{s.label}</option>)}</select>
                    <input value={party} onChange={(e) => setParty(e.target.value)} placeholder="Business / seller name" className="w-full min-w-0 rounded border p-2 text-sm" />
                    <div className="grid grid-cols-2 gap-2"><input value={phone} onChange={(e) => setPhone(e.target.value)} placeholder="03XXXXXXXXX" className="w-full min-w-0 rounded border p-2 text-sm" /><input value={cnic} onChange={(e) => setCnic(e.target.value)} placeholder="CNIC if individual" className="w-full min-w-0 rounded border p-2 text-sm" /></div>
                    <input value={address} onChange={(e) => setAddress(e.target.value)} placeholder="Address" className="w-full min-w-0 rounded border p-2 text-sm" />
                    <button data-testid="acquire-submit" disabled={busy || !productId || !source} onClick={() => void run(async () => {
                        const sourceRow = sources.find((s) => String(s.id) === source); const business = sourceRow?.metadata?.party_kind === 'business';
                        await api('/internal/admin/pos/inventory/products/' + productId + '/acquire', { method: 'POST', body: JSON.stringify({
                            quantity: Number(quantity), unit_purchase_price: cost, source_type_master_data_id: Number(source),
                            business_name: business ? party : null, seller_name: business ? null : party, seller_cnic: business ? null : cnic,
                            seller_phone: phone, seller_address: address, reason: 'POS acquisition',
                        }) }); await reload();
                    })} className="rounded bg-slate-950 px-3 py-2 text-sm font-semibold text-white disabled:opacity-40">Receive</button>
                </div>
            </section>
            {product && <section className="min-w-0 rounded-2xl border bg-white p-5"><h3 className="font-semibold">Stock adjustment</h3>
                <div className="mt-3 grid gap-2">
                    <select value={adjustType} onChange={(e) => setAdjustType(e.target.value)} className="w-full min-w-0 rounded border p-2 text-sm"><option value="correction_in">Correction in</option><option value="correction_out">Correction out</option><option value="damaged">Damaged</option><option value="lost">Lost</option></select>
                    <input value={adjustQty} onChange={(e) => setAdjustQty(e.target.value)} placeholder="Quantity" className="w-full min-w-0 rounded border p-2 text-sm" />
                    <input value={adjustReason} onChange={(e) => setAdjustReason(e.target.value)} placeholder="Reason" className="w-full min-w-0 rounded border p-2 text-sm" />
                    <button data-testid="stock-adjust" disabled={busy || (product.track_imei && adjustType !== 'correction_in' && !unitId)} onClick={() => void run(async () => {
                        await api('/internal/admin/pos/inventory/products/' + product.id + '/adjust', { method: 'POST', body: JSON.stringify({
                            type: adjustType, quantity: Number(adjustQty), reason: adjustReason, unit_id: product.track_imei && adjustType !== 'correction_in' ? unitId : null,
                        }) }); await reload();
                    })} className="rounded border px-3 py-2 text-sm font-semibold disabled:opacity-40">Apply adjustment</button>
                </div>
            </section>}
            {product?.track_imei && <section className="min-w-0 rounded-2xl border bg-white p-5"><h3 className="font-semibold">Unit / IMEI</h3>
                <select value={unitId} onChange={(e) => setUnitId(e.target.value)} className="mt-3 w-full rounded border p-2 text-sm"><option value="">Unit</option>{product.units.map((u) => <option key={u.id} value={u.id}>{u.code}</option>)}</select>
                <div className="mt-2 grid grid-cols-2 gap-2"><input value={imei1} onChange={(e) => setImei1(e.target.value)} placeholder="IMEI 1" className="w-full min-w-0 rounded border p-2 text-sm" /><input value={imei2} onChange={(e) => setImei2(e.target.value)} placeholder="IMEI 2" className="w-full min-w-0 rounded border p-2 text-sm" /></div>
                <div className="mt-2 grid grid-cols-2 gap-2"><select value={conditionId} onChange={(e) => setConditionId(e.target.value)} className="w-full min-w-0 rounded border p-2 text-sm"><option value="">Condition</option>{conditions.map((m) => <option key={m.id} value={m.id}>{m.label}</option>)}</select><select value={ptaId} onChange={(e) => setPtaId(e.target.value)} className="w-full min-w-0 rounded border p-2 text-sm"><option value="">PTA status</option>{ptaStatuses.map((m) => <option key={m.id} value={m.id}>{m.label}</option>)}</select></div>
                <div className="mt-3 flex flex-wrap gap-2"><button disabled={!unit} onClick={() => void run(async () => {
                    const values = [imei1, imei2].filter(Boolean); const imeis = Object.fromEntries(values.map((v, i) => [String(i + 1), v]));
                    await api('/internal/admin/pos/inventory/products/' + productId + '/imeis', { method: 'POST', body: JSON.stringify({ unit_id: unitId, version: unit?.version, imeis }) }); await reload();
                })} className="rounded bg-slate-950 px-3 py-2 text-sm font-semibold text-white">Save IMEI</button>
                <button disabled={!unit || (!conditionId && !ptaId)} onClick={() => void run(async () => {
                    const payload: Record<string, number> = {};
                    if (conditionId) payload.condition_master_data_id = Number(conditionId);
                    if (ptaId) payload.pta_status_master_data_id = Number(ptaId);
                    await api('/internal/admin/pos/inventory/units/' + unitId, { method: 'PATCH', body: JSON.stringify(payload) }); await reload();
                })} className="rounded border px-3 py-2 text-sm">Save unit attributes</button>
                {unit && <button onClick={() => void run(() => printLabel('unit', unit.id))} className="rounded border px-3 py-2 text-sm">Print unit label</button>}</div>
            </section>}
        </div>
    </div>;
}

function Sales({ catalogue, busy, run }: { catalogue: Catalogue | null; busy: boolean; run: (task: () => Promise<void>) => Promise<void> }) {
    const [cart, setCart] = useState<Array<{ product_id: string; name: string; quantity: number }>>([]);
    const [discount, setDiscount] = useState('0.00');
    const [reason, setReason] = useState('');
    const [promotion, setPromotion] = useState('');
    const [loyalty, setLoyalty] = useState('0');
    const [payments, setPayments] = useState<Payment[]>([]);
    const [quote, setQuote] = useState<Quote | null>(null);
    const [result, setResult] = useState<Record<string, unknown> | null>(null);
    const [customerName, setCustomerName] = useState('');
    const [customerPhone, setCustomerPhone] = useState('03');
    const [customerEmail, setCustomerEmail] = useState('');
    const [customerCnic, setCustomerCnic] = useState('');
    const [outputFormat, setOutputFormat] = useState<'a4' | 'thermal80'>('a4');
    const destinations = catalogue?.payment_destinations ?? [];
    const sale = useMemo(() => ({
        new_customer: Boolean(customerName.trim()), customer_name: customerName || null,
        customer_phone: customerPhone === '03' ? null : customerPhone || null,
        customer_email: customerEmail || null, customer_cnic: customerCnic || null,
        discount, discount_reason: reason || null, promotion_codes: promotion ? [promotion] : [],
        loyalty_points: Number(loyalty || 0), lines: cart.map((x) => ({ product_id: x.product_id, quantity: x.quantity })),
    }), [cart, discount, reason, promotion, loyalty, customerName, customerPhone, customerEmail, customerCnic]);
    const add = (p: Product) => setCart((old) => old.some((x) => x.product_id === p.id) ? old.map((x) => x.product_id === p.id ? { ...x, quantity: x.quantity + 1 } : x) : [...old, { product_id: p.id, name: p.name, quantity: 1 }]);
    const addPayment = () => { const d = destinations[0]; if (d) setPayments((old) => [...old, { method: d.method, destination_id: d.public_id, amount: '' }]); };
    const changePayment = (i: number, patch: Partial<Payment>) => setPayments((old) => old.map((p, index) => index === i ? { ...p, ...patch } : p));
    return <div className="grid min-w-0 gap-5 xl:grid-cols-2">
        <section className="min-w-0 rounded-2xl border bg-white p-5"><h3 className="font-semibold">Products</h3><div className="mt-3 grid gap-2">{catalogue?.products.map((p) => <button key={p.id} onClick={() => add(p)} className="rounded-xl border p-3 text-left"><strong>{p.name}</strong><span className="block text-xs text-slate-500">{p.code} · Available {p.qty} · PKR {p.sale_price}</span></button>)}</div></section>
        <section className="min-w-0 rounded-2xl border bg-white p-5"><h3 className="font-semibold">Sale & payment</h3>
            <div className="mt-3 grid gap-2">{cart.map((line, i) => <div key={line.product_id} className="flex items-center gap-2 rounded bg-slate-50 p-2"><span className="flex-1 text-sm">{line.name}</span><input aria-label={'Quantity ' + line.name} value={line.quantity} onChange={(e) => setCart((old) => old.map((x, index) => index === i ? { ...x, quantity: Math.max(1, Number(e.target.value) || 1) } : x))} className="w-20 rounded border p-1 text-sm" /></div>)}</div>
            <div className="mt-3 grid gap-2 sm:grid-cols-2"><input value={customerName} onChange={(e) => setCustomerName(e.target.value)} placeholder="Customer name (optional)" className="w-full min-w-0 rounded border p-2 text-sm" /><input value={customerPhone} onChange={(e) => setCustomerPhone(e.target.value)} placeholder="03XXXXXXXXX" className="w-full min-w-0 rounded border p-2 text-sm" /><input value={customerEmail} onChange={(e) => setCustomerEmail(e.target.value)} placeholder="Customer email (optional)" className="w-full min-w-0 rounded border p-2 text-sm" /><input value={customerCnic} onChange={(e) => setCustomerCnic(e.target.value)} placeholder="CNIC (optional)" className="w-full min-w-0 rounded border p-2 text-sm" /></div>
            <div className="mt-3 grid grid-cols-2 gap-2"><input value={discount} onChange={(e) => setDiscount(e.target.value)} placeholder="Manual discount" className="w-full min-w-0 rounded border p-2 text-sm" /><input value={reason} onChange={(e) => setReason(e.target.value)} placeholder="Discount reason" className="w-full min-w-0 rounded border p-2 text-sm" /><input value={promotion} onChange={(e) => setPromotion(e.target.value)} placeholder="Promotion code" className="w-full min-w-0 rounded border p-2 text-sm" /><input value={loyalty} onChange={(e) => setLoyalty(e.target.value)} placeholder="Loyalty points" className="w-full min-w-0 rounded border p-2 text-sm" /></div>
            <div className="mt-4 flex justify-between"><h4 className="text-sm font-semibold">Payment editor</h4><button onClick={addPayment} className="rounded border px-2 py-1 text-xs">Add Payment</button></div>
            <div className="mt-2 grid gap-2">{payments.map((payment, i) => <div key={i} className="grid gap-2 rounded-xl border p-3 sm:grid-cols-2">
                <select value={payment.destination_id} onChange={(e) => { const d = destinations.find((x) => x.public_id === e.target.value); changePayment(i, { destination_id: e.target.value, method: d?.method ?? payment.method }); }} className="w-full min-w-0 rounded border p-2 text-sm">{destinations.map((d) => <option key={d.public_id} value={d.public_id}>{d.display_name} · {d.method}</option>)}</select>
                <input value={payment.amount} onChange={(e) => changePayment(i, { amount: e.target.value })} placeholder="Amount" className="w-full min-w-0 rounded border p-2 text-sm" /><input value={payment.transaction_reference ?? ''} onChange={(e) => changePayment(i, { transaction_reference: e.target.value })} placeholder="Safe reference" className="w-full min-w-0 rounded border p-2 text-sm" />{payment.method === 'cash' && <input value={payment.cash_tendered ?? ''} onChange={(e) => changePayment(i, { cash_tendered: e.target.value })} placeholder="Cash tendered" className="w-full min-w-0 rounded border p-2 text-sm" />}
            </div>)}</div>
            <div className="mt-4 flex flex-wrap gap-2"><select value={outputFormat} onChange={(e) => setOutputFormat(e.target.value as 'a4' | 'thermal80')} className="rounded border p-2 text-sm"><option value="a4">A4 invoice</option><option value="thermal80">Thermal 80mm</option></select><button data-testid="server-totals" disabled={busy || cart.length === 0} onClick={() => void run(async () => setQuote(await api<Quote>('/internal/admin/pos/sales/quote', { method: 'POST', body: JSON.stringify({ ...sale, payments }) })))} className="rounded border px-3 py-2 text-sm font-semibold">Preview sale</button><button data-testid="finalize-sale" disabled={busy || !quote || quote.remaining !== '0.00'} onClick={() => void run(async () => { const data = await api<Record<string, unknown>>('/internal/admin/pos/sales', { method: 'POST', body: JSON.stringify({ sale, payments }) }); setResult(data); setCart([]); setPayments([]); setQuote(null); })} className="rounded bg-slate-950 px-3 py-2 text-sm font-semibold text-white disabled:opacity-40">Finalize sale</button></div>
            {quote && <div data-testid="authoritative-totals" className="mt-4 grid grid-cols-2 gap-2 rounded bg-slate-50 p-3 text-sm"><span>Invoice Total</span><strong>PKR {quote.payable}</strong><span>Payments</span><strong>PKR {quote.payments_total}</strong><span>Remaining</span><strong>PKR {quote.remaining}</strong><span>Cash change</span><strong>PKR {quote.cash_change}</strong></div>}
            {result && <div data-testid="sale-result" className="mt-4 rounded border border-emerald-200 bg-emerald-50 p-3 text-sm"><strong>Sale complete: {String(result.invoice_number ?? '')}</strong><p>Final PKR {String(result.final_bill ?? '')} · Discount PKR {String(result.discount ?? '0.00')} · Change PKR {String(result.cash_change_total ?? '0.00')}</p><button onClick={() => { setResult(null); setCustomerName(''); setCustomerPhone('03'); setCustomerEmail(''); setCustomerCnic(''); }} className="mt-2 rounded border bg-white px-3 py-1 text-xs">Done / New Invoice</button></div>}
            {Boolean(result?.invoice_id) && <div className="mt-4"><DocumentActions type="invoice" documentId={String(result?.invoice_id ?? '')} canSend={catalogue?.can_send_documents ?? false} busy={busy} run={run} defaultFormat={outputFormat} /></div>}
        </section>
        <ReturnPanel busy={busy} run={run} destinations={destinations} />
    </div>;
}

function ReturnPanel({ busy, run, destinations }: { busy: boolean; run: (task: () => Promise<void>) => Promise<void>; destinations: Destination[] }) {
    const [invoiceId, setInvoiceId] = useState('');
    const [invoice, setInvoice] = useState<Record<string, unknown> | null>(null);
    const [saleId, setSaleId] = useState('');
    const [unitId, setUnitId] = useState('');
    const [result, setResult] = useState<Record<string, unknown> | null>(null);
    const [refundDestination, setRefundDestination] = useState('');
    const [refundAmount, setRefundAmount] = useState('');
    const [refundResult, setRefundResult] = useState<Record<string, unknown> | null>(null);
    const [originalTenderId, setOriginalTenderId] = useState('');
    const lines = (invoice?.lines as Array<Record<string, unknown>> | undefined) ?? [];
    const tenders = (invoice?.payments as Array<Record<string, unknown>> | undefined) ?? [];
    const originalTender = tenders.find((tender) => String(tender.allocation_id) === originalTenderId) ?? tenders[0];
    return <section className="rounded-2xl border bg-white p-5 xl:col-span-2"><h3 className="font-semibold">Return / refund</h3>
        <div className="mt-3 flex gap-2"><input value={invoiceId} onChange={(e) => setInvoiceId(e.target.value)} placeholder="Invoice public ID" className="flex-1 rounded border p-2 text-sm" /><button disabled={!invoiceId || busy} onClick={() => void run(async () => {
            const loaded = await api<Record<string, unknown>>('/internal/admin/pos/invoices/' + invoiceId);
            setInvoice(loaded);
            const loadedTenders = (loaded.payments as Array<Record<string, unknown>> | undefined) ?? [];
            setOriginalTenderId(String(loadedTenders[0]?.allocation_id ?? ''));
            setRefundDestination(String(loadedTenders[0]?.destination_id ?? ''));
        })} className="rounded border px-3 py-2 text-sm">Load invoice</button></div>
        {lines.length > 0 && <div className="mt-3 grid gap-2 sm:grid-cols-4"><select value={saleId} onChange={(e) => { setSaleId(e.target.value); const line = lines.find((x) => x.sale_id === e.target.value); setUnitId(String(((line?.units as Array<Record<string, unknown>> | undefined) ?? [])[0]?.public_id ?? '')); }} className="w-full min-w-0 rounded border p-2 text-sm"><option value="">Sale line</option>{lines.map((line) => <option key={String(line.sale_id)} value={String(line.sale_id)}>{String(line.name)}</option>)}</select><input value={unitId} onChange={(e) => setUnitId(e.target.value)} placeholder="Sold unit ID if serialized" className="w-full min-w-0 rounded border p-2 text-sm" /><button disabled={!saleId || busy} onClick={() => void run(async () => {
            const accepted = await api<Record<string, unknown>>('/internal/admin/pos/returns', { method: 'POST', body: JSON.stringify({ invoice_id: invoiceId, reason: 'POS accepted return', lines: [{ sale_id: saleId, quantity: 1, stock_unit_id: unitId || null, condition: 'returned', disposition: 'sellable' }] }) });
            setResult(accepted); setRefundAmount(String(accepted.refund_due ?? '')); setRefundDestination(String(originalTender?.destination_id ?? ''));
        })} className="rounded bg-slate-950 px-3 py-2 text-sm font-semibold text-white">Accept return</button></div>}
        {result && <div className="mt-3 grid gap-2 rounded bg-slate-50 p-3 text-sm">
            <p>Accepted return {String(result.return_id ?? '')}. Refund due: PKR {String(result.refund_due ?? '')}.</p>
            {originalTender && <><div className="grid gap-2 sm:grid-cols-4"><select value={originalTenderId || String(originalTender.allocation_id)} onChange={(e) => {
                const selected = tenders.find((tender) => String(tender.allocation_id) === e.target.value);
                setOriginalTenderId(e.target.value); setRefundDestination(String(selected?.destination_id ?? ''));
            }} className="w-full min-w-0 rounded border bg-white p-2"><option value="">Original tender</option>{tenders.map((tender) => <option key={String(tender.allocation_id)} value={String(tender.allocation_id)}>{String(tender.destination_name)} · PKR {String(tender.amount)}</option>)}</select><select value={refundDestination} onChange={(e) => setRefundDestination(e.target.value)} className="w-full min-w-0 rounded border bg-white p-2"><option value="">Refund destination</option>{destinations.map((d) => <option key={d.public_id} value={d.public_id}>{d.display_name} · {d.method}</option>)}</select><input value={refundAmount} onChange={(e) => setRefundAmount(e.target.value)} placeholder="Refund amount" className="w-full min-w-0 rounded border bg-white p-2" /><button disabled={!refundDestination || !refundAmount || busy} onClick={() => void run(async () => {
                const differs = refundDestination !== String(originalTender.destination_id);
                const refunded = await api<Record<string, unknown>>('/internal/admin/pos/refunds', { method: 'POST', body: JSON.stringify({
                    return_id: result.return_id, original_tender_id: originalTender.allocation_id, refund_destination_id: refundDestination,
                    amount: refundAmount, override: differs, override_reason: differs ? 'POS operator requested alternate refund destination' : null,
                }) }); setRefundResult(refunded);
            })} className="rounded bg-slate-950 px-3 py-2 font-semibold text-white">Record refund</button></div><p className="text-xs text-slate-500">A different refund destination remains subject to MT-2.20 override permission/approval rules.</p></>}
        </div>}
        {refundResult && <p data-testid="refund-result" className="mt-3 rounded border border-emerald-200 bg-emerald-50 p-3 text-sm">Refund recorded: PKR {String(refundResult.amount ?? '')} via {String(refundResult.refund_method ?? '')}.</p>}
    </section>;
}
