import {execFileSync} from 'node:child_process';
import {expect,test} from '@playwright/test';

function mode(value:'hybrid'|'commerce_only'|'digital_only') {
  execFileSync('php',['artisan','db:seed','--class=Database\\Seeders\\WebsiteStorefrontE2eStateSeeder','--env=testing','--force'],
    {cwd:process.cwd(),stdio:'inherit',env:{...process.env,WEBSITE_E2E_MODE:value,WEBSITE_E2E_ACTION:''}});
}

test('H01 typed catalogue draft, real responsive Next publication, mode denial and rollback',async({page,context})=>{
  test.setTimeout(240_000);
  await page.setViewportSize({width:390,height:844});
  await page.goto('/products');
  const grid=page.getByTestId('catalogue-grid');
  await expect(grid).toHaveAttribute('data-desktop','3');
  await expect(page.getByText('MT51 Zero Stock',{exact:true})).toBeVisible();
  await expect(grid.getByText('8 GB RAM',{exact:true})).toBeVisible();
  await expect(grid.getByText('128 GB storage',{exact:true})).toBeVisible();
  await expect(grid.getByText('H01 browser warranty',{exact:true})).toBeVisible();
  const admin=await context.newPage();
  try {
    await admin.goto('http://127.0.0.1:18080/internal/admin/pos/login');
    await admin.getByTestId('login-email').fill('e2e-platform@example.invalid');
    await admin.getByTestId('login-password').fill('SyntheticPass123!');
    await admin.getByTestId('login-submit').click();
    await admin.waitForURL('**/internal/admin/pos');
    await admin.goto('http://127.0.0.1:18080/internal/admin/platform');
    await admin.getByRole('button',{name:'Website',exact:true}).click();
    const editor=admin.getByRole('region',{name:'Website catalogue presentation editor'});
    await expect(editor).toBeVisible();
    await expect(editor.getByRole('checkbox',{name:'Catalogue show_out_of_stock'})).toBeDisabled();
    await expect(editor.locator('aside[aria-label="Private catalogue preview"]')).toBeVisible();
    const publishSection=admin.getByRole('heading',{name:'Website presentation, branding, theme, navigation & SEO'})
      .locator('xpath=ancestor::section[1]');
    const save=async()=>{
      const response=admin.waitForResponse(r=>r.url().endsWith('/internal/admin/platform/presentation/draft')&&r.request().method()==='POST');
      await editor.getByRole('button',{name:'Save private catalogue draft'}).click();
      const result=await response;expect(result.status()).toBe(200);
      return (await result.json()).data.id as number;
    };
    const publish=async(id:number)=>{
      const response=admin.waitForResponse(r=>r.url().endsWith(`/internal/admin/platform/presentation/${id}/publish`)&&r.request().method()==='POST');
      await publishSection.getByTestId(`presentation-revision-${id}`).getByRole('button',{name:'Publish',exact:true}).click();
      expect((await response).status()).toBe(200);
    };
    const firstId=await save();
    await publish(firstId);
    await editor.getByRole('combobox',{name:'Catalogue grid_desktop'}).selectOption('6');
    await editor.getByRole('combobox',{name:'Catalogue grid_tablet'}).selectOption('4');
    await editor.getByRole('combobox',{name:'Catalogue grid_mobile'}).selectOption('2');
    await editor.getByRole('combobox',{name:'Catalogue image_ratio'}).selectOption('1:1');
    await editor.getByRole('combobox',{name:'Catalogue items_per_page'}).selectOption('48');
    await editor.getByRole('combobox',{name:'Catalogue card_density'}).selectOption('compact');
    await editor.getByRole('combobox',{name:'Catalogue default_sort'}).selectOption('newest');
    await editor.getByRole('combobox',{name:'Catalogue badge_behavior'}).selectOption('status_text');
    await editor.getByRole('checkbox',{name:'Catalogue show_brand'}).uncheck();
    await editor.getByRole('checkbox',{name:'Catalogue show_specs'}).uncheck();
    await editor.getByRole('checkbox',{name:'Catalogue show_compare'}).uncheck();
    await expect(editor.locator('aside[aria-label="Private catalogue preview"]')).toHaveAttribute('data-mobile','2');
    const secondId=await save();
    await page.goto('/products');
    await expect(grid).toHaveAttribute('data-desktop','3');
    await publish(secondId);
    await page.goto('/products');
    await expect(grid).toHaveAttribute('data-desktop','6');
    await expect(grid).toHaveAttribute('data-tablet','4');
    await expect(grid).toHaveAttribute('data-mobile','2');
    await expect(grid).toHaveAttribute('data-image-ratio','1:1');
    await expect(grid).toHaveAttribute('data-page-size','48');
    await expect(page.getByText('MT51 Zero Stock',{exact:true})).toBeVisible();
    await expect(grid.getByRole('link',{name:'Compare'})).toHaveCount(0);
    await expect(grid.getByText('8 GB RAM',{exact:true})).toHaveCount(0);
    await expect(grid.getByText('H01 browser warranty',{exact:true})).toBeVisible();
    const columns=async()=>page.evaluate(()=>getComputedStyle(document.querySelector('[data-testid="catalogue-grid"]')!).gridTemplateColumns.split(' ').filter(Boolean).length);
    expect(await columns()).toBe(2);
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth+2)).toBe(true);
    await page.setViewportSize({width:850,height:1000});expect(await columns()).toBe(4);
    await page.setViewportSize({width:1370,height:1100});expect(await columns()).toBe(6);
    mode('commerce_only');
    await page.goto('/products');await expect(grid).toHaveAttribute('data-desktop','6');
    mode('digital_only');
    expect((await page.goto('/products'))?.status()).toBe(404);
    mode('hybrid');
    await page.goto('/products');await expect(grid).toHaveAttribute('data-desktop','6');
    const rolled=admin.waitForResponse(r=>r.url().endsWith(`/internal/admin/platform/presentation/${firstId}/rollback`)&&r.request().method()==='POST');
    await publishSection.getByTestId(`presentation-revision-${firstId}`).getByRole('button',{name:'Rollback',exact:true}).click();
    expect((await rolled).status()).toBe(200);
    await page.goto('/products');await expect(grid).toHaveAttribute('data-desktop','3');
    await expect(grid).toHaveAttribute('data-mobile','1');
  } finally {
    mode('hybrid');
    if(!admin.isClosed()) {
      const result=await admin.goto('http://127.0.0.1:18080/internal/admin/pos');
      if(result?.status()===200){const logout=admin.getByTestId('logout');if(await logout.count()===1){
        const response=admin.waitForResponse(r=>r.url().endsWith('/internal/admin/auth/logout')&&r.request().method()==='POST');
        await logout.click();expect((await response).status()).toBe(200);
      }}
      await admin.close();
    }
  }
});
