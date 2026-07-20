<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Service\Security;

use Nelmio\SecurityBundle\ContentSecurityPolicy\NonceGeneratorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

// NelmioSecurityBundle's default generator returns a fresh random nonce per request, but Turbo Drive/Frames/Streams re-merge/re-execute <script> tags from fetched HTML into the already-loaded document without a real navigation, so a per-request nonce mismatches the CSP header the browser already enforces and every re-executed script gets blocked - storing the nonce in session keeps it stable for the whole visit, same fix as turbo-rails documents for this exact issue
class SessionNonceGenerator implements NonceGeneratorInterface
{
    private const SESSION_KEY = 'csp_nonce';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function generate(): string
    {
        if (!$this->requestStack->getCurrentRequest()?->hasSession()) {
            return $this->randomNonce();
        }

        $session = $this->requestStack->getSession();

        $nonce = $session->get(self::SESSION_KEY);
        if (null === $nonce) {
            // Storing an attribute (rather than deriving from the session id) also matters here: Symfony's SessionListener only sends the session cookie for a non-empty session, and start()-ing without ever writing to it leaves the session empty
            $nonce = $this->randomNonce();
            $session->set(self::SESSION_KEY, $nonce);
        }

        return $nonce;
    }

    // Same random nonce format for both the session-backed and session-less (fallback) paths
    private function randomNonce(): string
    {
        return bin2hex(random_bytes(16));
    }
}
