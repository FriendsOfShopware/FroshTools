const probePath = '/api/_action/frosh-tools/apps/reachability-probe';
const hexToken = /^[a-f0-9]{64}$/;

function json(data, status = 200) {
    return Response.json(data, { status, headers: { 'Cache-Control': 'no-store' } });
}

function publicShopUrl(value) {
    if (typeof value !== 'string') return null;
    try {
        const url = new URL(value);
        if (url.protocol !== 'https:' || url.username || url.password || url.search || url.hash) return null;
        if (url.port && url.port !== '443') return null;
        const host = url.hostname.toLowerCase().replace(/\.$/, '');
        if (!host.includes('.') || host.includes(':') || /^[\d.]+$/.test(host)) return null;
        if (/(^|\.)(localhost|local|internal|lan)$/.test(host)) return null;
        url.pathname = url.pathname.replace(/\/+$/, '') + probePath;
        return url;
    } catch {
        return null;
    }
}

export default {
    async fetch(request, env) {
        if (request.method !== 'POST') return json({ error: 'Use POST' }, 405);
        if (!env.CHECK_TOKEN) return json({ error: 'Worker access token is not configured' }, 503);
        if (request.headers.get('Authorization') !== `Bearer ${env.CHECK_TOKEN}`) {
            return json({ error: 'Unauthorized' }, 401);
        }
        if (Number(request.headers.get('Content-Length')) > 4096) return json({ error: 'Request too large' }, 413);
        let data;
        try {
            const body = await request.text();
            if (body.length > 4096) return json({ error: 'Request too large' }, 413);
            data = JSON.parse(body);
        } catch {
            return json({ error: 'Invalid JSON' }, 400);
        }
        const url = publicShopUrl(data?.appUrl);
        if (
            !url ||
            typeof data?.challenge !== 'string' ||
            !hexToken.test(data.challenge) ||
            typeof data?.expectedProof !== 'string' ||
            !hexToken.test(data.expectedProof)
        ) {
            return json({ error: 'A public HTTPS APP_URL and valid challenge and proof are required' }, 400);
        }
        url.searchParams.set('challenge', data.challenge);
        try {
            // Never forward the Worker access token or expected proof to the shop.
            const response = await fetch(url, {
                redirect: 'manual',
                signal: AbortSignal.timeout(5000),
                headers: { Accept: 'application/json', 'Cache-Control': 'no-store, no-cache' },
                cf: { cacheTtl: 0, cacheEverything: false },
            });
            if (response.status !== 200) {
                return json({
                    status: response.status >= 500 || response.status === 429 ? 'soft_fail' : 'hard_fail',
                    info: `APP_URL returned HTTP ${response.status} to the external reachability probe`,
                });
            }
            let result;
            try {
                result = await response.json();
            } catch {
                return json({ status: 'hard_fail', info: 'APP_URL returned an invalid verification response' });
            }
            if (result?.proof !== data.expectedProof) {
                return json({
                    status: 'hard_fail',
                    info: 'APP_URL did not return a valid reachability proof for this shop',
                });
            }
            return json({ status: 'pass', proof: result.proof, info: null });
        } catch {
            return json({ status: 'soft_fail', info: 'Cloudflare could not connect to APP_URL or the probe timed out' });
        }
    },
};
