@pushOnce('page-module-status', 'settings-status')
    <x-page-module-status module="settings" />
@endPushOnce

<section class="rm-settings rm-dish" data-page="restaurant-settings" data-page-module="settings" wire:ignore.self inert x-data="settingsWorkspace">
    @vite('resources/scss/availability.scss')
    <div class="rm-availability">
    <header class="rm-availability__section">
        <div>
            <flux:heading level="1" size="xl">{{ __('settings.title') }}</flux:heading>
            <flux:text>{{ $branchName }}</flux:text>
        </div>
        <flux:text>{{ __('settings.scope') }}</flux:text>
    </header>
    <flux:callout wire:offline variant="warning" :heading="__('settings.offline')" :text="__('settings.offline_help')" />
    @if ($missingSettings)
        <flux:callout :heading="__('settings.defaults')" :text="__('settings.defaults_help')" />
    @endif
    @error('settings')
        <flux:callout variant="danger" :heading="$message" role="alert" tabindex="-1" />
    @enderror
    <div class="rm-dish__layout rm-availability__section">
        <aside class="rm-availability__section menu-workspace-sections">
            <flux:input wire:model.live.debounce.250ms="search" type="search" :label="__('settings.search_label')" :placeholder="__('settings.search_placeholder')" maxlength="100" icon="magnifying-glass" />
            @if ($search !== '')
                <div class="rm-availability__result" role="region" aria-label="{{ __('settings.search_results') }}">
                    @forelse ($searchResults as $result)
                        <flux:button wire:key="settings-result-{{ $result['target'] }}" wire:click="selectSection('{{ $result['section'] }}', '{{ $result['target'] }}')" variant="ghost" wire:offline.attr="disabled">{{ $result['label'] }}</flux:button>
                    @empty
                        <flux:text>{{ __('settings.no_results') }}</flux:text>
                    @endforelse
                </div>
            @endif
            <nav class="rm-dish__nav" aria-label="{{ __('settings.sections') }}">
                @forelse ($sections as $link)
                    <flux:button :href="$link['href']" :icon="$link['icon']" :variant="$section === $link['key'] ? 'primary' : 'ghost'" :aria-current="$section === $link['key'] ? 'page' : null" :data-menu-section="$link['key']" x-on:click="navigateSection($event, $event.currentTarget.dataset.menuSection)">{{ $link['label'] }}</flux:button>
                @empty
                @endforelse
            </nav>
        </aside>
        <main class="rm-availability__section" data-menu-workspace-content>
            <section wire:show="section === 'profile'" class="rm-availability__editor" aria-labelledby="profile-heading" data-settings-active="{{ $section === 'profile' ? 'true' : 'false' }}">
                <flux:heading level="2" size="lg" id="profile-heading" tabindex="-1">{{ __('settings.section.profile') }}</flux:heading>
                <flux:text>{{ __('settings.help.profile') }}</flux:text>
                @if ($invalidStoredGroups['profile'])
                    <flux:callout variant="warning" :heading="__('settings.invalid_stored')" :text="__('settings.invalid_stored_help')" />
                @endif
                <flux:select variant="listbox" wire:model.live="contentLanguage" :label="__('settings.content_language')">
                    @forelse ($languageOptions as $code => $label)
                        <flux:select.option value="{{ $code }}" wire:key="content-language-{{ $code }}">{{ $label }}</flux:select.option>
                    @empty
                    @endforelse
                </flux:select>
                <form wire:submit="saveProfile" novalidate data-settings-group="profile" class="rm-availability__section">
                    <fieldset wire:loading.attr="disabled" wire:offline.attr="disabled" class="rm-availability__section">
                        <legend class="sr-only">{{ __('settings.section.profile') }}</legend>
                        <div id="public-text" tabindex="-1" class="rm-availability__section">
                            <flux:text>{{ __('settings.translation_help') }}</flux:text>
                            <flux:error name="profileForm.translations" :deep="false" />
                            @forelse ($languageOptions as $code => $label)
                                <div id="profileForm.translations.{{ $code }}" tabindex="-1" wire:show="contentLanguage === '{{ $code }}'" wire:key="profile-translation-{{ $code }}" class="rm-availability__section">
                                    <flux:error name="profileForm.translations.{{ $code }}" :deep="false" />
                                    <flux:input id="profileForm.translations.{{ $code }}.name" wire:model="profileForm.translations.{{ $code }}.name" :label="__('settings.public_name').' · '.$label" maxlength="160" />
                                    <flux:textarea id="profileForm.translations.{{ $code }}.description" wire:model="profileForm.translations.{{ $code }}.description" :label="__('settings.public_description').' · '.$label" rows="4" maxlength="1200" />
                                </div>
                            @empty
                            @endforelse
                            <flux:accordion>
                                <flux:accordion.item :expanded="$errors->has('profileForm.publicName') || $errors->has('profileForm.publicDescription')">
                                    <flux:accordion.heading>{{ __('settings.legacy_text') }}</flux:accordion.heading>
                                    <flux:accordion.content>
                                        <div class="rm-availability__section">
                                            <flux:text>{{ __('settings.legacy_help', ['name' => $internalName]) }}</flux:text>
                                            <flux:input id="profileForm.publicName" wire:model="profileForm.publicName" :label="__('settings.public_name')" maxlength="160" />
                                            <flux:textarea id="profileForm.publicDescription" wire:model="profileForm.publicDescription" :label="__('settings.public_description')" rows="3" maxlength="1200" />
                                        </div>
                                    </flux:accordion.content>
                                </flux:accordion.item>
                            </flux:accordion>
                        </div>
                        <div id="contacts" tabindex="-1" class="rm-availability__section">
                            <flux:heading level="3">{{ __('settings.search.contacts') }}</flux:heading>
                            <flux:text>{{ __('settings.contacts_public') }}</flux:text>
                            <div class="rm-availability__comparison">
                                <flux:input id="profileForm.phone" wire:model="profileForm.phone" :label="__('settings.profile.phone')" type="tel" maxlength="80" />
                                <flux:input id="profileForm.email" wire:model="profileForm.email" :label="__('settings.profile.email')" type="email" maxlength="255" />
                                <flux:input id="profileForm.websiteUrl" wire:model="profileForm.websiteUrl" :label="__('settings.profile.website_url')" type="url" maxlength="2048" />
                                <flux:input id="profileForm.instagramUrl" wire:model="profileForm.instagramUrl" :label="__('settings.profile.instagram_url')" type="url" maxlength="2048" />
                                <flux:input id="profileForm.facebookUrl" wire:model="profileForm.facebookUrl" :label="__('settings.profile.facebook_url')" type="url" maxlength="2048" />
                                <flux:input id="profileForm.tiktokUrl" wire:model="profileForm.tiktokUrl" :label="__('settings.profile.tiktok_url')" type="url" maxlength="2048" />
                            </div>
                        </div>
                        <flux:error name="profileForm" />
                        </fieldset>

                        <x-settings.group-actions group="profile" :saved="$saved['profile'] ?? false" />
                </form>
                <div id="images" tabindex="-1" class="rm-availability__section">
                    <flux:heading level="3">{{ __('settings.search.images') }}</flux:heading>
                    <flux:text>{{ __('settings.media.separate') }}</flux:text>
                    <flux:text>{{ __('settings.media.source.'.($publicProfile['logo_source'] ?? 'none')) }}</flux:text>
                    <div class="rm-availability__comparison">
                        <form wire:submit="saveLogo" data-settings-group="logo" class="rm-availability__section">
                            <fieldset wire:loading.attr="disabled" wire:offline.attr="disabled" class="rm-availability__section">
                                <legend>{{ __('settings.media.logo') }}</legend>
                                @if ($publicProfile['logo_url'])
                                    <img src="{{ $publicProfile['logo_url'] }}" alt="{{ $publicProfile['venue_name'] }}" class="size-24 object-contain rounded-lg" width="96" height="96">
                                @endif
                                <flux:file-upload wire:model="logo" :label="__('settings.media.logo')" accept="image/jpeg,image/png,image/webp">
                                    <flux:file-upload.dropzone :heading="__('settings.media.choose')" :text="__('settings.media.file_help')" />
                                </flux:file-upload>
                                <flux:error name="logo" />
                                <flux:text x-show="$wire.logo">{{ __('settings.unsaved') }}</flux:text>
                                @if ($selectedLogoPreview)
                                    <img src="{{ $selectedLogoPreview }}" alt="{{ __('settings.media.selection_preview') }}" class="size-24 object-contain rounded-lg" width="96" height="96">
                                @endif
                                @if (($saved['logo'] ?? false) && !$logo)
                                    <flux:text role="status">{{ __('settings.media.saved') }}</flux:text>
                                @endif
                                <div class="rm-availability__actions">
                                    <flux:button type="submit" variant="primary">{{ __('settings.media.save_logo') }}</flux:button>
                                    @if ($hasOwnLogo)
                                        <flux:button type="button" wire:click="removeImage('logo')">{{ __('settings.media.remove_own') }}</flux:button>
                                    @endif
                                </div>
                            </fieldset>
                            <flux:button type="button" x-show="$wire.logo" x-on:click="$wire.$set('logo', null, false)">{{ __('settings.cancel_changes') }}</flux:button>
                        </form>
                        <form wire:submit="saveCover" data-settings-group="cover" class="rm-availability__section">
                            <fieldset wire:loading.attr="disabled" wire:offline.attr="disabled" class="rm-availability__section">
                                <legend>{{ __('settings.media.cover') }}</legend>
                                @if ($publicProfile['cover_image_url'])
                                    <img src="{{ $publicProfile['cover_image_url'] }}" alt="" class="w-full h-40 object-cover rounded-lg" width="480" height="160">
                                @endif
                                <flux:file-upload wire:model="cover" :label="__('settings.media.cover')" accept="image/jpeg,image/png,image/webp">
                                    <flux:file-upload.dropzone :heading="__('settings.media.choose')" :text="__('settings.media.file_help')" />
                                </flux:file-upload>
                                <flux:error name="cover" />
                                <flux:text x-show="$wire.cover">{{ __('settings.unsaved') }}</flux:text>
                                @if ($selectedCoverPreview)
                                    <img src="{{ $selectedCoverPreview }}" alt="{{ __('settings.media.selection_preview') }}" class="w-full h-40 object-cover rounded-lg" width="480" height="160">
                                @endif
                                @if (($saved['cover'] ?? false) && !$cover)
                                    <flux:text role="status">{{ __('settings.media.saved') }}</flux:text>
                                @endif
                                <div class="rm-availability__actions">
                                    <flux:button type="submit" variant="primary">{{ __('settings.media.save_cover') }}</flux:button>
                                    @if ($hasOwnCover)
                                        <flux:button type="button" wire:click="removeImage('cover')">{{ __('settings.media.remove_own') }}</flux:button>
                                    @endif
                                </div>
                            </fieldset>
                            <flux:button type="button" x-show="$wire.cover" x-on:click="$wire.$set('cover', null, false)">{{ __('settings.cancel_changes') }}</flux:button>
                        </form>
                    </div>
                </div>
                <div class="rm-availability__actions">
                    <flux:button wire:click="openPreview(true)" icon="eye" wire:offline.attr="disabled">{{ __('settings.preview.draft') }}</flux:button>
                    <flux:button wire:click="openPreview(false)" wire:offline.attr="disabled">{{ __('settings.preview.saved') }}</flux:button>
                </div>
                @if ($previewOpen)
                    <section class="rm-availability__preview" data-menu-preview aria-label="{{ __('settings.preview.title') }}">
                        <div class="rm-availability__actions">
                            <flux:heading level="3">{{ $previewDraft ? __('settings.preview.draft') : __('settings.preview.saved') }}</flux:heading>
                            <flux:select wire:model.live="previewWidth" :label="__('settings.preview.width')">
                                <flux:select.option value="mobile">{{ __('settings.preview.mobile') }}</flux:select.option>
                                <flux:select.option value="wide">{{ __('settings.preview.wide') }}</flux:select.option>
                            </flux:select>
                            <flux:button x-on:click="$wire.$set('previewOpen', false, false)">{{ __('settings.close') }}</flux:button>
                        </div>
                        <flux:text>{{ __('settings.preview.help') }}</flux:text>
                        <article class="rm-availability__editor mx-auto {{ $previewWidth === 'mobile' ? 'max-w-sm' : 'w-full' }}" data-width="{{ $previewWidth }}" lang="{{ $contentLanguage }}">
                            @if ($preview['cover_image_url'])
                                <img src="{{ $preview['cover_image_url'] }}" alt="" class="w-full h-40 object-cover rounded-lg" width="640" height="220">
                            @endif
                            @if ($preview['logo_url'])
                                <img src="{{ $preview['logo_url'] }}" alt="" class="size-24 object-contain rounded-lg" width="80" height="80">
                            @endif
                            <h3>{{ $preview['venue_name'] }}</h3>
                            <p>{{ $preview['public_description'] }}</p>
                            @if ($preview['phone'])<p>{{ $preview['phone'] }}</p>@endif
                            @if ($preview['email'])<p>{{ $preview['email'] }}</p>@endif
                            @forelse (['website_url', 'instagram_url', 'facebook_url', 'tiktok_url'] as $link)
                                @if ($preview[$link])
                                    <p><a href="{{ $preview[$link] }}" rel="noopener noreferrer">{{ __('settings.profile.'.$link, [], $contentLanguage) }}</a></p>
                                @endif
                            @empty
                            @endforelse
                            @if (!$preview['has_contact_details'])<p>{{ __('settings.no_contacts', [], $contentLanguage) }}</p>@endif
                        </article>
                    </section>
                @endif
            </section>
            <section wire:show="section === 'guests'" class="rm-availability__editor" aria-labelledby="guests-heading" data-settings-active="{{ $section === 'guests' ? 'true' : 'false' }}">
                <flux:heading level="2" size="lg" id="guests-heading" tabindex="-1">{{ __('settings.section.guests') }}</flux:heading>
                <flux:text>{{ __('settings.help.guests') }}</flux:text>
                @if ($invalidStoredGroups['guests'])
                    <flux:callout variant="warning" :heading="__('settings.invalid_stored')" :text="__('settings.invalid_stored_help')" />
                @endif
                <form wire:submit="saveGuests" novalidate data-settings-group="guests" class="rm-availability__section">
                    <fieldset wire:loading.attr="disabled" wire:offline.attr="disabled" class="rm-availability__section">
                        <legend class="sr-only">{{ __('settings.section.guests') }}</legend>
                        <div id="joining" tabindex="-1" class="rm-availability__section">
                            <flux:heading level="3">{{ __('settings.search.joining') }}</flux:heading>
                            <flux:switch id="guests.allowGuestCreatedSessions" wire:model="guests.allowGuestCreatedSessions" :label="__('settings.fields.allow_guest_created_sessions')" :description="__('settings.guests.pending_help')" />
                            <flux:switch id="guests.allowWaiterOpenedSessions" wire:model="guests.allowWaiterOpenedSessions" :label="__('settings.fields.allow_waiter_opened_sessions')" :description="__('settings.guests.waiter_help')" />
                        </div>
                        <div id="invitations" tabindex="-1">
                            <flux:switch id="guests.allowGuestInviteLinks" wire:model="guests.allowGuestInviteLinks" :label="__('settings.fields.allow_guest_invite_links')" :description="__('settings.guests.invite_help')" />
                        </div>
                        <flux:callout :heading="__('settings.guests.confirmation')" :text="__('settings.guests.confirmation_help')" />
                        <flux:error name="guests" />
                        </fieldset>

                        <x-settings.group-actions group="guests" :saved="$saved['guests'] ?? false" />
                </form>
                <flux:callout :heading="__('settings.guests.legacy')" :text="__('settings.guests.legacy_help')" />
                <flux:text>{{ __('settings.guests.stored_mode', ['mode' => $legacyMode]) }}</flux:text>
                <div class="rm-availability__actions">
                    @forelse ($legacyServiceModes as $mode)
                        <flux:badge wire:key="stored-mode-{{ $loop->index }}">{{ $mode }}</flux:badge>
                    @empty
                        <flux:text>{{ __('settings.guests.no_modes') }}</flux:text>
                    @endforelse
                </div>
            </section>
            <section wire:show="section === 'settlement'" class="rm-availability__editor" aria-labelledby="settlement-heading" data-settings-active="{{ $section === 'settlement' ? 'true' : 'false' }}">
                <flux:heading level="2" size="lg" id="settlement-heading" tabindex="-1">{{ __('settings.section.settlement') }}</flux:heading>
                <flux:text>{{ __('settings.help.settlement') }}</flux:text>
                @if ($invalidStoredGroups['settlement'])
                    <flux:callout variant="warning" :heading="__('settings.invalid_stored')" :text="__('settings.invalid_stored_help')" />
                @endif
                @if ($currencyMismatch)
                    <flux:callout variant="warning" :heading="__('settings.currency_mismatch')" :text="__('settings.currency_mismatch_help')" />
                @endif
                <form wire:submit="saveSettlement" novalidate data-settings-group="settlement" class="rm-availability__section">
                    <fieldset wire:loading.attr="disabled" wire:offline.attr="disabled" class="rm-availability__section">
                        <legend class="sr-only">{{ __('settings.section.settlement') }}</legend>
                        <div id="currency" tabindex="-1" class="rm-availability__section">
                            <flux:select variant="listbox" searchable wire:model="settlement.defaultCurrency" :label="__('settings.fields.default_currency')" :disabled="$currencyBlocked">
                                @forelse ($currencyOptions as $code => $label)
                                    <flux:select.option value="{{ $code }}" wire:key="settings-currency-{{ $code }}">{{ $label }}</flux:select.option>
                                @empty
                                @endforelse
                            </flux:select>
                            @if ($currencyBlocked)<flux:text>{{ __('settings.errors.currency_has_data') }}</flux:text>@endif
                            <flux:text>{{ __('settings.currency_help') }}</flux:text>
                        </div>
                        <div id="service-charge" tabindex="-1" class="rm-availability__section">
                            <flux:switch id="settlement.serviceChargeEnabled" wire:model="settlement.serviceChargeEnabled" :label="__('settings.fields.service_charge_enabled')" />
                            <flux:input id="settlement.serviceChargePercent" wire:model="settlement.serviceChargePercent" :label="__('settings.fields.service_charge_percent')" inputmode="decimal" />
                            <flux:text>{{ __('settings.charge_help') }}</flux:text>
                            @if ($demoCharge !== null)<flux:text>{{ __('settings.charge_example', ['charge' => $demoCharge]) }}</flux:text>@endif
                        </div>
                        <div id="tips" tabindex="-1"><flux:switch id="settlement.tipsEnabled" wire:model="settlement.tipsEnabled" :label="__('settings.fields.tips_enabled')" :description="__('settings.tips_help')" /></div>
                        <flux:error name="settlement" />
                        </fieldset>

                        <x-settings.group-actions group="settlement" :saved="$saved['settlement'] ?? false" />
                </form>
            </section>
            <section wire:show="section === 'locale'" class="rm-availability__editor" aria-labelledby="locale-heading" data-settings-active="{{ $section === 'locale' ? 'true' : 'false' }}">
                <flux:heading level="2" size="lg" id="locale-heading" tabindex="-1">{{ __('settings.section.locale') }}</flux:heading>
                <flux:text>{{ __('settings.help.locale') }}</flux:text>
                @if ($invalidStoredGroups['locale'])
                    <flux:callout variant="warning" :heading="__('settings.invalid_stored')" :text="__('settings.invalid_stored_help')" />
                @endif
                <form wire:submit="saveLocale" novalidate data-settings-group="locale" class="rm-availability__section">
                    <fieldset wire:loading.attr="disabled" wire:offline.attr="disabled" class="rm-availability__section">
                        <legend class="sr-only">{{ __('settings.section.locale') }}</legend>
                        <div id="menu-language" tabindex="-1" class="rm-availability__section">
                            <flux:select id="locale.defaultLanguage" wire:model="locale.defaultLanguage" :label="__('settings.fields.default_language')">
                                @forelse ($languageOptions as $code => $label)
                                    <flux:select.option value="{{ $code }}" wire:key="settings-default-language-{{ $code }}">{{ $label }}</flux:select.option>
                                @empty
                                @endforelse
                            </flux:select>
                            <flux:text>{{ __('settings.language_help') }}</flux:text>
                        </div>
                        <flux:error name="locale" />
                        </fieldset>

                        <x-settings.group-actions group="locale" :saved="$saved['locale'] ?? false" />
                </form>
                <div id="timezone" tabindex="-1" class="rm-availability__section">
                    <flux:heading level="3">{{ __('settings.search.timezone') }}</flux:heading>
                    <flux:text>{{ $timezone }} · {{ $localTime }}</flux:text>
                    <flux:text>{{ __('settings.timezone_help') }}</flux:text>
                    @if ($identityUrl)<flux:button :href="$identityUrl" wire:navigate>{{ __('settings.open_identity') }}</flux:button>@endif
                </div>
            </section>
            <section wire:show="section === 'advanced'" class="rm-availability__editor" aria-labelledby="advanced-heading" data-settings-active="{{ $section === 'advanced' ? 'true' : 'false' }}">
                <flux:heading level="2" size="lg" id="advanced-heading" tabindex="-1">{{ __('settings.section.advanced') }}</flux:heading>
                <flux:text>{{ __('settings.help.advanced') }}</flux:text>
                @if ($invalidStoredGroups['advanced'])
                    <flux:callout variant="warning" :heading="__('settings.invalid_stored')" :text="__('settings.invalid_stored_help')" />
                @endif
                <form wire:submit="saveAdvanced" novalidate data-settings-group="advanced" class="rm-availability__section">
                    <fieldset wire:loading.attr="disabled" wire:offline.attr="disabled" class="rm-availability__section">
                        <legend class="sr-only">{{ __('settings.section.advanced') }}</legend>
                        <div id="polling" tabindex="-1" class="rm-availability__section">
                            <flux:input id="advanced.pollingIntervalSeconds" wire:model="advanced.pollingIntervalSeconds" :label="__('settings.fields.polling_interval_seconds')" type="number" min="1" max="60" />
                            <flux:text>{{ __('settings.polling_help') }}</flux:text>
                        </div>
                        <div id="inactivity" tabindex="-1" class="rm-availability__section">
                            <flux:input id="advanced.inactivityWarningMinutes" wire:model="advanced.inactivityWarningMinutes" :label="__('settings.fields.inactivity_warning_minutes')" type="number" min="1" max="1440" />
                            <flux:input id="advanced.pendingSessionExpireMinutes" wire:model="advanced.pendingSessionExpireMinutes" :label="__('settings.fields.pending_session_expire_minutes')" type="number" min="1" max="1440" />
                            <flux:text>{{ __('settings.inactivity_help') }}</flux:text>
                        </div>
                        <flux:error name="advanced" />
                        </fieldset>

                        <x-settings.group-actions group="advanced" :saved="$saved['advanced'] ?? false" />
                </form>
                <div id="cleanup" tabindex="-1" class="rm-availability__section">
                    <flux:heading level="3">{{ __('settings.search.cleanup') }}</flux:heading>
                    <flux:text>{{ __('settings.cleanup_help') }}</flux:text>
                    <flux:error name="cleanup" />
                    <flux:button wire:click="previewCleanup" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('settings.cleanup.preview') }}</flux:button>
                    @if ($cleanupResult !== [])
                        <flux:callout :heading="__('settings.cleanup.result')" role="status" :text="__('settings.cleanup.summary', ['checked' => $cleanupResult['checked'], 'cancelled' => $cleanupResult['pending_cancelled'], 'warnings' => $cleanupResult['preview_active_warnings'], 'changed' => $cleanupResult['skipped_changed']])" />
                        @if ($cleanupResult['has_more'])<flux:text>{{ __('settings.cleanup.more') }}</flux:text>@endif
                    @endif
                </div>
            </section>
            @if ($availabilityUrl)
                <flux:callout :heading="__('availability.title')" :text="__('availability.settings_link_description')">
                    <x-slot:actions><flux:button :href="$availabilityUrl" wire:navigate>{{ __('availability.open_center') }}</flux:button></x-slot:actions>
                </flux:callout>
            @endif
        </main>
    </div>
    <flux:modal name="settings-currency" class="workspace-restaurant__dialog">
        <div class="rm-availability__section">
            <flux:heading>{{ __('settings.currency_confirm') }}</flux:heading>
            @if ($currencyPreview !== [])<flux:text>{{ $currencyPreview['from'] }} → {{ $currencyPreview['to'] }}</flux:text>@endif
            <flux:text>{{ __('settings.currency_help') }}</flux:text>
            <flux:error name="settlement.defaultCurrency" />
            <flux:error name="settlement" />
            <div class="rm-availability__actions">
                <flux:modal.close><flux:button autofocus>{{ __('settings.cancel') }}</flux:button></flux:modal.close>
                <flux:button wire:click="confirmCurrency" variant="primary" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('settings.confirm') }}</flux:button>
            </div>
        </div>
    </flux:modal>
    <flux:modal name="settings-cleanup" class="workspace-restaurant__dialog">
        <div class="rm-availability__section">
            <flux:heading>{{ __('settings.cleanup.preview') }}</flux:heading>
            <flux:text>{{ __('settings.cleanup_help') }}</flux:text>
            @if ($cleanupPreview !== [])
                <flux:text>{{ __('settings.cleanup.preview_summary', ['checked' => $cleanupPreview['summary']['checked'], 'candidates' => $cleanupPreview['summary']['pending_candidates'], 'warnings' => $cleanupPreview['summary']['active_warnings']]) }}</flux:text>
                @if ($cleanupPreview['has_more'])<flux:text>{{ __('settings.cleanup.more') }}</flux:text>@endif
            @endif
            <flux:error name="cleanup" />
            <div class="rm-availability__actions">
                <flux:modal.close><flux:button autofocus>{{ __('settings.cancel') }}</flux:button></flux:modal.close>
                <flux:button wire:click="confirmCleanup" variant="danger" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('settings.cleanup.confirm') }}</flux:button>
            </div>
        </div>
    </flux:modal>
    <flux:modal name="settings-unsaved" class="workspace-restaurant__dialog">
        <div class="rm-availability__section">
            <flux:heading>{{ __('settings.unsaved') }}</flux:heading>
            <flux:text>{{ __('settings.leave_help') }}</flux:text>
            <div class="rm-availability__actions">
                <flux:button x-on:click="cancelNavigation" autofocus>{{ __('settings.stay') }}</flux:button>
                <flux:button x-on:click="discardAndNavigate" variant="danger">{{ __('settings.leave') }}</flux:button>
            </div>
        </div>
    </flux:modal>
    </div>
</section>
