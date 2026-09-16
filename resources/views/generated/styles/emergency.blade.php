/* Generated from resources/scss/emergency.scss. Do not edit. */
.rm-emergency {
  margin: 0;
  background: #fafaf9;
  color: #18181b;
  font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

.rm-emergency-main {
  display: flex;
  min-height: 100vh;
  align-items: center;
  justify-content: center;
  padding: 24px;
}

.rm-emergency-panel {
  width: 100%;
  max-width: 640px;
  border: 1px solid #e4e4e7;
  border-radius: 8px;
  background: #ffffff;
  padding: 24px;
  overflow-wrap: anywhere;
}

.rm-emergency-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
  font-size: 14px;
  font-weight: 700;
}
.rm-emergency-brand svg {
  flex-shrink: 0;
}

.rm-emergency-status {
  margin: 0 0 12px;
  color: #71717a;
  font-size: 13px;
  font-weight: 700;
}

.rm-emergency-title {
  margin: 0;
  color: #18181b;
  font-size: 28px;
  line-height: 1.2;
}

.rm-emergency-message {
  margin: 12px 0 0;
  color: #3f3f46;
  font-size: 16px;
  line-height: 1.6;
}

.rm-emergency-hint {
  margin: 12px 0 0;
  color: #71717a;
  font-size: 14px;
  line-height: 1.6;
}

.rm-emergency-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 24px;
}

.rm-emergency-action {
  display: inline-flex;
  min-height: 44px;
  align-items: center;
  border-radius: 8px;
  background: #18181b;
  padding: 0 14px;
  color: #ffffff;
  font-size: 14px;
  font-weight: 600;
  text-decoration: none;
}
.rm-emergency-action:focus-visible {
  outline: 2px solid currentColor;
  outline-offset: 3px;
  box-shadow: 0 0 0 3px #18181b;
}
.rm-emergency-action--secondary {
  border: 1px solid #d4d4d8;
  background: transparent;
  color: #18181b;
}

@media (forced-colors: active) {
  .rm-emergency-action {
    border: 1px solid ButtonText;
    color: LinkText;
  }
  .rm-emergency-action:focus-visible {
    outline-color: Highlight;
  }
}
