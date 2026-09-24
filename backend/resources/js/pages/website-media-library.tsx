import { useState } from 'react';

type MediaRow = { id:number; original_name:string; mime_type:string; byte_size:number; width:number; height:number; sha256:string; alt_text:string|null; status:string; created_at:string };
export default function WebsiteMediaLibrary({ rows, canManage, busy, onAlt, onReplace, onDelete }: {
  rows: MediaRow[]; canManage: boolean; busy: boolean;
  onAlt: (id: number, alt: string) => void;
  onReplace: (id: number, file: File) => void;
  onDelete: (id: number) => void;
}) {
  const [altEdits, setAltEdits] = useState<Record<number, string>>({});
  return <div className="mt-3 grid max-h-[540px] gap-3 overflow-auto">
    {rows.map(row => { const editable = canManage && !busy && row.status === 'active'; return <article key={row.id} data-testid={`website-media-${row.id}`} className="rounded border p-3 text-xs">
      <p><strong>#{row.id} {row.original_name}</strong> · {row.mime_type} · {Math.round(row.byte_size/1024)} KB · {row.status}</p>
      <p className="mt-1 text-slate-500">{row.width}×{row.height} · {row.sha256.slice(0, 16)}…</p>
      <label className="mt-2 block">Alternative text<input aria-label={`Media alt ${row.id}`} value={altEdits[row.id] ?? row.alt_text ?? ''} disabled={!editable} onChange={event => setAltEdits(values => ({ ...values, [row.id]: event.target.value }))} maxLength={500} className="mt-1 w-full rounded border p-2"/></label>
      <div className="mt-2 flex flex-wrap items-center gap-2"><button type="button" disabled={!editable} onClick={() => onAlt(row.id, altEdits[row.id] ?? row.alt_text ?? '')} className="rounded border px-2 py-1 disabled:opacity-40">Save alt text</button>
        <label className="rounded border px-2 py-1">Upload replacement as new asset ID<input aria-label={`Replace media ${row.id}`} type="file" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm" disabled={!editable} onChange={event => { const file = event.target.files?.[0]; if (file) onReplace(row.id, file); event.target.value = ''; }} className="mt-1 block max-w-52 text-xs"/></label>
        <button type="button" disabled={!editable} onClick={() => { if (window.confirm('Retire this media only if it has no current, draft or historical references?')) onDelete(row.id); }} className="rounded border px-2 py-1 disabled:opacity-40">Delete unused</button>
      </div>
    </article>; })}
    {rows.length === 0 && <p className="text-xs text-slate-500">No Website media yet.</p>}
  </div>;
}