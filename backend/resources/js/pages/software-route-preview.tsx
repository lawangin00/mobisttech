import { useState } from 'react';

type Release = { id:number; version:string; state:string; release_date:string; summary:string; notes?:Record<string,string[]>; impact_review?:Record<string,unknown> };
type FAQ = { question:string; answer:string };
type View = 'overview' | 'privacy' | 'terms' | 'faq' | 'releases';
const views:View[] = ['overview','privacy','terms','faq','releases'];
const text = (value:unknown):string => typeof value==='string'?value:'';
const strings = (value:unknown):string[] => Array.isArray(value)?value.filter((x):x is string=>typeof x==='string'):[];
function faqs(value:unknown):FAQ[] {
    return Array.isArray(value)?value.filter((x):x is FAQ=>Boolean(x)&&typeof x==='object'&&typeof x.question==='string'&&typeof x.answer==='string'):[];
}

/** Protected draft presentation only: never creates a public route or changes publication state. */
export default function SoftwareRoutePreview({snapshot,releases,revisionState}:{snapshot:Record<string,unknown>;releases:Release[];revisionState:string}) {
    const [view,setView]=useState<View>('overview');
    const name=text(snapshot.name)||'Untitled Software';
    const heading=view==='overview'?name:view==='faq'?`${name} FAQ`:view==='releases'?`${name} Releases`:`${name} ${view==='privacy'?'Privacy':'Terms'}`;
    return <section aria-label="Protected software route preview" className="mt-3 rounded-xl border bg-slate-50 p-3 sm:p-4">
        <p className="text-xs font-semibold text-amber-800">Protected preview · revision {revisionState} · not a public publication</p>
        <nav aria-label="Preview Software routes" className="mt-3 flex flex-wrap gap-2">
            {views.map(item=><button key={item} type="button" onClick={()=>setView(item)} aria-pressed={view===item} className={'rounded border px-3 py-1 text-xs '+(view===item?'bg-slate-950 text-white':'bg-white')}>{item[0].toUpperCase()+item.slice(1)}</button>)}
        </nav>
        <div className="mt-4 rounded-xl bg-white p-3 sm:p-5"><h3 className="text-xl font-bold">{heading}</h3>
            {view==='overview'&&<>
                <p className="mt-2 text-slate-600">{text(snapshot.summary)}</p>
                <article className="mt-4 space-y-3" dangerouslySetInnerHTML={{__html:text(snapshot.overview)}}/>
                {Array.isArray(snapshot.features)&&<section className="mt-5"><h4 className="font-semibold">Features</h4><ul className="mt-2 list-disc pl-5">{snapshot.features.filter((x):x is {title:string;description:string}=>Boolean(x)&&typeof x==='object'&&typeof x.title==='string'&&typeof x.description==='string').map((x,i)=><li key={i}><strong>{x.title}</strong>: {x.description}</li>)}</ul></section>}
                <section className="mt-5"><h4 className="font-semibold">Supported platforms</h4><ul className="list-disc pl-5">{strings(snapshot.platforms).map(item=><li key={item}>{item}</li>)}</ul></section>
                <section className="mt-5"><h4 className="font-semibold">System requirements</h4><div dangerouslySetInnerHTML={{__html:text(snapshot.system_requirements)}}/></section>
                {strings(snapshot.limitations).length>0&&<section className="mt-5"><h4 className="font-semibold">Limitations</h4><ul className="list-disc pl-5">{strings(snapshot.limitations).map(item=><li key={item}>{item}</li>)}</ul></section>}
            </>}
            {view==='privacy'&&<article className="mt-4 space-y-3" dangerouslySetInnerHTML={{__html:text(snapshot.privacy)}}/>}
            {view==='terms'&&<article className="mt-4 space-y-3" dangerouslySetInnerHTML={{__html:text(snapshot.terms)}}/>}
            {view==='faq'&&<div className="mt-4 space-y-4">{faqs(snapshot.faq).map((entry,i)=><section key={i}><h4 className="font-semibold">{entry.question}</h4><div dangerouslySetInnerHTML={{__html:entry.answer}}/></section>)}</div>}
            {view==='releases'&&<div className="mt-4 space-y-3">{releases.map(item=><section key={item.id} className="rounded border p-3"><h4 className="font-semibold">{item.version}</h4><p className="text-xs text-slate-500">{item.state} · {item.release_date}</p><p className="mt-1">{item.summary}</p>{(["added","changed","fixed","security"] as const).map(group=>strings(item.notes?.[group]).length>0&&<div key={group} className="mt-2"><h5 className="text-sm font-semibold">{group[0].toUpperCase()+group.slice(1)}</h5><ul className="list-disc pl-5 text-sm">{strings(item.notes?.[group]).map((note,i)=><li key={i}>{note}</li>)}</ul></div>)}<p className="mt-2 text-xs text-slate-500">Documentation impact review: {Object.keys(item.impact_review??{}).length} recorded fields</p></section>)}{releases.length===0&&<p>No release records yet.</p>}</div>}
        </div>
        <p className="mt-3 text-xs text-slate-600">Private content preview only. Public URLs, exact public-route appearance, SEO and media require separate published-route acceptance.</p>
    </section>;
}
