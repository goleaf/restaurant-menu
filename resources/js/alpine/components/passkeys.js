function passkeyState(adapter) {
    const owner = {};
    return {
        supported: false,
        loading: false,
        error: null,
        epoch: 0,
        configuration: {},
        init() {
            this.configuration = { ...this.$el.dataset };
            this.supported = adapter.isSupported();
        },
        cancelOperation() {
            this.epoch++;
            adapter.cancel(owner);
            this.loading = false;
        },
        destroy() { this.cancelOperation(); },
        async perform(operation, options, complete) {
            if (this.loading || !this.supported) return;
            this.loading = true;
            this.error = null;
            const epoch = ++this.epoch;
            try {
                const response = await adapter[operation](owner, options);
                if (epoch === this.epoch) await complete(response);
            } catch (error) {
                if (epoch === this.epoch && !adapter.isCancelled(error)) this.error = this.configuration.failure;
            } finally {
                if (epoch === this.epoch) this.loading = false;
            }
        },
    };
}

export function passkeyRegistration(adapter) {
    return {
        ...passkeyState(adapter),
        showForm: false,
        name: '',
        async register() {
            const name = this.name.trim();
            if (!name) return;
            await this.perform('register', { name }, async () => {
                this.name = '';
                this.showForm = false;
                await this.$wire.loadPasskeys();
            });
        },
        cancel() {
            this.cancelOperation();
            this.showForm = false;
            this.name = '';
            this.error = null;
        },
    };
}

export function passkeyVerification(adapter) {
    return {
        ...passkeyState(adapter),
        async verify() {
            await this.perform('verify', {
                routes: {
                    options: this.configuration.optionsUrl,
                    submit: this.configuration.submitUrl,
                },
            }, response => window.Livewire.navigate(response.redirect || this.configuration.fallbackUrl));
        },
    };
}
