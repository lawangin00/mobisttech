import { useEffect, useState } from 'react';

type Settings = {
  grid_desktop:number;grid_tablet:number;grid_mobile:number;image_ratio:string;card_density:string;
  items_per_page:number;default_sort:string;badge_behavior:string;
  show_brand:boolean;show_specs:boolean;show_colors:boolean;show_warranty:boolean;show_compare:boolean;show_out_of_stock:boolean;
};
const defaults:Settings={grid_desktop:3,grid_tablet:2,grid_mobile:1,image_ratio:'4:3',card_density:'comfortable',items_per_page:12,
  default_sort:'oldest',badge_behavior:'status_pill',show_brand:true,show_specs:true,show_colors:true,show_warranty:true,show_compare:true,show_out_of_stock:true};
const options:[keyof Settings,string,readonly (string|number)[]][]=[
  ['grid_desktop','Desktop columns',[3,4,5,6]],['grid_tablet','Tablet columns',[2,3,4]],['grid_mobile','Mobile columns',[1,2]],
  ['image_ratio','Image ratio',['16:9','4:3','1:1']],['card_density','Card density',['comfortable','compact']],
  ['items_per_page','Items per page',[12,24,36,48]],
  ['default_sort','Default sort',['newest','oldest','price_asc','price_desc','name_asc','name_desc']],
  ['badge_behavior','Stock badge',['status_text','status_pill','hidden']],
];
const toggles:[keyof Settings,string][]=[['show_brand','Show brand'],['show_specs','Show model/specs'],['show_colors','Show available colors'],
  ['show_warranty','Show warranty'],['show_compare','Show comparison link'],['show_out_of_stock','Keep published out-of-stock items visible']];
function safe(snapshot:Record<string,unknown>):Settings {
  const out={...defaults};
  for(const [key,,values] of options){if(values.includes(snapshot[key] as string|number))(out as unknown as Record<string,unknown>)[key]=snapshot[key];}
  for(const [key] of toggles){if(typeof snapshot[key]==='boolean')(out as unknown as Record<string,unknown>)[key]=snapshot[key];}
  out.show_out_of_stock=true;
  return out;
}
const grid=(value:Settings)=>[
  value.grid_mobile===2?'grid-cols-2':'grid-cols-1',
  ({2:'sm:grid-cols-2',3:'sm:grid-cols-3',4:'sm:grid-cols-4'} as Record<number,string>)[value.grid_tablet],
  ({3:'lg:grid-cols-3',4:'lg:grid-cols-4',5:'lg:grid-cols-5',6:'lg:grid-cols-6'} as Record<number,string>)[value.grid_desktop],
].join(' ');
export default function WebsiteCatalogueBuilder({snapshot,publishedId,busy,allowed,save}:{
  snapshot:Record<string,unknown>;publishedId:number;busy:boolean;allowed:boolean;save:(settings:Settings)=>void;
}) {
  const [value,setValue]=useState<Settings>(()=>safe(snapshot));
  useEffect(()=>setValue(safe(snapshot)),[publishedId]);
  const set=(key:keyof Settings,next:string|number|boolean)=>setValue(current=>({...current,[key]:next}));
  return <section aria-label="Website catalogue presentation editor" className="rounded-2xl border bg-white p-5 xl:col-span-2">
    <h2 className="font-semibold">Website catalogue presentation</h2>
    <p className="mt-1 text-sm text-slate-600">Fourteen bounded layout/display settings. Preview is private and uses example placeholders, never unpublished products. Draft, publish and rollback are separate protected actions.</p>
    <fieldset disabled={busy||!allowed} className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      {options.map(([key,label,values])=><label key={key} className="text-xs">{label}<select aria-label={'Catalogue '+key} value={String(value[key])} onChange={event=>set(key,typeof values[0]==='number'?Number(event.target.value):event.target.value)} className="mt-1 w-full rounded border p-2 text-sm">
        {values.map(option=><option key={option} value={option}>{String(option).replaceAll('_',' ')}</option>)}</select></label>)}
      {toggles.map(([key,label])=><label key={key} className="flex items-center gap-2 text-xs"><input type="checkbox" aria-label={'Catalogue '+key} checked={value[key]===true} disabled={key==='show_out_of_stock'} onChange={event=>set(key,event.target.checked)}/>{label}</label>)}
    </fieldset>
    <aside aria-label="Private catalogue preview" data-mobile={value.grid_mobile} data-tablet={value.grid_tablet} data-desktop={value.grid_desktop} data-page-size={value.items_per_page} className="mt-4 rounded-xl border p-3">
      <p className="mb-3 text-xs text-slate-500">Private layout preview · no real product records</p>
      <div className={'grid '+grid(value)+(value.card_density==='compact'?' gap-2':' gap-5')}>
        {[1,2,3,4,5,6].map(item=><article key={item} className={'min-w-0 rounded-xl border bg-white '+(value.card_density==='compact'?'p-2':'p-4')}>
          <div className={'rounded bg-slate-100 '+(value.image_ratio==='1:1'?'aspect-square':value.image_ratio==='16:9'?'aspect-video':'aspect-[4/3]')}/>
          {value.show_brand&&<p className="mt-2 text-xs">Example brand</p>}<p className="font-semibold">Example product {item}</p>
          {value.show_specs&&<p className="text-xs">Example model</p>}{value.show_colors&&<p className="text-xs">Color: example</p>}
          {value.show_warranty&&<p className="text-xs">Example warranty</p>}
          {value.badge_behavior!=='hidden'&&<span className={'text-xs '+(value.badge_behavior==='status_pill'?'rounded-full bg-slate-100 p-1':'')}>Out of stock</span>}
          {value.show_compare&&<p className="text-xs underline">Compare</p>}
        </article>)}
      </div>
    </aside>
    <button type="button" disabled={busy||!allowed} onClick={()=>save(value)} className="mt-3 rounded border px-3 py-2 text-sm disabled:opacity-40">Save private catalogue draft</button>
  </section>;
}
