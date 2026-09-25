import {defineConfig} from '@playwright/test';
export default defineConfig({
  testDir:'./tests/browser-website',testMatch:'h01-catalogue-presentation.spec.ts',workers:1,retries:0,reporter:[['line']],
  outputDir:'./storage/framework/testing/playwright-h01-catalogue',
  use:{baseURL:'http://127.0.0.1:13000',channel:'msedge',trace:'retain-on-failure',screenshot:'only-on-failure'},
  webServer:{command:'npm --prefix ../website run start',url:'http://127.0.0.1:13000/',reuseExistingServer:false,timeout:60000,
    env:{...process.env,WEBSITE_API_TIMEOUT_MS:'12000'}},
});
