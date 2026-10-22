// Renders one Inertia page with the production SSR bundle (M7.16 smoke test).
// Usage: node tests/fixtures/ssr-render.mjs <bundle> < page.json  → prints {"head":[...],"body":"..."}
// The bundle starts its own SSR server on import; listening is switched off here.
import http from 'node:http';
import { pathToFileURL } from 'node:url';

http.Server.prototype.listen = function listen() {
    return this;
};

// Keep stdout for the result: the bundle logs "Starting SSR server…".
console.log = (...args) => process.stderr.write(`${args.join(' ')}\n`);

const chunks = [];
for await (const chunk of process.stdin) {
    chunks.push(chunk);
}

try {
    const page = JSON.parse(Buffer.concat(chunks).toString('utf8'));
    const { default: render } = await import(
        pathToFileURL(process.argv[2]).href
    );
    const result = await render(page);
    // Exit only once the pipe has taken everything: large pages are written in chunks.
    process.stdout.write(JSON.stringify(result), () => process.exit(0));
} catch (error) {
    process.stderr.write(String(error?.stack ?? error));
    process.exit(1);
}
