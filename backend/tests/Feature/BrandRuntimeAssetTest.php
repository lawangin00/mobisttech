<?php

namespace Tests\Feature;

use Tests\TestCase;

final class BrandRuntimeAssetTest extends TestCase
{
    public function test_canonical_master_artwork_matches_approved_source_hashes(): void
    {
        $root = dirname(base_path());
        $expected = [
            'Canva/Logo_mobiST.svg' => 'ab19de501dca602b8df93335fe760a45890c0458711750434202953a2ec2c999',
            'Canva/Wordmark_mobiST.svg' => '683b8b412ecc160c0202eaeafa1594966d9b15f2de13b66633b22959426e2515',
            'Logo/Logo_mobiST_Master.png' => '97508b395193a9e3d26a09eca048a0dcf9595bba8c04b41345098bdce9a3c295',
            'Logo/Logo_mobiST_Master_Editable.svg' => '0864ad8406632db34bf9e4a7ddce64f6981c03001e3da9154dd45f82b6688227',
            'Logo/Logo_mobiST_Square.png' => '4bc5066b123059b3c4576b902846d992600ddacb0cef66f52a624172ac3544db',
            'Wordmark/Wordmark_mobiST_Editable.svg' => '2e8f80c03110b00a756d00a4c17855b2e24d88c43639e5bae67105bf1b59659f',
            'Wordmark/Wordmark_mobiST_Master.png' => 'c02ef9d3c60c504d6d3f7fda6a36ae91ab6312635edfcd71a076b273527488cf',
            'Wordmark/Wordmark_mobiST_Square.png' => '588ce5a5826c2b999ab0a843cc0a0b47e7d3475b001d1401879883e8de8b9b0c',
        ];

        foreach ($expected as $relative => $hash) {
            $path = $root.DIRECTORY_SEPARATOR.'brand'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $this->assertFileExists($path);
            $this->assertSame($hash, hash_file('sha256', $path), $relative);
        }
    }

    public function test_runtime_derivatives_and_brand_references_are_canonical(): void
    {
        $root = dirname(base_path());
        $wordmark = file_get_contents($root.'/brand/Wordmark/Wordmark_mobiST_Editable.svg');
        $mark = file_get_contents($root.'/brand/Logo/Logo_mobiST_Master_Editable.svg');

        foreach (['backend', 'website'] as $target) {
            $public = $root.'/'.$target.'/public';
            $this->assertSame($wordmark, file_get_contents($public.'/brand/mobist-wordmark.svg'));
            $this->assertSame($mark, file_get_contents($public.'/brand/mobist-mark.svg'));
            foreach ([
                '/brand/mobist-wordmark-print.png',
                '/brand/mobist-mark-watermark.png',
                '/brand/mobist-icon-512.png',
                '/favicon.ico',
                '/apple-touch-icon.png',
                '/icon-192.png',
                '/icon-512.png',
            ] as $asset) {
                $this->assertFileExists($public.$asset);
                $this->assertGreaterThan(1000, filesize($public.$asset), $target.$asset);
            }
        }

        $backendCss = file_get_contents(base_path('resources/css/app.css'));
        $websiteCss = file_get_contents($root.'/website/src/app/globals.css');
        $tokens = file_get_contents($root.'/brand/design-tokens.css');
        $this->assertStringContainsString("@import './brand-tokens.css'", $backendCss);
        $this->assertStringContainsString('@import "./brand-tokens.css"', $websiteCss);
        $this->assertStringContainsString("'Instrument Sans'", $tokens);

        $runtimeText = file_get_contents(base_path('resources/js/pages/pos-shell.tsx'))
            .file_get_contents(base_path('resources/js/pages/pos-login.tsx'))
            .file_get_contents($root.'/website/src/components/site-shell.tsx');
        $this->assertStringContainsString('/brand/mobist-wordmark.svg', $runtimeText);
        $this->assertStringContainsString('/brand/mobist-mark-watermark.png', $runtimeText);
        $this->assertStringNotContainsString('Brandkit - mobiST', $runtimeText);
        $this->assertStringNotContainsString('/Canva/', $runtimeText);
    }
}
