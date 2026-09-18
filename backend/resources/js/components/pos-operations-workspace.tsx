import { useEffect, useMemo, useState } from 'react';

type Permissions = {
    canCash: boolean; canCashApprove: boolean; canTradeIn: boolean; canRepairs: boolean; canReconcile: boolean;
};
type NonCash = {
    destination_id: string; method: string; display_name: string; gross_expected_receipts: string;
    allocation_count: number; settled_allocation_count: number; merchant_fees: string; adjustments: string;
    expected_net: string; received_net: string; settlement_variance: string;
};
type CashEntry = {
    entry_id: string; type: string; amount: string; reason: string; reference: string | null;
    status: string; review_notes?: string | null;
};
type CashSession = {
    session_id: string; business_date: string; status: string; version: number; opening_cash: string;
    summary?: {
        cash_sales: string; approved_cash_in: string; cash_refunds: string; expenses: string; payouts: string;
        expected_cash: string; non_cash_destinations: NonCash[];
    };
    entries?: CashEntry[];
    closing?: Record<string, unknown>;
};
type Settlement = {
    allocation_id: string; method: string; amount: string; destination_name: string; reconciliation_state: string;
    settlement_version: number; latest: null | {
        fee_amount: string; adjustment_amount: string; expected_net_amount: string;
        received_net_amount: string; variance_amount: string; recorded_at: string;
    };
};
type TradeIn = {
    trade_in_id: string; status: string; version: number; product_id: string; settlement_mode: string;
    valuation_amount: string; invoice_id: string | null; device_serial: string | null; condition: string;
    imeis: string[]; seller: { name: string; cnic: string; phone: string }; cancel_reason?: string | null;
    events?: Array<{ sequence: number; event_type: string; snapshot: Record<string, unknown>; created_at: string }>;
};
type RepairRow = {
    id: string; number: string; customer_name: string; customer_phone: string | null; device_label: string;
    identifier_type: string; identifier_value: string; status: string; version: number;
};
type RepairDetail = {
    repair_id: string; repair_number: string; version: number; customer_name: string; customer_phone: string | null;
    device_label: string; identifier_type: string; identifier_value: string; issue_description: string;
    received_condition: string | null; accessories_received: string | null; diagnosis: string | null;
    internal_notes: string | null; status: string; approved_estimate_id: string | null;
    estimates: Array<{
        estimate_id: string; version: number; status: string; parts_total: string; labor_total: string;
        grand_total: string; currency: string; notes: string | null;
        lines: Array<{ type: string; product_id: string | null; description: string; quantity: number; unit_price: string; line_total: string }>;
    }>;
    payments: Array<{ allocation_id: string; method: string; amount: string; reconciliation_state: string }>;
    events: Array<{ event_type: string; status: string; occurred_at: string; data?: Record<string, unknown> }>;
};
type Data = {
    outlet: { id: string; name: string }; permissions: Permissions; cash_session: CashSession | null;
    cash_history: Array<{ session_id: string; business_date: string; status: string; opening_cash: string; expected_cash: string | null; actual_cash: string | null; variance_amount: string | null }>;
    settlements: Settlement[];
    serialized_products: Array<{ id: string; name: string; code: string; required_imei_slots: number }>;
    trade_ins: TradeIn[]; invoice_candidates: Array<{ id: string; number: string; final_bill: string }>;
    repair_setting: { enabled: boolean; version: number | null } | null; repairs: RepairRow[];
    repair_parts: Array<{ id: string; name: string; code: string; qty: number; sale_price: string }>;
    payment_destinations: Array<{ public_id: string; method: string; display_name: string; provider_label?: string | null; masked_identifier?: string | null }>;
};

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

export default function PosOperationsWorkspace() {
    const [data, setData] = useState<Data | null>(null);
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState('');
    const load = async () => setData(await api<Data>('/internal/admin/pos/operations'));
    useEffect(() => { void load(); }, []);
    const run = async (task: () => Promise<void>, reload = true) => {
        setBusy(true); setMessage('');
        try { await task(); if (reload) await load(); }
        catch (error) { setMessage(error instanceof Error ? error.message : 'Request failed.'); }
        finally { setBusy(false); }
    };
    if (!data) return <div className="mt-6 rounded-2xl border bg-slate-50 p-5 text-sm">Loading operations…</div>;
    return <div data-testid="operations-workspace" className="mt-6 grid min-w-0 gap-5">
        {message && <p role="alert" className="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{message}</p>}
        {(data.permissions.canCash || data.permissions.canCashApprove) && <CashPanel data={data} busy={busy} run={run} />}
        {data.permissions.canReconcile && <SettlementPanel data={data} busy={busy} run={run} />}
        {data.permissions.canTradeIn && <TradeInPanel data={data} busy={busy} run={run} />}
        {data.permissions.canRepairs && <RepairPanel data={data} busy={busy} run={run} />}
    </div>;
}

function CashPanel({ data, busy, run }: { data: Data; busy: boolean; run: (task: () => Promise<void>, reload?: boolean) => Promise<void> }) {
    const session = data.cash_session;
    const [opening, setOpening] = useState('0.00');
    const [entryType, setEntryType] = useState('expense');
    const [entryAmount, setEntryAmount] = useState('');
    const [entryReason, setEntryReason] = useState('');
    const [entryReference, setEntryReference] = useState('');
    const [actual, setActual] = useState('');
    const [varianceReason, setVarianceReason] = useState('');
    const [historyId, setHistoryId] = useState('');
    const [history, setHistory] = useState<CashSession | null>(null);
    const summary = session?.summary;
    return <section className="min-w-0 rounded-2xl border bg-white p-5">
        <div className="flex flex-wrap items-start justify-between gap-3"><div><h3 className="font-semibold">Cash & Day Closing</h3><p className="text-xs text-slate-500">Opening cash, cash sales/refunds, expenses/payouts, expected cash, actual count and variance.</p></div><span className="rounded-full bg-slate-100 px-3 py-1 text-xs">{session ? 'Open session' : 'No open session'}</span></div>
        {!session && data.permissions.canCash && <div className="mt-4 flex flex-col gap-2 sm:flex-row"><input value={opening} onChange={(e) => setOpening(e.target.value)} placeholder="Opening cash" className="min-w-0 flex-1 rounded border p-2 text-sm" /><button data-testid="cash-open" disabled={busy} onClick={() => void run(async () => { await api('/internal/admin/pos/operations/cash/open', { method: 'POST', body: JSON.stringify({ opening_cash: opening }) }); })} className="rounded bg-slate-950 px-3 py-2 text-sm font-semibold text-white">Open cash session</button></div>}
        {summary && session && <div className="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
            {[
                ['Opening Cash', session.opening_cash], ['Cash Sales', summary.cash_sales], ['Cash Refunds', summary.cash_refunds],
                ['Cash In', summary.approved_cash_in], ['Expenses', summary.expenses], ['Payouts', summary.payouts], ['Expected Cash', summary.expected_cash],
            ].map(([label, value]) => <div key={label} className="rounded-xl bg-slate-50 p-3 text-sm"><span className="block text-xs text-slate-500">{label}</span><strong>PKR {value}</strong></div>)}
        </div>}
        {summary?.non_cash_destinations.length ? <div className="mt-4 min-w-0 overflow-auto"><table className="w-full min-w-[700px] text-left text-xs"><thead><tr className="border-b"><th className="p-2">Non-cash destination</th><th>Gross</th><th>Settled</th><th>Fees</th><th>Adjustments</th><th>Expected net</th><th>Received</th><th>Variance</th></tr></thead><tbody>{summary.non_cash_destinations.map((row) => <tr key={row.destination_id} className="border-b"><td className="p-2">{row.display_name} · {row.method}</td><td>{row.gross_expected_receipts}</td><td>{row.settled_allocation_count}/{row.allocation_count}</td><td>{row.merchant_fees}</td><td>{row.adjustments}</td><td>{row.expected_net}</td><td>{row.received_net}</td><td>{row.settlement_variance}</td></tr>)}</tbody></table></div> : null}
        {session && data.permissions.canCash && <div className="mt-5 grid gap-3 lg:grid-cols-2">
            <div className="rounded-xl border p-4"><h4 className="text-sm font-semibold">Expense / payout / cash in</h4><div className="mt-3 grid gap-2"><select value={entryType} onChange={(e) => setEntryType(e.target.value)} className="w-full min-w-0 rounded border p-2 text-sm"><option value="expense">Expense</option><option value="payout">Payout</option><option value="cash_in">Cash in</option></select><input value={entryAmount} onChange={(e) => setEntryAmount(e.target.value)} placeholder="Amount" className="w-full min-w-0 rounded border p-2 text-sm" /><input value={entryReason} onChange={(e) => setEntryReason(e.target.value)} placeholder="Reason" className="w-full min-w-0 rounded border p-2 text-sm" /><input value={entryReference} onChange={(e) => setEntryReference(e.target.value)} placeholder="Reference (optional)" className="w-full min-w-0 rounded border p-2 text-sm" /><button disabled={busy || !entryAmount || !entryReason} onClick={() => void run(async () => { await api('/internal/admin/pos/operations/cash/' + session.session_id + '/entries', { method: 'POST', body: JSON.stringify({ session_version: session.version, type: entryType, amount: entryAmount, reason: entryReason, reference: entryReference || null }) }); setEntryAmount(''); setEntryReason(''); setEntryReference(''); })} className="rounded border px-3 py-2 text-sm font-semibold">Record entry</button></div></div>
            <div className="rounded-xl border p-4"><h4 className="text-sm font-semibold">Close day</h4><p className="mt-1 text-xs text-slate-500">Backend computes variance from authoritative expected cash.</p><div className="mt-3 grid gap-2"><input value={actual} onChange={(e) => setActual(e.target.value)} placeholder="Actual cash count" className="w-full min-w-0 rounded border p-2 text-sm" /><input value={varianceReason} onChange={(e) => setVarianceReason(e.target.value)} placeholder="Variance reason if non-zero" className="w-full min-w-0 rounded border p-2 text-sm" /><button data-testid="cash-close" disabled={busy || !actual} onClick={() => void run(async () => { await api('/internal/admin/pos/operations/cash/' + session.session_id + '/close', { method: 'POST', body: JSON.stringify({ session_version: session.version, actual_cash: actual, variance_reason: varianceReason || null }) }); })} className="rounded bg-slate-950 px-3 py-2 text-sm font-semibold text-white">Close & reconcile</button></div></div>
        </div>}
        {session?.entries?.length ? <div className="mt-5 grid gap-2"><h4 className="text-sm font-semibold">Cash entries</h4>{session.entries.map((entry) => <div key={entry.entry_id} className="flex flex-wrap items-center gap-2 rounded-lg border p-3 text-sm"><span className="min-w-0 flex-1">{entry.type} · PKR {entry.amount} · {entry.reason} · <strong>{entry.status}</strong></span>{entry.status === 'pending' && data.permissions.canCashApprove && <><button disabled={busy} onClick={() => void run(async () => { await api('/internal/admin/pos/operations/cash/' + session.session_id + '/entries/' + entry.entry_id + '/review', { method: 'POST', body: JSON.stringify({ session_version: session.version, decision: 'approved', notes: null }) }); })} className="rounded border px-2 py-1 text-xs">Approve</button><button disabled={busy} onClick={() => void run(async () => { await api('/internal/admin/pos/operations/cash/' + session.session_id + '/entries/' + entry.entry_id + '/review', { method: 'POST', body: JSON.stringify({ session_version: session.version, decision: 'rejected', notes: 'Rejected in POS operations review' }) }); })} className="rounded border px-2 py-1 text-xs">Reject</button></>}</div>)}</div> : null}
        <div className="mt-5 rounded-xl bg-slate-50 p-4"><h4 className="text-sm font-semibold">Closing history drill-down</h4><div className="mt-2 flex flex-col gap-2 sm:flex-row"><select value={historyId} onChange={(e) => setHistoryId(e.target.value)} className="min-w-0 flex-1 rounded border bg-white p-2 text-sm"><option value="">Cash session</option>{data.cash_history.map((row) => <option key={row.session_id} value={row.session_id}>{row.business_date} · {row.status} · variance {row.variance_amount ?? '—'}</option>)}</select><button disabled={!historyId || busy} onClick={() => void run(async () => { setHistory(await api<CashSession>('/internal/admin/pos/operations/cash/' + historyId)); }, false)} className="rounded border bg-white px-3 py-2 text-sm">Open detail</button></div>{history && <pre className="mt-3 max-h-64 max-w-full overflow-auto rounded bg-slate-950 p-3 text-xs text-white">{JSON.stringify(history, null, 2)}</pre>}</div>
    </section>;
}

function SettlementPanel({ data, busy, run }: { data: Data; busy: boolean; run: (task: () => Promise<void>, reload?: boolean) => Promise<void> }) {
    const [id, setId] = useState('');
    const [fee, setFee] = useState('0.00');
    const [adjustment, setAdjustment] = useState('0.00');
    const [received, setReceived] = useState('');
    const [reference, setReference] = useState('');
    const selected = data.settlements.find((row) => row.allocation_id === id);
    return <section className="min-w-0 rounded-2xl border bg-white p-5"><h3 className="font-semibold">Provider settlement reconciliation</h3><p className="mt-1 text-xs text-slate-500">Fees and adjustments stay separate; backend computes expected net, received net, variance and reconciliation state.</p><div className="mt-4 grid gap-2 lg:grid-cols-2"><select value={id} onChange={(e) => { setId(e.target.value); const row = data.settlements.find((x) => x.allocation_id === e.target.value); setReceived(row?.amount ?? ''); }} className="w-full min-w-0 rounded border p-2 text-sm"><option value="">Non-cash allocation</option>{data.settlements.map((row) => <option key={row.allocation_id} value={row.allocation_id}>{row.destination_name} · {row.method} · PKR {row.amount} · {row.reconciliation_state}</option>)}</select><div className="grid grid-cols-2 gap-2"><input value={fee} onChange={(e) => setFee(e.target.value)} placeholder="Fee" className="w-full min-w-0 rounded border p-2 text-sm" /><input value={adjustment} onChange={(e) => setAdjustment(e.target.value)} placeholder="Adjustment +/-" className="w-full min-w-0 rounded border p-2 text-sm" /><input value={received} onChange={(e) => setReceived(e.target.value)} placeholder="Received net" className="w-full min-w-0 rounded border p-2 text-sm" /><input value={reference} onChange={(e) => setReference(e.target.value)} placeholder="Provider reference" className="w-full min-w-0 rounded border p-2 text-sm" /></div></div><button data-testid="settlement-save" disabled={!selected || !received || busy} onClick={() => void run(async () => { await api('/internal/admin/pos/operations/settlements/' + selected?.allocation_id, { method: 'POST', body: JSON.stringify({ settlement_version: selected?.settlement_version, fee_amount: fee, adjustment_amount: adjustment, received_net_amount: received, external_reference: reference || null, notes: null }) }); })} className="mt-3 rounded bg-slate-950 px-3 py-2 text-sm font-semibold text-white disabled:opacity-40">Confirm settlement</button>{selected?.latest && <div className="mt-3 grid gap-2 rounded bg-slate-50 p-3 text-xs sm:grid-cols-3"><span>Fees: {selected.latest.fee_amount}</span><span>Adjustment: {selected.latest.adjustment_amount}</span><span>Expected net: {selected.latest.expected_net_amount}</span><span>Received: {selected.latest.received_net_amount}</span><span>Variance: {selected.latest.variance_amount}</span><span>State: {selected.reconciliation_state}</span></div>}</section>;
}

function TradeInPanel({ data, busy, run }: { data: Data; busy: boolean; run: (task: () => Promise<void>, reload?: boolean) => Promise<void> }) {
    const [product, setProduct] = useState('');
    const selectedProduct = data.serialized_products.find((row) => row.id === product);
    const [seller, setSeller] = useState(''); const [cnic, setCnic] = useState(''); const [phone, setPhone] = useState('03'); const [address, setAddress] = useState('');
    const [serial, setSerial] = useState(''); const [imei1, setImei1] = useState(''); const [imei2, setImei2] = useState('');
    const [condition, setCondition] = useState('used'); const [diagnostics, setDiagnostics] = useState('Basic diagnostics completed');
    const [valuation, setValuation] = useState(''); const [mode, setMode] = useState('purchase'); const [invoice, setInvoice] = useState('');
    const [selectedId, setSelectedId] = useState(''); const [detail, setDetail] = useState<TradeIn | null>(null); const [cancelReason, setCancelReason] = useState('');
    const active = detail ?? data.trade_ins.find((row) => row.trade_in_id === selectedId) ?? null;
    const loadDetail = async (id: string) => { setSelectedId(id); setDetail(id ? await api<TradeIn>('/internal/admin/pos/operations/trade-ins/' + id) : null); };
    return <section className="min-w-0 rounded-2xl border bg-white p-5"><h3 className="font-semibold">Individual seller trade-in</h3><p className="mt-1 text-xs text-slate-500">Seller privacy stays masked in history. Valuation and sale-credit reservation are server controlled.</p><div className="mt-4 grid gap-2 sm:grid-cols-2"><select value={product} onChange={(e) => setProduct(e.target.value)} className="w-full min-w-0 rounded border p-2 text-sm"><option value="">Serialized product</option>{data.serialized_products.map((row) => <option key={row.id} value={row.id}>{row.name} · {row.required_imei_slots} IMEI slot(s)</option>)}</select><input value={seller} onChange={(e) => setSeller(e.target.value)} placeholder="Seller name" className="w-full min-w-0 rounded border p-2 text-sm" /><input value={cnic} onChange={(e) => setCnic(e.target.value)} placeholder="CNIC" className="w-full min-w-0 rounded border p-2 text-sm" /><input value={phone} onChange={(e) => setPhone(e.target.value)} placeholder="03XXXXXXXXX" className="w-full min-w-0 rounded border p-2 text-sm" /><input value={address} onChange={(e) => setAddress(e.target.value)} placeholder="Seller address" className="w-full min-w-0 rounded border p-2 text-sm" /><input value={serial} onChange={(e) => setSerial(e.target.value)} placeholder="Device serial (optional)" className="w-full min-w-0 rounded border p-2 text-sm" /><input value={imei1} onChange={(e) => setImei1(e.target.value)} placeholder="IMEI 1" className="w-full min-w-0 rounded border p-2 text-sm" />{(selectedProduct?.required_imei_slots ?? 0) > 1 && <input value={imei2} onChange={(e) => setImei2(e.target.value)} placeholder="IMEI 2" className="w-full min-w-0 rounded border p-2 text-sm" />}<input value={condition} onChange={(e) => setCondition(e.target.value)} placeholder="Condition" className="w-full min-w-0 rounded border p-2 text-sm" /><textarea value={diagnostics} onChange={(e) => setDiagnostics(e.target.value)} placeholder="Diagnostics, one per line" rows={3} className="w-full min-w-0 rounded border p-2 text-sm" /><input value={valuation} onChange={(e) => setValuation(e.target.value)} placeholder="Valuation PKR" className="w-full min-w-0 rounded border p-2 text-sm" /><select value={mode} onChange={(e) => setMode(e.target.value)} className="w-full min-w-0 rounded border p-2 text-sm"><option value="purchase">Purchase / buyback</option><option value="sale_credit">Sale credit</option></select>{mode === 'sale_credit' && <select value={invoice} onChange={(e) => setInvoice(e.target.value)} className="w-full min-w-0 rounded border p-2 text-sm"><option value="">Untendered POS invoice</option>{data.invoice_candidates.map((row) => <option key={row.id} value={row.id}>{row.number} · PKR {row.final_bill}</option>)}</select>}</div><button data-testid="trade-create" disabled={busy || !product || !seller || !cnic || !phone || !address || !imei1 || !valuation || (mode === 'sale_credit' && !invoice)} onClick={() => void run(async () => { const imeis = [imei1, imei2].filter(Boolean); const result = await api<TradeIn>('/internal/admin/pos/operations/trade-ins/' + product, { method: 'POST', body: JSON.stringify({ seller_name: seller, seller_cnic: cnic, seller_phone: phone, seller_address: address, device_serial: serial || null, imeis, condition, diagnostics: diagnostics.split('\n').map((x) => x.trim()).filter(Boolean), valuation_amount: valuation, settlement_mode: mode, invoice_id: mode === 'sale_credit' ? invoice : null }) }); await loadDetail(result.trade_in_id); })} className="mt-3 rounded bg-slate-950 px-3 py-2 text-sm font-semibold text-white disabled:opacity-40">Create valuation/intake</button>
        <div className="mt-5 rounded-xl bg-slate-50 p-4"><h4 className="text-sm font-semibold">Trade-in history & lifecycle</h4><select value={selectedId} onChange={(e) => void loadDetail(e.target.value)} className="mt-2 w-full min-w-0 rounded border bg-white p-2 text-sm"><option value="">Trade-in</option>{data.trade_ins.map((row) => <option key={row.trade_in_id} value={row.trade_in_id}>{row.seller.name} · PKR {row.valuation_amount} · {row.status}</option>)}</select>{active && <div className="mt-3 grid gap-2 text-sm"><p>{active.seller.name} · {active.seller.cnic} · {active.seller.phone}</p><p>{active.imeis.join(', ')} · {active.condition} · {active.settlement_mode} · version {active.version}</p><div className="flex flex-wrap gap-2">{active.status === 'pending' && <button disabled={busy} onClick={() => void run(async () => { const updated = await api<TradeIn>('/internal/admin/pos/operations/trade-ins/' + active.trade_in_id + '/approve', { method: 'POST', body: JSON.stringify({ version: active.version }) }); setDetail(updated); })} className="rounded border bg-white px-3 py-2 text-sm">Approve valuation</button>}{active.status === 'approved' && <button disabled={busy} onClick={() => void run(async () => { const updated = await api<TradeIn>('/internal/admin/pos/operations/trade-ins/' + active.trade_in_id + '/receive', { method: 'POST', body: JSON.stringify({ version: active.version }) }); setDetail(updated); })} className="rounded border bg-white px-3 py-2 text-sm">Receive into stock</button>}{['pending','approved'].includes(active.status) && <><input value={cancelReason} onChange={(e) => setCancelReason(e.target.value)} placeholder="Cancellation reason" className="min-w-0 flex-1 rounded border bg-white p-2 text-sm" /><button disabled={busy || !cancelReason} onClick={() => void run(async () => { const updated = await api<TradeIn>('/internal/admin/pos/operations/trade-ins/' + active.trade_in_id + '/cancel', { method: 'POST', body: JSON.stringify({ version: active.version, reason: cancelReason }) }); setDetail(updated); })} className="rounded border bg-white px-3 py-2 text-sm">Cancel</button></>}</div>{detail?.events && <pre className="max-h-48 max-w-full overflow-auto rounded bg-slate-950 p-3 text-xs text-white">{JSON.stringify(detail.events, null, 2)}</pre>}</div>}</div>
    </section>;
}

type EstimateLine = { type: 'labor' | 'part'; product_id: string; description: string; quantity: string; unit_price: string };

function RepairPanel({ data, busy, run }: { data: Data; busy: boolean; run: (task: () => Promise<void>, reload?: boolean) => Promise<void> }) {
    const setting = data.repair_setting ?? { enabled: false, version: null };
    const [name, setName] = useState(''); const [phone, setPhone] = useState('03'); const [device, setDevice] = useState('');
    const [identifierType, setIdentifierType] = useState('imei'); const [identifier, setIdentifier] = useState('');
    const [issue, setIssue] = useState(''); const [receivedCondition, setReceivedCondition] = useState(''); const [accessories, setAccessories] = useState('');
    const [selectedId, setSelectedId] = useState(''); const [detail, setDetail] = useState<RepairDetail | null>(null);
    const [status, setStatus] = useState('diagnosing'); const [diagnosis, setDiagnosis] = useState('');
    const [estimateNotes, setEstimateNotes] = useState('');
    const [lines, setLines] = useState<EstimateLine[]>([{ type: 'labor', product_id: '', description: 'Repair labor', quantity: '1', unit_price: '0.00' }]);
    const [payments, setPayments] = useState<Array<{ destination_id: string; method: string; amount: string; cash_tendered?: string; transaction_reference?: string }>>([]);
    const approvedEstimate = detail?.estimates.find((row) => row.estimate_id === detail.approved_estimate_id);
    const loadDetail = async (id: string) => {
        setSelectedId(id);
        if (!id) { setDetail(null); return; }
        const value = await api<RepairDetail>('/internal/admin/pos/operations/repairs/' + id);
        setDetail(value); setStatus(value.status); setDiagnosis(value.diagnosis ?? '');
    };
    const transitions = useMemo(() => {
        const map: Record<string, string[]> = {
            received: ['diagnosing','cancelled'], diagnosing: ['awaiting_approval','cancelled'],
            awaiting_approval: ['diagnosing','cancelled'], approved: ['repairing','cancelled'],
            repairing: ['ready_for_collection','cancelled'], ready_for_collection: ['delivered'], delivered: ['closed'],
        };
        return detail ? (map[detail.status] ?? []) : [];
    }, [detail]);
    const addPayment = () => {
        const destination = data.payment_destinations[0];
        if (destination) setPayments((old) => [...old, { destination_id: destination.public_id, method: destination.method, amount: '' }]);
    };
    return <section className="min-w-0 rounded-2xl border bg-white p-5"><div className="flex flex-wrap items-start justify-between gap-3"><div><h3 className="font-semibold">Paid repairs</h3><p className="text-xs text-slate-500">Warranties remain separate. Disabling paid repair blocks new intake but preserves historical access.</p></div><button disabled={busy} onClick={() => void run(async () => { await api('/internal/admin/pos/operations/repairs/configure', { method: 'POST', body: JSON.stringify({ enabled: !setting.enabled, version: setting.version }) }); })} className="rounded border px-3 py-2 text-xs">{setting.enabled ? 'Disable new repair intake' : 'Enable paid repair intake'}</button></div>
        <div className="mt-4 grid gap-2 sm:grid-cols-2"><input disabled={!setting.enabled} value={name} onChange={(e) => setName(e.target.value)} placeholder="Customer name" className="w-full min-w-0 rounded border p-2 text-sm disabled:bg-slate-100" /><input disabled={!setting.enabled} value={phone} onChange={(e) => setPhone(e.target.value)} placeholder="Customer phone" className="w-full min-w-0 rounded border p-2 text-sm disabled:bg-slate-100" /><input disabled={!setting.enabled} value={device} onChange={(e) => setDevice(e.target.value)} placeholder="Device label" className="w-full min-w-0 rounded border p-2 text-sm disabled:bg-slate-100" /><select disabled={!setting.enabled} value={identifierType} onChange={(e) => setIdentifierType(e.target.value)} className="w-full min-w-0 rounded border p-2 text-sm disabled:bg-slate-100"><option value="imei">IMEI</option><option value="serial">Serial</option><option value="other">Other identifier</option></select><input disabled={!setting.enabled} value={identifier} onChange={(e) => setIdentifier(e.target.value)} placeholder="Device identifier" className="w-full min-w-0 rounded border p-2 text-sm disabled:bg-slate-100" /><input disabled={!setting.enabled} value={receivedCondition} onChange={(e) => setReceivedCondition(e.target.value)} placeholder="Received condition" className="w-full min-w-0 rounded border p-2 text-sm disabled:bg-slate-100" /><textarea disabled={!setting.enabled} value={issue} onChange={(e) => setIssue(e.target.value)} placeholder="Issue description" rows={3} className="w-full min-w-0 rounded border p-2 text-sm disabled:bg-slate-100" /><textarea disabled={!setting.enabled} value={accessories} onChange={(e) => setAccessories(e.target.value)} placeholder="Accessories received" rows={3} className="w-full min-w-0 rounded border p-2 text-sm disabled:bg-slate-100" /></div><button data-testid="repair-open" disabled={busy || !setting.enabled || !name || !device || !identifier || !issue} onClick={() => void run(async () => { const created = await api<RepairDetail>('/internal/admin/pos/operations/repairs', { method: 'POST', body: JSON.stringify({ customer_id: null, customer_name: name, customer_phone: phone || null, device_label: device, identifier_type: identifierType, identifier_value: identifier, issue_description: issue, received_condition: receivedCondition || null, accessories_received: accessories || null, internal_notes: null }) }); await loadDetail(created.repair_id); })} className="mt-3 rounded bg-slate-950 px-3 py-2 text-sm font-semibold text-white disabled:opacity-40">Open paid repair</button>
        <div className="mt-5 rounded-xl bg-slate-50 p-4"><h4 className="text-sm font-semibold">Repair history & lifecycle</h4><select value={selectedId} onChange={(e) => void loadDetail(e.target.value)} className="mt-2 w-full min-w-0 rounded border bg-white p-2 text-sm"><option value="">Repair job</option>{data.repairs.map((row) => <option key={row.id} value={row.id}>{row.number} · {row.device_label} · {row.status}</option>)}</select>
        {detail && <div className="mt-4 grid min-w-0 gap-4">
            <div className="grid gap-2 rounded border bg-white p-3 text-sm sm:grid-cols-2"><span>{detail.customer_name} · {detail.customer_phone ?? 'no phone'}</span><span>{detail.device_label} · {detail.identifier_type}: {detail.identifier_value}</span><span>Status: <strong>{detail.status}</strong> · version {detail.version}</span><span>{detail.payments.length ? 'Collected: PKR ' + detail.payments.map((p) => p.amount).join(' + ') : 'No payment collected'}</span></div>
            {transitions.length > 0 && <div className="grid gap-2 sm:grid-cols-3"><select value={status} onChange={(e) => setStatus(e.target.value)} className="w-full min-w-0 rounded border bg-white p-2 text-sm"><option value={detail.status}>{detail.status}</option>{transitions.map((value) => <option key={value} value={value}>{value}</option>)}</select><input value={diagnosis} onChange={(e) => setDiagnosis(e.target.value)} placeholder="Diagnosis" className="w-full min-w-0 rounded border bg-white p-2 text-sm" /><button disabled={busy || status === detail.status} onClick={() => void run(async () => { const updated = await api<RepairDetail>('/internal/admin/pos/operations/repairs/' + detail.repair_id + '/status', { method: 'POST', body: JSON.stringify({ status, diagnosis: diagnosis || null, internal_notes: detail.internal_notes }) }); setDetail(updated); })} className="rounded border bg-white px-3 py-2 text-sm">Update repair status</button></div>}
            {['diagnosing','awaiting_approval'].includes(detail.status) && !detail.approved_estimate_id && <div className="rounded border bg-white p-3"><h5 className="text-sm font-semibold">Estimate</h5><div className="mt-2 grid gap-2">{lines.map((line, index) => <div key={index} className="grid gap-2 sm:grid-cols-5"><select value={line.type} onChange={(e) => setLines((old) => old.map((x, i) => i === index ? { ...x, type: e.target.value as 'labor' | 'part', product_id: '' } : x))} className="w-full min-w-0 rounded border p-2 text-sm"><option value="labor">Labor</option><option value="part">Part</option></select>{line.type === 'part' ? <select value={line.product_id} onChange={(e) => setLines((old) => old.map((x, i) => i === index ? { ...x, product_id: e.target.value } : x))} className="w-full min-w-0 rounded border p-2 text-sm"><option value="">Part product</option>{data.repair_parts.map((part) => <option key={part.id} value={part.id}>{part.name} · stock {part.qty}</option>)}</select> : <input value={line.unit_price} onChange={(e) => setLines((old) => old.map((x, i) => i === index ? { ...x, unit_price: e.target.value } : x))} placeholder="Labor unit price" className="w-full min-w-0 rounded border p-2 text-sm" />}<input value={line.description} onChange={(e) => setLines((old) => old.map((x, i) => i === index ? { ...x, description: e.target.value } : x))} placeholder="Description" className="w-full min-w-0 rounded border p-2 text-sm" /><input value={line.quantity} onChange={(e) => setLines((old) => old.map((x, i) => i === index ? { ...x, quantity: e.target.value } : x))} placeholder="Qty" className="w-full min-w-0 rounded border p-2 text-sm" /><button onClick={() => setLines((old) => old.filter((_, i) => i !== index))} className="rounded border px-2 py-1 text-xs">Remove</button></div>)}</div><div className="mt-2 flex flex-wrap gap-2"><button onClick={() => setLines((old) => [...old, { type: 'labor', product_id: '', description: 'Labor', quantity: '1', unit_price: '0.00' }])} className="rounded border px-2 py-1 text-xs">Add labor</button><button onClick={() => setLines((old) => [...old, { type: 'part', product_id: '', description: 'Part', quantity: '1', unit_price: '' }])} className="rounded border px-2 py-1 text-xs">Add part</button></div><input value={estimateNotes} onChange={(e) => setEstimateNotes(e.target.value)} placeholder="Estimate notes" className="mt-2 w-full min-w-0 rounded border p-2 text-sm" /><button disabled={busy || !detail.diagnosis || lines.length === 0} onClick={() => void run(async () => { const payloadLines = lines.map((line) => ({ type: line.type, product_id: line.type === 'part' ? line.product_id : null, description: line.description, quantity: Number(line.quantity), unit_price: line.type === 'labor' ? line.unit_price : null })); const updated = await api<RepairDetail>('/internal/admin/pos/operations/repairs/' + detail.repair_id + '/estimate', { method: 'POST', body: JSON.stringify({ notes: estimateNotes || null, lines: payloadLines }) }); setDetail(updated); })} className="mt-2 rounded border px-3 py-2 text-sm">Create estimate</button></div>}
            {detail.estimates.length > 0 && <div className="grid gap-2">{detail.estimates.map((estimate) => <div key={estimate.estimate_id} className="rounded border bg-white p-3 text-sm"><div className="flex flex-wrap justify-between gap-2"><span>Estimate v{estimate.version} · <strong>{estimate.status}</strong></span><span>PKR {estimate.grand_total}</span></div><div className="mt-2 text-xs text-slate-600">{estimate.lines.map((line) => line.description + ' × ' + line.quantity + ' = ' + line.line_total).join(' · ')}</div>{estimate.status === 'proposed' && detail.status === 'awaiting_approval' && <div className="mt-2 flex gap-2"><button disabled={busy} onClick={() => void run(async () => { const updated = await api<RepairDetail>('/internal/admin/pos/operations/repairs/' + detail.repair_id + '/estimate/' + estimate.estimate_id + '/decision', { method: 'POST', body: JSON.stringify({ approved: true }) }); setDetail(updated); })} className="rounded border px-2 py-1 text-xs">Approve estimate</button><button disabled={busy} onClick={() => void run(async () => { const updated = await api<RepairDetail>('/internal/admin/pos/operations/repairs/' + detail.repair_id + '/estimate/' + estimate.estimate_id + '/decision', { method: 'POST', body: JSON.stringify({ approved: false }) }); setDetail(updated); })} className="rounded border px-2 py-1 text-xs">Reject estimate</button></div>}</div>)}</div>}
            {detail.approved_estimate_id && ['approved','repairing'].includes(detail.status) && <button disabled={busy} onClick={() => void run(async () => { const updated = await api<RepairDetail>('/internal/admin/pos/operations/repairs/' + detail.repair_id + '/parts', { method: 'POST', body: '{}' }); setDetail(updated); })} className="rounded border bg-white px-3 py-2 text-sm">Consume approved parts / start repair</button>}
            {approvedEstimate && detail.payments.length === 0 && ['approved','repairing','ready_for_collection'].includes(detail.status) && <div className="rounded border bg-white p-3"><div className="flex flex-wrap items-center justify-between gap-2"><h5 className="text-sm font-semibold">Collect repair payment · PKR {approvedEstimate.grand_total}</h5><button onClick={addPayment} className="rounded border px-2 py-1 text-xs">Add payment</button></div><div className="mt-2 grid gap-2">{payments.map((payment, index) => <div key={index} className="grid gap-2 sm:grid-cols-3"><select value={payment.destination_id} onChange={(e) => { const d = data.payment_destinations.find((x) => x.public_id === e.target.value); setPayments((old) => old.map((x, i) => i === index ? { ...x, destination_id: e.target.value, method: d?.method ?? x.method } : x)); }} className="w-full min-w-0 rounded border p-2 text-sm">{data.payment_destinations.map((d) => <option key={d.public_id} value={d.public_id}>{d.display_name} · {d.method}</option>)}</select><input value={payment.amount} onChange={(e) => setPayments((old) => old.map((x, i) => i === index ? { ...x, amount: e.target.value } : x))} placeholder="Amount" className="w-full min-w-0 rounded border p-2 text-sm" />{payment.method === 'cash' ? <input value={payment.cash_tendered ?? ''} onChange={(e) => setPayments((old) => old.map((x, i) => i === index ? { ...x, cash_tendered: e.target.value } : x))} placeholder="Cash tendered" className="w-full min-w-0 rounded border p-2 text-sm" /> : <input value={payment.transaction_reference ?? ''} onChange={(e) => setPayments((old) => old.map((x, i) => i === index ? { ...x, transaction_reference: e.target.value } : x))} placeholder="Safe reference" className="w-full min-w-0 rounded border p-2 text-sm" />}</div>)}</div><button data-testid="repair-collect" disabled={busy || payments.length === 0 || payments.some((p) => !p.amount)} onClick={() => void run(async () => { const updated = await api<{ repair_id: string }>('/internal/admin/pos/operations/repairs/' + detail.repair_id + '/collect', { method: 'POST', body: JSON.stringify({ payments }) }); await loadDetail(updated.repair_id); })} className="mt-2 rounded bg-slate-950 px-3 py-2 text-sm font-semibold text-white disabled:opacity-40">Collect approved estimate</button></div>}
            <details className="rounded border bg-white p-3"><summary className="cursor-pointer text-sm font-semibold">Linked repair history / audit</summary><pre className="mt-2 max-h-64 max-w-full overflow-auto rounded bg-slate-950 p-3 text-xs text-white">{JSON.stringify(detail.events, null, 2)}</pre></details>
        </div>}</div>
    </section>;
}
