export class Events {
    listeners = new Map();
    addEventListener(name, listener, options = {}) {
        const entries = this.listeners.get(name) ?? [];
        entries.push({ listener, options });
        this.listeners.set(name, entries);
    }
    removeEventListener(name, listener) {
        this.listeners.set(name, (this.listeners.get(name) ?? []).filter(entry => entry.listener !== listener));
    }
    dispatchEvent(event) {
        for (const entry of [...(this.listeners.get(event.type) ?? [])]) {
            if (entry.options.signal?.aborted) continue;
            entry.listener(event);
            if (entry.options.once) this.removeEventListener(event.type, entry.listener);
        }
    }
    listenerCount() {
        return [...this.listeners.values()].flat().filter(entry => !entry.options.signal?.aborted).length;
    }
}

export class Element extends Events {
    dataset = {};
    attributes = new Map();
    children = new Map();
    ancestors = new Map();
    isConnected = true;
    hidden = false;
    textContent = '';
    value = '';
    focused = 0;
    selected = 0;
    clicks = 0;
    setAttribute(name, value) { this.attributes.set(name, value); }
    removeAttribute(name) { this.attributes.delete(name); }
    getAttribute(name) { return this.attributes.get(name) ?? null; }
    hasAttribute(name) { return this.attributes.has(name); }
    querySelector(name) { return this.children.get(name)?.[0] ?? null; }
    querySelectorAll(name) { return this.children.get(name) ?? []; }
    contains(element) { return this === element || [...this.children.values()].flat().includes(element); }
    closest(name) { return this.ancestors.get(name) ?? (this.selector === name ? this : null); }
    focus() { this.focused++; }
    select() { this.selected++; }
    click() { this.clicks++; }
    getClientRects() { return this.hidden ? [] : [1]; }
}
export class Button extends Element {}
export class Time extends Element {}

export function browser(t) {
    const document = new Events();
    const window = new Events();
    const navigator = { onLine: true };
    const state = { now: 0, interceptors: new Set(), intervals: new Map(), nextId: 0, events: [] };
    window.setInterval = callback => { const id = ++state.nextId; state.intervals.set(id, callback); return id; };
    window.clearInterval = id => state.intervals.delete(id);
    window.Livewire = {
        interceptMessage(callback) { state.interceptors.add(callback); return () => state.interceptors.delete(callback); },
        navigate(url) { state.navigation = url; },
    };
    const globals = { window, document, navigator, Element, HTMLElement: Element, HTMLButtonElement: Button, HTMLTimeElement: Time };
    for (const [name, value] of Object.entries(globals)) {
        const original = Object.getOwnPropertyDescriptor(globalThis, name);
        Object.defineProperty(globalThis, name, { configurable: true, value });
        t.after(() => original ? Object.defineProperty(globalThis, name, original) : delete globalThis[name]);
    }
    t.mock.method(Date, 'now', () => state.now);
    return {
        document, window, navigator, state,
        tick(ms) { state.now += ms; for (const callback of [...state.intervals.values()]) callback(); },
        component(factory, config) {
            const instance = factory(config);
            instance.$el = new Element();
            instance.$refs = {};
            instance.$wire = { $id: 'component' };
            instance.$nextTick = callback => callback();
            instance.$watch = () => {};
            instance.$dispatch = (type, detail) => state.events.push({ type, detail });
            return instance;
        },
        message(component, actions = [], payload = {}) {
            const sent = [], finished = [], renders = [];
            for (const intercept of state.interceptors) intercept({
                message: { component, actions },
                onSend: callback => sent.push(callback),
                onFinish: callback => finished.push(callback),
                onSuccess: callback => callback({ payload, onRender: callback => renders.push(callback) }),
            });
            return { send() { sent.forEach(callback => callback()); }, finish() { renders.forEach(callback => callback()); finished.forEach(callback => callback()); } };
        },
    };
}

export function event(overrides = {}) {
    return { prevented: false, stopped: false, button: 0, preventDefault() { this.prevented = true; }, stopImmediatePropagation() { this.stopped = true; }, ...overrides };
}
