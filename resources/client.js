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

    source.onmessage = (event) => {
        const payload = JSON.parse(event.data);
        if (!payload.id) {
            return;
        }

        const element = document.getElementById(payload.id);
        if (element) {
            Idiomorph.morph(element, payload.html);
        }
    };

    return source;
}
