import { menuWorkspace } from './menu-workspace.js';

const modelFor = group => group === 'profile' ? 'profileForm' : group;

export function settingsWorkspace() {
    const base = menuWorkspace({ modal: 'settings-unsaved', retainSectionDrafts: true, retainedParameters: ['section', 'language', 'group'], invalidEvent: 'settings-focus', invalidSelector: '[data-settings-active="true"] [aria-invalid="true"], [data-settings-active="true"] [role="alert"]' });
    return {
        ...base,
        groupDirty(group) {
            const model = modelFor(group);
            return Object.hasOwn(this.$wire.baselines, group) ? JSON.stringify(this.$wire.$get(model)) !== JSON.stringify(this.$wire.$get(`baselines.${group}`)) : false;
        },
        hasUnsavedChanges() {
            return Object.keys(this.$wire.baselines).some(group => this.groupDirty(group)) || Boolean(this.$wire.logo || this.$wire.cover);
        },
        discardAndNavigate() {
            for (const group of Object.keys(this.$wire.baselines)) {
                this.$wire.$set(modelFor(group), JSON.parse(JSON.stringify(this.$wire.$get(`baselines.${group}`))), false);
            }
            this.$wire.$set('logo', null, false);
            this.$wire.$set('cover', null, false);
            return base.discardAndNavigate.call(this);
        },
        discardGroupLocally(event, group) {
            if (!Object.hasOwn(this.$wire.baselines, group)) return;
            this.discardFormLocally(event, modelFor(group), `baselines.${group}`);
        },
    };
}
