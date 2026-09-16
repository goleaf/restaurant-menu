import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { after, before, test } from 'node:test';
import { chromium, webkit } from 'playwright';

const assets = process.env.FLUX_RUNTIME_ASSET ? [process.env.FLUX_RUNTIME_ASSET] : ['flux.module.js', 'flux.js', 'flux.min.js'];
let browser;
before(async () => {
    browser = process.env.FLUX_RUNTIME_BROWSER === 'webkit'
        ? await webkit.launch()
        : await chromium.launch({ channel: 'chrome', chromiumSandbox: true });
});
after(async () => { await browser?.close(); });

async function fixture(t, asset, body, options = {}) {
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 }, ...options });
    const page = await context.newPage();
    page.setDefaultTimeout(5000);
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => { if (['warning', 'error'].includes(message.type())) errors.push(message.text()); });
    t.after(async () => { await context.close(); assert.deepEqual(errors, [], 'runtime must have no browser errors or warnings'); });
    await page.route('https://flux-runtime.test/**', route => route.fulfill({ contentType: 'text/html', body: `<!doctype html><html lang="en"><head><title>Local Flux runtime fixture</title></head><body>${body}</body></html>` }));
    await page.goto('https://flux-runtime.test/');
    await page.evaluate(() => {
        window.runtimeListeners = [];
        const add = EventTarget.prototype.addEventListener;
        const remove = EventTarget.prototype.removeEventListener;
        EventTarget.prototype.addEventListener = function (type, callback, options) {
            window.runtimeListeners.push({ target: this, type, callback });
            return add.call(this, type, callback, options);
        };
        EventTarget.prototype.removeEventListener = function (type, callback, options) {
            window.runtimeListeners = window.runtimeListeners.filter(entry => !(entry.target === this && entry.type === type && entry.callback === callback));
            return remove.call(this, type, callback, options);
        };
        window.runtimeRegistrations = [];
        const define = customElements.define.bind(customElements);
        customElements.define = (name, constructor, options) => {
            window.runtimeRegistrations.push(name);
            return define(name, constructor, options);
        };
    });
    const directory = process.env.FLUX_RUNTIME_ORIGINAL ? '../flux-pro/dist/' : '../packages/livewire/flux-pro/dist/';
    await page.addScriptTag({ content: readFileSync(new URL(directory + asset, import.meta.url), 'utf8'), type: asset.includes('module') ? 'module' : undefined });
    await page.waitForFunction(() => customElements.get('ui-otp'));
    return page;
}

const otp = '<ui-otp name="code" value="1234">' + '<input data-flux-otp-input>'.repeat(4) + '</ui-otp>';
const toastTemplate = '<template><div><div><slot name="text"></slot><template name="link"><a><slot name="text"></slot></a></template></div></div></template>';

for (const asset of assets) {
    test(`${asset}: OTP replaces one character without clearing the full code, accepts paste and backspace`, async t => {
        const page = await fixture(t, asset, otp);
        await page.locator('input[data-flux-otp-input]').nth(1).focus();
        await page.evaluate(() => {
            const input = document.querySelectorAll('[data-flux-otp-input]')[1];
            input.setSelectionRange(1, 1);
            input.dispatchEvent(new InputEvent('beforeinput', { bubbles: true, inputType: 'insertText', data: '9' }));
            input.value = '29';
            input.dispatchEvent(new InputEvent('input', { bubbles: true, inputType: 'insertText', data: '9' }));
        });
        assert.equal(await page.locator('ui-otp').evaluate(el => el.value), '1934');
        await page.evaluate(() => {
            const input = document.querySelector('[data-flux-otp-input]');
            input.dispatchEvent(new InputEvent('beforeinput', { bubbles: true, inputType: 'insertFromPaste', data: '8765' }));
            input.value = '8765';
            input.dispatchEvent(new InputEvent('input', { bubbles: true, inputType: 'insertFromPaste', data: '8765' }));
        });
        assert.equal(await page.locator('ui-otp').evaluate(el => el.value), '8765');
        await page.keyboard.press('Backspace');
        assert.equal(await page.locator('ui-otp').evaluate(el => el.value), '876');
        assert.equal(await page.locator('[data-flux-otp-input]').nth(2).evaluate(el => el === document.activeElement), true);
    });

    test(`${asset}: OTP touch preserves native pointer focus and selects the digit on click`, async t => {
        const page = await fixture(t, asset, otp, { hasTouch: true, isMobile: true });
        const cancelled = await page.locator('[data-flux-otp-input]').nth(1).evaluate(el => !el.dispatchEvent(new PointerEvent('pointerdown', { pointerType: 'touch', bubbles: true, cancelable: true })));
        assert.equal(cancelled, false);
        await page.locator('[data-flux-otp-input]').nth(1).tap();
        await page.waitForFunction(() => document.activeElement?.selectionStart === 0 && document.activeElement?.selectionEnd === 1);
        assert.equal(await page.locator('[data-flux-otp-input]').nth(1).evaluate(el => el === document.activeElement), true);
    });

    test(`${asset}: modal disable-escape preserves dialog and regular escape restores trigger focus`, async t => {
        const page = await fixture(t, asset, '<ui-modal disable-escape><button id="guarded">Open guarded</button><dialog><button autofocus>Inside</button></dialog></ui-modal><ui-modal><button id="normal">Open regular</button><dialog><button autofocus>Inside</button></dialog></ui-modal>');
        await page.locator('#guarded').focus();
        await page.keyboard.press('Enter');
        await page.keyboard.press('Escape');
        assert.equal(await page.locator('dialog').nth(0).evaluate(el => el.open), true);
        await page.locator('dialog').nth(0).evaluate(el => el._dialogable.cancel());
        await page.waitForFunction(() => !document.querySelector('dialog').open);
        await page.locator('#normal').focus();
        await page.keyboard.press('Enter');
        assert.equal(await page.locator('dialog').nth(1).locator('[autofocus]').evaluate(el => el === document.activeElement), true);
        await page.keyboard.press('Escape');
        await page.waitForFunction(() => !document.querySelectorAll('dialog')[1].open);
        assert.equal(await page.locator('#normal').evaluate(el => el === document.activeElement), true);
    });

    test(`${asset}: removed sidebar, tooltip and standalone toast release global listeners`, async t => {
        const page = await fixture(t, asset, `<ui-sidebar collapsible="true" persist="false"></ui-sidebar><ui-tooltip><button>Help</button><div popover>Hint</div></ui-tooltip><ui-toast popover="manual">${toastTemplate}</ui-toast>`);
        const before = await page.evaluate(() => ({
            sidebar: window.runtimeListeners.filter(x => x.target === document && x.type === 'flux-sidebar-toggle').length,
            focus: window.runtimeListeners.filter(x => x.target === document && x.type === 'focusin').length,
            media: window.runtimeListeners.filter(x => x.target instanceof MediaQueryList && x.type === 'change').length,
            escape: window.runtimeListeners.filter(x => x.target === document && x.type === 'keydown').length,
        }));
        await page.evaluate(() => document.querySelectorAll('ui-sidebar, ui-tooltip, ui-toast').forEach(el => el.remove()));
        const after = await page.evaluate(() => ({
            sidebar: window.runtimeListeners.filter(x => x.target === document && x.type === 'flux-sidebar-toggle').length,
            focus: window.runtimeListeners.filter(x => x.target === document && x.type === 'focusin').length,
            media: window.runtimeListeners.filter(x => x.target instanceof MediaQueryList && x.type === 'change').length,
            escape: window.runtimeListeners.filter(x => x.target === document && x.type === 'keydown').length,
        }));
        for (const name of Object.keys(before)) assert.equal(after[name], before[name] - 1, `${name} listener cleanup`);
    });

    test(`${asset}: sidebar stays active while a collapsed group dropdown is open`, async t => {
        const page = await fixture(t, asset, '<ui-sidebar collapsible="true" persist="false"><ui-dropdown data-flux-sidebar-group-dropdown><button>Group</button><ui-menu popover><button>First item</button></ui-menu></ui-dropdown></ui-sidebar>');
        await page.locator('ui-dropdown > button').focus();
        await page.keyboard.press('Enter');
        await page.waitForFunction(() => document.querySelector('ui-menu button') === document.activeElement);
        assert.equal(await page.locator('ui-sidebar').getAttribute('data-flux-sidebar-active'), '');
        await page.evaluate(() => { document.activeElement.blur(); document.querySelector('ui-sidebar').dispatchEvent(new Event('mouseleave')); });
        assert.equal(await page.locator('ui-sidebar').getAttribute('data-flux-sidebar-active'), '');
    });

    test(`${asset}: tooltip dropdown leaves scrolling unlocked and closes when its reference leaves clipping bounds`, async t => {
        const page = await fixture(t, asset, '<div style="height:80px;overflow:auto;width:250px" id="scroller"><ui-dropdown data-flux-tooltip><button>Tooltip</button><div popover>Hint</div></ui-dropdown><div style="height:1000px"></div></div>');
        await page.locator('button').click();
        assert.equal(await page.locator('html').getAttribute('data-flux-scroll-unlock'), null);
        await page.locator('#scroller').evaluate(el => { el.scrollTop = 500; });
        await page.waitForFunction(() => !document.querySelector('[popover]').matches(':popover-open'));
    });

    test(`${asset}: navmenu selection closes and hover menu opening preserves current focus`, async t => {
        const page = await fixture(t, asset, '<input id="current"><ui-dropdown><button>Navigation</button><nav data-flux-navmenu popover><button data-flux-navmenu-item>Choose</button></nav></ui-dropdown><ui-dropdown hover><button id="hover">Hover</button><ui-menu popover><button>Item</button></ui-menu></ui-dropdown>');
        await page.locator('ui-dropdown > button').first().click();
        await page.locator('[data-flux-navmenu-item]').click();
        await page.waitForFunction(() => !document.querySelector('nav').matches(':popover-open'));
        await page.locator('#current').focus();
        await page.locator('#hover').hover();
        await page.waitForFunction(() => document.querySelector('ui-menu').matches(':popover-open'));
        await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
        assert.equal(await page.locator('#current').evaluate(el => el === document.activeElement), true);
    });

    test(`${asset}: disclosures remain findable and defer hidden state until collapse transition completes`, async t => {
        const page = await fixture(t, asset, '<ui-disclosure><button>Details</button><div>Searchable text</div></ui-disclosure><ui-disclosure><button>Animated</button><div x-collapse>Animated text</div></ui-disclosure>');
        const details = page.locator('ui-disclosure > div');
        assert.equal(await details.first().getAttribute('hidden'), 'until-found');
        await details.first().dispatchEvent('beforematch');
        assert.equal(await details.first().getAttribute('hidden'), null);
        assert.equal(await page.locator('ui-disclosure > button').first().getAttribute('aria-expanded'), 'true');
        await page.locator('ui-disclosure > button').nth(1).click();
        await page.locator('ui-disclosure > button').nth(1).click();
        assert.equal(await details.nth(1).getAttribute('hidden'), null);
        await details.nth(1).evaluate(el => el.dispatchEvent(new TransitionEvent('transitionend', { propertyName: 'height', bubbles: true })));
        assert.equal(await details.nth(1).getAttribute('hidden'), 'until-found');
    });

    test(`${asset}: toast links hydrate safely and removing the last toast resets expansion`, async t => {
        const page = await fixture(t, asset, `<ui-toast-group popover="manual"><ui-toast>${toastTemplate}</ui-toast></ui-toast-group>`);
        await page.locator('ui-toast-group').evaluate(el => el.showToast({ duration: 0, slots: { text: '<b>Plain</b>' }, link: { text: '<em>Open</em>', href: '/details', navigate: true, download: true, target: '_self', rel: 'nofollow', onclick: 'alert(1)' } }));
        const link = page.locator('ui-toast-group > div a');
        assert.equal(await link.textContent(), '<em>Open</em>');
        assert.equal(await link.getAttribute('href'), '/details');
        assert.equal(await link.getAttribute('wire:navigate'), '');
        assert.equal(await link.getAttribute('download'), '');
        assert.equal(await link.getAttribute('onclick'), null);
        assert.equal(await page.locator('ui-toast-group b, ui-toast-group em').count(), 0);
        await page.locator('ui-toast-group > div').dispatchEvent('mouseenter');
        await page.locator('ui-toast-group > div').evaluate(el => el.destroyToast());
        assert.equal(await page.locator('ui-toast-group').evaluate(el => el.expanded), false);
    });

    test(`${asset}: combined bundle registers Free and Pro elements once`, async t => {
        const page = await fixture(t, asset, '');
        const registrations = await page.evaluate(() => window.runtimeRegistrations);
        assert.equal(new Set(registrations).size, registrations.length);
        for (const name of ['ui-modal', 'ui-otp', 'ui-sidebar', 'ui-calendar', 'ui-date-picker', 'ui-slider']) assert.ok(registrations.includes(name), name);
        assert.equal(await page.evaluate(() => typeof window.Alpine), 'undefined', 'the package must not install a second Alpine runtime');
    });

    test(`${asset}: Flux.toast forwards link options through its public event contract`, async t => {
        const page = await fixture(t, asset, '');
        const detail = await page.evaluate(() => {
            // The store needs Alpine's registration API; this fixture isolates event payloads.
            window.Alpine = { reactive: value => value, magic() {}, effect() {}, data() {} };
            document.dispatchEvent(new Event('alpine:init'));
            let payload;
            document.addEventListener('toast-show', event => { payload = event.detail; }, { once: true });
            window.Flux.toast('Saved', { duration: 0, link: { href: '/receipt', text: 'View receipt' } });
            return payload;
        });
        assert.deepEqual(detail, { slots: { text: 'Saved' }, dataset: {}, duration: 0, link: { href: '/receipt', text: 'View receipt' } });
    });
}
