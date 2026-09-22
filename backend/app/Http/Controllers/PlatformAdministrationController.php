<?php

namespace App\Http\Controllers;

use App\Business\BusinessProfile;
use App\Cms\WebsiteCms;
use App\Cms\WebsiteModePublication;
use App\Documents\CanonicalDocuments;
use App\Identity\Access;
use App\Identity\TeamMemberAdministration;
use App\Integrations\IntegrationManager;
use App\Loyalty\LoyaltyServices;
use App\Models\Admin;
use App\Models\Outlet;
use App\Payments\PosPaymentOperations;
use App\Pos\PosConfiguration;
use App\Promotions\PromotionServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

final class PlatformAdministrationController extends Controller
{
    private const TEMPLATE_KEYS = [
        'invoice_whatsapp', 'warranty_whatsapp', 'invoice_email_subject',
        'invoice_email_body', 'warranty_email_subject', 'warranty_email_body',
    ];

    public function page()
    {
        $actor = $this->actor();
        abort_unless($this->canEnter($actor), 403);

        return Inertia::render('platform-admin', [
            'identity' => ['name' => $actor->name, 'job_title' => $actor->job_title],
            'can_manage_payments' => app(Access::class)->allows($actor, 'website.payments.manage'),
        ]);
    }
