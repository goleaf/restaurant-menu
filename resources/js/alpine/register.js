import { branchPickerDisabled } from './components/branch-picker.js';
import { availabilityWorkspace } from './components/availability-workspace.js';
import { floorWorkspace } from './components/floor-workspace.js';
import { connectivity } from './components/connectivity.js';
import { guestDishDialog, guestMenu } from './components/guest-menu.js';
import { guestInvite } from './components/guest-invite.js';
import { httpForm } from './components/http-form.js';
import { kitchenTimers } from './components/kitchen-timers.js';
import { menuImagePicker, menuImagePresentationEditor } from './components/menu-image-picker.js';
import { menuTranslations } from './components/menu-translations.js';
import { settingsWorkspace } from './components/settings-workspace.js';
import { menuWorkspace } from './components/menu-workspace.js';
import { workspaceNavigation } from './components/navigation-search.js';
import { notificationPanel } from './components/notification-panel.js';
import { passkeyRegistration, passkeyVerification } from './components/passkeys.js';
import { catalogTransfer, catalogUpload, recoveryCodes, restaurantDashboard } from './components/presentation.js';
import { securityClipboard } from './components/security-clipboard.js';
import { restoreUpload } from './components/restore-upload.js';
import { invitationClipboard, staffEditor, staffWorkspace } from './components/staff-workspace.js';
import { twoFactorChallenge } from './components/two-factor.js';
import { waiterSounds } from './components/waiter-sounds.js';
import { dialogLabel, focusInput, focusSelf, printDocument } from './bindings/presentation.js';
import { createPasskeysAdapter } from '../integrations/passkeys.js';

const registered = new WeakSet();

export function registerAlpineComponents(Alpine) {
    if (registered.has(Alpine)) return;

    const passkeys = createPasskeysAdapter();
    const providers = {
        availabilityWorkspace, branchPickerDisabled, connectivity, floorWorkspace, guestDishDialog, guestInvite, guestMenu, httpForm,
        invitationClipboard, kitchenTimers, menuImagePicker, menuImagePresentationEditor,
        menuTranslations, menuWorkspace, settingsWorkspace, notificationPanel, securityClipboard, staffEditor,
        staffWorkspace, twoFactorChallenge, waiterSounds, workspaceNavigation,
        catalogTransfer, catalogUpload, recoveryCodes, restaurantDashboard, restoreUpload,
        passkeyRegistration: () => passkeyRegistration(passkeys),
        passkeyVerification: () => passkeyVerification(passkeys),
    };

    Object.entries(providers).forEach(([name, factory]) => Alpine.data(name, factory));
    Object.entries({ dialogLabel, focusInput, focusSelf, printDocument }).forEach(([name, binding]) => Alpine.bind(name, binding));
    registered.add(Alpine);
}
