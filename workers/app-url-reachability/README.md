# External APP_URL reachability Worker

A standalone Cloudflare Worker that calls a shop from outside its network and compares the returned proof with an expected proof. The FroshTools Apps tab delegates its reachability check to this Worker, using its own public probe endpoint.

## Request contract

The public service is deployed at `https://app-url-check.fos.gg/`. Send a POST with the following body:

```json
{
    "appUrl": "https://shop.example.com",
    "challenge": "<64 lowercase hexadecimal characters>",
    "expectedProof": "<64 lowercase hexadecimal characters>"
}
```

Use `Authorization: Bearer FroshTools`. The deployed `CHECK_TOKEN` is the public value `FroshTools`; it is a protocol marker, not a secret or protection against public use. The Worker requests `APP_URL/api/_action/frosh-tools/apps/reachability-probe?challenge=<challenge>` and expects HTTP 200 with `{ "proof": "<expectedProof>" }`. The plugin provides this public shop probe. Only the challenge is forwarded to the shop; the expected proof and public request token remain in the POST to the Worker.

A successful response is `{ "status": "pass", "proof": "...", "info": null }`. A wrong proof, malformed response, redirect, or non-retryable HTTP error returns `hard_fail`. Timeouts, connection failures, HTTP 429 and server errors return `soft_fail`. Worker request validation/header errors use HTTP 4xx; missing Worker configuration uses HTTP 503.

The caller should generate a fresh challenge for every check. FroshTools signs it using the Symfony application secret and supplies the expected proof to the Worker. No app ID or application secret needs to be sent to Cloudflare.

## Deploy

Use Node.js 22.18 or later. From this directory:

```sh
npm ci
npx cf auth login
```

For the public service, create a `.dev.vars` file (ignored by Git) containing:

```dotenv
CHECK_TOKEN=FroshTools
```

Deploy the Worker and upload its secret with:

```sh
npm run deploy -- --secrets-file .dev.vars
```

The deployment config publishes the Worker on the custom domain `app-url-check.fos.gg`. The `fos.gg` zone must be in the Cloudflare account used for deployment.

The shop must have a public HTTPS APP_URL. Redirects are reported as failures: use the canonical URL. This Worker supports standard HTTPS port 443 and rejects IP literals, local hostnames, embedded credentials and query strings in APP_URL.

Run `npm test` for the Worker tests, `npm run dev` for local development (loads `.dev.vars`), and `npm run build` to build the bundle. Run `npm run deploy -- --dry-run` to validate deployment without publishing. No database or KV bindings are required. The public service is already deployed; these commands are for subsequent deployments.

Deployment settings and the required secret are declared in `cloudflare.config.ts`. The Cloudflare CLI (`cf`, currently in beta) uses Wrangler as the build backend, with build settings in `wrangler.config.ts`.

See [Cloudflare fetch](https://developers.cloudflare.com/workers/runtime-apis/fetch/), [Cloudflare CLI migration](https://developers.cloudflare.com/cf/wrangler/migrate/), and [programmatic configuration](https://developers.cloudflare.com/cf/projects/cloudflare-config/).
