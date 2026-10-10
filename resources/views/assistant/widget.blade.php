<div
    x-data="assistantWidget(@js([
        'chatUrl' => route('assistant.chat'),
        'historyUrl' => route('assistant.history'),
        'confirmUrl' => url('/api/assistant/actions'),
    ]))"
    x-on:keydown.escape.window="open = false"
    class="assistant-widget"
    style="position: fixed; right: 1rem; bottom: 1rem; z-index: 60; font-family: inherit;"
>
    <button type="button" id="assistant-toggle" x-on:click="toggle()" :aria-expanded="open.toString()" aria-controls="assistant-panel"
            class="fi-btn fi-color-primary fi-bg-color-600 text-white" style="border-radius: 9999px; padding: .6rem 1rem; box-shadow: 0 4px 14px rgba(0,0,0,.25); background: var(--primary-600, #166534); color: #fff;">
        <span aria-hidden="true">✦</span> <span>Assistant</span>
    </button>

    <section id="assistant-panel" x-show="open" x-cloak role="dialog" aria-label="Assistant"
             style="position: absolute; right: 0; bottom: 3.2rem; width: min(24rem, calc(100vw - 2rem)); height: min(32rem, 70vh); display: flex; flex-direction: column; background: #fff; color: #111; border: 1px solid #d1d5db; border-radius: .75rem; box-shadow: 0 10px 30px rgba(0,0,0,.25);">
        <header style="display: flex; justify-content: space-between; align-items: center; padding: .6rem .8rem; border-bottom: 1px solid #e5e7eb;">
            <strong>Assistant</strong>
            <span>
                <button type="button" x-on:click="clearHistory()" style="font-size: .75rem; text-decoration: underline; margin-right: .5rem;">Clear history</button>
                <button type="button" x-on:click="open = false" aria-label="Close assistant">✕</button>
            </span>
        </header>

        <div x-ref="log" role="log" aria-live="polite" style="flex: 1; overflow-y: auto; padding: .8rem; display: flex; flex-direction: column; gap: .5rem; font-size: .875rem;">
            <p x-show="messages.length === 0" style="color: #6b7280;">Ask where to find something, what is wrong on this page, or about your own data.</p>
            <template x-for="(m, i) in messages" :key="i">
                <div>
                    <template x-if="m.kind === 'text'">
                        <div data-testid="assistant-message" :data-role="m.role" :style="m.role === 'user' ? 'align-self:flex-end;background:#e0f2fe;' : 'background:#f3f4f6;'" style="padding: .45rem .65rem; border-radius: .6rem; white-space: pre-wrap;" x-text="m.text"></div>
                    </template>
                    <template x-if="m.kind === 'link'">
                        <div data-testid="assistant-link-card" style="border: 1px solid #d1d5db; border-radius: .6rem; padding: .5rem;">
                            <div style="font-weight: 600" x-text="m.title"></div>
                            <div style="color: #6b7280; font-size: .75rem" x-text="m.menuPath"></div>
                            <ol x-show="m.steps && m.steps.length" style="margin: .3rem 0 .3rem 1rem; list-style: decimal; font-size: .75rem;"><template x-for="step in m.steps" :key="step"><li x-text="step"></li></template></ol>
                            <a :href="m.url" data-testid="assistant-link" style="display: inline-block; margin-top: .3rem; padding: .25rem .7rem; border-radius: .4rem; background: var(--primary-600, #166534); color: #fff; text-decoration: none;">Open</a>
                        </div>
                    </template>
                    <template x-if="m.kind === 'confirm'">
                        <div data-testid="assistant-confirm" :data-status="m.status" style="border: 2px solid #f59e0b; border-radius: .6rem; padding: .5rem;">
                            <div style="font-weight: 600" x-text="m.title"></div>
                            <div x-text="m.summary" style="font-size: .8rem"></div>
                            <pre style="font-size: .7rem; background: #f9fafb; padding: .3rem; overflow-x: auto;" x-text="JSON.stringify(m.preview, null, 1)"></pre>
                            <div x-show="m.status === 'pending'" style="display: flex; gap: .5rem; margin-top: .3rem;">
                                <button type="button" data-testid="assistant-confirm-yes" x-on:click="decide(m, 'confirm')" style="padding: .25rem .8rem; border-radius: .4rem; background: #166534; color: #fff;">Confirm</button>
                                <button type="button" data-testid="assistant-confirm-no" x-on:click="decide(m, 'cancel')" style="padding: .25rem .8rem; border-radius: .4rem; background: #e5e7eb;">Cancel</button>
                            </div>
                            <div x-show="m.status !== 'pending'" data-testid="assistant-confirm-result" style="font-size: .8rem; color: #374151;" x-text="m.resultText"></div>
                        </div>
                    </template>
                    <template x-if="m.kind === 'note'"><div style="color: #6b7280; font-size: .75rem" x-text="m.text"></div></template>
                </div>
            </template>
            <div x-show="busy" style="color: #6b7280; font-size: .8rem">Thinking…</div>
        </div>

        <form x-on:submit.prevent="send()" style="display: flex; gap: .4rem; padding: .6rem; border-top: 1px solid #e5e7eb;">
            <label class="sr-only" for="assistant-input" style="position:absolute;left:-9999px">Message</label>
            <input id="assistant-input" type="text" x-model="draft" maxlength="2000" autocomplete="off" placeholder="Ask the assistant…" style="flex: 1; border: 1px solid #d1d5db; border-radius: .4rem; padding: .35rem .5rem; color: #111;" />
            <button type="submit" id="assistant-send" :disabled="busy || draft.trim() === ''" style="padding: .35rem .8rem; border-radius: .4rem; background: var(--primary-600, #166534); color: #fff;">Send</button>
        </form>
    </section>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('assistantWidget', (config) => ({
            open: false, busy: false, draft: '', messages: [], loaded: false,
            csrf() { return document.querySelector('meta[name="csrf-token"]')?.content ?? ''; },
            async toggle() {
                this.open = !this.open;
                if (this.open && !this.loaded) { await this.loadHistory(); }
                this.$nextTick(() => document.getElementById('assistant-input')?.focus());
            },
            async loadHistory() {
                this.loaded = true;
                try {
                    const response = await fetch(config.historyUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                    const data = await response.json();
                    this.messages = data.messages.map((m) => m.role === 'action'
                        ? { kind: 'confirm', id: m.id, title: m.content, summary: '', preview: m.preview, status: m.status ?? 'pending', resultText: m.status === 'confirmed' ? 'Confirmed.' : (m.status === 'cancelled' ? 'Cancelled.' : '') }
                        : { kind: 'text', role: m.role, text: m.content });
                } catch (e) { /* history is optional */ }
            },
            context() {
                return {
                    route: window.location.pathname, url: window.location.href, title: document.title,
                    errors: [...document.querySelectorAll('.fi-fo-field-wrp-error-message, [data-validation-error]')].map((e) => e.textContent.trim()).filter(Boolean).slice(0, 10),
                };
            },
            scroll() { this.$nextTick(() => { const log = this.$refs.log; log.scrollTop = log.scrollHeight; }); },
            async send() {
                const text = this.draft.trim();
                if (!text || this.busy) { return; }
                this.messages.push({ kind: 'text', role: 'user', text });
                this.draft = ''; this.busy = true; this.scroll();
                try {
                    const response = await fetch(config.chatUrl, {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', Accept: 'text/event-stream', 'X-CSRF-TOKEN': this.csrf() },
                        body: JSON.stringify({ message: text, context: this.context() }),
                    });
                    if (!response.ok) { this.messages.push({ kind: 'note', text: response.status === 429 ? 'Too many messages. Please wait a moment.' : 'The assistant could not answer.' }); return; }
                    const reader = response.body.getReader(); const decoder = new TextDecoder(); let buffer = '';
                    while (true) {
                        const { value, done } = await reader.read();
                        if (done) { break; }
                        buffer += decoder.decode(value, { stream: true });
                        let index;
                        while ((index = buffer.indexOf('\n\n')) !== -1) { this.handle(buffer.slice(0, index)); buffer = buffer.slice(index + 2); this.scroll(); }
                    }
                } catch (e) {
                    this.messages.push({ kind: 'note', text: 'Connection problem. Please try again.' });
                } finally { this.busy = false; this.scroll(); }
            },
            handle(raw) {
                const data = raw.split('\n').find((line) => line.startsWith('data:'));
                if (!data) { return; }
                let event; try { event = JSON.parse(data.slice(5).trim()); } catch (e) { return; }
                if (event.type === 'text') { this.messages.push({ kind: 'text', role: 'assistant', text: event.text }); }
                else if (event.type === 'link') { this.messages.push({ kind: 'link', ...event }); }
                else if (event.type === 'confirm') { this.messages.push({ kind: 'confirm', id: event.id, title: event.title, summary: event.summary, preview: event.preview, status: 'pending', resultText: '' }); }
            },
            async decide(message, action) {
                const response = await fetch(`${config.confirmUrl}/${message.id}/${action}`, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf() } });
                const data = await response.json().catch(() => ({}));
                message.status = action === 'confirm' ? (data.ok ? 'confirmed' : 'failed') : 'cancelled';
                message.resultText = action === 'confirm' ? (data.ok ? `Done: ${data.summary}` : `Could not do it: ${data.summary ?? 'error'}`) : 'Cancelled — nothing changed.';
                this.scroll();
            },
            async clearHistory() {
                await fetch(config.historyUrl, { method: 'DELETE', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf() } });
                this.messages = [];
            },
        }));
    });
</script>
