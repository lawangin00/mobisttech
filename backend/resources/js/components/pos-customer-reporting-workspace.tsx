import { useEffect, useMemo, useState } from 'react';

type Area = 'invoices' | 'warranty' | 'claims' | 'reports';
type Invoice = { id: string; number: string; customer_id: number | null; customer_name: string | null; customer_phone: string | null; customer_email: string | null; customer_cnic: string | null; salesperson_name: string | null; final_bill: string; currency: string; created_at: string };
type Customer = { id: string; name: string; email: string | null; mobile: string | null; version: number; invoices: Invoice[] };
type ClaimRow = { id: string; number: string; status: string; version: number; quantity: number; received_at: string; expected_completion_at: string | null; invoice_id: string; invoice_number: string; customer_name: string | null; customer_phone: string | null; product_name: string };
type SaleCandidate = { sale_id: string; invoice_id: string; invoice_number: string; customer_name: string | null; product_name: string; quantity: number; returned_quantity: number; track_imei: boolean; units: Array<{ id: string; code: string }>; warranty_type: string | null; warranty_unit: number | null; warranty_duration: number | null };
type Report = {
    sales: Record<string, string | number>;
    payments: {
        pos_tender_total: string; website_payment_total: string; provider_fees: string; expected_settlement: string; net_settlement: string;
        settlement_variance: string; unreconciled_tenders: number; method_breakdown: Record<string, string>;
        destination_breakdown: Array<{ method: string; destination: string; amount: string }>;
        website_gateway_breakdown: Record<string, string>;
    };
    activity: Record<string, number>;
};
type Paging = {page:number;pages:number;total:number;per_page:number;q:string;category:string;options:string[];auto_focus_search:boolean;remember_search:boolean};
type Data = { area: Area; outlet: { id: string; name: string }; can_send_documents: boolean; invoices: Invoice[]; customers: Customer[]; claims: ClaimRow[]; sale_candidates: SaleCandidate[]; report: Report | null; pagination: Paging | null; warranty_intake_category: string | null };
type ClaimDetail = Record<string, unknown> & { claim_id: string; claim_number: string; status: string; invoice_id: string; invoice_number: string; activity_log: Array<Record<string, unknown>>; warranty: Record<string, unknown> };
type DocRender = { document_type: string; format: string; filename: string; document_sha256: string; html?: string; pdf_base64?: string; action?: string };
type EmailDraft = DocRender & { to: string; subject: string; message: string };
type Delivery = { attempt_id: string; channel: string; state: string; recipient: string | null; failure_summary: string | null; intentional_resend: boolean; prepared_at: string | null; completed_at: string | null };
type WhatsappPrepared = Delivery & { phone: string; message: string; whatsapp_uri: string; operator_instruction: string; attachment: null | { filename: string; sha256: string; pdf: string } };

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
function saveBase64(filename: string, value: string) {
    const bytes = Uint8Array.from(atob(value), (char) => char.charCodeAt(0));
    const url = URL.createObjectURL(new Blob([bytes], { type: 'application/pdf' }));
    const a = document.createElement('a'); a.href = url; a.download = filename; a.click(); URL.revokeObjectURL(url);
}
function openHtml(html: string) {
    const popup = window.open('', '_blank', 'width=900,height=1000');
    if (!popup) throw new Error('Print window was blocked.');
    popup.document.write('<html><body>' + html + '</body></html>'); popup.document.close(); popup.focus(); popup.print();
}

export default function PosCustomerReportingWorkspace({ area }: { area: Area }) {
    const [data, setData] = useState<Data | null>(null);
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState('');
    const [from, setFrom] = useState(''); const [to, setTo] = useState('');
    const [query, setQuery] = useState(''); const [category, setCategory] = useState('');
    const [filter, setFilter] = useState({q:'',category:'',page:1});
    const load = async (next = filter) => {
        const q = area === 'reports' ? '?' + new URLSearchParams({ ...(from ? { from } : {}), ...(to ? { to } : {}) }).toString()
            : (area === 'invoices' || area === 'claims' || area === 'warranty') ? '?' + new URLSearchParams({q:next.q,...(next.category?{category:next.category}:{}),page:String(next.page)}).toString() : '';
        const result = await api<Data>('/internal/admin/pos/customer-reporting/' + area + q);
        setData(result); setFilter(next);
        if(result.pagination){setQuery(next.q);setCategory(result.pagination.category);}
    };
    useEffect(() => { void load(); }, [area]);
    const run = async (task: () => Promise<void>, reload = true) => {
        setBusy(true); setMessage('');
        try { await task(); if (reload) await load(); }
        catch (error) { setMessage(error instanceof Error ? error.message : 'Request failed.'); }
        finally { setBusy(false); }
    };
    if (!data) return <div className="mt-6 rounded-2xl border bg-slate-50 p-5 text-sm">Loading…</div>;
    return <div data-testid={'mt43-' + area} className="mt-6 grid min-w-0 gap-5">
        {data.pagination && <section data-testid="history-controls" className="rounded-xl border bg-white p-3"><form className="flex flex-wrap items-center gap-2" onSubmit={(event)=>{event.preventDefault();void run(()=>load({q:query.trim(),category,page:1}),false);}}><input data-testid="history-search" autoFocus={data.pagination.auto_focus_search} value={query} onChange={(event)=>setQuery(event.target.value)} maxLength={100} aria-label="Search history" className="min-w-0 flex-1 rounded border p-2 text-sm" placeholder="Search history"/><select data-testid="history-category" aria-label="Search category" className="rounded border p-2 text-sm" value={category} onChange={(event)=>setCategory(event.target.value)}>{data.pagination.options.map((item)=><option key={item} value={item}>{item.replaceAll('_',' ')}</option>)}</select><button data-testid="history-apply" disabled={busy} className="rounded border p-2 text-sm" type="submit">Search</button></form><div className="mt-2 flex flex-wrap items-center gap-2 text-xs"><span data-testid="history-total">{data.pagination.total} records · {data.pagination.per_page} per page · {data.pagination.page}/{data.pagination.pages}</span><button data-testid="history-prev" className="rounded border px-2 py-1 disabled:opacity-40" disabled={busy||data.pagination.page<=1} onClick={()=>void run(()=>load({...filter,page:data.pagination!.page-1}),false)}>Previous</button><button data-testid="history-next" className="rounded border px-2 py-1 disabled:opacity-40" disabled={busy||data.pagination.page>=data.pagination.pages} onClick={()=>void run(()=>load({...filter,page:data.pagination!.page+1}),false)}>Next</button></div></section>}
        {message && <p role="alert" className="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{message}</p>}
        {area === 'invoices' && <Invoices data={data} busy={busy} run={run} />}
        {area === 'warranty' && <WarrantyHistory data={data} busy={busy} run={run} />}
        {area === 'claims' && <Claims data={data} busy={busy} run={run} area={area} />}
        {area === 'reports' && <Reports data={data} busy={busy} run={run} from={from} to={to} setFrom={setFrom} setTo={setTo} reload={load} />}
    </div>;
}

function Invoices({ data, busy, run }: { data: Data; busy: boolean; run: (task: () => Promise<void>, reload?: boolean) => Promise<void> }) {
    const [invoiceId, setInvoiceId] = useState(data.invoices[0]?.id ?? '');
    const [customerId, setCustomerId] = useState('');
    const selected = data.invoices.find((row) => row.id === invoiceId) ?? data.invoices[0];
    const customer = data.customers.find((row) => row.id === customerId);
    return <>
        <section className="min-w-0 rounded-2xl border bg-white p-5"><h3 className="font-semibold">Customers & invoice history</h3>
            <div className="mt-3 grid gap-3 lg:grid-cols-2"><div><select value={customerId} onChange={(e) => setCustomerId(e.target.value)} className="w-full min-w-0 rounded border p-2 text-sm"><option value="">Customer history</option>{data.customers.map((row) => <option key={row.id} value={row.id}>{row.name} · {row.mobile ?? row.email ?? 'no contact'}</option>)}</select>{customer && <div className="mt-2 rounded bg-slate-50 p-3 text-sm"><p><strong>{customer.name}</strong> · {customer.mobile ?? '—'} · {customer.email ?? '—'}</p><div className="mt-2 grid gap-1">{customer.invoices.map((row) => <button key={row.id} onClick={() => setInvoiceId(row.id)} className="rounded border bg-white p-2 text-left text-xs">{row.number} · PKR {row.final_bill} · {row.created_at}</button>)}</div></div>}</div>
            <div><select value={selected?.id ?? ''} onChange={(e) => setInvoiceId(e.target.value)} className="w-full min-w-0 rounded border p-2 text-sm"><option value="">Invoice</option>{data.invoices.map((row) => <option key={row.id} value={row.id}>{row.number} · {row.customer_name ?? 'Walk-in'} · PKR {row.final_bill}</option>)}</select>{selected && <div className="mt-2 rounded bg-slate-50 p-3 text-sm"><p>{selected.customer_name ?? 'Walk-in'} · {selected.customer_phone ?? '—'} · {selected.customer_email ?? '—'}</p><p>{selected.salesperson_name ?? '—'} · {selected.created_at}</p></div>}</div></div>
        </section>
        {selected && <DocumentActions key={selected.id} type="invoice" documentId={selected.id} canSend={data.can_send_documents} busy={busy} run={run} />}
    </>;
}

function WarrantyHistory({ data, busy, run }: { data: Data; busy: boolean; run: (task: () => Promise<void>, reload?: boolean) => Promise<void> }) {
    const [selectedId, setSelectedId] = useState(data.claims[0]?.id ?? '');
    const selected = data.claims.find((row) => row.id === selectedId) ?? data.claims[0];
    return <>
        <section className="min-w-0 rounded-2xl border bg-white p-5"><h3 className="font-semibold">Warranty Claim Receipts</h3><p className="mt-1 text-xs text-slate-500">Historical warranty receipts are outlet-scoped. Claim intake/lifecycle remains in Claims.</p><select value={selected?.id ?? ''} onChange={(e) => setSelectedId(e.target.value)} className="mt-3 w-full min-w-0 rounded border p-2 text-sm"><option value="">Warranty claim</option>{data.claims.map((row) => <option key={row.id} value={row.id}>{row.number} · {row.product_name} · {row.status}</option>)}</select>{selected && <div className="mt-3 rounded bg-slate-50 p-3 text-sm"><p>{selected.number} · {selected.customer_name ?? '—'} · {selected.product_name}</p><p>{selected.invoice_number} · received {selected.received_at}</p></div>}</section>
        {selected && <DocumentActions key={selected.id} type="warranty" documentId={selected.id} canSend={data.can_send_documents} busy={busy} run={run} />}
    </>;
}

function Claims({ data, busy, run, area: _area }: { data: Data; busy: boolean; run: (task: () => Promise<void>, reload?: boolean) => Promise<void>; area: 'claims' }) {
    const [selectedId, setSelectedId] = useState(data.claims[0]?.id ?? '');
    const [detail, setDetail] = useState<ClaimDetail | null>(null);
    const [saleId, setSaleId] = useState('');
    const [intakeTerm, setIntakeTerm] = useState('');
    const [intakeCategory, setIntakeCategory] = useState(data.warranty_intake_category ?? 'all');
    const [intakeProductCategory, setIntakeProductCategory] = useState('');
    const [intakeResults, setIntakeResults] = useState<SaleCandidate[] | null>(null);
    const intakeCandidates = intakeResults ?? data.sale_candidates;
    const [unitId, setUnitId] = useState('');
    const [issue, setIssue] = useState('');
    const [receivedCondition, setReceivedCondition] = useState('');
    const [accessories, setAccessories] = useState('');
    const [assigned, setAssigned] = useState('');
    const [status, setStatus] = useState('diagnosing');
    const [diagnosis, setDiagnosis] = useState('');
    const [resolution, setResolution] = useState('');
    const sale = intakeCandidates.find((row) => row.sale_id === saleId);
    const transitions = useMemo(() => ({
        received: ['diagnosing','rejected'], diagnosing: ['awaiting_parts','repaired','replaced','rejected'],
        awaiting_parts: ['diagnosing','repaired','replaced','rejected'], repaired: ['ready_for_collection'],
        replaced: ['ready_for_collection'], rejected: ['ready_for_collection','closed'], ready_for_collection: ['delivered'],
        delivered: ['closed'], closed: [],
    } as Record<string,string[]>), []);
    useEffect(() => { setSelectedId(''); setDetail(null); }, [data.pagination?.page, data.pagination?.q, data.pagination?.category]);
    const loadClaim = async (id: string) => { setSelectedId(id); setDetail(id ? await api<ClaimDetail>('/internal/admin/pos/customer-reporting/claims/' + id) : null); };
    return <>
        <section className="min-w-0 rounded-2xl border bg-white p-5"><h3 className="font-semibold">Claim lifecycle</h3>
            <form data-testid="warranty-intake-search" className="mt-3 flex flex-wrap gap-2" onSubmit={(event) => { event.preventDefault(); void run(async () => { const params = new URLSearchParams({q:intakeTerm.trim(),category:intakeCategory,...(intakeProductCategory?{product_category:intakeProductCategory}:{})}); const result = await api<{sale_candidates:SaleCandidate[]}>('/internal/admin/pos/customer-reporting/claims/sale-search?'+params.toString()); setIntakeResults(result.sale_candidates); setSaleId(''); setUnitId(''); },false); }}><input data-testid="intake-query" required maxLength={255} value={intakeTerm} onChange={(event)=>setIntakeTerm(event.target.value)} placeholder="Find invoice, customer or IMEI (all sales)" className="min-w-0 flex-1 rounded border p-2 text-sm" /><select data-testid="intake-category" value={intakeCategory} onChange={(event)=>setIntakeCategory(event.target.value)} className="rounded border p-2 text-sm">{['all','invoice_id','customer_name','customer_cnic','contact_number','product','imei'].map((item)=><option key={item} value={item}>{item.replaceAll('_',' ')}</option>)}</select><select data-testid="intake-product-category" value={intakeProductCategory} onChange={(event)=>setIntakeProductCategory(event.target.value)} className="rounded border p-2 text-sm"><option value="">All products</option>{['mobile_phone','tablet','accessory'].map((item)=><option key={item} value={item}>{item.replaceAll('_',' ')}</option>)}</select><button type="submit" data-testid="intake-find" disabled={busy || !intakeTerm.trim()} className="rounded border px-3 py-2 text-sm">Find sale</button></form><p data-testid="intake-result-count" className="mt-1 text-xs text-slate-600">{intakeResults===null?'Latest 100 sale occurrences shown; search can find older invoices.':intakeResults.length+' matching sale occurrences (maximum 20).'}</p>
            <div className="mt-3 grid gap-2 sm:grid-cols-2"><select data-testid="intake-sale" value={saleId} onChange={(e) => { setSaleId(e.target.value); setUnitId(''); }} className="w-full min-w-0 rounded border p-2 text-sm"><option value="">Eligible sale occurrence</option>{intakeCandidates.map((row) => <option key={row.sale_id} value={row.sale_id}>{row.invoice_number} · {row.product_name} · {row.customer_name ?? 'Walk-in'} · {row.warranty_type ?? 'no warranty'}</option>)}</select>{sale?.track_imei ? <select value={unitId} onChange={(e) => setUnitId(e.target.value)} className="w-full min-w-0 rounded border p-2 text-sm"><option value="">Sold unit</option>{sale.units.map((unit) => <option key={unit.id} value={unit.id}>{unit.code}</option>)}</select> : <input value="1" readOnly className="w-full min-w-0 rounded border bg-slate-50 p-2 text-sm" />}</div>
            <div className="mt-2 grid gap-2 sm:grid-cols-2"><textarea value={issue} onChange={(e) => setIssue(e.target.value)} placeholder="Issue description" rows={3} className="w-full min-w-0 rounded border p-2 text-sm" /><input value={receivedCondition} onChange={(e) => setReceivedCondition(e.target.value)} placeholder="Received condition" className="w-full min-w-0 rounded border p-2 text-sm" /><input value={accessories} onChange={(e) => setAccessories(e.target.value)} placeholder="Accessories received" className="w-full min-w-0 rounded border p-2 text-sm" /><input value={assigned} onChange={(e) => setAssigned(e.target.value)} placeholder="Assigned to" className="w-full min-w-0 rounded border p-2 text-sm" /></div>
            <button data-testid="claim-open" disabled={busy || !saleId || !issue || (sale?.track_imei === true && !unitId)} onClick={() => void run(async () => { const opened = await api<ClaimDetail>('/internal/admin/pos/customer-reporting/claims', { method: 'POST', body: JSON.stringify({ sale_id: saleId, stock_unit_id: unitId || null, quantity: 1, issue_description: issue, received_condition: receivedCondition || null, accessories_received: accessories || null, assigned_to: assigned || null, expected_completion_at: null }) }); await loadClaim(opened.claim_id); })} className="mt-3 rounded bg-slate-950 px-3 py-2 text-sm font-semibold text-white disabled:opacity-40">Open warranty claim</button>
        </section>
        <section className="min-w-0 rounded-2xl border bg-white p-5"><h3 className="font-semibold">Historical claims</h3><select value={data.claims.some((row)=>row.id===selectedId)?selectedId:''} onChange={(e) => void loadClaim(e.target.value)} className="mt-3 w-full min-w-0 rounded border p-2 text-sm"><option value="">Claim</option>{data.claims.map((row) => <option key={row.id} value={row.id}>{row.number} · {row.product_name} · {row.status}</option>)}</select>
            {detail && <div className="mt-3 grid gap-3"><div className="rounded bg-slate-50 p-3 text-sm"><p>{String(detail.claim_number)} · <strong>{String(detail.status)}</strong> · {String(detail.invoice_number)}</p><p>{String(detail.customer_name ?? '')} · {String(detail.product_name ?? '')}</p></div>
            {(transitions[String(detail.status)] ?? []).length > 0 && <div className="grid gap-2 sm:grid-cols-4"><select value={status} onChange={(e) => setStatus(e.target.value)} className="w-full min-w-0 rounded border p-2 text-sm"><option value="">Next status</option>{(transitions[String(detail.status)] ?? []).map((value) => <option key={value} value={value}>{value}</option>)}</select><input value={diagnosis} onChange={(e) => setDiagnosis(e.target.value)} placeholder="Diagnosis" className="w-full min-w-0 rounded border p-2 text-sm" /><input value={resolution} onChange={(e) => setResolution(e.target.value)} placeholder="Resolution" className="w-full min-w-0 rounded border p-2 text-sm" /><button disabled={busy || !status} onClick={() => void run(async () => { const updated = await api<ClaimDetail>('/internal/admin/pos/customer-reporting/claims/' + detail.claim_id, { method: 'POST', body: JSON.stringify({ status, assigned_to: assigned || null, diagnosis: diagnosis || null, resolution: resolution || null, internal_notes: null, expected_completion_at: null, customer_satisfied: null, follow_up_required: false, follow_up_at: null, follow_up_notes: null }) }); setDetail(updated); setStatus(''); })} className="rounded border px-3 py-2 text-sm">Update status</button></div>}
            <details className="rounded border p-3"><summary className="cursor-pointer text-sm font-semibold">Warranty & activity history</summary><pre className="mt-2 max-h-64 max-w-full overflow-auto rounded bg-slate-950 p-3 text-xs text-white">{JSON.stringify({ warranty: detail.warranty, activity: detail.activity_log }, null, 2)}</pre></details></div>}
        </section>
        {detail && <DocumentActions key={detail.claim_id} type="warranty" documentId={detail.claim_id} canSend={data.can_send_documents} busy={busy} run={run} />}
    </>;
}

function Reports({ data, busy, run, from, to, setFrom, setTo, reload }: { data: Data; busy: boolean; run: (task: () => Promise<void>, reload?: boolean) => Promise<void>; from: string; to: string; setFrom: (v: string) => void; setTo: (v: string) => void; reload: () => Promise<void> }) {
    const report = data.report;
    const [showDestinations, setShowDestinations] = useState(false);
    if (!report) return null;
    const exportCsv = async () => { const q = '?' + new URLSearchParams({ ...(from ? { from } : {}), ...(to ? { to } : {}) }).toString(); const result = await api<{ filename: string; csv: string }>('/internal/admin/pos/customer-reporting/report/csv' + q); const url = URL.createObjectURL(new Blob([result.csv], { type: 'text/csv' })); const a = document.createElement('a'); a.href = url; a.download = result.filename; a.click(); URL.revokeObjectURL(url); };
    return <section className="min-w-0 rounded-2xl border bg-white p-5"><div className="flex flex-wrap items-start justify-between gap-3"><div><h3 className="font-semibold">Outlet dashboard & reports</h3><p className="text-xs text-slate-500">Invoice totals are counted once; Payment Mix keeps POS and Website channels distinct.</p></div><div className="flex flex-wrap gap-2"><input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="rounded border p-2 text-sm" /><input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="rounded border p-2 text-sm" /><button disabled={busy} onClick={() => void run(reload, false)} className="rounded border px-3 py-2 text-sm">Apply</button><button disabled={busy} onClick={() => void run(exportCsv, false)} className="rounded border px-3 py-2 text-sm">Export CSV</button></div></div>
        <div className="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">{Object.entries(report.sales).filter(([,v]) => typeof v !== 'string' || !String(v).startsWith('Invoice totals')).map(([key,value]) => <div key={key} className="rounded bg-slate-50 p-3 text-sm"><span className="block text-xs text-slate-500">{key.replaceAll('_',' ')}</span><strong>{String(value)}</strong></div>)}</div>
        <div className="mt-5 rounded-xl border p-4"><div className="flex flex-wrap items-center justify-between gap-2"><h4 className="font-semibold">Payment Mix</h4><button onClick={() => setShowDestinations((old) => !old)} className="rounded border px-2 py-1 text-xs">{showDestinations ? 'Hide destination detail' : 'Destination drill-down'}</button></div><div className="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4"><div>POS: <strong>PKR {report.payments.pos_tender_total}</strong></div><div>Website: <strong>PKR {report.payments.website_payment_total}</strong></div><div>Fees: <strong>PKR {report.payments.provider_fees}</strong></div><div>Settlement variance: <strong>PKR {report.payments.settlement_variance}</strong></div>{Object.entries(report.payments.method_breakdown).map(([method,value]) => <div key={method}>{method}: <strong>PKR {value}</strong></div>)}{Object.entries(report.payments.website_gateway_breakdown).map(([gateway,value]) => <div key={'w-'+gateway}>Website {gateway}: <strong>PKR {value}</strong></div>)}</div>{showDestinations && <div className="mt-3 grid gap-2">{report.payments.destination_breakdown.map((row,index) => <div key={index} className="rounded bg-slate-50 p-2 text-sm">{row.destination} · {row.method} · PKR {row.amount}</div>)}</div>}</div>
        <div className="mt-5 grid gap-2 sm:grid-cols-3">{Object.entries(report.activity).map(([key,value]) => <div key={key} className="rounded border p-3 text-sm"><span className="text-slate-500">{key.replaceAll('_',' ')}</span><strong className="ml-2">{value}</strong></div>)}</div>
    </section>;
}

export function DocumentActions({ type, documentId, canSend, busy, run, defaultFormat = 'a4' }: { type: 'invoice' | 'warranty'; documentId: string; canSend: boolean; busy: boolean; run: (task: () => Promise<void>, reload?: boolean) => Promise<void>; defaultFormat?: 'a4' | 'thermal80' }) {
    const [format, setFormat] = useState<'a4' | 'thermal80'>(type === 'warranty' ? 'a4' : defaultFormat);
    const [preview, setPreview] = useState<DocRender | null>(null);
    const [draft, setDraft] = useState<EmailDraft | null>(null);
    const [history, setHistory] = useState<Delivery[]>([]);
    const [wa, setWa] = useState<WhatsappPrepared | null>(null);
    const base = '/internal/admin/pos/customer-reporting/documents/' + type + '/' + documentId;
    const loadHistory = async () => setHistory(await api<Delivery[]>(base + '/history'));
    return <section className="min-w-0 rounded-2xl border bg-white p-5"><div className="flex flex-wrap items-center justify-between gap-2"><div><h3 className="font-semibold">Document Actions</h3><p className="text-xs text-slate-500">Preview first. Finalization/delivery never auto-downloads or auto-sends.</p></div>{type === 'invoice' && <select value={format} onChange={(e) => setFormat(e.target.value as 'a4'|'thermal80')} className="rounded border p-2 text-sm"><option value="a4">A4</option><option value="thermal80">Thermal 80mm</option></select>}</div>
        <div className="mt-3 flex flex-wrap gap-2"><button disabled={busy} onClick={() => void run(async () => setPreview(await api<DocRender>(base + '/preview?format=' + format)), false)} className="rounded border px-3 py-2 text-sm">Preview</button><button disabled={busy} onClick={() => void run(async () => { const result = await api<DocRender>(base + '/print?format=' + format); openHtml(result.html ?? ''); }, false)} className="rounded border px-3 py-2 text-sm">Print</button><button disabled={busy} onClick={() => void run(async () => { const result = await api<DocRender>(base + '/pdf?format=' + format); if (!result.pdf_base64) throw new Error('PDF payload is unavailable.'); saveBase64(result.filename, result.pdf_base64); }, false)} className="rounded border px-3 py-2 text-sm">Save PDF</button>{canSend && <><button disabled={busy} onClick={() => void run(async () => setDraft(await api<EmailDraft>(base + '/email-draft')), false)} className="rounded border px-3 py-2 text-sm">Send via Email</button><button disabled={busy} onClick={() => void run(async () => { const prepared = await api<WhatsappPrepared>(base + '/whatsapp', { method: 'POST', body: JSON.stringify({ requires_attachment: true }) }); setWa(prepared); await loadHistory(); }, false)} className="rounded border px-3 py-2 text-sm">Send via WhatsApp</button></>}<button disabled={busy} onClick={() => void run(loadHistory, false)} className="rounded border px-3 py-2 text-sm">Delivery history</button></div>
        {preview?.html && <div className="mt-3 max-h-72 overflow-auto rounded border bg-slate-50 p-4 text-sm" dangerouslySetInnerHTML={{ __html: preview.html }} />}
        {draft && <div className="mt-3 grid gap-2 rounded border p-3"><input value={draft.to} onChange={(e) => setDraft({ ...draft, to: e.target.value })} placeholder="Recipient email" className="w-full min-w-0 rounded border p-2 text-sm" /><input value={draft.subject} onChange={(e) => setDraft({ ...draft, subject: e.target.value })} placeholder="Subject" className="w-full min-w-0 rounded border p-2 text-sm" /><textarea value={draft.message} onChange={(e) => setDraft({ ...draft, message: e.target.value })} rows={4} className="w-full min-w-0 rounded border p-2 text-sm" /><button disabled={busy} onClick={() => void run(async () => { await api(base + '/email', { method: 'POST', body: JSON.stringify({ to: draft.to, subject: draft.subject, message: draft.message, resend: history.some((x) => x.channel === 'email' && x.state === 'sent') }) }); setDraft(null); await loadHistory(); }, false)} className="rounded bg-slate-950 px-3 py-2 text-sm font-semibold text-white">Confirm Email Send</button></div>}
        {wa && <div className="mt-3 rounded border bg-amber-50 p-3 text-sm"><p>{wa.operator_instruction}</p><p className="mt-1 text-xs">WhatsApp state is <strong>{wa.state}</strong>; opening WhatsApp is not recorded as sent.</p><button onClick={() => void run(async () => { window.open(wa.whatsapp_uri, '_blank'); await api('/internal/admin/pos/customer-reporting/documents/whatsapp/' + wa.attempt_id + '/opened', { method: 'POST', body: '{}' }); await loadHistory(); }, false)} className="mt-2 rounded border bg-white px-3 py-2 text-sm">Open WhatsApp</button></div>}
        {history.length > 0 && <div className="mt-3 grid gap-2">{history.map((row) => <div key={row.attempt_id} className="rounded bg-slate-50 p-2 text-xs">{row.channel} · <strong>{row.state}</strong> · {row.recipient ?? '—'}{row.failure_summary ? ' · ' + row.failure_summary : ''}{row.intentional_resend ? ' · intentional resend' : ''}</div>)}</div>}
    </section>;
}
