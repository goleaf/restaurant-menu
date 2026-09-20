import assert from 'node:assert/strict';
import test from 'node:test';

test('feature modules export factories without accessing a browser or registering handlers on import', async () => {
    const groups = {
        'menu-workspace': ['menuWorkspace'],
        'settings-workspace': ['settingsWorkspace'],
        'menu-translations': ['menuTranslations'],
        'menu-image-picker': ['menuImagePicker', 'menuImagePresentationEditor'],
        'staff-workspace': ['staffWorkspace', 'staffEditor', 'invitationClipboard'],
        'navigation-search': ['workspaceNavigation'],
        'kitchen-timers': ['kitchenTimers'],
        'waiter-sounds': ['waiterSounds'],
    };
    for (const [file, names] of Object.entries(groups)) {
        const module = await import(`../resources/js/alpine/components/${file}.js`);
        for (const name of names) assert.equal(typeof module[name], 'function', name);
    }
});
