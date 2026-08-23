<?php

declare(strict_types=1);

namespace App\Livewire\PublicQr;

use App\Actions\Menus\GetGuestMenuForBranchAction;
use App\Enums\OrganizationSubscriptionStatus;
use App\Enums\QrCodeStatus;
use App\Enums\SupportedLocale;
use App\Models\QrCode;
use App\Models\ServicePoint;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.guest')]
class Show extends Component
{
    private GetGuestMenuForBranchAction $getGuestMenuForBranch;

    #[Locked]
    public string $token = '';

    public string $state = 'not_found';

    public string $title = '';

    public string $message = '';

    #[Locked]
    public int $branchId = 0;

    public string $shortCode = '';

    #[Url(as: 'lang')]
    public string $language = '';

    /**
     * @var array<string, string>
     */
    public array $languageOptions = [];

    public function boot(GetGuestMenuForBranchAction $getGuestMenuForBranch): void
    {
        $this->getGuestMenuForBranch = $getGuestMenuForBranch;
    }

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->languageOptions = SupportedLocale::labels();
        $requestedLanguage = request()->query('lang');
        $hasRequestedLanguage = is_string($requestedLanguage) && SupportedLocale::isSupported($requestedLanguage);
        $this->language = $hasRequestedLanguage
            ? SupportedLocale::normalize($requestedLanguage)
            : SupportedLocale::normalize(null, App::currentLocale());
        $this->applyGuestLocale();

        $qrCode = $this->findQrCode($token);

        if (! $qrCode instanceof QrCode) {
            $this->showError('not_found', __('qr.errors.not_found.title'), __('qr.errors.not_found.description'));

            return;
        }

        if ($qrCode->status === QrCodeStatus::Disabled) {
            $this->showError('disabled', __('qr.errors.disabled.title'), __('qr.errors.disabled.description'));

            return;
        }

        if ($qrCode->status === QrCodeStatus::Revoked) {
            $this->showError('revoked', __('qr.errors.revoked.title'), __('qr.errors.revoked.description'));

            return;
        }

        $servicePoint = $qrCode->servicePoint;

        if (! $servicePoint instanceof ServicePoint || ! $servicePoint->is_active) {
            $this->showError(
                'inactive_service_point',
                __('qr.errors.service_point_unavailable.title'),
                __('qr.errors.service_point_unavailable.description'),
            );

            return;
        }

        $branch = $servicePoint->branch;

        if ($branch->organization->subscription?->status === OrganizationSubscriptionStatus::Inactive) {
            $this->showError(
                'restaurant_unavailable',
                __('guest.table.restaurant_unavailable_title'),
                __('guest.table.restaurant_unavailable_message'),
            );

            return;
        }

        $this->branchId = $branch->id;
        $this->language = $this->getGuestMenuForBranch->resolveLanguageForBranch(
            $branch->id,
            $hasRequestedLanguage ? $this->language : null,
        );
        $this->applyGuestLocale();
        $this->state = 'ready';
        $this->title = $branch->publicDisplayName();
        $this->message = __('guest.table.enter_name');
        $this->shortCode = $qrCode->short_code;
    }

    public function updatedLanguage(): void
    {
        $this->language = $this->branchId > 0
            ? $this->getGuestMenuForBranch->resolveLanguageForBranch($this->branchId, $this->language)
            : SupportedLocale::normalize($this->language);

        $this->applyGuestLocale();
        $this->message = __('guest.table.enter_name');
    }

    public function render(): View
    {
        $this->applyGuestLocale();

        return view('livewire.public-qr.show', [
            'pageErrorCard' => $this->pageErrorCard(),
        ])->title($this->state === 'ready' ? $this->title : __('qr.labels.guest_page_title'));
    }

    private function findQrCode(string $token): ?QrCode
    {
        return QrCode::query()
            ->select([
                'id',
                'service_point_id',
                'public_token',
                'short_code',
                'status',
            ])
            ->with([
                'servicePoint' => fn ($query) => $query
                    ->select(['id', 'branch_id', 'is_active'])
                    ->with([
                        'branch' => fn ($branchQuery) => $branchQuery
                            ->select(['id', 'organization_id', 'name', 'public_name'])
                            ->with([
                                'organization' => fn ($organizationQuery) => $organizationQuery
                                    ->select(['id'])
                                    ->with([
                                        'subscription' => fn ($subscriptionQuery) => $subscriptionQuery
                                            ->select(['id', 'organization_id', 'status']),
                                    ]),
                            ]),
                    ]),
            ])
            ->where('public_token', $token)
            ->first();
    }

    private function showError(string $state, string $title, string $message): void
    {
        $this->state = $state;
        $this->title = $title;
        $this->message = $message;
    }

    /**
     * @return array{visible: bool, state: string, tone: string, kicker: string, title: string, message: string, support_text: string, primary_label: string|null, primary_url: string|null, secondary_label: string|null, secondary_url: string|null}
     */
    private function pageErrorCard(): array
    {
        if ($this->state === 'ready') {
            return [
                'visible' => false,
                'state' => '',
                'tone' => 'zinc',
                'kicker' => '',
                'title' => '',
                'message' => '',
                'support_text' => '',
                'primary_label' => null,
                'primary_url' => null,
                'secondary_label' => null,
                'secondary_url' => null,
            ];
        }

        $state = $this->state !== '' ? $this->state : 'not_found';

        return [
            'visible' => true,
            'state' => $state,
            'tone' => $this->toneForErrorState($state),
            'kicker' => $this->kickerForPageErrorState($state),
            'title' => $this->title,
            'message' => $this->message,
            'support_text' => $this->supportTextForPageErrorState($state),
            'primary_label' => $state === 'not_found' ? __('guest.table.open_start_page') : __('guest.table.try_again'),
            'primary_url' => $state === 'not_found' ? route('guest.home') : $this->currentPublicQrUrl(),
            'secondary_label' => null,
            'secondary_url' => null,
        ];
    }

    private function toneForErrorState(string $state): string
    {
        return match ($state) {
            'disabled',
            'inactive_service_point',
            'restaurant_unavailable' => 'amber',
            'not_found',
            'revoked' => 'rose',
            default => 'zinc',
        };
    }

    private function kickerForPageErrorState(string $state): string
    {
        return match ($state) {
            'not_found' => __('qr.labels.access'),
            'disabled',
            'revoked' => __('qr.labels.access_paused'),
            'inactive_service_point' => __('guest.table.place_unavailable'),
            'restaurant_unavailable' => __('guest.table.restaurant_unavailable'),
            default => __('guest.table.guest_access'),
        };
    }

    private function supportTextForPageErrorState(string $state): string
    {
        return match ($state) {
            'not_found',
            'revoked' => __('qr.support.current_code'),
            'disabled',
            'inactive_service_point',
            'restaurant_unavailable' => __('qr.support.reopen_access'),
            default => __('qr.support.help'),
        };
    }

    private function currentPublicQrUrl(): string
    {
        return route('public.qr.show', [
            'token' => $this->token,
            'lang' => $this->language,
        ]);
    }

    private function applyGuestLocale(): void
    {
        $this->language = SupportedLocale::normalize($this->language, App::currentLocale());
        App::setLocale($this->language);
        session()->put('interface_locale', $this->language);
    }
}
