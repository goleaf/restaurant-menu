<section
    data-page="restaurant-onboarding"
    class="mx-auto flex w-full min-w-0 max-w-content flex-1 flex-col gap-6 wrap-anywhere text-text-primary"
    x-on:onboarding-step-changed.window="$nextTick(() => $el.querySelector('[data-onboarding-step-heading]')?.focus())"
    x-on:onboarding-validation-failed.window="$nextTick(() => ($el.querySelector('[aria-invalid=true]') ?? document.getElementById('onboarding-validation-summary'))?.focus())"
>
    <header class="min-w-0 border-b border-border-subtle pb-6">
        <p class="text-sm font-semibold text-brand-700 dark:text-brand-300">
            {{ __('ui.onboarding.restaurant_setup.bystryi_start') }}
        </p>
        <h1 class="mt-1 text-3xl font-semibold tracking-tight text-text-primary">
            {{ __('ui.onboarding.restaurant_setup.nastroit_restoran') }}
        </h1>
        <p class="mt-2 max-w-reading text-sm leading-6 text-text-muted">
            {{ __('ui.onboarding.restaurant_setup.proidite_prostye_sagi_nazvanie_adres_pervyi') }}
        </p>

        <div class="mt-5 lg:hidden">
            <div class="mb-2 flex min-w-0 items-start justify-between gap-3 text-sm">
                <p class="font-medium text-text-primary">
                    {{ __('ui.onboarding.restaurant_setup.progress', ['current' => $step, 'total' => 8]) }}
                </p>
                <p class="min-w-0 wrap-anywhere text-right text-text-muted">{{ $this->steps[$step - 1]['label'] }}</p>
            </div>
            <progress
                class="h-2 w-full overflow-hidden rounded-full accent-brand-600"
                value="{{ $step }}"
                max="8"
                aria-label="{{ __('ui.onboarding.restaurant_setup.progress_accessible', ['current' => $step, 'total' => 8]) }}"
            >
                {{ __('ui.onboarding.restaurant_setup.progress', ['current' => $step, 'total' => 8]) }}
            </progress>

            @if ($this->setup['highest_step'] > 1)
                <details data-onboarding-mobile-summary class="group mt-4 min-w-0 border-t border-border-subtle pt-2">
                    <summary class="flex min-h-touch cursor-pointer list-none items-center justify-between gap-3 rounded-control px-2 text-sm font-semibold text-text-primary outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2 focus-visible:ring-offset-canvas">
                        <span class="min-w-0 wrap-anywhere">{{ __('ui.onboarding.restaurant_setup.cto_uze_sozdano') }}</span>
                        <flux:icon.chevron-down class="size-4 shrink-0 text-text-muted transition-transform duration-state group-open:rotate-180 motion-reduce:transition-none" />
                    </summary>
                    <x-onboarding.setup-summary :summary="$this->summary" class="px-2 pb-2 pt-3" />
                </details>
            @endif
        </div>
    </header>

    <div class="grid min-w-0 gap-6 lg:grid-cols-[15rem_minmax(0,1fr)] xl:gap-8">
        <aside class="hidden min-w-0 lg:block">
            <nav aria-label="{{ __('ui.onboarding.restaurant_setup.steps_navigation') }}">
                <ol class="grid gap-1.5">
                    @foreach ($this->steps as $wizardStep)
                        <li wire:key="onboarding-step-{{ $wizardStep['number'] }}">
                            <button
                                type="button"
                                wire:click="goToStep({{ $wizardStep['number'] }})"
                                wire:offline.attr="disabled"
                                @disabled(! $wizardStep['is_available'])
                                @if ($wizardStep['is_current']) aria-current="step" @endif
                                @class([
                                    'group flex min-h-touch w-full min-w-0 items-center gap-3 rounded-control border px-3 py-2 text-left outline-none transition duration-state focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2 focus-visible:ring-offset-canvas motion-reduce:transition-none disabled:cursor-not-allowed disabled:opacity-50',
                                    'border-brand-200 bg-surface-selected text-text-primary' => $wizardStep['is_current'],
                                    'border-transparent bg-transparent text-text-muted hover:border-border-subtle hover:bg-surface-muted hover:text-text-primary' => ! $wizardStep['is_current'],
                                ])
                            >
                                <span
                                    @class([
                                        'grid size-8 shrink-0 place-items-center rounded-full border text-xs font-semibold',
                                        'border-success-border bg-success-surface text-success' => $wizardStep['is_done'],
                                        'border-brand-300 bg-brand-100 text-brand-800 dark:bg-brand-900 dark:text-brand-200' => $wizardStep['is_current'] && ! $wizardStep['is_done'],
                                        'border-border-subtle bg-surface text-text-muted' => ! $wizardStep['is_current'] && ! $wizardStep['is_done'],
                                    ])
                                >
                                    @if ($wizardStep['is_done'])
                                        <flux:icon.check class="size-4" />
                                    @else
                                        {{ $wizardStep['number'] }}
                                    @endif
                                </span>

                                <span class="min-w-0">
                                    <span class="block wrap-anywhere text-sm font-medium leading-5">{{ $wizardStep['label'] }}</span>
                                    <span class="block text-xs leading-4 text-text-muted">
                                        {{ $wizardStep['is_done'] ? __('ui.departments.dashboard.gotovo') : ($wizardStep['is_current'] ? __('ui.onboarding.restaurant_setup.seicas') : __('ui.onboarding.restaurant_setup.pozze')) }}
                                    </span>
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ol>
            </nav>

            <section aria-labelledby="created-context-heading" class="mt-6 border-t border-border-subtle pt-5">
                <h2 id="created-context-heading" class="text-sm font-semibold text-text-primary">
                    {{ __('ui.onboarding.restaurant_setup.cto_uze_sozdano') }}
                </h2>
                <x-onboarding.setup-summary :summary="$this->summary" class="mt-3" />
            </section>
        </aside>

        <section aria-labelledby="active-step-heading" class="min-w-0 rounded-card border border-border-subtle bg-surface p-4 shadow-card sm:p-6 lg:p-8">
            @if ($errors->any())
                <flux:callout
                    id="onboarding-validation-summary"
                    class="mb-6"
                    role="alert"
                    tabindex="-1"
                    variant="danger"
                    icon="exclamation-triangle"
                    :heading="__('ui.onboarding.restaurant_setup.validation_heading')"
                    :text="__('ui.onboarding.restaurant_setup.validation_recovery')"
                />
            @endif

            @if ($step === 1)
                <form wire:key="restaurant-onboarding-step-1" wire:submit="createOrganization" wire:loading.attr="aria-busy" wire:target="createOrganization" class="grid min-w-0 grid-cols-[minmax(0,1fr)] gap-6">
                    <div class="max-w-reading">
                        <p class="text-sm font-semibold text-brand-700 dark:text-brand-300">{{ __('ui.onboarding.restaurant_setup.sag_1') }}</p>
                        <flux:heading id="active-step-heading" data-onboarding-step-heading tabindex="-1" class="mt-1 outline-none" size="xl" level="2">{{ __('ui.onboarding.restaurant_setup.kto_vladeet_zavedeniem') }}</flux:heading>
                        <p class="mt-2 text-sm leading-6 text-text-muted">{{ __('ui.onboarding.restaurant_setup.napisite_nazvanie_kompanii_ili_vladelca_vy_a') }}</p>
                    </div>

                    <flux:input
                        wire:model="form.organizationName"
                        name="organization_name"
                        error:name="form.organizationName"
                        error:id="organization-name-error"
                        description:id="organization-name-help"
                        :invalid="$errors->has('form.organizationName')"
                        aria-describedby="organization-name-help organization-name-error"
                        :label="__('ui.onboarding.restaurant_setup.nazvanie_kompanii')"
                        :description="__('ui.onboarding.restaurant_setup.organization_name_help')"
                        :placeholder="__('ui.onboarding.restaurant_setup.organization_name_placeholder')"
                        type="text"
                        required
                        maxlength="120"
                        autocomplete="organization"
                        autocapitalize="words"
                    />

                    <div class="grid min-w-0 gap-3 border-t border-border-subtle pt-5 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                        <p id="onboarding-create-organization-status" class="min-h-5 min-w-0 wrap-anywhere text-center text-sm text-text-muted sm:text-right" role="status" aria-live="polite" aria-atomic="true">
                            <span wire:loading wire:target="createOrganization">{{ __('ui.onboarding.restaurant_setup.saving_step') }}</span>
                        </p>
                        <flux:button class="w-full whitespace-normal sm:w-auto" icon="arrow-right" variant="primary" type="submit" aria-describedby="onboarding-create-organization-status" wire:loading.attr="disabled" wire:offline.attr="disabled" wire:target="createOrganization">
                            {{ __('ui.onboarding.restaurant_setup.dalse') }}
                        </flux:button>
                    </div>
                </form>
            @elseif ($step === 2)
                <form wire:key="restaurant-onboarding-step-2" wire:submit="createBrand" wire:loading.attr="aria-busy" wire:target="createBrand" class="grid min-w-0 grid-cols-[minmax(0,1fr)] gap-6">
                    <div class="max-w-reading">
                        <p class="text-sm font-semibold text-brand-700 dark:text-brand-300">{{ __('ui.onboarding.restaurant_setup.sag_2') }}</p>
                        <flux:heading id="active-step-heading" data-onboarding-step-heading tabindex="-1" class="mt-1 outline-none" size="xl" level="2">{{ __('ui.onboarding.restaurant_setup.kak_nazyvaetsia_restoran') }}</flux:heading>
                        <p class="mt-2 text-sm leading-6 text-text-muted">{{ __('ui.onboarding.restaurant_setup.eto_nazvanie_gosti_i_sotrudniki_budut_uznava') }}</p>
                    </div>

                    <flux:input
                        wire:model="form.brandName"
                        name="brand_name"
                        error:name="form.brandName"
                        error:id="brand-name-error"
                        :invalid="$errors->has('form.brandName')"
                        aria-describedby="brand-name-error"
                        :label="__('ui.onboarding.restaurant_setup.nazvanie_restorana')"
                        :placeholder="__('ui.onboarding.restaurant_setup.brand_name_placeholder')"
                        type="text"
                        required
                        maxlength="120"
                        autocomplete="organization"
                        autocapitalize="words"
                    />

                    <div class="grid min-w-0 gap-3 border-t border-border-subtle pt-5 sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center">
                        <flux:button class="w-full whitespace-normal sm:w-auto" icon="arrow-left" type="button" wire:click="goToStep(1)" wire:offline.attr="disabled">{{ __('ui.onboarding.restaurant_setup.nazad') }}</flux:button>
                        <p id="onboarding-create-brand-status" class="min-h-5 min-w-0 wrap-anywhere text-center text-sm text-text-muted" role="status" aria-live="polite" aria-atomic="true">
                            <span wire:loading wire:target="createBrand">{{ __('ui.onboarding.restaurant_setup.saving_step') }}</span>
                        </p>
                        <flux:button class="w-full whitespace-normal sm:w-auto" icon="arrow-right" variant="primary" type="submit" aria-describedby="onboarding-create-brand-status" wire:loading.attr="disabled" wire:offline.attr="disabled" wire:target="createBrand">
                            {{ __('ui.onboarding.restaurant_setup.dalse') }}
                        </flux:button>
                    </div>
                </form>
            @elseif ($step === 3)
                <form wire:key="restaurant-onboarding-step-3" wire:submit="createBranch" wire:loading.attr="aria-busy" wire:target="createBranch" class="grid min-w-0 grid-cols-[minmax(0,1fr)] gap-6">
                    <div class="max-w-reading">
                        <p class="text-sm font-semibold text-brand-700 dark:text-brand-300">{{ __('ui.onboarding.restaurant_setup.sag_3') }}</p>
                        <flux:heading id="active-step-heading" data-onboarding-step-heading tabindex="-1" class="mt-1 outline-none" size="xl" level="2">{{ __('ui.onboarding.restaurant_setup.gde_naxoditsia_eta_tocka') }}</flux:heading>
                        <p class="mt-2 text-sm leading-6 text-text-muted">{{ __('ui.onboarding.restaurant_setup.ukazite_adres_pervogo_filiala_potom_mozno_do') }}</p>
                    </div>

                    <div class="grid min-w-0 grid-cols-[minmax(0,1fr)] gap-5 md:grid-cols-2">
                        <flux:input wire:model="form.branchName" name="branch_name" error:name="form.branchName" error:id="branch-name-error" :invalid="$errors->has('form.branchName')" aria-describedby="branch-name-error" :label="__('ui.onboarding.restaurant_setup.nazvanie_filiala')" :placeholder="__('ui.onboarding.restaurant_setup.branch_name_placeholder')" type="text" required maxlength="160" autocomplete="off" autocapitalize="words" />
                        <flux:input wire:model="form.branchAddress" name="branch_address" error:name="form.branchAddress" error:id="branch-address-error" :invalid="$errors->has('form.branchAddress')" aria-describedby="branch-address-error" :label="__('ui.onboarding.restaurant_setup.adres_filiala')" :placeholder="__('ui.onboarding.restaurant_setup.branch_address_placeholder')" type="text" required maxlength="255" autocomplete="street-address" />
                        <flux:input wire:model="form.branchCity" name="branch_city" error:name="form.branchCity" error:id="branch-city-error" :invalid="$errors->has('form.branchCity')" aria-describedby="branch-city-error" :label="__('ui.onboarding.restaurant_setup.gorod')" :placeholder="__('ui.onboarding.restaurant_setup.branch_city_placeholder')" type="text" required maxlength="120" autocomplete="address-level2" autocapitalize="words" />
                        <flux:input wire:model="form.branchCountryCode" name="branch_country_code" error:name="form.branchCountryCode" error:id="branch-country-code-error" description:id="branch-country-code-help" :invalid="$errors->has('form.branchCountryCode')" aria-describedby="branch-country-code-help branch-country-code-error" :label="__('ui.onboarding.restaurant_setup.strana')" :description="__('ui.onboarding.restaurant_setup.country_help')" :placeholder="__('ui.onboarding.restaurant_setup.country_placeholder')" type="text" required maxlength="2" pattern="[A-Za-z]{2}" list="restaurant-country-options" autocomplete="country" autocapitalize="characters" spellcheck="false" />
                        <datalist id="restaurant-country-options">
                            @foreach ($this->countryOptions as $countryCode => $countryLabel)
                                <option wire:key="onboarding-country-{{ $countryCode }}" value="{{ $countryCode }}" label="{{ $countryLabel }}"></option>
                            @endforeach
                        </datalist>

                        <flux:input wire:model="form.branchTimezone" name="branch_timezone" error:name="form.branchTimezone" error:id="branch-timezone-error" description:id="branch-timezone-help" :invalid="$errors->has('form.branchTimezone')" aria-describedby="branch-timezone-help branch-timezone-error" :label="__('ui.onboarding.restaurant_setup.casovoi_poias')" :description="__('ui.onboarding.restaurant_setup.timezone_help')" :placeholder="__('ui.onboarding.restaurant_setup.timezone_placeholder')" type="text" required maxlength="64" list="restaurant-timezone-options" autocomplete="off" spellcheck="false" />
                        <datalist id="restaurant-timezone-options">
                            @foreach ($this->timezoneOptions as $timezoneIdentifier => $timezoneLabel)
                                <option wire:key="onboarding-timezone-{{ $timezoneIdentifier }}" value="{{ $timezoneIdentifier }}" label="{{ $timezoneLabel }}"></option>
                            @endforeach
                        </datalist>

                        <flux:select wire:model="form.branchCurrency" name="branch_currency" error:name="form.branchCurrency" error:id="branch-currency-error" description:id="branch-currency-help" :invalid="$errors->has('form.branchCurrency')" aria-describedby="branch-currency-help branch-currency-error" :label="__('ui.onboarding.restaurant_setup.valiuta')" :description="__('ui.onboarding.restaurant_setup.currency_help')" required>
                            <flux:select.option value="">{{ __('ui.onboarding.restaurant_setup.select_currency') }}</flux:select.option>
                            @foreach ($this->currencyOptions as $currencyCode => $currencyLabel)
                                <flux:select.option wire:key="onboarding-branch-currency-{{ $currencyCode }}" value="{{ $currencyCode }}">{{ $currencyLabel }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div class="grid min-w-0 gap-3 border-t border-border-subtle pt-5 sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center">
                        <flux:button class="w-full whitespace-normal sm:w-auto" icon="arrow-left" type="button" wire:click="goToStep(2)" wire:offline.attr="disabled">{{ __('ui.onboarding.restaurant_setup.nazad') }}</flux:button>
                        <p id="onboarding-create-branch-status" class="min-h-5 min-w-0 wrap-anywhere text-center text-sm text-text-muted" role="status" aria-live="polite" aria-atomic="true">
                            <span wire:loading wire:target="createBranch">{{ __('ui.onboarding.restaurant_setup.saving_step') }}</span>
                        </p>
                        <flux:button class="w-full whitespace-normal sm:w-auto" icon="arrow-right" variant="primary" type="submit" aria-describedby="onboarding-create-branch-status" wire:loading.attr="disabled" wire:offline.attr="disabled" wire:target="createBranch">{{ __('ui.onboarding.restaurant_setup.dalse') }}</flux:button>
                    </div>
                </form>
            @elseif ($step === 4)
                <form wire:key="restaurant-onboarding-step-4" wire:submit="createArea" wire:loading.attr="aria-busy" wire:target="createArea" class="grid min-w-0 grid-cols-[minmax(0,1fr)] gap-6">
                    <div class="max-w-reading">
                        <p class="text-sm font-semibold text-brand-700 dark:text-brand-300">{{ __('ui.onboarding.restaurant_setup.sag_4') }}</p>
                        <flux:heading id="active-step-heading" data-onboarding-step-heading tabindex="-1" class="mt-1 outline-none" size="xl" level="2">{{ __('ui.onboarding.restaurant_setup.dobavte_pervyi_zal') }}</flux:heading>
                        <p class="mt-2 text-sm leading-6 text-text-muted">{{ __('ui.onboarding.restaurant_setup.naprimer_glavnyi_zal_terrasa_vip_zal_stoly_p') }}</p>
                    </div>

                    <div class="grid min-w-0 grid-cols-[minmax(0,1fr)] gap-5 md:grid-cols-3">
                        <flux:input wire:model="form.areaName" name="area_name" error:name="form.areaName" error:id="area-name-error" :invalid="$errors->has('form.areaName')" aria-describedby="area-name-error" :label="__('ui.onboarding.restaurant_setup.nazvanie_zony')" :placeholder="__('ui.onboarding.restaurant_setup.area_name_placeholder')" type="text" required maxlength="160" autocomplete="off" autocapitalize="words" />
                        <flux:select wire:model="form.areaType" name="area_type" error:name="form.areaType" error:id="area-type-error" description:id="area-type-help" :invalid="$errors->has('form.areaType')" aria-describedby="area-type-help area-type-error" :label="__('ui.onboarding.restaurant_setup.tip_zony')" :description="__('ui.onboarding.restaurant_setup.area_type_help')" required>
                            @foreach ($this->areaTypeOptions as $areaType => $areaTypeLabel)
                                <flux:select.option wire:key="onboarding-area-type-{{ $areaType }}" value="{{ $areaType }}">{{ $areaTypeLabel }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:select wire:model="form.areaIcon" name="area_icon" error:name="form.areaIcon" error:id="area-icon-error" description:id="area-icon-help" :invalid="$errors->has('form.areaIcon')" aria-describedby="area-icon-help area-icon-error" :label="__('ui.onboarding.restaurant_setup.ikonka')" :description="__('ui.onboarding.restaurant_setup.area_icon_help')" required>
                            @foreach ($this->areaIconOptions as $areaIcon => $areaIconLabel)
                                <flux:select.option wire:key="onboarding-area-icon-{{ $areaIcon }}" value="{{ $areaIcon }}">{{ $areaIconLabel }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div class="grid min-w-0 gap-3 border-t border-border-subtle pt-5 sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center">
                        <flux:button class="w-full whitespace-normal sm:w-auto" icon="arrow-left" type="button" wire:click="goToStep(3)" wire:offline.attr="disabled">{{ __('ui.onboarding.restaurant_setup.nazad') }}</flux:button>
                        <p id="onboarding-create-area-status" class="min-h-5 min-w-0 wrap-anywhere text-center text-sm text-text-muted" role="status" aria-live="polite" aria-atomic="true">
                            <span wire:loading wire:target="createArea">{{ __('ui.onboarding.restaurant_setup.saving_step') }}</span>
                        </p>
                        <flux:button class="w-full whitespace-normal sm:w-auto" icon="arrow-right" variant="primary" type="submit" aria-describedby="onboarding-create-area-status" wire:loading.attr="disabled" wire:offline.attr="disabled" wire:target="createArea">{{ __('ui.onboarding.restaurant_setup.dalse') }}</flux:button>
                    </div>
                </form>
            @elseif ($step === 5)
                <form wire:key="restaurant-onboarding-step-5" wire:submit="createServicePoints" wire:loading.attr="aria-busy" wire:target="createServicePoints" class="grid min-w-0 grid-cols-[minmax(0,1fr)] gap-6">
                    <div class="max-w-reading">
                        <p class="text-sm font-semibold text-brand-700 dark:text-brand-300">{{ __('ui.onboarding.restaurant_setup.sag_5') }}</p>
                        <flux:heading id="active-step-heading" data-onboarding-step-heading tabindex="-1" class="mt-1 outline-none" size="xl" level="2">{{ __('ui.onboarding.restaurant_setup.dobavte_pervye_stoly') }}</flux:heading>
                        <p class="mt-2 text-sm leading-6 text-text-muted">{{ __('ui.onboarding.restaurant_setup.sistema_sozdast_neskolko_stolov_srazu_qr_poz') }}</p>
                    </div>

                    <div class="grid min-w-0 grid-cols-[minmax(0,1fr)] gap-5 md:grid-cols-3">
                        <flux:input wire:model="form.tableCount" name="table_count" error:name="form.tableCount" error:id="table-count-error" description:id="table-count-help" :invalid="$errors->has('form.tableCount')" aria-describedby="table-count-help table-count-error" :label="__('ui.onboarding.restaurant_setup.skolko_stolov')" :description="__('ui.onboarding.restaurant_setup.table_count_help')" type="number" required min="1" max="20" step="1" inputmode="numeric" autocomplete="off" />
                        <flux:input wire:model="form.tablePrefix" name="table_prefix" error:name="form.tablePrefix" error:id="table-prefix-error" description:id="table-prefix-help" :invalid="$errors->has('form.tablePrefix')" aria-describedby="table-prefix-help table-prefix-error" :label="__('ui.onboarding.restaurant_setup.prefiks_nazvaniia_stolov')" :description="__('ui.onboarding.restaurant_setup.table_prefix_help')" :placeholder="__('ui.onboarding.restaurant_setup.table_prefix_placeholder')" type="text" required maxlength="40" autocomplete="off" />
                        <flux:input wire:model="form.tableCapacity" name="table_capacity" error:name="form.tableCapacity" error:id="table-capacity-error" description:id="table-capacity-help" :invalid="$errors->has('form.tableCapacity')" aria-describedby="table-capacity-help table-capacity-error" :label="__('ui.onboarding.restaurant_setup.mest_za_kazdym_stolom')" :description="__('ui.onboarding.restaurant_setup.table_capacity_help')" type="number" required min="1" max="50" step="1" inputmode="numeric" autocomplete="off" />
                    </div>

                    <div class="grid min-w-0 gap-3 border-t border-border-subtle pt-5 sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center">
                        <flux:button class="w-full whitespace-normal sm:w-auto" icon="arrow-left" type="button" wire:click="goToStep(4)" wire:offline.attr="disabled">{{ __('ui.onboarding.restaurant_setup.nazad') }}</flux:button>
                        <p id="onboarding-create-service-points-status" class="min-h-5 min-w-0 wrap-anywhere text-center text-sm text-text-muted" role="status" aria-live="polite" aria-atomic="true">
                            <span wire:loading wire:target="createServicePoints">{{ __('ui.onboarding.restaurant_setup.saving_step') }}</span>
                        </p>
                        <flux:button class="w-full whitespace-normal sm:w-auto" icon="arrow-right" variant="primary" type="submit" aria-describedby="onboarding-create-service-points-status" wire:loading.attr="disabled" wire:offline.attr="disabled" wire:target="createServicePoints">{{ __('ui.onboarding.restaurant_setup.sozdat_stoly') }}</flux:button>
                    </div>
                </form>
            @elseif ($step === 6)
                <div wire:key="restaurant-onboarding-step-6" wire:loading.attr="aria-busy" wire:target="generateQrCodes" class="grid min-w-0 grid-cols-[minmax(0,1fr)] gap-6">
                    <div class="max-w-reading">
                        <p class="text-sm font-semibold text-brand-700 dark:text-brand-300">{{ __('ui.onboarding.restaurant_setup.sag_6') }}</p>
                        <flux:heading id="active-step-heading" data-onboarding-step-heading tabindex="-1" class="mt-1 outline-none" size="xl" level="2">{{ __('ui.onboarding.restaurant_setup.sozdaite_qr_dlia_stolov') }}</flux:heading>
                        <p class="mt-2 text-sm leading-6 text-text-muted">{{ __('ui.onboarding.restaurant_setup.odin_stol_polucaet_odin_postoiannyi_qr_v_ssy') }}</p>
                    </div>

                    <div class="flex items-start gap-4 border-y border-border-subtle bg-surface-muted px-4 py-5 sm:px-5" role="status">
                        <span class="grid size-10 shrink-0 place-items-center rounded-full bg-surface-selected text-brand-700 dark:text-brand-300"><flux:icon.qr-code class="size-5" /></span>
                        <div>
                            <p class="font-semibold text-text-primary">{{ __('ui.onboarding.restaurant_setup.qr_ready_heading') }}</p>
                            <p class="mt-1 text-sm leading-6 text-text-muted">{{ trans_choice('ui.onboarding.restaurant_setup.1_budet_sozdan_postoiannyi_qr_2_budet_sozdan', $this->summary['service_points'], ['count' => $this->summary['service_points']]) }}</p>
                        </div>
                    </div>
                    <flux:error name="form.tableCount" />

                    <div class="grid min-w-0 gap-3 border-t border-border-subtle pt-5 sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center">
                        <flux:button class="w-full whitespace-normal sm:w-auto" icon="arrow-left" type="button" wire:click="goToStep(5)" wire:offline.attr="disabled">{{ __('ui.onboarding.restaurant_setup.nazad') }}</flux:button>
                        <p id="onboarding-generate-qr-status" class="min-h-5 min-w-0 wrap-anywhere text-center text-sm text-text-muted" role="status" aria-live="polite" aria-atomic="true">
                            <span wire:loading wire:target="generateQrCodes">{{ __('ui.onboarding.restaurant_setup.generating_qr') }}</span>
                        </p>
                        <flux:button class="w-full whitespace-normal sm:w-auto" icon="qr-code" variant="primary" type="button" wire:click="generateQrCodes" aria-describedby="onboarding-generate-qr-status" wire:loading.attr="disabled" wire:offline.attr="disabled" wire:target="generateQrCodes">{{ __('ui.onboarding.restaurant_setup.sgenerirovat_qr') }}</flux:button>
                    </div>
                </div>
            @elseif ($step === 7)
                <form wire:key="restaurant-onboarding-step-7" wire:submit="createStarterMenu" wire:loading.attr="aria-busy" wire:target="createStarterMenu" class="grid min-w-0 grid-cols-[minmax(0,1fr)] gap-6">
                    <div class="max-w-reading">
                        <p class="text-sm font-semibold text-brand-700 dark:text-brand-300">{{ __('ui.onboarding.restaurant_setup.sag_7') }}</p>
                        <flux:heading id="active-step-heading" data-onboarding-step-heading tabindex="-1" class="mt-1 outline-none" size="xl" level="2">{{ __('ui.onboarding.restaurant_setup.dobavte_pervoe_meniu') }}</flux:heading>
                        <p class="mt-2 text-sm leading-6 text-text-muted">{{ __('ui.onboarding.restaurant_setup.dlia_proverki_dostatocno_odnogo_razdela_i_od') }}</p>
                    </div>

                    <div class="grid min-w-0 grid-cols-[minmax(0,1fr)] gap-5 md:grid-cols-2">
                        <flux:input wire:model="form.menuName" name="menu_name" error:name="form.menuName" error:id="menu-name-error" :invalid="$errors->has('form.menuName')" aria-describedby="menu-name-error" :label="__('ui.onboarding.restaurant_setup.nazvanie_meniu')" :placeholder="__('ui.onboarding.restaurant_setup.menu_name_placeholder')" type="text" required maxlength="160" autocomplete="off" autocapitalize="words" />
                        <flux:input wire:model="form.categoryName" name="category_name" error:name="form.categoryName" error:id="category-name-error" :invalid="$errors->has('form.categoryName')" aria-describedby="category-name-error" :label="__('ui.onboarding.restaurant_setup.razdel_meniu')" :placeholder="__('ui.onboarding.restaurant_setup.category_name_placeholder')" type="text" required maxlength="160" autocomplete="off" autocapitalize="words" />
                        <flux:input wire:model="form.itemName" name="item_name" error:name="form.itemName" error:id="item-name-error" :invalid="$errors->has('form.itemName')" aria-describedby="item-name-error" :label="__('ui.onboarding.restaurant_setup.pervoe_bliudo')" :placeholder="__('ui.onboarding.restaurant_setup.item_name_placeholder')" type="text" required maxlength="180" autocomplete="off" autocapitalize="sentences" />
                        <flux:input wire:model="form.itemPrice" name="item_price" error:name="form.itemPrice" error:id="item-price-error" description:id="item-price-help" :invalid="$errors->has('form.itemPrice')" aria-describedby="item-price-help item-price-error" :label="__('ui.onboarding.restaurant_setup.cena')" :description="__('ui.onboarding.restaurant_setup.item_price_help', ['currency' => $form->branchCurrency])" type="number" required min="0" max="999999.99" step="0.01" inputmode="decimal" autocomplete="off" />
                    </div>

                    <div class="grid min-w-0 gap-3 border-t border-border-subtle pt-5 sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center">
                        <flux:button class="w-full whitespace-normal sm:w-auto" icon="arrow-left" type="button" wire:click="goToStep(6)" wire:offline.attr="disabled">{{ __('ui.onboarding.restaurant_setup.nazad') }}</flux:button>
                        <p id="onboarding-create-menu-status" class="min-h-5 min-w-0 wrap-anywhere text-center text-sm text-text-muted" role="status" aria-live="polite" aria-atomic="true">
                            <span wire:loading wire:target="createStarterMenu">{{ __('ui.onboarding.restaurant_setup.saving_step') }}</span>
                        </p>
                        <flux:button class="w-full whitespace-normal sm:w-auto" icon="check" variant="primary" type="submit" aria-describedby="onboarding-create-menu-status" wire:loading.attr="disabled" wire:offline.attr="disabled" wire:target="createStarterMenu">{{ __('ui.onboarding.restaurant_setup.dobavit_meniu') }}</flux:button>
                    </div>
                </form>
            @else
                <div wire:key="restaurant-onboarding-step-8" class="grid min-w-0 grid-cols-[minmax(0,1fr)] gap-6">
                    <div class="max-w-reading">
                        <p class="text-sm font-semibold text-success">{{ __('ui.departments.dashboard.gotovo') }}</p>
                        <flux:heading id="active-step-heading" data-onboarding-step-heading tabindex="-1" class="mt-1 outline-none" size="xl" level="2">{{ __('ui.onboarding.restaurant_setup.restoran_gotov_k_proverke') }}</flux:heading>
                        <p class="mt-2 text-sm leading-6 text-text-muted">{{ __('ui.onboarding.restaurant_setup.otkroite_gostevuiu_stranicu_i_ubedites_cto_q') }}</p>
                    </div>

                    @if ($this->summary['guest_url'])
                        <div class="border-y border-success-border bg-success-surface px-4 py-5 sm:px-5">
                            <p class="text-sm font-medium text-success">{{ __('ui.onboarding.restaurant_setup.primary_next_step') }}</p>
                            <p class="mt-1 font-semibold text-text-primary">{{ __('ui.onboarding.restaurant_setup.otkryt_gostevoe_meniu') }}</p>
                            <p class="mt-1 text-sm leading-6 text-text-muted">{{ __('ui.onboarding.restaurant_setup.ssylka_soderzit_tolko_skrytyi_qr_token') }}</p>
                            <flux:button class="mt-4 max-w-full whitespace-normal" icon="arrow-up-right" variant="primary" :href="$this->summary['guest_url']" target="_blank" rel="noopener">{{ __('ui.onboarding.restaurant_setup.otkryt_gostevoe_meniu') }}</flux:button>
                        </div>
                    @endif

                    <section aria-labelledby="next-actions-heading">
                        <h2 id="next-actions-heading" class="text-sm font-semibold text-text-primary">{{ __('ui.onboarding.restaurant_setup.next_actions') }}</h2>
                        <ul class="mt-2 divide-y divide-border-subtle border-y border-border-subtle">
                            @if ($this->summary['print_url'])
                                <li><a href="{{ $this->summary['print_url'] }}" class="flex min-h-touch min-w-0 items-center justify-between gap-4 py-3 text-sm font-medium text-text-primary outline-none hover:text-brand-700 focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2 dark:hover:text-brand-300" wire:navigate><span class="min-w-0 wrap-anywhere">{{ __('ui.onboarding.restaurant_setup.napecatat_qr') }}</span><flux:icon.chevron-right class="size-4 shrink-0 text-text-muted" /></a></li>
                            @endif
                            @if ($this->summary['branch_url'])
                                <li><a href="{{ $this->summary['branch_url'] }}" class="flex min-h-touch min-w-0 items-center justify-between gap-4 py-3 text-sm font-medium text-text-primary outline-none hover:text-brand-700 focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2 dark:hover:text-brand-300" wire:navigate><span class="min-w-0 wrap-anywhere">{{ __('ui.onboarding.restaurant_setup.otkryt_nastroiki_filiala') }}</span><flux:icon.chevron-right class="size-4 shrink-0 text-text-muted" /></a></li>
                            @endif
                            @if ($this->summary['menu_url'])
                                <li><a href="{{ $this->summary['menu_url'] }}" class="flex min-h-touch min-w-0 items-center justify-between gap-4 py-3 text-sm font-medium text-text-primary outline-none hover:text-brand-700 focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2 dark:hover:text-brand-300" wire:navigate><span class="min-w-0 wrap-anywhere">{{ __('ui.onboarding.restaurant_setup.dopolnit_meniu') }}</span><flux:icon.chevron-right class="size-4 shrink-0 text-text-muted" /></a></li>
                            @endif
                        </ul>
                    </section>
                </div>
            @endif
        </section>
    </div>
</section>
