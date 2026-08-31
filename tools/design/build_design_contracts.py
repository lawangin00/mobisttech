"""Generate design-only schema/route lineage, API contracts and acceptance cases."""
from pathlib import Path
import hashlib
import json
import re

ROOT=Path(__file__).resolve().parents[2]
OUT=ROOT/'docs/design'
INPUT=ROOT/'docs/migration/SOURCE_SYMBOL_INVENTORY.json'

def write(name,value):
    OUT.mkdir(parents=True,exist_ok=True)
    (OUT/name).write_text(json.dumps(value,indent=2)+'\n',encoding='utf-8',newline='\n')

RENAMES={
 ('pos','users'):['outlets'], ('pos','shop_admins'):['outlet_admins'],
 ('pos','website_orders'):['reservations'], ('pos','website_order_lines'):['reservation_lines'],
 ('pos','website_order_allocations'):['reservation_allocations'],
 ('pos','integration_requests'):['legacy_integration_requests'],
 ('website','products'):['products','product_listings'],
 ('website','pos_integration_outbox'):['legacy_integration_events'],
}
EPHEMERAL={'sessions','password_reset_tokens','personal_access_tokens','cache','cache_locks','jobs','job_batches','failed_jobs'}
MODEL_TABLES={
 'Admin':'admins','SuperAdmin':'super_admins','User':'users','ShopAdmin':'shop_admins',
 'Product':'products','ProductImei':'product_imeis','StockUnit':'stock_units','StockMovement':'stock_movements',
 'StockAcquisition':'stock_acquisitions','Sale':'sales','Invoice':'invoices','Claim':'claims','BackupRecord':'backup_records',
 'IntegrationRequest':'integration_requests','WebsiteOrder':'website_orders','WebsiteOrderLine':'website_order_lines',
 'WebsiteOrderAllocation':'website_order_allocations','PosSetting':'pos_settings','PosAuditLog':'pos_audit_logs',
 'PosConfigurationRevision':'pos_configuration_revisions','PosMasterDataOption':'pos_master_data_options',
 'PosMasterDataUsage':'pos_master_data_usages','PosMediaAsset':'pos_media_assets','PosMediaUsage':'pos_media_usages',
 'Order':'orders','OrderItem':'order_items','Payment':'payments','DigitalService':'digital_services',
 'ServiceRequest':'service_requests','ProjectQuote':'project_quotes','ProductReview':'product_reviews',
 'PosIntegrationOutbox':'pos_integration_outbox','AdminAuditLog':'admin_audit_logs','SiteSetting':'site_settings',
 'SiteSecretSetting':'site_secret_settings','SiteConfigurationRevision':'site_configuration_revisions',
 'SiteMediaAsset':'site_media_assets','SiteMediaUsage':'site_media_usages',
 'SiteNavigationItem':'site_navigation_items','SiteManagedPage':'site_managed_pages',
}

def mappings(inv):
    tables={}; migrations=[];models=[]
    for f in inv['files']:
        if '/migrations/' in f['path']:
            mentions=sorted(set(t for s in f['schema'] for t in re.findall(r"Schema::(?:create|table)\(['\"]([^'\"]+)",s['declaration'])))
            migrations.append({'source':f['source'],'path':f['path'],'sha256':f['sha256'],'tables':mentions,
              'declarations':f['schema'],'policy':'Reconcile every column/constraint; data backfills require controlled equivalent transforms, never run source migrations on originals.'})
            for table in mentions:tables.setdefault((f['source'],table),[]).append(f['path'])
        if f['path'].startswith('app/Models/'):
            name=Path(f['path']).stem;table=MODEL_TABLES[name]
            models.append({'source':f['source'],'model':name,'source_table':table,'source_path':f['path'],
                           'target_tables':RENAMES.get((f['source'],table),[table]),'families':f['families']})
    rows=[]
    for (source,table),evidence in sorted(tables.items()):
        mode='preserve-fields-map-foreign-keys'
        if table in EPHEMERAL:mode='reset-runtime-state-preserve-required-failure-evidence'
        if table=='account_sessions':mode='invalidate-sessions-retain-security-history'
        if table in ('integration_requests','pos_integration_outbox'):mode='archive-transport-evidence-replace-live-behavior'
        if (source,table)==('website','products'):mode='verify-external-product-link-migrate-listing-metadata-quarantine-ambiguity'
        rows.append({'source':source,'table':table,'target_tables':RENAMES.get((source,table),[table]),
                     'mode':mode,'evidence':evidence,
                     'columns':'Preserve source fields unless explicit rename/derived/ephemeral disposition; unknown columns block import.',
                     'foreign_keys':'Resolve by source-qualified migration_identity_map; POS shop_id becomes outlet_id; preserve historical snapshot IDs separately.'})
    routes=[]
    for r in inv['routes']:
        disposition='adapt-protected-Laravel-Inertia-route-preserve-service-policy'
        if r['source']=='website':disposition='Next-page-or-versioned-REST-adapter-preserve-source-behavior'
        if r['source']=='website' and r['uri'].startswith('admin'):disposition='move-protected-CMS-to-backend-retain-Website-role-policy'
        if r['source']=='pos' and r['uri'].startswith('api/website'):disposition='retire-cross-database-transport-only-after-local-service-contract-parity'
        routes.append({'source':r['source'],'method':r['method'],'uri':r['uri'],'name':r['name'],
                       'action':r['action'],'middleware':r['middleware'],'families':r['families'],
                       'disposition':disposition,'implementation_status':'Pending'})
    return {'point':'MT-1.2','source_inventory_sha256':hashlib.sha256(INPUT.read_bytes()).hexdigest(),
      'authority':'Design only; actual source-data/target-schema execution is not claimed.',
      'tables':rows,'models':models,'migration_files':migrations,'route_dispositions':routes,
      'new_tables':{
        'customers':'id,public_id UNIQUE,website_user_id nullable UNIQUE FK users,display/contact fields,version; no credential/role merge',
        'customer_source_links':'id,customer_id FK,source/table/key,verification_kind,verified_actor/time; source tuple UNIQUE',
        'product_listings':'id,public_id UNIQUE,product_id UNIQUE FK,slug UNIQUE,public content/media/publication fields',
        'active_imeis':'imei PRIMARY KEY,stock_unit_id FK,slot_no; UNIQUE(stock_unit_id,slot_no)',
        'payment_receipts':'id,payment_id FK,event_id,transaction_reference,merchant,mode,amount DECIMAL(19,2),currency,payload_hash,verified_at; gateway/merchant/mode/event UNIQUE',
        'returns':'id,public_id UNIQUE,invoice_id FK,order_id nullable FK,actor,reason,status,idempotency_key,version',
        'return_lines':'id,return_id FK,sale_id FK,stock_unit_id nullable FK,quantity,condition,accepted_at; per-receipt/unit uniqueness plus locked cumulative limits',
        'refunds':'id,public_id UNIQUE,payment_id FK,return_id nullable FK,amount DECIMAL(19,2),currency,status,provider/reference,evidence_hash; operation UNIQUE',
        'idempotency_requests':'id,actor_scope,operation,key,request_hash,resource_type/id,status,response_expires_at; UNIQUE(actor_scope,operation,key)',
        'domain_events':'id UUID PRIMARY KEY,aggregate_type/id/version,event_type,payload,lease_until,attempts,next_attempt_at,completed_at; operation identity UNIQUE',
        'publication_versions':'domain PRIMARY KEY,version BIGINT,updated_at; increment with business transaction',
        'document_sequences':'namespace/outlet/date PRIMARY KEY,next_sequence; locked allocation and document unique key',
        'resource_capabilities':'id,resource_type/id,operation,token_hash UNIQUE,expires_at,revoked_at; no raw bearer token stored',
        'migration_runs':'id,input_manifest_hash,code/schema_hash,target_identity,status,started_at,completed_at',
        'migration_identity_map':'source/table/key/target_table PRIMARY KEY,target_id,run_id FK,row_digest,outcome; explicit reviewed many-to-one only',
        'migration_quarantine':'id,run_id FK,source/table/key,reason,evidence_reference,resolution; sensitive payload outside Git',
        'migration_reconciliation':'id,run_id FK,control_name,source_value,target_value,result,evidence_hash',
        'migration_source_history':'source/migration PRIMARY KEY,source_hash,source_batch,run_id FK; never target executed migration ledger',
      }}

def obj(properties,required=None):
    return {'type':'object','additionalProperties':False,'properties':properties,'required':list(properties) if required is None else required}
def ref(name):return {'$ref':'#/components/schemas/'+name}
def array(item,**kw):return {'type':'array','items':item,**kw}
STR={'type':'string'}
ID={'type':'string','minLength':1,'maxLength':100}
MONEY={'type':'string','pattern':r'^(0|[1-9][0-9]{0,16})\.[0-9]{2}$'}
REV={'type':'integer','minimum':1}

def api():
    s={
      'Money':MONEY,'Identifier':ID,
      'Error':obj({'error':obj({'code':STR,'message':STR,'request_id':STR,'fields':{'type':'object','additionalProperties':array(STR)}},['code','message','request_id'])}),
      'Account':obj({'id':ID,'name':STR,'email':{'type':'string','format':'email'},'mobile':STR}),
      'RegisterInput':obj({'name':{'type':'string','minLength':1,'maxLength':160},'email':{'type':'string','format':'email'},'mobile':{'type':'string','pattern':r'^03\d{9}$'},'password':{'type':'string','minLength':8,'maxLength':128},'password_confirmation':STR}),
      'LoginInput':obj({'email':{'type':'string','format':'email'},'password':{'type':'string','minLength':1,'maxLength':128}}),
      'ForgotInput':obj({'email':{'type':'string','format':'email'}}),
      'ResetInput':obj({'email':{'type':'string','format':'email'},'token':STR,'password':{'type':'string','minLength':8,'maxLength':128},'password_confirmation':STR}),
      'Message':obj({'message':STR,'request_id':STR}),
      'Category':obj({'code':{'enum':['mobile_phone','tablet','accessory']},'label':STR,'slug':STR}),
      'Variant':obj({'key':ID,'label':STR,'available_quantity':{'type':'integer','minimum':0},'price':ref('Money')}),
      'Product':obj({'id':ID,'slug':STR,'title':STR,'category':STR,'outlet_id':ID,'price':ref('Money'),'currency':{'const':'PKR'},'available_quantity':{'type':'integer','minimum':0},'variants':array(ref('Variant')),'publication_version':REV}),
      'Content':obj({'slug':STR,'title':STR,'sanitized_html':STR,'seo':obj({'title':STR,'description':STR}),'publication_version':REV}),
      'NavigationItem':obj({'label':STR,'url':STR,'position':{'type':'integer','minimum':0},'parent_id':{'type':['string','null']},'id':ID}),
      'CartLineInput':obj({'product_id':ID,'variant_key':ID,'quantity':{'type':'integer','minimum':1,'maximum':99}}),
      'CartLine':obj({'id':ID,'product_id':ID,'variant_key':ID,'quantity':{'type':'integer','minimum':1,'maximum':20},'unit_price':ref('Money'),'line_total':ref('Money')}),
      'QuantityInput':obj({'quantity':{'type':'integer','minimum':1,'maximum':99}}),
      'Cart':obj({'id':ID,'version':REV,'lines':array(ref('CartLine'),maxItems=50),'total':ref('Money'),'currency':{'const':'PKR'}}),
      'CheckoutLine':obj({'product_id':ID,'variant_key':ID,'quantity':{'type':'integer','minimum':1,'maximum':20},'expected_unit_price':ref('Money')}),
      'CheckoutInput':obj({'cart_id':ID,'cart_version':REV,'outlet_id':ID,'lines':array(ref('CheckoutLine'),minItems=1,maxItems=50),
         'customer':obj({'name':{'type':'string','minLength':1,'maxLength':160},'mobile':{'type':'string','pattern':r'^03\d{9}$'},'email':{'type':['string','null'],'format':'email','maxLength':190}},['name','mobile']),
         'delivery':obj({'city':{'type':'string','minLength':1,'maxLength':120},'address':{'type':'string','minLength':1,'maxLength':1000}}),
         'gateway':{'enum':['cod','jazzcash','easypaisa','card']},'currency':{'const':'PKR'}}),
      'OrderItem':obj({'id':ID,'title':STR,'variant_key':STR,'quantity':{'type':'integer','minimum':1},'unit_price':ref('Money'),'line_total':ref('Money'),'outlet_label':STR}),
      'Order':obj({'id':ID,'order_number':STR,'type':{'enum':['mobile','digital']},'version':REV,'items':array(ref('OrderItem')),'total':ref('Money'),'currency':{'const':'PKR'},
         'payment_status':{'enum':['pending','pay_on_delivery','paid','failed']},
         'fulfillment_status':{'enum':['pending','confirmed','processing','ready','dispatched','completed','cancelled','returned']},
         'stock_status':{'enum':['not_required','active','held_cod','consumed','released','expired','stock_exception']},
         'refund_status':{'enum':['none','pending','partial','refunded']}}),
      'PaymentInput':obj({'gateway':{'enum':['cod','jazzcash','easypaisa','card']},'expected_order_version':REV}),
      'Payment':obj({'id':ID,'status':{'enum':['pending','awaiting_collection','paid','failed','reconciliation_required']},'amount':ref('Money'),'currency':{'const':'PKR'},'redirect_url':{'type':['string','null'],'format':'uri'}}),
      'CancelInput':obj({'reason':{'type':'string','minLength':1,'maxLength':1000}}),
      'ReviewInput':obj({'product_id':ID,'order_item_id':ID,'rating':{'type':'integer','minimum':1,'maximum':5},'body':{'type':'string','maxLength':5000}}),
      'Review':obj({'id':ID,'rating':{'type':'integer','minimum':1,'maximum':5},'body':STR,'moderation_status':{'enum':['pending','approved','rejected']}}),
      'DigitalService':obj({'id':ID,'slug':STR,'title':STR,'sanitized_html':STR,'price':{'anyOf':[ref('Money'),{'type':'null'}]}}),
      'ServiceRequestInput':obj({'service_id':ID,'name':{'type':'string','minLength':1,'maxLength':160},'mobile':{'type':'string','pattern':r'^03\d{9}$'},'email':{'type':'string','format':'email'},'message':{'type':'string','minLength':1,'maxLength':5000}}),
      'ServiceRequest':obj({'id':ID,'reference':STR,'status':STR}),
      'Quote':obj({'id':ID,'reference':STR,'title':STR,'amount':ref('Money'),'currency':{'const':'PKR'},'status':STR,'version':REV}),
      'QuotePaymentInput':obj({'expected_quote_version':REV,'gateway':{'enum':['jazzcash','easypaisa','card']}}),
      'ProviderPayload':{'type':'object','description':'Provider-specific authentic callback schema is adapter-owned and cannot activate before H-02. Never accept raw card data.'},
    }
    s['CartLine']['properties']['quantity']['maximum']=99
    s['QuoteAccessInput']=obj({'reference':{'type':'string','minLength':1,'maxLength':160},'mobile':{'type':'string','pattern':r'^03\d{9}$'}})
    s['PaymentOption']=obj({'gateway':{'enum':['cod','jazzcash','easypaisa','card']},'enabled':{'type':'boolean'},'eligible':{'type':'boolean'},'reason':STR})
    for input_name in ('CheckoutInput','PaymentInput','QuotePaymentInput'):
        s[input_name]['properties']['wallet_account']={'type':'string','maxLength':20,'description':'Only a configured wallet adapter may require this account identifier; never PAN/CVV; redact from logs.'}
    s['Home']=obj({'title':STR,'sections':array(obj({'id':ID,'type':STR,'position':{'type':'integer','minimum':0},'sanitized_html':STR})),
                  'seo':s['Content']['properties']['seo'],'publication_version':REV})
    s['Product']['properties'].update({'description_html':STR,'brand':STR,'model':STR,
      'images':array(obj({'url':{'type':'string','format':'uri'},'alt':STR})),
      'specifications':array(obj({'label':STR,'value':STR}))})
    s['Presentation']=obj({'business_name':STR,'logo_url':{'type':'string','format':'uri'},
       'css_variables':array(obj({'name':{'type':'string','pattern':r'^--[a-z0-9-]+$'},'value':{'type':'string','maxLength':200}})),
       'header_html':STR,'footer_html':STR,'catalogue_page_size':{'enum':[12,24,36,48]},
       'show_out_of_stock':{'type':'boolean'},'publication_version':REV})
    spec={'openapi':'3.1.1','info':{'title':'mobiST Tech shared Laravel REST contract','version':'1.0.0-design','description':'MT-1.2 design only. No endpoint is implemented by this artifact. Additional backend admin/Inertia operations retain source policies through the route disposition register.'},
      'servers':[{'url':'/api/v1'}],'paths':{},'components':{'schemas':s,'securitySchemes':{
       'CustomerSession':{'type':'apiKey','in':'cookie','name':'mobist_customer_session','description':'First-party Laravel session; CSRF header required on unsafe requests.'},
       'GuestSession':{'type':'apiKey','in':'cookie','name':'mobist_guest_session','description':'Opaque server-owned guest cart/order scope; not a login or arbitrary customer ID.'},
       'ResourceCapability':{'type':'http','scheme':'bearer','description':'Hashed, scoped, revocable resource capability; never an admin/customer API token.'}}}}
    operations=[
      ('get','/categories','listCategories','Category','public',None,True,'P02,W02'),
      ('get','/products','listProducts','Product','public',None,True,'W02'),
      ('get','/products/{id}','getProduct','Product','public',None,False,'W02'),
      ('get','/products/by-slug/{slug}','getProductBySlug','Product','public',None,False,'W02'),
      ('get','/presentation','getPresentation','Presentation','public',None,False,'W07,W06'),
      ('get','/pages/{slug}','getPage','Content','public',None,False,'W06'),
      ('get','/navigation','getNavigation','NavigationItem','public',None,True,'W06'),
      ('get','/home','getHome','Home','public',None,False,'W06'),
      ('get','/compare','compareProducts','Product','public',None,True,'W02'),
      ('get','/services','listServices','DigitalService','public',None,True,'W05'),
      ('post','/auth/register','registerCustomer','Account','guest','RegisterInput',False,'W01'),
      ('post','/auth/login','loginCustomer','Account','guest','LoginInput',False,'W01'),
      ('post','/auth/logout','logoutCustomer','Message','customer',None,False,'W01'),
      ('post','/auth/forgot-password','forgotPassword','Message','guest','ForgotInput',False,'W01'),
      ('post','/auth/reset-password','resetPassword','Message','guest','ResetInput',False,'W01'),
      ('get','/account','getAccount','Account','customer',None,False,'W01'),
      ('get','/cart','getCart','Cart','customer_or_guest',None,False,'W03'),
      ('get','/cart/payment-options','cartPaymentOptions','PaymentOption','customer_or_guest',None,True,'W04'),
      ('post','/cart/lines','addCartLine','Cart','customer_or_guest','CartLineInput',False,'W03'),
      ('patch','/cart/lines/{id}','updateCartLine','Cart','customer_or_guest','QuantityInput',False,'W03'),
      ('delete','/cart/lines/{id}','removeCartLine','Cart','customer_or_guest',None,False,'W03'),
      ('post','/orders','createOrder','Order','customer_or_guest','CheckoutInput',False,'W03,W04,X01'),
      ('get','/orders','listOwnOrders','Order','customer',None,True,'W03'),
      ('get','/orders/{id}','getOrder','Order','owner_or_capability',None,False,'W03'),
      ('get','/orders/{id}/invoice','getInvoice','Order','owner_or_capability',None,False,'W03,P06'),
      ('post','/orders/{id}/payments','initiatePayment','Payment','owner_session','PaymentInput',False,'W04'),
      ('get','/orders/{id}/payments/{paymentId}','getPayment','Payment','owner_or_capability',None,False,'W04'),
      ('post','/orders/{id}/cancel','cancelUnpaidOrder','Order','owner_session','CancelInput',False,'W03,X01'),
      ('post','/reviews','submitReview','Review','customer','ReviewInput',False,'W03'),
      ('post','/service-requests','createServiceRequest','ServiceRequest','customer_or_guest','ServiceRequestInput',False,'W05'),
      ('get','/service-requests/{id}','getServiceRequest','ServiceRequest','capability',None,False,'W05'),
      ('get','/quotes/{id}','getQuote','Quote','capability',None,False,'W05'),
      ('post','/quote-access/requests','requestQuoteAccess','Message','customer_or_guest','QuoteAccessInput',False,'W05'),
      ('get','/quotes/{id}/payment-options','quotePaymentOptions','PaymentOption','capability',None,True,'W05,W04'),
      ('post','/quotes/{id}/payments','payQuote','Order','capability_write','QuotePaymentInput',False,'W05,W04'),
      ('post','/provider-events/{provider}','receiveProviderEvent','Message','verified_provider','ProviderPayload',False,'W04'),
    ]
    for method,path,op,response,policy,request,many,families in operations:
        write_op=method!='get';business_write=write_op and not path.startswith('/auth/') and policy!='verified_provider'
        secured={'public':[], 'guest':[], 'customer':[{'CustomerSession':[]}],
          'customer_or_guest':[{'CustomerSession':[]},{'GuestSession':[]}],
          'owner_session':[{'CustomerSession':[]},{'GuestSession':[]}],
          'owner_or_capability':[{'CustomerSession':[]},{'GuestSession':[]},{'ResourceCapability':[]}],
          'capability':[{'ResourceCapability':[]}], 'capability_write':[{'ResourceCapability':[]}], 'verified_provider':[]}[policy]
        params=[{'name':p,'in':'path','required':True,'schema':({'enum':['jazzcash','easypaisa','card']} if p=='provider' else ID)} for p in re.findall(r'{(\w+)}',path)]
        if many:
            params += [{'name':'page','in':'query','schema':{'type':'integer','minimum':1,'default':1}}, {'name':'per_page','in':'query','schema':{'type':'integer','minimum':1,'maximum':100,'default':25}}]
        if business_write:params.append({'name':'Idempotency-Key','in':'header','required':True,'schema':{'type':'string','minLength':16,'maxLength':128,'pattern':r'^[!-~]+$'}})
        if write_op and policy!='verified_provider':params.append({'name':'X-XSRF-TOKEN','in':'header','required':True,'schema':STR})
        if method in ('patch','delete') or op in ('cancelUnpaidOrder','initiatePayment','payQuote'):
            params.append({'name':'If-Match','in':'header','required':True,'schema':{'type':'string','pattern':r'^"[1-9][0-9]*"$'}})
        body=obj({'data':array(ref(response)) if many else ref(response)})
        if many:
            body['properties']['meta']=obj({'page':{'type':'integer','minimum':1},'per_page':{'type':'integer','minimum':1,'maximum':100},'total':{'type':'integer','minimum':0}});body['required'].append('meta')
        responses={'200':{'description':'Authorized result or idempotent replay','content':{'application/json':{'schema':body}}}}
        if op=='getInvoice':responses['200']['content']={'application/pdf':{'schema':{'type':'string','format':'binary'}}};responses['200']['description']='Authorized immutable invoice PDF; no private operational fields'
        if business_write and method=='post':responses['201']={'description':'Created result','content':{'application/json':{'schema':body}}}
        for code in ['400','401','403','404','409','412','419','422','428','429','503']:
            responses[code]={'description':'See stable error contract in TRANSACTIONS_API_SECURITY.md','content':{'application/json':{'schema':ref('Error')}}}
        spec['paths'].setdefault(path,{})[method]={'operationId':op,'summary':op,'tags':families.split(','),'security':secured,
          'x-policy':policy,'x-target-status':'Pending','x-csrf-required':write_op and policy!='verified_provider',
          'x-idempotency-required':business_write,'x-provider-verification-required':policy=='verified_provider',
          'parameters':params,'responses':responses}
        if request:spec['paths'][path][method]['requestBody']={'required':True,'content':{'application/json':{'schema':ref(request)}}}
    spec['paths']['/products']['get']['parameters'] += [
      {'name':'q','in':'query','schema':{'type':'string','maxLength':200}},
      {'name':'category','in':'query','schema':{'enum':['mobile_phone','tablet','accessory']}},
      {'name':'sort','in':'query','schema':{'enum':['newest','oldest','price_asc','price_desc','name_asc','name_desc']}},
      {'name':'outlet_id','in':'query','schema':ID}]
    spec['paths']['/products']['get']['parameters'] += [
      {'name':name,'in':'query','schema':({'type':'integer','minimum':0} if name in ('ram','storage') else MONEY if name in ('min_price','max_price') else {'type':'string','maxLength':100})}
      for name in ('brand','subcategory','condition','pta','availability','min_price','max_price','ram','storage')]
    spec['paths']['/products']['get']['parameters']=[{**p,'schema':{**p['schema'],'default':24}} if p['name']=='per_page' else p for p in spec['paths']['/products']['get']['parameters']]
    spec['paths']['/products']['get']['x-default-page-size']='Published catalogue.items_per_page (12/24/36/48), fallback 24; preserve source setting'
    spec['paths']['/compare']['get']['parameters']=[{'name':'products','in':'query','required':True,'schema':array(ID,minItems=1,maxItems=4,uniqueItems=True),'style':'form','explode':False}]
    spec['paths']['/quote-access/requests']['post']['x-access-recovery']='Reference/mobile are a lookup hint only. Generic result; issue capability only via verified quote contact/account or authorized operator, never return it based only on contact matching.'
    spec['x-csrf-bootstrap']={'path':'/sanctum/csrf-cookie','method':'GET','base':'same customer origin outside /api/v1','result':'204 plus XSRF cookie; guest session created before unsafe guest requests'}
    return spec

CASES=[
 ('identity_collision','MT-2.2','POS User 7 and Website User 7 share an email','Map to outlet and Website account separately; no credentials/roles merged'),
 ('customer_contact_match','MT-2.2','POS invoice and guest order have a registered customer email','No ownership link without verified source/account proof'),
 ('role_boundary','MT-2.2','Website manager attempts credential replacement or POS super-admin acts as Website owner','Deny without exact independent grant'),
 ('reset_realm','MT-2.2','Same email exists in POS admin and Website users; reset token is swapped','Only original broker/realm account may reset'),
 ('csrf_session','MT-4.1','Missing/expired CSRF, forged origin, reused session after password change','Reject; rotate/invalidate sessions and preserve realm isolation'),
 ('public_privacy','MT-3.4','Catalogue/account DTO includes cost,CNIC,secret or another customer data','Reject projection; public/private cache separation enforced'),
 ('product_link_collision','MT-2.3','Website external ID matches wrong outlet/category or no POS product','Quarantine/unpublish; never synthesize stock'),
 ('imei_reentry','MT-2.4','An IMEI sold historically is bought back; another active unit already claims it','Allow historical reuse only when no active owner; conflicting active claim fails'),
 ('last_quantity_race','MT-2.4','POS sale and Website checkout race for last quantity','Exactly one succeeds; no negative stock'),
 ('last_unit_race','MT-2.4','Two variants/requests reference one physical unit','One active allocation; complete IMEI slots and correct variant required'),
 ('price_change','MT-2.7','Price changes after cart display before checkout','Conflict with safe current price; no silent charge change'),
 ('mixed_outlet','MT-2.7','Physical checkout contains two outlets','422; no partial reservations or orders'),
 ('idempotency_replay','MT-2.7','Same actor/key/payload repeated before/after response expiry','One durable operation and same resource, no duplicate sale/payment'),
 ('idempotency_conflict','MT-2.7','Same key with changed amount/lines or actor accesses another response','409 or denied; no leaked replay'),
 ('confirm_expire_race','MT-2.7','Payment confirmation and expiry/release at deadline','One terminal reservation transition; late money preserved as reconciliation incident'),
 ('duplicate_callback','MT-2.7','Provider replays event or changes transaction/order/amount/currency','Matching replay harmless; mismatches rejected and audited'),
 ('late_payment','MT-2.7','Valid money receipt arrives after reservation release/cancellation','Preserve paid receipt, flag stock_exception, no unallocated sale'),
 ('unknown_provider_result','MT-2.7','Provider times out after receiving charge request','Reconcile persisted intent before new charge; no automatic duplicate'),
 ('cod_hold','MT-2.7','COD dispatch exceeds online TTL; another sale competes','Held COD stays unavailable; collection once; no unpaid completion'),
 ('disabled_provider','MT-5.3','Unconfigured gateway or browser paid=true payload','503/422; no invented verification or card data storage'),
 ('quote_tampering','MT-5.4','Client supplies changed approved quote amount or stolen/expired token','Deny; server quote/version/ownership are authoritative'),
 ('return_duplicate','MT-2.5','Repeated receipt or quantity beyond original sale','At most one accepted return per unit; remaining-quantity cap'),
 ('refund_cap','MT-2.7','Concurrent refunds or unknown provider results exceed verified collected balance','Locked limit subtracts completed and pending/unknown refund intents; release only after verified failure; status alone never proves refund'),
 ('history_snapshot','MT-3.1','Business/theme/product/warranty/customer changes after sale','Original invoice/claim snapshot and identifiers remain unchanged'),
 ('revision_conflict','MT-3.2','Two editors publish same old revision; secret rotated before rollback','Stale publish rejected; no secret resurrection'),
 ('cache_after_commit','MT-3.4','Commit then crash before invalidation; Redis unavailable','Versioned master reads see committed state; checkout never trusts stale cache'),
 ('worker_retry','MT-3.3','Lease expires and same event is delivered twice/out of order','Deduplicate effects, reclaim safely, expose terminal failures'),
 ('decimal_rounding','MT-2.5','0.10+0.20, residual discount allocation, overflow/exponent/more than 2 decimals','Exact 0.30 and deterministic totals; reject invalid representations'),
 ('source_import_rerun','MT-7.1','Same manifest rerun then changed source digest','No duplicate; changed input blocked pending reviewed run'),
 ('relationship_quarantine','MT-7.1','Missing FK/unknown column/invalid date/duplicate source alias','Quarantine with reconciled count; active obligations block promotion'),
 ('encrypted_recovery','MT-3.3','Missing/wrong/rotated key or tampered cipher/media manifest','Fail safely; providers disabled; no plaintext logging or empty-secret success'),
 ('post_write_rollback','MT-7.1','Failure after target accepted writes/provider receipt','Recover target journal; never redirect to stale source or sync writes back'),
 ('capability_scope','MT-3.4','Order-read token used to pay another quote or after revocation','Deny operation/resource mismatch and stale token; no referrer/log leak'),
 ('private_storage','MT-3.3','Traversal/SVG executable/upload or direct CNIC/backup URL','Reject unsafe upload; object-specific authorization required'),
]

def acceptance():
    return {'point':'MT-1.2','scope':'Design scenarios, not executed target tests',
      'reservation_transitions':{
       'active':{'confirm':'consumed','expire':'expired','release':'released'},
       'held_cod':{'collect':'consumed','cancel':'released'},
       'consumed':{},'expired':{},'released':{}},
      'cases':[{'id':'DCASE-'+str(i).zfill(2),'name':name,'implementation_gate':gate,'given':given,'expected':expected,'status':'Pending implementation test'} for i,(name,gate,given,expected) in enumerate(CASES,1)]}

def main():
    inv=json.loads(INPUT.read_text())
    mapping=mappings(inv);write('SCHEMA_MAPPING.json',mapping)
    spec=api();write('openapi.json',spec)
    write('DESIGN_ACCEPTANCE_CASES.json',acceptance())
    print('Design catalog:',len(mapping['tables']),'source-qualified tables;',len(mapping['models']),'models;',len(mapping['migration_files']),'migrations;',len(mapping['route_dispositions']),'routes')
    print('API operations:',sum(len(v) for v in spec['paths'].values()),'; acceptance cases:',len(CASES))

if __name__=='__main__':main()
