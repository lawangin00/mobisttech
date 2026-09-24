import { useEffect, useState } from 'react';

type Role = 'main_logo' | 'wordmark' | 'header_logo' | 'footer_logo' | 'square_icon' | 'favicon' | 'social_image';
const roles: Array<[Role, string]> = [
  ['main_logo', 'Main logo'], ['wordmark', 'Wordmark'], ['header_logo', 'Header logo'],
  ['footer_logo', 'Footer logo'], ['square_icon', 'Square icon'], ['favicon', 'Favicon'],
  ['social_image', 'Social image'],
];
type Media = { id: number; original_name: string; mime_type: string; status: string; width: number; height: number };
function selected(snapshot: Record<string, unknown>): Record<Role, number | null> {
  return Object.fromEntries(roles.map(([role]) => [role, Number.isInteger(snapshot[role]) && Number(snapshot[role]) > 0 ? Number(snapshot[role]) : null])) as Record<Role, number | null>;
}
export default function WebsiteBrandingBuilder({ snapshot, publishedId, media, busy, allowed, save }: {
  snapshot: Record<string, unknown>; publishedId: number; media: Media[]; busy: boolean;
  allowed: (permission: string) => boolean; save: (branding: Record<Role, number | null>) => void;
}) {
  const [branding, setBranding] = useState(() => selected(snapshot));
  useEffect(() => { setBranding(selected(snapshot)); }, [publishedId]);
  return <section aria-label="Website branding editor" className="rounded-2xl border bg-white p-5 xl:col-span-2">
    <h2 className="font-semibold">Website branding assets</h2>
    <p className="mt-1 text-sm text-slate-600">Select verified private images from Content / Website media library. Fallback uses existing approved fixed mobiST assets. Preview is private; publishing a revision changes only its explicitly selected role.</p>
    <fieldset disabled={busy || !allowed('website.branding.manage')} className="mt-3 grid gap-3 sm:grid-cols-2">
      {roles.map(([role, label]) => <label key={role} className="text-xs">{label}<select aria-label={`Website branding ${role}`} className="mt-1 w-full rounded border p-2 text-sm" value={branding[role] ?? ''} onChange={e => setBranding(current => ({ ...current, [role]: e.target.value ? Number(e.target.value) : null }))}>
        <option value="">Approved fallback</option>
        {media.filter(m => m.status === 'active' && ['image/jpeg', 'image/png', 'image/webp'].includes(m.mime_type) && (!['square_icon', 'favicon'].includes(role) || (m.width > 0 && m.height > 0 && m.width/m.height >= 0.8 && m.width/m.height <= 1.25))).map(m => <option key={m.id} value={m.id}>#{m.id} {m.original_name}</option>)}
      </select></label>)}
    </fieldset>
    <aside aria-label="Private branding preview" className="mt-3 rounded border p-3 text-xs">{roles.map(([role, label]) => <p key={role}>{label}: {branding[role] ? `private image #${branding[role]}` : 'approved fallback'}</p>)}</aside>
    <button disabled={busy || !allowed('website.branding.manage')} onClick={() => save(branding)} className="mt-3 rounded border px-3 py-2 text-sm disabled:opacity-40">Save private branding draft</button>
  </section>;
}
