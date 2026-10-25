<?php

/*
|--------------------------------------------------------------------------
| Trusted proxies (M12.18)
|--------------------------------------------------------------------------
|
| Read by Laravel's TrustProxies middleware. Behind Cloudflare and the server's own proxy,
| the request's address is the last proxy's unless that proxy is trusted here, which breaks
| the per-IP rate limiters, Turnstile's remoteip and the Daraja IP allowlist. A comma list
| of addresses or CIDR ranges, or `*` when the origin only accepts traffic from the proxies
| (for example a firewall that admits Cloudflare's ranges only). Empty trusts nothing.
|
*/

return [

    'proxies' => env('TRUSTED_PROXIES') ?: null,

];
