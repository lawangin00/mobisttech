import { useEffect, useState } from 'react';

const definitions = [
  ['primary', 'Primary', '#008080'], ['primary_hover', 'Primary hover', '#005b60'],
  ['secondary', 'Secondary', '#005b60'], ['accent', 'Accent', '#00b4d8'],
  ['background', 'Background', '#f7f8fb'], ['surface', 'Surface', '#ffffff'],
  ['text', 'Text', '#111827'], ['muted_text', 'Muted text', '#667085'],
  ['border', 'Border', '#e7eaf0'],
] as const;
type Theme = Record<(typeof definitions)[number][0], string>;
function initialTheme(snapshot: Record<string, unknown>): Theme {
  return Object.fromEntries(definitions.map(([key, , fallback]) => [key,
    typeof snapshot[key] === 'string' && /^#[0-9a-fA-F]{6}$/.test(snapshot[key] as string)
      ? snapshot[key] as string : fallback])) as Theme;
}
export default function WebsiteThemeBuilder({ snapshot, publishedId, busy, allowed, save }: {
  snapshot: Record<string, unknown>; publishedId: number; busy: boolean;
  allowed: (permission: string) => boolean; save: (theme: Theme) => void;
}) {
  const [theme, setTheme] = useState<Theme>(() => initialTheme(snapshot));
  useEffect(() => { setTheme(initialTheme(snapshot)); }, [publishedId]);
  const canEdit = allowed('website.theme.manage');
  return <section aria-label="Website theme editor" className="rounded-2xl border bg-white p-5 xl:col-span-2">
    <h2 className="font-semibold">Website theme colors</h2>
    <p className="mt-1 text-sm text-slate-600">Nine approved color tokens. Preview locally; save a private draft, then separately publish or roll back its revision. The fixed mobiST logo files are unchanged.</p>
    <fieldset disabled={busy || !canEdit} className="mt-3 grid gap-3 sm:grid-cols-3">
      {definitions.map(([key, label]) => <label key={key} className="text-xs">{label}<input aria-label={`Website theme ${key}`} type="color" value={theme[key]} onChange={event => setTheme(current => ({ ...current, [key]: event.target.value }))} className="mt-1 block h-10 w-full rounded border"/><input aria-label={`Website theme ${key} hex`} type="text" maxLength={7} value={theme[key]} onChange={event => setTheme(current => ({ ...current, [key]: event.target.value }))} className="mt-1 w-full rounded border p-2 font-mono text-xs"/></label>)}
    </fieldset>
    <aside aria-label="Private theme preview" className="mt-3 rounded border p-4" style={{ background: theme.background, color: theme.text, borderColor: theme.border }}>
      <p className="text-xs" style={{ color: theme.muted_text }}>Private preview; not published</p>
      <p className="mt-2 font-semibold">Website color and contrast preview</p>
      <div className="mt-2 rounded p-3" style={{ background: theme.surface, color: theme.text }}><span className="rounded px-3 py-2" style={{ background: theme.primary, color: '#ffffff' }}>Brand primary</span></div>
    </aside>
    <button type="button" disabled={busy || !canEdit || definitions.some(([key]) => !/^#[0-9a-fA-F]{6}$/.test(theme[key]))} onClick={() => save(theme)} className="mt-3 rounded border px-3 py-2 text-sm disabled:opacity-40">Save private theme draft</button>
  </section>;
}