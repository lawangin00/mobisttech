// D01=B: explicit operator opt-in, per-tab only; NEVER write free-text queries or customer/device identifiers.
const KEY = 'mobist.pos.search-tab.v1';
type Memory = { scope: string; consent: true; categories: Record<string, string> };
function read(scope: string, enabled: boolean): Memory | null {
    try {
        if (!enabled) { window.sessionStorage.removeItem(KEY); return null; }
        const raw = window.sessionStorage.getItem(KEY);
        if (!raw) return null;
        const data: unknown = JSON.parse(raw);
        if (!data || typeof data !== 'object' || !('scope' in data) || !('consent' in data)
            || !('categories' in data) || data.scope !== scope || data.consent !== true
            || !data.categories || typeof data.categories !== 'object') {
            window.sessionStorage.removeItem(KEY); return null;
        }
        return data as Memory;
    } catch { return null; }
}
export function tabSearchOptedIn(scope: string, enabled: boolean): boolean { return read(scope, enabled) !== null; }
export function tabSearchCategory(scope: string, enabled: boolean, area: string, allowed: string[]): string | null {
    const value = read(scope, enabled)?.categories[area];
    return value && allowed.includes(value) ? value : null;
}
export function tabSearchConsent(scope: string, enabled: boolean, optedIn: boolean): void {
    try { if (enabled && optedIn) window.sessionStorage.setItem(KEY, JSON.stringify({ scope, consent: true, categories: {} }));
        else window.sessionStorage.removeItem(KEY); } catch { /* storage may be blocked; stay opt-out */ }
}
export function tabSearchSaveCategory(scope: string, enabled: boolean, area: string, category: string, allowed: string[]): void {
    const memory = read(scope, enabled);
    if (!memory || !allowed.includes(category)) return;
    try { window.sessionStorage.setItem(KEY, JSON.stringify({ ...memory, categories: { ...memory.categories, [area]: category } })); }
    catch { /* no persistence when storage is unavailable */ }
}
export function clearPosTabSearch(): void { try { window.sessionStorage.removeItem(KEY); } catch { /* no-op */ } }
