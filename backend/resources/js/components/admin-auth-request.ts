export async function adminAuthRequest(path: string, method: 'POST' | 'PATCH', payload: Record<string, string>): Promise<Record<string, unknown>> {
    const cookie = await fetch('/internal/admin/auth/csrf-cookie', {credentials:'same-origin', headers:{Accept:'application/json'}});
    const initial = await cookie.json().catch(() => ({})) as {data?:{csrf_token?:string}};
    if (!cookie.ok || !initial.data?.csrf_token) throw new Error('Secure request token is unavailable.');
    const response = await fetch(path, {method, credentials:'same-origin', headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':initial.data.csrf_token}, body:JSON.stringify(payload)});
    const body = await response.json().catch(() => ({})) as {data?:Record<string,unknown>;message?:string;error?:{message?:string;fields?:Record<string,string[]>};errors?:Record<string,string[]>};
    if (response.status===503 && path==='/internal/admin/auth/forgot-password') throw new Error('Email recovery is unavailable. Contact your administrator.');
    if (!response.ok) throw new Error(Object.values(body.error?.fields??body.errors??{}).flat()[0]??body.error?.message??body.message??'Request failed.');
    return body.data??{};
}
