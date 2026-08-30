/**
 * Browser-side half of the push-hub protocol: opens one EventSource per
 * channel and morphs the matching element in place whenever the hub
 * broadcasts an update. No polling, no manual reconnect logic needed —
 * EventSource retries the connection natively.
 *
 * Uses Idiomorph (vendored in ./vendor/idiomorph/) to patch only what
 * changed, instead of a wholesale outerHTML replacement — this preserves
 * focus, scroll position and any DOM/CSS state on parts of the component
 * that didn't actually change.
 */
export function connectPushChannel(channel, { hubOrigin = 'http://127.0.0.1:8081' } = {}) {
    const source = new EventSource(`${hubOrigin}/subscribe?channel=${encodeURIComponent(channel)}`);

    source.onopen = () => {
        console.log(`[push-hub] connected: ${channel}`);
    };

    source.onerror = () => {
        console.error(`[push-hub] connection error on channel "${channel}" (readyState=${source.readyState})`);
    };

    source.onmessage = (event) => {
        try {
            const payload = JSON.parse(event.data);

            if (payload.reload) {
                console.log(`[push-hub] reload signal on channel "${channel}" — reloading`);
                location.reload();
                return;
            }

            if (!payload.id) {
                console.warn('[push-hub] message with no id, ignoring', payload);
                return;
            }

            const element = document.getElementById(payload.id);
            if (!element) {
                console.warn(`[push-hub] no element with id "${payload.id}" found in the DOM`);
                return;
            }

            if (typeof Idiomorph === 'undefined') {
                console.error('[push-hub] Idiomorph is not loaded — falling back to outerHTML replace');
                element.outerHTML = payload.html;
                return;
            }

            Idiomorph.morph(element, payload.html);
            console.log(`[push-hub] morphed #${payload.id} from channel "${channel}"`);
        } catch (error) {
            console.error('[push-hub] failed to handle pushed update', error);
        }
    };

    return source;
}
