import { useEffect, useState } from 'react';

type Nav = { key: string; label: string; parent_key: string | null; destination_type: 'route' | 'page' | 'url'; destination_key: string | null; destination_payload: { url: string } | null; capability_scope: string; sort_order: number; is_visible: boolean; is_enabled: boolean; target_behavior: 'same_tab' | 'new_tab' };
type Section = { key: 'hero' | 'products' | 'solutions' | 'about' | 'contact'; enabled: boolean; order: number };
type Footer = { footer_description: string; footer_copyright: string; show_account: boolean; show_contact: boolean; show_policies: boolean; show_search: boolean; show_cart: boolean; sticky: boolean; footer_show_logo: boolean; footer_show_navigation: boolean; footer_navigation_layout: 'one_column' | 'two_columns'; contact_cta: 'hidden' | 'contact'; contact_cta_label: string };
const names: Section['key'][] = ['hero', 'products', 'solutions', 'about', 'contact'];
const scopes = ['common', 'digital', 'commerce', 'digital_only', 'hybrid', 'commerce_only'];
const baseFooter: Footer = { footer_description: '', footer_copyright: '', show_account: true, show_contact: true, show_policies: true, show_search: false, show_cart: true, sticky: false, footer_show_logo: false, footer_show_navigation: false, footer_navigation_layout: 'one_column', contact_cta: 'hidden', contact_cta_label: '' };
const baseSections: Section[] = names.map((key, index) => ({ key, enabled: true, order: (index + 1) * 10 }));
function initialNav(snapshot: Record<string, unknown>): Nav[] {
  if (!Array.isArray(snapshot.navigation)) return [];
  return snapshot.navigation.map((raw: unknown, index) => {
    const item = raw as Record<string, unknown>;
    const type: Nav['destination_type'] = item.destination_type === 'page' || item.destination_type === 'url' ? item.destination_type : 'route';
    return {
      key: String(item.key ?? ''), label: String(item.label ?? ''), parent_key: typeof item.parent_key === 'string' ? item.parent_key : null,
      destination_type: type, destination_key: typeof item.destination_key === 'string' ? item.destination_key : null,
      destination_payload: item.destination_payload && typeof item.destination_payload === 'object' ? item.destination_payload as { url: string } : null,
      capability_scope: String(item.capability_scope ?? 'common'), sort_order: Number(item.sort_order ?? (index + 1) * 10),
      is_visible: item.is_visible !== false, is_enabled: item.is_enabled !== false,
      target_behavior: item.target_behavior === 'new_tab' ? 'new_tab' : 'same_tab',
    };
  });
}
export default function WebsitePresentationBuilder({ snapshot, version, allowed, busy, save }: {
  snapshot: Record<string, unknown>; version: number; allowed: (permission: string) => boolean; busy: boolean;
  save: (value: Record<string, unknown>) => void;
}) {
  const [nav, setNav] = useState<Nav[]>(() => initialNav(snapshot));
  const [sections, setSections] = useState<Section[]>(() => {
    const home = snapshot.homepage as { sections?: Section[] } | undefined;
    return Array.isArray(home?.sections) ? home.sections : baseSections;
  });
  const [footer, setFooter] = useState<Footer>(() => ({ ...baseFooter, ...(snapshot.header_footer as Partial<Footer> ?? {}) }));
  useEffect(() => {
    setNav(initialNav(snapshot));
    const home = snapshot.homepage as { sections?: Section[] } | undefined;
    setSections(Array.isArray(home?.sections) ? home.sections : baseSections);
    setFooter({ ...baseFooter, ...(snapshot.header_footer as Partial<Footer> ?? {}) });
  }, [version]); // Rebase only when a DIFFERENT published revision is selected; preserve unsaved edits on data reload.
  const canNav = allowed('website.navigation.manage');
  const canContent = allowed('website.content.manage');
  const editNav = (index: number, delta: Partial<Nav>) => setNav(items => items.map((item, i) => i === index ? { ...item, ...delta } : item));
  const shiftNav = (index: number, offset: number) => setNav(items => {
    const next = [...items]; const other = index + offset;
    if (other < 0 || other >= next.length) return items;
    [next[index], next[other]] = [next[other], next[index]];
    return next.map((item, i) => ({ ...item, sort_order: (i + 1) * 10 }));
  });
  const editSection = (key: Section['key'], delta: Partial<Section>) => setSections(items => items.map(item => item.key === key ? { ...item, ...delta } : item));
  const navPreview = nav.filter(item => item.is_enabled && item.is_visible && (!item.parent_key || nav.some(parent => parent.key === item.parent_key && parent.is_enabled && parent.is_visible)));
  return <section aria-label="Guided Website presentation builder" className="rounded-2xl border bg-white p-5 xl:col-span-2">
    <h2 className="font-semibold">Guided navigation, homepage and header/footer</h2>
    <p className="mt-1 text-sm text-slate-600">Preview edits here, save a private revision, then use the existing Publish control. The advanced JSON editor below remains available.</p>
    <div className="mt-4 grid gap-5 lg:grid-cols-2">
      <fieldset className="rounded border p-3" disabled={busy || !canNav}>
        <legend className="px-1 font-semibold">Navigation items</legend>
        {nav.map((item, index) => <div key={index} className="mb-3 rounded border p-2 text-xs" data-testid={`guided-nav-${index}`}>
          <div className="grid gap-2 sm:grid-cols-2"><label>Key<input aria-label={`Navigation key ${index}`} className="mt-1 w-full rounded border p-2" value={item.key} onChange={e => editNav(index, { key: e.target.value })}/></label><label>Label<input aria-label={`Navigation label ${index}`} className="mt-1 w-full rounded border p-2" value={item.label} onChange={e => editNav(index, { label: e.target.value })}/></label>
          <label>Parent<select aria-label={`Navigation parent ${index}`} className="mt-1 w-full rounded border p-2" value={item.parent_key ?? ''} onChange={e => editNav(index, { parent_key: e.target.value || null })}><option value="">Top level</option>{nav.filter((other, i) => i !== index && other.key).map(other => <option key={other.key} value={other.key}>{other.label || other.key}</option>)}</select></label>
          <label>Destination<select aria-label={`Navigation type ${index}`} className="mt-1 w-full rounded border p-2" value={item.destination_type} onChange={e => editNav(index, { destination_type: e.target.value as Nav['destination_type'] })}><option value="route">Public route</option><option value="page">Managed page</option><option value="url">External HTTPS URL</option></select></label>
          <label className="sm:col-span-2">{item.destination_type === 'url' ? 'HTTPS URL' : 'Destination key'}<input aria-label={`Navigation destination ${index}`} className="mt-1 w-full rounded border p-2" value={item.destination_type === 'url' ? item.destination_payload?.url ?? '' : item.destination_key ?? ''} onChange={e => editNav(index, item.destination_type === 'url' ? { destination_payload: { url: e.target.value }, destination_key: null } : { destination_key: e.target.value, destination_payload: null })}/></label>
          <label>Capability<select aria-label={`Navigation scope ${index}`} className="mt-1 w-full rounded border p-2" value={item.capability_scope} onChange={e => editNav(index, { capability_scope: e.target.value })}>{scopes.map(scope => <option key={scope}>{scope}</option>)}</select></label>
          <label>Target<select aria-label={`Navigation target ${index}`} className="mt-1 w-full rounded border p-2" value={item.target_behavior} onChange={e => editNav(index, { target_behavior: e.target.value as Nav['target_behavior'] })}><option value="same_tab">Same tab</option><option value="new_tab">New tab</option></select></label></div>
          <div className="mt-2 flex flex-wrap gap-2"><label><input type="checkbox" checked={item.is_visible} onChange={e => editNav(index, { is_visible: e.target.checked })}/> Visible</label><label><input type="checkbox" checked={item.is_enabled} onChange={e => editNav(index, { is_enabled: e.target.checked })}/> Enabled</label><button className="rounded border px-2" onClick={() => shiftNav(index, -1)}>Up</button><button className="rounded border px-2" onClick={() => shiftNav(index, 1)}>Down</button><button className="rounded border px-2" onClick={() => setNav(items => items.filter((_, i) => i !== index))}>Remove</button></div>
        </div>)}
        <button type="button" className="rounded border px-3 py-2 text-xs" onClick={() => setNav(items => [...items, { key: '', label: '', parent_key: null, destination_type: 'route', destination_key: 'home', destination_payload: null, capability_scope: 'common', sort_order: (items.length + 1) * 10, is_visible: true, is_enabled: true, target_behavior: 'same_tab' }])}>Add navigation item</button>
        <aside aria-label="Navigation draft preview" className="mt-3 rounded bg-slate-50 p-3 text-xs"><strong>Private navigation preview</strong><p className="mt-2">{navPreview.map(item => item.label || item.key).join(' · ') || 'No visible items'}</p></aside>
      </fieldset>
      <div className="grid gap-4">
        <fieldset disabled={busy || !canContent} className="rounded border p-3"><legend className="px-1 font-semibold">Homepage sections</legend>
          {sections.map(item => <div key={item.key} className="mb-2 flex items-center gap-2 text-xs"><label className="flex flex-1 items-center gap-2"><input aria-label={`Show ${item.key}`} type="checkbox" checked={item.enabled} onChange={e => editSection(item.key, { enabled: e.target.checked })}/>{item.key}</label><label>Order<input aria-label={`Order ${item.key}`} type="number" min={0} max={100} className="ml-1 w-20 rounded border p-1" value={item.order} onChange={e => editSection(item.key, { order: Number(e.target.value) })}/></label></div>)}
          <aside aria-label="Homepage draft preview" className="rounded bg-slate-50 p-3 text-xs">{[...sections].sort((a,b) => a.order - b.order || a.key.localeCompare(b.key)).filter(item => item.enabled).map(item => item.key).join(' → ') || 'No published sections selected'}</aside>
        </fieldset>
        <fieldset disabled={busy || !canContent} className="rounded border p-3"><legend className="px-1 font-semibold">Header and footer</legend>
          <label className="block text-xs">Footer description<input aria-label="Footer description" className="mt-1 w-full rounded border p-2" value={footer.footer_description ?? ''} onChange={e => setFooter({ ...footer, footer_description: e.target.value })}/></label>
          <label className="mt-2 block text-xs">Footer copyright<input aria-label="Footer copyright" className="mt-1 w-full rounded border p-2" value={footer.footer_copyright ?? ''} onChange={e => setFooter({ ...footer, footer_copyright: e.target.value })}/></label>
          {(['show_account', 'show_contact', 'show_policies', 'show_search', 'show_cart', 'sticky', 'footer_show_logo', 'footer_show_navigation'] as const).map(key => <label key={key} className="mt-2 mr-3 inline-flex items-center gap-1 text-xs"><input aria-label={key.replaceAll('_',' ')} type="checkbox" checked={footer[key]} onChange={e => setFooter({ ...footer, [key]: e.target.checked })}/>{key.replace('_', ' ')}</label>)}
          <label className="mt-2 block text-xs">Footer navigation layout<select aria-label="Footer navigation layout" className="mt-1 w-full rounded border p-2" value={footer.footer_navigation_layout} onChange={e => setFooter({ ...footer, footer_navigation_layout: e.target.value as Footer['footer_navigation_layout'] })}><option value="one_column">One column</option><option value="two_columns">Two columns</option></select></label>
          <label className="mt-2 block text-xs">Contact CTA<select aria-label="Contact CTA" className="mt-1 w-full rounded border p-2" value={footer.contact_cta} onChange={e => setFooter({ ...footer, contact_cta: e.target.value as Footer['contact_cta'] })}><option value="hidden">Hidden</option><option value="contact">Contact by email</option></select></label>
          <label className="mt-2 block text-xs">Contact CTA label<input aria-label="Contact CTA label" className="mt-1 w-full rounded border p-2" value={footer.contact_cta_label} onChange={e => setFooter({ ...footer, contact_cta_label: e.target.value })}/></label>
          <aside aria-label="Header/footer draft preview" className="mt-3 rounded bg-slate-50 p-3 text-xs">{footer.footer_description || 'Default footer description'} · {footer.footer_copyright || 'No copyright override'}</aside>
        </fieldset>
      </div>
    </div>
    <button type="button" disabled={busy || !(canNav || canContent)} className="mt-4 rounded bg-slate-950 px-4 py-2 text-sm text-white disabled:opacity-40" onClick={() => save({ ...(canNav ? { navigation: nav.map((item,index) => ({ ...item, sort_order: (index + 1) * 10 })) } : {}), ...(canContent ? { homepage: { sections }, header_footer: footer } : {}) })}>Save guided presentation draft</button>
  </section>;
}
