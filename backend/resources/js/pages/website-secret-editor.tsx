import { useState } from 'react';

type Credential = { key: string; label: string; configured: boolean; masked_value: string | null; version: number; rotated_at: string | null };

export default function WebsiteSecretEditor({ credentials, busy, allowed, submit }: {
  credentials: Credential[]; busy: boolean; allowed: boolean;
  submit: (key: string, value: string, clear: () => void) => void;
}) {
  const [key, setKey] = useState('');
  const [value, setValue] = useState('');
  const current = credentials.find(item => item.key === key) ?? credentials[0];
  if (!allowed || credentials.length === 0) return null;
  return <section aria-label="Website credential editor" className="rounded-2xl border bg-white p-5 xl:col-span-2">
    <h2 className="font-semibold">Website credentials</h2>
    <p className="mt-1 text-sm text-slate-600">Encrypted configuration only; external payment providers remain disabled until independently authorized. Saved values are never displayed, including to administrators. Reconfirm your password if your recent authentication has expired.</p>
    <div className="mt-3 grid gap-3 sm:grid-cols-2">
      <label className="text-sm">Credential
        <select aria-label="Website credential key" className="mt-1 w-full rounded border p-2" value={current?.key ?? ''}
          onChange={event => { setKey(event.target.value); setValue(''); }}>
          {credentials.map(item => <option key={item.key} value={item.key}>{item.label}</option>)}
        </select>
      </label>
      <div className="rounded border p-3 text-sm" aria-label="Credential status">
        {current?.configured ? `Configured ${current.masked_value ?? ''} (version ${current.version})` : 'Not configured'}
      </div>
      <label className="text-sm sm:col-span-2">New value (never displayed after saving)
        <input aria-label="New Website credential value" type="password" autoComplete="new-password" className="mt-1 w-full rounded border p-2" value={value}
          onChange={event => setValue(event.target.value)} maxLength={8192} />
      </label>
    </div>
    <button type="button" className="mt-3 rounded border px-3 py-2 text-sm disabled:opacity-40"
      disabled={busy || !current || !value.trim()}
      onClick={() => current && submit(current.key, value, () => setValue(''))}>Replace credential</button>
  </section>;
}
