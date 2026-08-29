# Vendored: Idiomorph 0.3.0

Source: https://github.com/bigskysoftware/idiomorph
License: BSD-2-Clause (see `LICENSE` in this directory)

Vendored (not installed via a JS package manager) because bridge-mode targets
sites with no existing front-end build step — the client only needs a
`<script>` tag, nothing to compile.

Used by `../client.js` to morph a component's existing DOM node into its new
server-rendered HTML in place (preserving focus, scroll position, and any
node identity outside what actually changed) instead of a wholesale
`outerHTML` replacement.
