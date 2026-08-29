/**
 * Browser-side half of the push-hub protocol: opens one EventSource per
 * channel and replaces the matching element's outerHTML whenever the hub
 * broadcasts an update. No polling, no manual reconnect logic needed —
 * EventSource retries the connection natively.
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
            element.outerHTML = payload.html;
        }
    };

    return source;
}
