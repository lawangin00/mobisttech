import { expect, test } from '@playwright/test';

const email = 'mt75-fresh-owner@example.invalid';
const password = 'SyntheticFreshOwner123!';

test('MT75 fresh publication reaches real Next.js product and guest cart', async ({ page }) => {
    test.setTimeout(330_000);
    await page.goto('/internal/admin/pos/login');
    await page.getByTestId('login-email').fill(email);
    await page.getByTestId('login-password').fill(password);
    await page.getByTestId('login-submit').click();
    await page.waitForURL('**/internal/admin/pos');

    await expect(page.getByTestId('member-name')).toHaveText('MT75 Fresh Protected Owner');
    await expect(page.getByTestId('outlet-select')).toHaveCount(0);
    await expect(page.getByTestId('no-pos-access')).toBeVisible();
    await expect(page.getByTestId('nav-inventory')).toHaveCount(0);
    const denied = await page.request.get('/internal/admin/pos/workspace/inventory');
    expect(denied.status()).toBe(403);

    await page.getByTestId('manage-outlets-link').click();
    await page.waitForURL('**/internal/admin/outlet-management');
    await expect(page.getByText('001 · MT75 Fresh First Outlet')).toHaveCount(0);
    await page.getByTestId('outlet-owner-password').fill(password);
    await page.getByTestId('outlet-new-name').fill('MT75 Fresh First Outlet');
    await page.getByTestId('outlet-new-address').fill('Target-only synthetic address');
    const created = page.waitForResponse(response => response.url().endsWith('/internal/admin/outlet-management')
        && response.request().method() === 'POST');
    await page.getByTestId('outlet-create').click();
    expect((await created).status()).toBe(201);
    await expect(page.getByText('001 · MT75 Fresh First Outlet')).toBeVisible();

    await page.getByTestId('outlet-manage-profile-001').click();
    await expect(page.getByTestId('outlet-profile-editor')).toBeVisible();
    await page.getByTestId('outlet-profile-business_legal_name').fill('MT75 Fresh Legal Identity');
    await page.getByTestId('outlet-profile-business_phone').fill('+92 300 0000000');
    await page.getByTestId('outlet-profile-business_hours').fill('Mon-Sat 10:00-20:00');
    await page.getByTestId('outlet-managed-password').fill(password);
    await page.getByTestId('outlet-profile-save').click();
    await expect(page.getByTestId('outlet-profile-message')).toHaveText('Outlet profile saved.');
    await expect(page.getByTestId('outlet-profile-code')).toHaveText('001');

    await page.getByRole('link', { name: 'Back to POS' }).click();
    await page.waitForURL('**/internal/admin/pos');
    await expect(page.getByTestId('outlet-select')).toHaveValue('');
    await page.getByTestId('outlet-select').selectOption({ label: 'MT75 Fresh First Outlet' });
    await page.waitForURL('**/internal/admin/pos');
    await expect(page.getByTestId('outlet-select')).toHaveValue(/.+/);
    await page.getByRole('link', { name: 'Inventory', exact: true }).click();
    await page.waitForURL('**/internal/admin/pos/workspace/inventory');
    await expect(page.getByTestId('workspace-inventory')).toBeVisible();
    await expect(page.getByTestId('outlet-profile-code')).toHaveCount(0);

    await page.getByPlaceholder('Product name').fill('MT75 Fresh Accessory');
    await page.getByPlaceholder('Model').fill('MT75 Premium 128');
    await page.getByTestId('product-category').selectOption({ label: 'Accessories' });
    await page.getByPlaceholder('Purchase price').fill('100.00');
    await page.getByPlaceholder('Sale price').fill('150.00');
    await page.getByRole('combobox', { name: 'Warranty type' }).selectOption('shop_warranty');
    await page.getByRole('spinbutton', { name: 'Warranty duration' }).fill('30');
    await page.getByTestId('product-save').click();
    const product = page.getByRole('button', { name: /MT75 Fresh Accessory/ });
    await expect(product).toContainText('Qty 0');

    const receive = page.getByRole('heading', { name: 'Receive stock' }).locator('xpath=ancestor::section[1]');
    await receive.locator('select').nth(0).selectOption({ label: 'MT75 Fresh Accessory' });
    await receive.getByPlaceholder('Qty').fill('3');
    await receive.getByPlaceholder('Unit cost').fill('100.00');
    await receive.locator('select').nth(1).selectOption({ label: 'Supplier' });
    await receive.getByPlaceholder('Business / seller name').fill('MT75 Synthetic Supplier');
    await receive.getByPlaceholder('03XXXXXXXXX').fill('03000000000');
    await receive.getByPlaceholder('Address').fill('Target-only supplier address');
    await page.getByTestId('acquire-submit').click();
    await expect(page.getByRole('button', { name: /MT75 Fresh Accessory/ })).toContainText('Qty 3');
    await page.goto('/internal/admin/platform');
    const modeSection = page.getByRole('heading', { name: 'Website operating mode' }).locator('xpath=ancestor::section[1]');
    await modeSection.getByRole('button', { name: 'Save mode draft' }).click();
    await modeSection.getByRole('button', { name: 'Publish', exact: true }).first().click();
    await expect(modeSection).toContainText('Current: hybrid');
    await page.goto('/internal/admin/pos/workspace/inventory');
    await page.getByRole('button', { name: 'Website listing' }).click();
    await expect(page.getByTestId('website-listing-editor')).toBeVisible();
    await page.getByTestId('website-listing-publish').click();
    await expect(page.getByTestId('website-listing-editor')).toHaveCount(0);
    const publicProduct = await page.request.get('http://127.0.0.1:18080/api/v1/catalogue/products/mt75-fresh-accessory');
    expect(publicProduct.status()).toBe(200);
    const publicData = await publicProduct.json() as { data: { name: string; price: string; availability: {quantity:number} } };
    expect(publicData.data).toMatchObject({name:'MT75 Fresh Accessory',price:'150.00',availability:{quantity:3}});
    expect(JSON.stringify(publicData.data)).not.toMatch(/purchase_price|customer_phone|customer_email|imei|object_key/);
    const detailVariants = (await publicProduct.json() as {data:{variants:Array<{key:string;availability:{quantity:number}}>}}).data.variants;
    expect(detailVariants).toMatchObject([{key:'standard',availability:{quantity:3}}]);
    expect(JSON.stringify(detailVariants).toLowerCase()).not.toMatch(/imei|unit_no|purchase_price/);
    // Fresh serialized product: protected POS definition -> acquisition -> IMEI -> explicit Website publication.
    await page.goto('/internal/admin/pos/workspace/master-data');
    await expect(page.getByTestId('master-data-workspace')).toBeVisible();
    await page.getByTestId('master-list').selectOption('product_brand');
    await page.getByTestId('master-label').fill('MT75 Synthetic Brand');
    await page.getByTestId('master-create').click();
    await expect(page.getByRole('alert')).toContainText('Option create successful.');
    await page.goto('/internal/admin/pos/workspace/inventory');
    await page.getByPlaceholder('Product name').fill('MT75 Fresh Tracked Phone');
    await page.getByTestId('product-category').selectOption({label:'Mobiles'});
    await page.getByTestId('product-brand').selectOption({index:1});
    await page.getByPlaceholder('Model').fill('MT75 Tracked 128');
    await page.getByTestId('product-ram').selectOption({label:'8 GB'});
    await page.getByTestId('product-storage').selectOption({label:'128 GB'});
    await page.getByTestId('product-sim').selectOption({label:'Single Physical SIM'});
    await page.getByPlaceholder('Purchase price').fill('500.00');
    await page.getByPlaceholder('Sale price').fill('750.00');
    await page.getByLabel('Track IMEI').check();
    await page.getByTestId('product-save').click();
    const tracked=page.getByRole('button',{name:/MT75 Fresh Tracked Phone/});
    await expect(tracked).toContainText('Qty 0');
    const trackedReceive=page.getByRole('heading',{name:'Receive stock'}).locator('xpath=ancestor::section[1]');
    await trackedReceive.locator('select').nth(0).selectOption({label:'MT75 Fresh Tracked Phone'});
    await trackedReceive.getByPlaceholder('Qty').fill('1');
    await trackedReceive.getByPlaceholder('Unit cost').fill('500.00');
    await trackedReceive.locator('select').nth(1).selectOption({label:'Supplier'});
    await trackedReceive.getByPlaceholder('Business / seller name').fill('MT75 Synthetic Supplier');
    await trackedReceive.getByPlaceholder('03XXXXXXXXX').fill('03000000000');
    await trackedReceive.getByPlaceholder('Address').fill('Target-only synthetic address');
    await page.getByTestId('acquire-submit').click();
    await expect(tracked).toContainText('Qty 1');
    const unit=page.getByRole('heading',{name:'Unit / IMEI'}).locator('xpath=ancestor::section[1]');
    await unit.locator('select').nth(0).selectOption({index:1});
    await unit.getByPlaceholder('IMEI 1').fill('MT75-PRIVATE-TRACKED-IMEI');
    await unit.getByRole('button',{name:'Save IMEI'}).click();
    await unit.locator('select').nth(1).selectOption({label:'Used / Kit'});
    await unit.locator('select').nth(2).selectOption({label:'PTA Approved'});
    await unit.getByRole('button',{name:'Save unit attributes'}).click();
    await tracked.locator('..').getByRole('button',{name:'Website listing'}).click();
    await page.getByTestId('website-listing-publish').click();
    await expect(page.getByTestId('website-listing-editor')).toHaveCount(0);
    const trackedApi=await page.request.get('http://127.0.0.1:18080/api/v1/catalogue/products/mt75-fresh-tracked-phone');
    expect(trackedApi.status()).toBe(200);
    const trackedData=(await trackedApi.json() as {data:{availability:{quantity:number};variants:Array<{key:string;condition:string;pta_status:string;availability:{quantity:number}}>}}).data;
    expect(trackedData.availability.quantity).toBe(1);
    expect(trackedData.variants).toMatchObject([{condition:'used',pta_status:'pta_approved',availability:{quantity:1}}]);
    expect(JSON.stringify(trackedData).toLowerCase()).not.toMatch(/imei|unit_no|purchase_price|seller_phone/);
    const publicTracked=await page.goto('http://127.0.0.1:13000/products/mt75-fresh-tracked-phone');
    expect(publicTracked?.status()).toBe(200);
    await expect(page.getByRole('heading',{name:'MT75 Fresh Tracked Phone'})).toBeVisible();
    await expect(page.locator('main').getByText('1 available').first()).toBeVisible();
    await expect(page.getByRole('heading',{name:'Available variants'}).locator('..')).toContainText('pta_approved');
    expect((await page.content()).toLowerCase()).not.toMatch(/mt75-private-tracked-imei|purchase_price|seller_phone/);
    const trackedFilter=await page.goto('http://127.0.0.1:13000/products?category=mobile_phone&condition=used&pta_status=pta_approved&ram_gb=8&storage_gb=128');
    expect(trackedFilter?.status()).toBe(200);
    await expect(page.locator('main article')).toHaveCount(1);
    await expect(page.locator('main article')).toContainText('MT75 Fresh Tracked Phone');
    const sortedPage=await page.goto('http://127.0.0.1:13000/products?sort=price_desc&category=accessory');
    expect(sortedPage?.status()).toBe(200);
    await expect(page.getByRole('combobox',{name:'Sort products'})).toHaveValue('price_desc');
    await expect(page.locator('main article').getByRole('link',{name:'MT75 Fresh Accessory'}).first()).toBeVisible();
    for (const [oldPath, destination] of [
        ['/mobiles','/products?category=mobile_phone'],
        ['/mobiles/mt75-fresh-accessory','/products/mt75-fresh-accessory'],
        ['/product/mt75-fresh-accessory','/products/mt75-fresh-accessory'],
        ['/products/mobiles','/products?category=mobile_phone'],
        ['/products/tablets','/products?category=tablet'],
        ['/products/accessories','/products?category=accessory'],
    ]) {
        const redirect=await page.request.get('http://127.0.0.1:13000'+oldPath,{maxRedirects:0});
        expect(redirect.status(),oldPath).toBe(301);
        const target=new URL(redirect.headers()['location'],'http://127.0.0.1:13000');
        expect(target.pathname+target.search).toBe(destination);
    }
    const legacyProduct=await page.goto('http://127.0.0.1:13000/product/mt75-fresh-accessory');
    expect(legacyProduct?.status()).toBe(200);
    await expect(page).toHaveURL('http://127.0.0.1:13000/products/mt75-fresh-accessory');


    const categories = await page.request.get('http://127.0.0.1:18080/api/v1/catalogue/categories');
    expect(categories.status()).toBe(200);
    const categoryRows = await categories.json() as {data:Array<{code:string;products:number}>};
    expect(categoryRows.data.some(row=>row.code==='accessory' && row.products>=1)).toBe(true);
    const filtered = await page.request.get('http://127.0.0.1:18080/api/v1/catalogue/products?category=accessory&q=Fresh');
    expect(filtered.status()).toBe(200);
    const filteredData = await filtered.json() as {data:{items:Array<{slug:string;price:string}>}};
    expect(filteredData.data.items).toContainEqual(expect.objectContaining({slug:'mt75-fresh-accessory',price:'150.00'}));
    const noMatch = await page.request.get('http://127.0.0.1:18080/api/v1/catalogue/products?category=mobile_phone&q=Accessory');
    expect(noMatch.status()).toBe(200);
    expect((await noMatch.json() as {data:{items:unknown[]}}).data.items).toHaveLength(0);
    const matchedPage = await page.goto('http://127.0.0.1:13000/products?category=accessory&q=Fresh');
    expect(matchedPage?.status()).toBe(200);
    await expect(page.locator('main article').getByRole('link',{name:'MT75 Fresh Accessory'}).first()).toBeVisible();
    const excludedPage = await page.goto('http://127.0.0.1:13000/products?category=mobile_phone&q=Accessory');
    expect(excludedPage?.status()).toBe(200);
    await expect(page.getByText('No products match this search.')).toBeVisible();
    await expect(page.locator('main article')).toHaveCount(0);

    const displayed = await page.goto('http://127.0.0.1:13000/products/mt75-fresh-accessory');
    expect(displayed?.status()).toBe(200);
    await expect(page.getByRole('heading',{name:'MT75 Fresh Accessory'})).toBeVisible();
    const businessResponse=await page.request.get('http://127.0.0.1:18080/api/v1/business-profile');
    expect(businessResponse.status()).toBe(200);
    const business=(await businessResponse.json() as {data:{public_website:string}}).data;
    const publicOrigin=business.public_website.replace(/\/$/,'');
    const productCanonical=publicOrigin+'/products/mt75-fresh-accessory';
    await expect(page.locator('link[rel=canonical]')).toHaveAttribute('href',productCanonical);
    const productSchema=JSON.parse((await page.locator('script[type="application/ld+json"]').textContent()) ?? '{}') as {offers:{url:string;price:string;priceCurrency:string;availability:string}};
    expect(productSchema.offers).toMatchObject({url:productCanonical,price:'150.00',priceCurrency:'PKR',availability:'https://schema.org/InStock'});
    expect(JSON.stringify(productSchema).toLowerCase()).not.toMatch(/purchase_price|imei|unit_no|seller_phone|outlet_id/);
    const unpublished=await page.request.get('http://127.0.0.1:13000/products/mt75-private-unpublished');
    expect(unpublished.status()).toBe(404);
    await expect(page.getByText('Rs 150',{exact:true})).toBeVisible();
    await expect(page.locator('main').getByText('3 available').first()).toBeVisible();
    const html=(await page.content()).toLowerCase();
    for(const privateField of ['purchase_price','customer_cnic','customer_phone','imei','object_key']) expect(html).not.toContain(privateField);
    await page.getByRole('button',{name:'Add to cart'}).click();
    await expect(page.getByText('Added to cart.',{exact:true})).toBeVisible();
    await page.goto('http://127.0.0.1:13000/cart');
    await expect(page.getByRole('link',{name:'MT75 Fresh Accessory'})).toBeVisible();
    // Create protected synthetic subcategory, then select it for the separately published zero-stock POS product.
    await page.goto('/internal/admin/pos/workspace/master-data');
    await page.getByTestId('master-list').selectOption('product_subcategory');
    await page.getByRole('combobox',{name:'Parent category'}).selectOption('accessory');
    await page.getByTestId('master-label').fill('MT75 Budget Accessories');
    await page.getByTestId('master-create').click();
    await expect(page.getByRole('alert')).toContainText('Option create successful.');
    await page.goto('/internal/admin/pos/workspace/inventory');
    await page.getByPlaceholder('Product name').fill('MT75 Fresh Budget Accessory');
    await page.getByPlaceholder('Model').fill('MT75 Budget 64');
    await page.getByTestId('product-category').selectOption({label:'Accessories'});
    await page.getByTestId('product-subcategory').selectOption({label:'MT75 Budget Accessories'});
    await page.getByPlaceholder('Purchase price').fill('50.00');
    await page.getByPlaceholder('Sale price').fill('90.00');
    await page.getByTestId('product-save').click();
    const second=page.getByRole('button',{name:/MT75 Fresh Budget Accessory/});
    await expect(second).toContainText('Qty 0');
    await second.locator('..').getByRole('button',{name:'Website listing'}).click();
    await expect(page.getByTestId('website-listing-editor')).toContainText('MT75 Fresh Budget Accessory');
    await page.getByTestId('website-listing-publish').click();
    await expect(page.getByTestId('website-listing-editor')).toHaveCount(0);
    const subcatCode='accessory_mt75_budget_accessories';
    const scopedUrl='http://127.0.0.1:13000/products?category=accessory&subcategory='+subcatCode;
    const publicSubcat=await page.request.get('http://127.0.0.1:18080/api/v1/catalogue/products?category=accessory&subcategory='+subcatCode);
    expect(publicSubcat.status()).toBe(200);
    expect((await publicSubcat.json() as {data:{items:Array<{slug:string;subcategory:{code:string;label:string}}>} }).data.items)
        .toMatchObject([{slug:'mt75-fresh-budget-accessory',subcategory:{code:subcatCode,label:'MT75 Budget Accessories'}}]);
    expect((await publicSubcat.text()).toLowerCase()).not.toMatch(/purchase_price|seller_phone|imei|outlet_id/);
    const filteredSubcat=await page.goto(scopedUrl);
    expect(filteredSubcat?.status()).toBe(200);
    await expect(page.getByRole('textbox',{name:'Subcategory code'})).toHaveValue(subcatCode);
    await expect(page.locator('main article')).toHaveCount(1);
    await expect(page.locator('main article')).toContainText('MT75 Fresh Budget Accessory');
    const subcatMissing=await page.goto(scopedUrl.replace(subcatCode,'accessory_unknown'));
    expect(subcatMissing?.status()).toBe(200);
    await expect(page.locator('main article')).toHaveCount(0);
    const budgetDetail=await page.goto('http://127.0.0.1:13000/products/mt75-fresh-budget-accessory');
    expect(budgetDetail?.status()).toBe(200);
    await expect(page.locator('main')).toContainText('MT75 Budget Accessories');
    const emptySchema=JSON.parse((await page.locator('script[type="application/ld+json"]').textContent()) ?? '{}') as {offers:{url:string;availability:string}};
    expect(emptySchema.offers).toMatchObject({url:publicOrigin+'/products/mt75-fresh-budget-accessory',availability:'https://schema.org/OutOfStock'});
    const budgetModel=await page.goto('http://127.0.0.1:13000/products?model=Budget&category=accessory');
    expect(budgetModel?.status()).toBe(200);
    await expect(page.getByRole('textbox',{name:'Model'})).toHaveValue('Budget');
    await expect(page.locator('main article')).toHaveCount(1);
    await expect(page.locator('main article')).toContainText('MT75 Fresh Budget Accessory');
    const premiumModel=await page.goto('http://127.0.0.1:13000/products?model=Premium&category=accessory');
    expect(premiumModel?.status()).toBe(200);
    await expect(page.locator('main article')).toHaveCount(1);
    await expect(page.locator('main article')).toContainText('MT75 Fresh Accessory');
    const unknownBrand=await page.goto('http://127.0.0.1:13000/products?brand=UnknownBrand');
    expect(unknownBrand?.status()).toBe(200);
    await expect(page.getByText('No products match this search.')).toBeVisible();
    const deviceFilter=await page.goto('http://127.0.0.1:13000/products?category=accessory&condition=used&pta_status=pta_approved&ram_gb=8&storage_gb=256');
    expect(deviceFilter?.status()).toBe(200);
    await expect(page.getByRole('combobox',{name:'Device condition'})).toHaveValue('used');
    await expect(page.getByRole('combobox',{name:'PTA status'})).toHaveValue('pta_approved');
    await expect(page.getByRole('spinbutton',{name:'RAM in GB'})).toHaveValue('8');
    await expect(page.getByRole('spinbutton',{name:'Storage in GB'})).toHaveValue('256');
    await expect(page.getByText('No products match this search.')).toBeVisible();
    await expect(page.locator('main article')).toHaveCount(0);
    const priced=await page.goto('http://127.0.0.1:13000/products?category=accessory&min_price=80&max_price=100');
    expect(priced?.status()).toBe(200);
    await expect(page.getByRole('spinbutton',{name:'Minimum price'})).toHaveValue('80');
    await expect(page.locator('main article')).toHaveCount(1);
    await expect(page.locator('main article')).toContainText('MT75 Fresh Budget Accessory');
    const dear=await page.goto('http://127.0.0.1:13000/products?category=accessory&min_price=120');
    expect(dear?.status()).toBe(200);
    await expect(page.locator('main article')).toHaveCount(1);
    await expect(page.locator('main article')).toContainText('MT75 Fresh Accessory');
    const two=await page.goto('http://127.0.0.1:13000/compare?a=mt75-fresh-accessory&b=mt75-fresh-budget-accessory');
    expect(two?.status()).toBe(200);
    await expect(page.locator('main article')).toHaveCount(2);
    await expect(page.locator('main article').nth(0)).toContainText('Rs 150');
    await expect(page.locator('main article').nth(1)).toContainText('Rs 90');
    await expect(page.locator('main article').nth(1)).toContainText('Out of stock');
    await expect(page.locator('main article').nth(0)).toContainText('3 in stock');
    expect((await page.content()).toLowerCase()).not.toMatch(/purchase_price|customer_cnic|customer_phone|imei|object_key/);
    const compared=await page.goto('http://127.0.0.1:13000/compare?a=mt75-fresh-accessory&b=mt75-fresh-accessory');
    expect(compared?.status()).toBe(200);
    await expect(page.getByRole('heading',{name:'Compare products'})).toBeVisible();
    await expect(page.locator('main article')).toHaveCount(1);
    await expect(page.locator('main article')).toContainText('MT75 Fresh Accessory');
    await expect(page.locator('main article')).toContainText('Rs 150');
    await expect(page.locator('main article')).toContainText('3 in stock');
    await expect(page.locator('main article')).toContainText('1 variant');
    const absent=await page.goto('http://127.0.0.1:13000/compare?a=mt75-fresh-accessory&b=mt75-not-published');
    expect(absent?.status()).toBe(200);
    await expect(page.locator('main article')).toHaveCount(1);
    expect((await page.content()).toLowerCase()).not.toMatch(/purchase_price|customer_cnic|customer_phone|imei|object_key/);
    // Publish actual protected mode revisions and verify fresh public route isolation.
    for (const choice of ['digital_only','commerce_only','hybrid'] as const) {
        await page.goto('/internal/admin/platform');
        const modes=page.getByRole('heading',{name:'Website operating mode'}).locator('xpath=ancestor::section[1]');
        await modes.locator('select').selectOption(choice);
        const drafted=page.waitForResponse(r=>r.url().endsWith('/internal/admin/platform/website-mode/draft') && r.request().method()==='POST');
        await modes.getByRole('button',{name:'Save mode draft'}).click();
        expect((await drafted).ok()).toBe(true);
        const published=page.waitForResponse(r=>/\/website-mode\/[^/]+\/publish$/.test(r.url()) && r.request().method()==='POST');
        await modes.getByRole('button',{name:'Publish',exact:true}).first().click();
        expect((await published).ok()).toBe(true);
        await expect(modes).toContainText('Current: '+choice);
        const profile=await page.request.get('http://127.0.0.1:18080/api/v1/website-profile');
        expect(profile.status()).toBe(200);
        expect((await profile.json() as {data:{mode:string}}).data.mode).toBe(choice);
        const listing=await page.request.get('http://127.0.0.1:18080/api/v1/catalogue/products/mt75-fresh-accessory');
        expect(listing.status()).toBe(choice==='digital_only'?404:200);
        const nextPage=await page.goto('http://127.0.0.1:13000/products/mt75-fresh-accessory');
        expect(nextPage?.status()).toBe(choice==='digital_only'?404:200);
        if(choice!=='digital_only') await expect(page.getByRole('heading',{name:'MT75 Fresh Accessory'})).toBeVisible();
        const site=await page.request.get('http://127.0.0.1:13000/sitemap.xml');
        expect(site.status(),choice+' sitemap').toBe(200);
        const xml=await site.text();
        const robot=await page.request.get('http://127.0.0.1:13000/robots.txt');
        expect(robot.status(),choice+' robots').toBe(200);
        const rules=await robot.text();
        for(const privatePath of ['/internal/','/api/','/account','/cart','/checkout','/compare','/reset-password'])
            expect(rules,choice+' '+privatePath).toContain('Disallow: '+privatePath);
        expect(xml).not.toMatch(/\/(?:internal|account|cart|checkout|compare|reset-password)(?:\/|<|$)|mt75-not-published/);
        const publicProduct='/products/mt75-fresh-accessory';
        for (const publicPath of ['/products','/categories','/categories/accessory',publicProduct])
            expect(xml.includes(publicPath),choice+' sitemap '+publicPath).toBe(choice!=='digital_only');
        expect(xml.includes('/services'),choice+' services index').toBe(choice!=='commerce_only');
        if(choice==='digital_only') for(const excluded of ['/products','/categories','/mobiles','/product/'])
            expect(rules).toContain('Disallow: '+excluded);
        if(choice==='commerce_only') for(const excluded of ['/services','/enquiry'])
            expect(rules).toContain('Disallow: '+excluded);
        for(const route of ['/products','/categories/accessory','/compare','/cart','/checkout']) {
            const response=await page.request.get('http://127.0.0.1:13000'+route);
            expect(response.status(),choice+' '+route).toBe(choice==='digital_only'?404:200);
        }
        for(const route of ['/services','/enquiry']) {
            const response=await page.request.get('http://127.0.0.1:13000'+route);
            expect(response.status(),choice+' '+route).toBe(choice==='commerce_only'?404:200);
        }
        if(choice!=='digital_only') {
            const productHtml=await page.content();
            expect(productHtml).toContain('/products/mt75-fresh-accessory');
            expect(productHtml.toLowerCase()).not.toMatch(/purchase_price|customer_cnic|customer_phone|imei|object_key/);
        }
    }
});

test('MT75 fresh customer registers, validates own cart and cancels COD checkout', async ({ page, browser }) => {
    test.setTimeout(105_000);
    await page.goto('http://127.0.0.1:13000/products/mt75-fresh-accessory');
    await expect(page.getByRole('heading', {name:'MT75 Fresh Accessory'})).toBeVisible();
    await page.getByRole('button', {name:'Add to cart'}).click();
    await expect(page.getByText('Added to cart.', {exact:true})).toBeVisible();
    await page.goto('http://127.0.0.1:13000/cart');
    const guestQuote = page.waitForResponse(r => r.url().endsWith('/api/customer/cart/quote'));
    await page.getByRole('button', {name:'Validate cart'}).click();
    expect((await guestQuote).status()).toBe(401);
    await page.goto('http://127.0.0.1:13000/account');
    await page.getByRole('button', {name:'Create account'}).first().click();
    const form = page.locator('form');
    await form.getByPlaceholder('Name').fill('MT75 Website Buyer');
    await form.getByPlaceholder('03XXXXXXXXX').fill('03007500055');
    await form.getByLabel('Email').fill('mt75-new-customer@example.invalid');
    await form.getByLabel('Password', {exact:true}).fill('SyntheticBuyerStrong123!');
    await form.getByPlaceholder('Confirm password').fill('SyntheticBuyerStrong123!');
    const registration = page.waitForResponse(r => r.url().endsWith('/api/customer/auth/register') && r.request().method()==='POST');
    await form.getByRole('button', {name:'Create account'}).click();
    expect((await registration).status()).toBe(201);
    await expect(page.getByRole('heading', {name:'MT75 Website Buyer'})).toBeVisible();
    await page.goto('http://127.0.0.1:13000/cart');
    const quoteReady=page.waitForResponse(r => r.url().endsWith('/api/customer/cart/quote') && r.request().method()==='POST');
    await page.getByRole('button',{name:'Validate cart'}).click();
    const quoteResponse=await quoteReady;
    expect(quoteResponse.status()).toBe(200);
    const quote=await quoteResponse.json() as {data:{subtotal:string}};
    expect(quote.data.subtotal).toBe('150.00');
    await expect(page.getByText(/Subtotal: PKR 150\.00/)).toBeVisible();
    await page.goto('http://127.0.0.1:13000/checkout');
    await expect(page.getByRole('radio')).toHaveCount(4);
    await expect(page.getByRole('radio',{name:/Cash on Delivery/})).toBeEnabled();
    for(const channel of [/JazzCash/,/Easypaisa/,/Credit \/ Debit Card/]) {
        await expect(page.getByRole('radio',{name:channel})).toBeDisabled();
    }
    await page.getByLabel('City').fill('Karachi');
    await page.getByLabel('Delivery address').fill('Synthetic MT75-only delivery address');
    const orderReady=page.waitForResponse(r => r.url().endsWith('/api/customer/orders') && r.request().method()==='POST');
    await page.getByRole('button',{name:'Place order'}).click();
    const orderResponse=await orderReady;
    expect(orderResponse.status(),await orderResponse.text()).toBe(201);
    const order=await orderResponse.json() as {data:{order_id:string;payment_status:string;amount:string}};
    expect(order.data).toMatchObject({payment_status:'pending_collection',amount:'150.00'});
    await expect(page).toHaveURL('http://127.0.0.1:13000/account/orders/'+order.data.order_id);
    await expect(page.getByRole('heading',{name:'Order status'})).toBeVisible();
    // Reuse the exact signed customer session, CSRF and completed checkout key.
    const csrfResponse=await page.request.get('http://127.0.0.1:13000/api/customer/auth/csrf-cookie');
    expect(csrfResponse.status()).toBe(200);
    const csrf=(await csrfResponse.json() as {data:{csrf_token:string}}).data.csrf_token;
    const checkoutKey=orderResponse.request().headers()['idempotency-key'];
    expect(checkoutKey).toBeTruthy();
    const originalBody=orderResponse.request().postDataJSON() as Record<string,unknown>;
    const replay=await page.request.post('http://127.0.0.1:13000/api/customer/orders',{
        headers:{'X-CSRF-TOKEN':csrf,'Idempotency-Key':checkoutKey,Accept:'application/json'},
        data:originalBody,
    });
    expect(replay.status(),await replay.text()).toBe(201);
    expect((await replay.json() as {data:{order_id:string}}).data.order_id).toBe(order.data.order_id);
    const lines=originalBody.lines as Array<{product_id:string;quantity:number}>;
    expect(lines).toHaveLength(1);
    const overflow={...originalBody,lines:[{product_id:lines[0].product_id,quantity:4}]};
    const oversell=await page.request.post('http://127.0.0.1:13000/api/customer/orders',{
        headers:{'X-CSRF-TOKEN':csrf,'Idempotency-Key':crypto.randomUUID(),Accept:'application/json'},
        data:overflow,
    });
    expect([409,422]).toContain(oversell.status());
    const ownOrders=await page.request.get('http://127.0.0.1:13000/api/customer/orders');
    expect(ownOrders.status()).toBe(200);
    const orderRows=await ownOrders.json() as {data:{items:Array<{id:string}>}};
    expect(orderRows.data.items.filter(row=>row.id===order.data.order_id)).toHaveLength(1);
    const stranger=await browser.newContext();
    try {
        const denied=await stranger.request.get('http://127.0.0.1:13000/api/customer/orders/'+order.data.order_id);
        expect(denied.status()).toBe(401);
    } finally { await stranger.close(); }
    const cancelReady=page.waitForResponse(r => r.url().endsWith('/cancel') && r.request().method()==='POST');
    await page.getByRole('button',{name:'Cancel order'}).click();
    expect((await cancelReady).status()).toBe(200);
    await expect(page.getByText(/cancelled.*unpaid/i)).toBeVisible();
    const inventory=await page.request.get('http://127.0.0.1:18080/api/v1/catalogue/products/mt75-fresh-accessory');
    expect(inventory.status()).toBe(200);
    const fresh=await inventory.json() as {data:{availability:{quantity:number}}};
    expect(fresh.data.availability.quantity).toBe(3);
    await page.goto('http://127.0.0.1:13000/cart');
    await expect(page.getByText('Your cart is empty.',{exact:true})).toBeVisible();
});


test('MT75 public Next.js stock reaches zero under COD hold and returns after cancellation', async ({ page }) => {
    test.setTimeout(105_000);
    await page.goto('http://127.0.0.1:13000/account');
    await page.getByLabel('Email').fill('mt75-new-customer@example.invalid');
    await page.getByLabel('Password').fill('SyntheticBuyerStrong123!');
    const login=page.waitForResponse(r=>r.url().endsWith('/api/customer/auth/login')&&r.request().method()==='POST');
    await page.locator('form').getByRole('button',{name:'Sign in',exact:true}).click();
    expect((await login).status()).toBe(200);
    await expect(page.getByRole('heading',{name:'MT75 Website Buyer'})).toBeVisible();
    const productPage=await page.goto('http://127.0.0.1:13000/products/mt75-fresh-accessory');
    expect(productPage?.status()).toBe(200);
    await page.getByRole('button',{name:'Add to cart'}).click();
    await expect(page.getByText('Added to cart.',{exact:true})).toBeVisible();
    await page.goto('http://127.0.0.1:13000/cart');
    await page.getByRole('button',{name:'+'}).click();
    await page.getByRole('button',{name:'+'}).click();
    const quoteReady=page.waitForResponse(r=>r.url().endsWith('/api/customer/cart/quote')&&r.request().method()==='POST');
    await page.getByRole('button',{name:'Validate cart'}).click();
    expect((await quoteReady).status()).toBe(200);
    await expect(page.getByText(/Subtotal: PKR 450\.00/)).toBeVisible();
    await page.goto('http://127.0.0.1:13000/checkout');
    await expect(page.getByRole('radio',{name:/Cash on Delivery/})).toBeEnabled();
    await page.getByLabel('City').fill('Karachi');
    await page.getByLabel('Delivery address').fill('Synthetic MT75 full-stock hold address');
    const created=page.waitForResponse(r=>r.url().endsWith('/api/customer/orders')&&r.request().method()==='POST');
    await page.getByRole('button',{name:'Place order'}).click();
    const response=await created;
    expect(response.status(),await response.text()).toBe(201);
    const order=await response.json() as {data:{order_id:string;amount:string;payment_status:string}};
    expect(order.data).toMatchObject({amount:'450.00',payment_status:'pending_collection'});
    const api='http://127.0.0.1:18080/api/v1/catalogue/products/mt75-fresh-accessory';
    const soldOut=await page.request.get(api);
    expect(soldOut.status()).toBe(200);
    expect((await soldOut.json() as {data:{availability:{quantity:number;in_stock:boolean}}}).data.availability)
        .toMatchObject({quantity:0,in_stock:false});
    // W02 catalogue page retains sold-out listings while the COD hold is active.
    const catalogueUrl='http://127.0.0.1:13000/products?category=accessory&sort=price_asc';
    const heldIndex=await page.goto(catalogueUrl);
    expect(heldIndex?.status()).toBe(200);
    const cards=page.locator('main article');
    await expect(cards).toHaveCount(2);
    await expect(cards.nth(0)).toContainText('MT75 Fresh Budget Accessory');
    await expect(cards.nth(0)).toContainText('Out of stock');
    await expect(cards.nth(1)).toContainText('MT75 Fresh Accessory');
    await expect(cards.nth(1)).toContainText('Out of stock');
    const onlyStocked=await page.goto(catalogueUrl+'&availability=in_stock');
    expect(onlyStocked?.status()).toBe(200);
    await expect(page.getByRole('combobox',{name:'Stock availability'})).toHaveValue('in_stock');
    await expect(page.locator('main article')).toHaveCount(0);
    const onlySoldOut=await page.goto(catalogueUrl+'&availability=out_of_stock');
    expect(onlySoldOut?.status()).toBe(200);
    await expect(page.locator('main article')).toHaveCount(2);
    await expect(page.locator('main article')).toContainText(['MT75 Fresh Budget Accessory','MT75 Fresh Accessory']);
    const pagingUrl='http://127.0.0.1:18080/api/v1/catalogue/products?category=accessory&sort=price_asc&limit=1';
    const firstPage=await page.request.get(pagingUrl);
    expect(firstPage.status()).toBe(200);
    const firstRow=await firstPage.json() as {data:{items:Array<{slug:string}>;page:{next_cursor:string;has_more:boolean}}};
    expect(firstRow.data.items[0].slug).toBe('mt75-fresh-budget-accessory');
    expect(firstRow.data.page.has_more).toBe(true);
    const secondPage=await page.request.get(pagingUrl+'&after='+encodeURIComponent(firstRow.data.page.next_cursor));
    expect(secondPage.status()).toBe(200);
    const secondRow=await secondPage.json() as {data:{items:Array<{slug:string;availability:{quantity:number}}>}};
    expect(secondRow.data.items[0]).toMatchObject({slug:'mt75-fresh-accessory',availability:{quantity:0}});
    const unavailable=await page.goto('http://127.0.0.1:13000/products/mt75-fresh-accessory');
    expect(unavailable?.status()).toBe(200);
    await expect(page.getByRole('button',{name:'Out of stock'})).toBeDisabled();
    await expect(page.locator('main').getByText('Out of stock').first()).toBeVisible();
    await page.goto('http://127.0.0.1:13000/account/orders/'+order.data.order_id);
    const cancelled=page.waitForResponse(r=>r.url().endsWith('/cancel')&&r.request().method()==='POST');
    await page.getByRole('button',{name:'Cancel order'}).click();
    expect((await cancelled).status()).toBe(200);
    const restocked=await page.request.get(api);
    expect(restocked.status()).toBe(200);
    expect((await restocked.json() as {data:{availability:{quantity:number;in_stock:boolean}}}).data.availability)
        .toMatchObject({quantity:3,in_stock:true});
    const restockedIndex=await page.goto(catalogueUrl);
    expect(restockedIndex?.status()).toBe(200);
    await expect(page.locator('main article')).toHaveCount(2);
    await expect(page.locator('main article').nth(0)).toContainText('Out of stock');
    await expect(page.locator('main article').nth(1)).toContainText('MT75 Fresh Accessory');
    await expect(page.locator('main article').nth(1)).toContainText('3 in stock');
    const releasedSecondPage=await page.request.get(pagingUrl+'&after='+encodeURIComponent(firstRow.data.page.next_cursor));
    expect(releasedSecondPage.status()).toBe(200);
    expect((await releasedSecondPage.json() as {data:{items:Array<{slug:string;availability:{quantity:number}}>}}).data.items[0])
        .toMatchObject({slug:'mt75-fresh-accessory',availability:{quantity:3}});
    const restockedFilter=await page.goto(catalogueUrl+'&availability=in_stock');
    expect(restockedFilter?.status()).toBe(200);
    await expect(page.locator('main article')).toHaveCount(1);
    await expect(page.locator('main article')).toContainText('MT75 Fresh Accessory');
    const stillSoldOut=await page.goto(catalogueUrl+'&availability=out_of_stock');
    expect(stillSoldOut?.status()).toBe(200);
    await expect(page.locator('main article')).toHaveCount(1);
    await expect(page.locator('main article')).toContainText('MT75 Fresh Budget Accessory');
    const freshPage=await page.goto('http://127.0.0.1:13000/products/mt75-fresh-accessory');
    expect(freshPage?.status()).toBe(200);
    await expect(page.locator('main').getByText('3 available').first()).toBeVisible();
    await expect(page.getByRole('button',{name:'Add to cart'})).toBeEnabled();
});
