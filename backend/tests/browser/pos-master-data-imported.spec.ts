import {execFileSync} from 'node:child_process';
import {expect,test} from '@playwright/test';

test('P02 source-qualified synthetic imported product remains editable, historically safe and outlet-scoped',async({page})=>{
    test.setTimeout(60000);
    for(const seeder of ['PosMasterDataVariantE2eEnsureSeeder','PosMasterDataImportedE2eSeeder']){
        execFileSync('php',['artisan','db:seed','--class=Database\\Seeders\\'+seeder,'--env=testing','--force'],{cwd:process.cwd(),stdio:'inherit'});
    }
    await page.goto('/internal/admin/pos/login');
    await page.getByTestId('login-email').fill('e2e-protected-owner@example.invalid');
    await page.getByTestId('login-password').fill('SyntheticPass123!');
    await page.getByTestId('login-submit').click();await page.waitForURL('**/internal/admin/pos');
    const selected=page.waitForResponse(r=>r.url().endsWith('/internal/admin/outlets/select')&&r.request().method()==='POST');
    await page.getByTestId('outlet-select').selectOption({label:'MT75 P02 Variant Outlet'});
    expect((await selected).status()).toBe(200);
    await page.goto('/internal/admin/pos/workspace/inventory');
    await expect(page.getByTestId('workspace-inventory')).toBeVisible();
    const search='/internal/admin/pos/catalogue?mode=inventory&q=MT75%20P02%20Imported%20Phone&category=product_name';
    const initial=await page.request.get(search);expect(initial.status()).toBe(200);
    const original=await initial.json() as {data:{products:{id:string;code:string;brand_snapshot:string;brand_display:string;qty:number;version:number}[]}};
    expect(original.data.products).toHaveLength(1);
    const imported=original.data.products[0];
    expect(imported).toMatchObject({code:'MST-MOB-908-000807',brand_snapshot:'MT75 Original Imported Brand',brand_display:'MT75 P02 Imported Brand',qty:0,version:1});
    const history=page.getByTestId('inventory-brand-history-'+imported.id);
    await expect(history).toContainText('Original brand: MT75 Original Imported Brand');
    await expect(history).toContainText('Current brand: MT75 P02 Imported Brand');
    await page.getByTestId('product-edit-'+imported.id).click();
    await expect(page.getByTestId('product-edit-mode')).toBeVisible();
    await expect(page.getByTestId('product-brand').locator('option:checked')).toHaveText('MT75 P02 Imported Brand');
    await page.getByTestId('product-subcategory').selectOption({label:'MT75 P02 Smartphone'});
    await page.getByTestId('product-ram').selectOption({label:'8 GB'});
    await page.getByTestId('product-storage').selectOption({label:'128 GB'});
    await page.getByTestId('product-sim').selectOption({label:'MT75 P02 Dual SIM'});
    await page.getByPlaceholder('Model',{exact:true}).fill('Imported Model I2');
    const changed=page.waitForResponse(r=>r.url().endsWith('/internal/admin/pos/inventory/products')&&r.request().method()==='POST');
    await page.getByTestId('product-save').click();
    const response=await changed;expect(response.status()).toBe(200);
    const saved=await response.json() as {data:{id:string;version:number}};
    expect(saved.data.id).toBe(imported.id);expect(saved.data.version).toBe(2);
    const after=await page.request.get(search);expect(after.status()).toBe(200);
    const body=await after.json() as {data:{products:{id:string;code:string;model:string;brand_snapshot:string;brand_display:string;ram_display:string;storage_display:string;sim_display:string;qty:number}[]}};
    expect(body.data.products).toHaveLength(1);
    expect(body.data.products[0]).toMatchObject({id:imported.id,code:imported.code,model:'Imported Model I2',
        brand_snapshot:'MT75 Original Imported Brand',brand_display:'MT75 P02 Imported Brand',
        ram_display:'8 GB',storage_display:'128 GB',sim_display:'MT75 P02 Dual SIM',qty:0});
    await expect(history).toContainText('Original brand: MT75 Original Imported Brand');
    const changedOutlet=page.waitForResponse(r=>r.url().endsWith('/internal/admin/outlets/select')&&r.request().method()==='POST');
    await page.goto('/internal/admin/pos');
    await page.getByTestId('outlet-select').selectOption({label:'E2E Sales Outlet'});
    expect((await changedOutlet).status()).toBe(200);
    const foreign=await page.request.get(search);expect(foreign.status()).toBe(200);
    expect((await foreign.json() as {data:{products:unknown[]}}).data.products).toHaveLength(0);
});
