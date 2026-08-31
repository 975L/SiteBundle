<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Says whether a "twig_content" block may include the template path it carries.
 *
 * That path is typed into the back office, and the block includes it as-is: without this, anyone who can edit a
 * page's blocks has the site render any template Twig can reach, its own and every installed bundle's, out of the
 * context it was written for. Twig's loader already refuses to leave the directories it knows, so this is not
 * about reading arbitrary files - it is about who decides what a page renders.
 *
 * The rule is that a template a site wrote for itself is fair game, and nothing else is.
 */
class TwigContentTemplateChecker
{
    /**
     * The one namespaced path allowed, the example BlockFixtureProvider writes.
     *
     * A demonstration block has to point somewhere, and the bundle's own example is the only template shipped for
     * that purpose - excluding it would have the bundle break its own fixture.
     */
    private const string ALLOWED_NAMESPACE_PREFIX = '@c975LSite/examples/';

    public function __construct(
        #[Autowire(param: 'twig.default_path')]
        private readonly string $defaultPath,
        private readonly LoggerInterface $logger,
    ) {
    }

    // Answers on the path as written, never on the one it resolves to: a symlinked templates/ directory would make a resolved path land outside, which a containment check would take for the traversal it is meant to stop
    public function isAllowed(?string $templatePath): bool
    {
        $templatePath = trim((string) $templatePath);

        // An empty path is a block that never carried one, not a refusal worth a log line
        if ('' === $templatePath) {
            return false;
        }

        // The null byte is refused with the traversal rather than left to is_file(), which raises a ValueError on it - a crafted post would answer a 500 where it should read as a plain refusal
        if (str_contains($templatePath, '..') || str_contains($templatePath, "\0")) {
            return $this->refuse($templatePath);
        }

        if (str_starts_with($templatePath, self::ALLOWED_NAMESPACE_PREFIX)) {
            return is_file($this->bundleTemplatesPath($templatePath)) ? true : $this->refuse($templatePath);
        }

        // Every other namespace is somebody else's: a bundle's templates are written for the screens that bundle renders, and are not this site's to hang on a page
        if (str_starts_with($templatePath, '@')) {
            return $this->refuse($templatePath);
        }

        return is_file($this->defaultPath . '/' . $templatePath) ? true : $this->refuse($templatePath);
    }

    // Leaves a trace of what the block was pointing at: the page renders nothing where the block was, and a site updating within ^8 would otherwise lose a section with nothing anywhere to explain it
    private function refuse(string $templatePath): false
    {
        $this->logger->warning('twig_content block refused a template path', ['path' => $templatePath]);

        return false;
    }

    // "@c975LSite/examples/x.html.twig" read against the bundle's own templates/ directory
    private function bundleTemplatesPath(string $templatePath): string
    {
        return \dirname(__DIR__, 2) . '/templates/' . substr($templatePath, \strlen('@c975LSite/'));
    }
}
