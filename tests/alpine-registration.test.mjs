import assert from 'node:assert/strict';
import test from 'node:test';

test('one bootstrap registers all providers and reusable bindings exactly once per Alpine owner', async () => {
    const { registerAlpineComponents } = await import('../resources/js/alpine/register.js');
    const providers = new Map(), bindings = new Map();
    const Alpine = {
        data(name, factory) { assert.equal(providers.has(name), false, name); providers.set(name, factory); },
        bind(name, factory) { assert.equal(bindings.has(name), false, name); bindings.set(name, factory); },
    };
    registerAlpineComponents(Alpine);
    registerAlpineComponents(Alpine);
    for (const name of ['branchPickerDisabled', 'connectivity', 'guestDishDialog', 'guestInvite', 'guestMenu', 'httpForm', 'invitationClipboard', 'kitchenTimers', 'menuImagePicker', 'menuImagePresentationEditor', 'menuTranslations', 'menuWorkspace', 'notificationPanel', 'passkeyRegistration', 'passkeyVerification', 'securityClipboard', 'staffEditor', 'staffWorkspace', 'twoFactorChallenge', 'waiterSounds', 'workspaceNavigation', 'restaurantDashboard', 'catalogTransfer', 'catalogUpload', 'recoveryCodes']) {
        assert.equal(typeof providers.get(name), 'function', name);
    }
    assert.equal(providers.has('onboardingFocus'), false, 'The retired wizard must not register a second focus owner.');
    assert.equal(typeof providers.get('settingsWorkspace'), 'function');
    assert.equal(typeof providers.get('passkeyRegistration')().register, 'function');
    assert.equal(typeof providers.get('passkeyVerification')().verify, 'function');
    for (const name of ['dialogLabel', 'focusInput', 'focusSelf', 'printDocument']) assert.equal(typeof bindings.get(name), 'function', name);
    const second = [];
    registerAlpineComponents({ data: name => second.push(name), bind() {} });
    assert.equal(second.length, providers.size);
});
