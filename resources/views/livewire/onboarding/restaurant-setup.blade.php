<section data-page="restaurant-onboarding" class="flex h-full w-full flex-1 flex-col gap-6">
    <header class="flex flex-col gap-3">
        <div class="flex flex-col gap-1">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('ui.onboarding.restaurant_setup.bystryi_start') }}</p>
            <h1 class="text-2xl font-semibold text-zinc-950 dark:text-white">{{ __('ui.onboarding.restaurant_setup.nastroit_restoran') }}</h1>
            <p class="max-w-2xl text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('ui.onboarding.restaurant_setup.proidite_prostye_sagi_nazvanie_adres_pervyi') }}
            </p>
        </div>
    </header>

    <div class="grid gap-4 xl:grid-cols-[18rem_1fr]">
        <aside class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('ui.onboarding.restaurant_setup.sagi') }}</flux:heading>

            <div class="mt-4 grid gap-2">
                @foreach ($this->steps as $wizardStep)
                    <button
                        type="button"
                        wire:key="onboarding-step-{{ $wizardStep['number'] }}"
                        wire:click="goToStep({{ $wizardStep['number'] }})"
                        @disabled(! $wizardStep['is_available'])
                        class="flex min-h-14 w-full items-center gap-3 rounded-lg border px-3 py-2 text-left transition {{ $wizardStep['is_current'] ? 'border-zinc-900 bg-zinc-100 dark:border-white dark:bg-zinc-800' : 'border-zinc-200 bg-zinc-50 hover:bg-white dark:border-zinc-800 dark:bg-zinc-950 dark:hover:bg-zinc-900' }} disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <flux:badge :icon="$wizardStep['icon']" :color="$wizardStep['is_done'] ? 'green' : ($wizardStep['is_current'] ? 'amber' : 'zinc')">
                            {{ $wizardStep['number'] }}
                        </flux:badge>

                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-zinc-950 dark:text-white">{{ $wizardStep['label'] }}</span>
                            <span class="block text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $wizardStep['is_done'] ? __('ui.departments.dashboard.gotovo') : ($wizardStep['is_current'] ? __('ui.onboarding.restaurant_setup.seicas') : __('ui.onboarding.restaurant_setup.pozze')) }}
                            </span>
                        </span>
                    </button>
                @endforeach
            </div>

            <div class="mt-5 rounded-lg border border-zinc-200 bg-zinc-50 p-3 text-sm text-zinc-600 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-300">
                <p class="font-medium text-zinc-900 dark:text-white">{{ __('ui.onboarding.restaurant_setup.cto_uze_sozdano') }}</p>
                <dl class="mt-2 grid gap-1">
                    <div class="flex justify-between gap-3">
                        <dt>{{ __('ui.livewire.onboarding.restaurantsetup.kompaniia') }}</dt>
                        <dd class="truncate font-medium">{{ $this->summary['organization'] ?? __('ui.onboarding.restaurant_setup.net') }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt>{{ __('ui.livewire.onboarding.restaurantsetup.restoran') }}</dt>
                        <dd class="truncate font-medium">{{ $this->summary['brand'] ?? __('ui.onboarding.restaurant_setup.net') }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt>{{ __('ui.onboarding.restaurant_setup.tocka') }}</dt>
                        <dd class="truncate font-medium">{{ $this->summary['branch'] ?? __('ui.onboarding.restaurant_setup.net') }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt>{{ __('ui.livewire.onboarding.restaurantsetup.zona') }}</dt>
                        <dd class="truncate font-medium">{{ $this->summary['area'] ?? __('ui.onboarding.restaurant_setup.net') }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt>{{ __('ui.livewire.onboarding.restaurantsetup.stoly') }}</dt>
                        <dd class="font-medium">{{ $this->summary['service_points'] }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt>{{ __('permissions.groups.qr') }}</dt>
                        <dd class="font-medium">{{ $this->summary['qr_codes'] }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt>{{ __('ui.livewire.onboarding.restaurantsetup.meniu') }}</dt>
                        <dd class="truncate font-medium">{{ $this->summary['menu'] ?? __('ui.onboarding.restaurant_setup.net') }}</dd>
                    </div>
                </dl>
            </div>
        </aside>

        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            @if ($step === 1)
                <form wire:submit="createOrganization" class="grid gap-5">
                    <div class="flex flex-col gap-1">
                        <flux:badge icon="building-office" color="amber">{{ __('ui.onboarding.restaurant_setup.sag_1') }}</flux:badge>
                        <flux:heading size="xl">{{ __('ui.onboarding.restaurant_setup.kto_vladeet_zavedeniem') }}</flux:heading>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.onboarding.restaurant_setup.napisite_nazvanie_kompanii_ili_vladelca_vy_a') }}</p>
                    </div>

                    <flux:input wire:model="form.organizationName" :label="__('ui.onboarding.restaurant_setup.nazvanie_kompanii')" type="text" required maxlength="120" autocomplete="organization" />

                    <div class="flex flex-wrap gap-3">
                        <flux:button icon="arrow-right" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createOrganization">
                            {{ __('ui.onboarding.restaurant_setup.dalse') }}
                        </flux:button>
                    </div>
                </form>
            @elseif ($step === 2)
                <form wire:submit="createBrand" class="grid gap-5">
                    <div class="flex flex-col gap-1">
                        <flux:badge icon="building-storefront" color="amber">{{ __('ui.onboarding.restaurant_setup.sag_2') }}</flux:badge>
                        <flux:heading size="xl">{{ __('ui.onboarding.restaurant_setup.kak_nazyvaetsia_restoran') }}</flux:heading>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.onboarding.restaurant_setup.eto_nazvanie_gosti_i_sotrudniki_budut_uznava') }}</p>
                    </div>

                    <flux:input wire:model="form.brandName" :label="__('ui.onboarding.restaurant_setup.nazvanie_restorana')" type="text" required maxlength="120" />

                    <div class="flex flex-wrap gap-3">
                        <flux:button icon="arrow-left" type="button" wire:click="goToStep(1)">{{ __('ui.onboarding.restaurant_setup.nazad') }}</flux:button>
                        <flux:button icon="arrow-right" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createBrand">
                            {{ __('ui.onboarding.restaurant_setup.dalse') }}
                        </flux:button>
                    </div>
                </form>
            @elseif ($step === 3)
                <form wire:submit="createBranch" class="grid gap-5">
                    <div class="flex flex-col gap-1">
                        <flux:badge icon="map-pin" color="amber">{{ __('ui.onboarding.restaurant_setup.sag_3') }}</flux:badge>
                        <flux:heading size="xl">{{ __('ui.onboarding.restaurant_setup.gde_naxoditsia_eta_tocka') }}</flux:heading>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.onboarding.restaurant_setup.ukazite_adres_pervogo_filiala_potom_mozno_do') }}</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <flux:input wire:model="form.branchName" :label="__('ui.onboarding.restaurant_setup.nazvanie_tocki')" type="text" required maxlength="160" />
                        <flux:input wire:model="form.branchAddress" :label="__('ui.livewire.onboarding.restaurantsetup.adres')" type="text" required maxlength="255" />
                        <flux:input wire:model="form.branchCity" :label="__('ui.onboarding.restaurant_setup.gorod')" type="text" required maxlength="120" />
                        <flux:input wire:model="form.branchCountry" :label="__('ui.onboarding.restaurant_setup.strana')" type="text" required maxlength="120" />
                        <flux:input wire:model="form.branchTimezone" :label="__('ui.onboarding.restaurant_setup.casovoi_poias')" type="text" required maxlength="64" />
                        <flux:field>
                            <flux:label>{{ __('ui.onboarding.restaurant_setup.valiuta') }}</flux:label>
                            <flux:select wire:model="form.branchCurrency">
                                @foreach ($currencyOptions as $currencyCode => $currencyLabel)
                                    <flux:select.option wire:key="onboarding-branch-currency-{{ $currencyCode }}" value="{{ $currencyCode }}">
                                        {{ $currencyLabel }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="form.branchCurrency" />
                        </flux:field>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <flux:button icon="arrow-left" type="button" wire:click="goToStep(2)">{{ __('ui.onboarding.restaurant_setup.nazad') }}</flux:button>
                        <flux:button icon="arrow-right" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createBranch">
                            {{ __('ui.onboarding.restaurant_setup.dalse') }}
                        </flux:button>
                    </div>
                </form>
            @elseif ($step === 4)
                <form wire:submit="createArea" class="grid gap-5">
                    <div class="flex flex-col gap-1">
                        <flux:badge icon="rectangle-group" color="amber">{{ __('ui.onboarding.restaurant_setup.sag_4') }}</flux:badge>
                        <flux:heading size="xl">{{ __('ui.onboarding.restaurant_setup.dobavte_pervyi_zal') }}</flux:heading>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.onboarding.restaurant_setup.naprimer_glavnyi_zal_terrasa_vip_zal_stoly_p') }}</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <flux:input wire:model="form.areaName" :label="__('ui.onboarding.restaurant_setup.nazvanie_zony')" type="text" required maxlength="160" />

                        <flux:select wire:model="form.areaType" :label="__('ui.onboarding.restaurant_setup.cto_eto')">
                            <flux:select.option value="hall">{{ __('ui.livewire.organizations.brands.branches.areas.zal') }}</flux:select.option>
                            <flux:select.option value="terrace">{{ __('ui.livewire.organizations.brands.branches.areas.terrasa') }}</flux:select.option>
                            <flux:select.option value="vip_room">{{ __('ui.livewire.organizations.brands.branches.areas.vip_zal') }}</flux:select.option>
                            <flux:select.option value="custom">{{ __('ui.livewire.organizations.brands.branches.areas.svoia_zona') }}</flux:select.option>
                        </flux:select>

                        <flux:select wire:model="form.areaIcon" :label="__('ui.onboarding.restaurant_setup.ikonka')">
                            <flux:select.option value="rectangle-group">{{ __('ui.livewire.organizations.brands.branches.areas.zal') }}</flux:select.option>
                            <flux:select.option value="sparkles">{{ __('ui.livewire.organizations.brands.branches.areas.vip') }}</flux:select.option>
                            <flux:select.option value="sun">{{ __('ui.livewire.organizations.brands.branches.areas.terrasa') }}</flux:select.option>
                            <flux:select.option value="map-pin">{{ __('ui.onboarding.restaurant_setup.drugoe') }}</flux:select.option>
                        </flux:select>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <flux:button icon="arrow-left" type="button" wire:click="goToStep(3)">{{ __('ui.onboarding.restaurant_setup.nazad') }}</flux:button>
                        <flux:button icon="arrow-right" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createArea">
                            {{ __('ui.onboarding.restaurant_setup.dalse') }}
                        </flux:button>
                    </div>
                </form>
            @elseif ($step === 5)
                <form wire:submit="createServicePoints" class="grid gap-5">
                    <div class="flex flex-col gap-1">
                        <flux:badge icon="squares-2x2" color="amber">{{ __('ui.onboarding.restaurant_setup.sag_5') }}</flux:badge>
                        <flux:heading size="xl">{{ __('ui.onboarding.restaurant_setup.dobavte_pervye_stoly') }}</flux:heading>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.onboarding.restaurant_setup.sistema_sozdast_neskolko_stolov_srazu_qr_poz') }}</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <flux:input wire:model="form.tableCount" :label="__('ui.onboarding.restaurant_setup.skolko_stolov')" type="number" required min="1" max="20" />
                        <flux:input wire:model="form.tablePrefix" :label="__('ui.onboarding.restaurant_setup.kak_nazvat')" type="text" required maxlength="40" />
                        <flux:input wire:model="form.tableCapacity" :label="__('ui.onboarding.restaurant_setup.gostei_za_stolom')" type="number" required min="1" max="50" />
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <flux:button icon="arrow-left" type="button" wire:click="goToStep(4)">{{ __('ui.onboarding.restaurant_setup.nazad') }}</flux:button>
                        <flux:button icon="arrow-right" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createServicePoints">
                            {{ __('ui.onboarding.restaurant_setup.sozdat_stoly') }}
                        </flux:button>
                    </div>
                </form>
            @elseif ($step === 6)
                <div class="grid gap-5">
                    <div class="flex flex-col gap-1">
                        <flux:badge icon="qr-code" color="amber">{{ __('ui.onboarding.restaurant_setup.sag_6') }}</flux:badge>
                        <flux:heading size="xl">{{ __('ui.onboarding.restaurant_setup.sozdaite_qr_dlia_stolov') }}</flux:heading>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.onboarding.restaurant_setup.odin_stol_polucaet_odin_postoiannyi_qr_v_ssy') }}</p>
                    </div>

                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-600 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-300">
                        {{ trans_choice('ui.onboarding.restaurant_setup.1_budet_sozdan_postoiannyi_qr_2_budet_sozdan', count($servicePointIds), ['count' => count($servicePointIds)]) }}
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <flux:button icon="arrow-left" type="button" wire:click="goToStep(5)">{{ __('ui.onboarding.restaurant_setup.nazad') }}</flux:button>
                        <flux:button icon="qr-code" variant="primary" type="button" wire:click="generateQrCodes" wire:loading.attr="disabled" wire:target="generateQrCodes">
                            {{ __('ui.onboarding.restaurant_setup.sgenerirovat_qr') }}
                        </flux:button>
                    </div>
                </div>
            @elseif ($step === 7)
                <form wire:submit="createStarterMenu" class="grid gap-5">
                    <div class="flex flex-col gap-1">
                        <flux:badge icon="book-open" color="amber">{{ __('ui.onboarding.restaurant_setup.sag_7') }}</flux:badge>
                        <flux:heading size="xl">{{ __('ui.onboarding.restaurant_setup.dobavte_pervoe_meniu') }}</flux:heading>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.onboarding.restaurant_setup.dlia_proverki_dostatocno_odnogo_razdela_i_od') }}</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <flux:input wire:model="form.menuName" :label="__('ui.onboarding.restaurant_setup.nazvanie_meniu')" type="text" required maxlength="160" />
                        <flux:input wire:model="form.categoryName" :label="__('ui.onboarding.restaurant_setup.razdel_meniu')" type="text" required maxlength="160" />
                        <flux:input wire:model="form.itemName" :label="__('ui.onboarding.restaurant_setup.pervoe_bliudo')" type="text" required maxlength="180" />
                        <flux:input wire:model="form.itemPrice" :label="__('ui.onboarding.restaurant_setup.cena')" type="number" required min="0" max="999999.99" step="0.01" />
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <flux:button icon="arrow-left" type="button" wire:click="goToStep(6)">{{ __('ui.onboarding.restaurant_setup.nazad') }}</flux:button>
                        <flux:button icon="check" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createStarterMenu">
                            {{ __('ui.onboarding.restaurant_setup.dobavit_meniu') }}
                        </flux:button>
                    </div>
                </form>
            @else
                <div class="grid gap-5">
                    <div class="flex flex-col gap-1">
                        <flux:badge icon="check-circle" color="green">{{ __('ui.departments.dashboard.gotovo') }}</flux:badge>
                        <flux:heading size="xl">{{ __('ui.onboarding.restaurant_setup.restoran_gotov_k_proverke') }}</flux:heading>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.onboarding.restaurant_setup.otkroite_gostevuiu_stranicu_i_ubedites_cto_q') }}</p>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        @if ($this->summary['guest_url'])
                            <a href="{{ $this->summary['guest_url'] }}" target="_blank" rel="noopener" class="flex min-h-24 items-center gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 transition hover:bg-white dark:border-zinc-800 dark:bg-zinc-950 dark:hover:bg-zinc-900">
                                <flux:badge icon="book-open" color="green">{{ __('ui.onboarding.restaurant_setup.gost') }}</flux:badge>
                                <span>
                                    <span class="block font-semibold text-zinc-950 dark:text-white">{{ __('ui.onboarding.restaurant_setup.otkryt_gostevoe_meniu') }}</span>
                                    <span class="mt-1 block text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.onboarding.restaurant_setup.ssylka_soderzit_tolko_skrytyi_qr_token') }}</span>
                                </span>
                            </a>
                        @endif

                        @if ($this->summary['print_url'])
                            <a href="{{ $this->summary['print_url'] }}" class="flex min-h-24 items-center gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 transition hover:bg-white dark:border-zinc-800 dark:bg-zinc-950 dark:hover:bg-zinc-900" wire:navigate>
                                <flux:badge icon="printer" color="zinc">{{ __('permissions.groups.qr') }}</flux:badge>
                                <span>
                                    <span class="block font-semibold text-zinc-950 dark:text-white">{{ __('ui.onboarding.restaurant_setup.napecatat_qr') }}</span>
                                    <span class="mt-1 block text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.onboarding.restaurant_setup.otkroite_stranicu_pecati_nakleek') }}</span>
                                </span>
                            </a>
                        @endif

                        @if ($this->summary['branch_url'])
                            <a href="{{ $this->summary['branch_url'] }}" class="flex min-h-24 items-center gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 transition hover:bg-white dark:border-zinc-800 dark:bg-zinc-950 dark:hover:bg-zinc-900" wire:navigate>
                                <flux:badge icon="squares-2x2" color="zinc">{{ __('ui.livewire.onboarding.restaurantsetup.stoly') }}</flux:badge>
                                <span>
                                    <span class="block font-semibold text-zinc-950 dark:text-white">{{ __('ui.onboarding.restaurant_setup.otkryt_nastroiki_filiala') }}</span>
                                    <span class="mt-1 block text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.onboarding.restaurant_setup.zony_stoly_i_qr_ostaiutsia_v_obycnom_crud') }}</span>
                                </span>
                            </a>
                        @endif

                        @if ($this->summary['menu_url'])
                            <a href="{{ $this->summary['menu_url'] }}" class="flex min-h-24 items-center gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 transition hover:bg-white dark:border-zinc-800 dark:bg-zinc-950 dark:hover:bg-zinc-900" wire:navigate>
                                <flux:badge icon="book-open" color="zinc">{{ __('ui.livewire.onboarding.restaurantsetup.meniu') }}</flux:badge>
                                <span>
                                    <span class="block font-semibold text-zinc-950 dark:text-white">{{ __('ui.onboarding.restaurant_setup.dopolnit_meniu') }}</span>
                                    <span class="mt-1 block text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.onboarding.restaurant_setup.dobavte_bliuda_ceny_foto_i_modifikatory_pozz') }}</span>
                                </span>
                            </a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</section>
