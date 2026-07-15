<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

// Shared honeypot + submission-timing anti-bot check, used by every public form that needs it
// (registration, reset-password request...) so the heuristic lives in one place instead of
// being copy-pasted into each scaffolded Form/Controller pair
class FormBotProtection
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    // Honeypot: real users never see or fill this field (hidden inline, no dependency on any
    // CSS framework class being available), so any non-empty value here means a bot filled
    // every input blindly
    public function addHoneypotField(FormBuilderInterface $builder): void
    {
        $offscreen = 'position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;';

        $builder->add('website', null, [
            'label' => 'Website',
            'label_attr' => ['style' => $offscreen],
            'required' => false,
            'mapped' => false,
            'data' => '',
            'row_attr' => ['style' => $offscreen],
            'attr' => [
                'placeholder' => 'Website',
                'autocomplete' => 'off',
                'tabindex' => '-1',
                'aria-hidden' => 'true',
            ],
        ]);
    }

    // Timestamps when the form was first displayed, to later measure how fast it was filled -
    // call once, right after building the GET response, before isSuspicious()
    public function startTimer(Request $request, string $sessionKey): void
    {
        $session = $request->getSession();
        if (null === $session->get($sessionKey)) {
            $session->set($sessionKey, time());
        }
    }

    // Bot detection: honeypot field filled, or form submitted faster than a human could fill it
    // (site-form-delay, in seconds). Call once per submission, after startTimer() populated the
    // same $sessionKey - the caller should silently redirect on true, with no hint to the bot
    public function isSuspicious(Request $request, FormInterface $form, string $sessionKey): bool
    {
        $session = $request->getSession();
        $startedAt = (int) $session->get($sessionKey, 0);
        $session->remove($sessionKey);

        // Falls back to 7s if "site-form-delay" isn't seeded, matching ContactFormBundle
        $formDelay = $this->configService->get('site-form-delay') ?? 7;

        return !empty($form->get('website')->getData())
            || (time() - $startedAt) < $formDelay;
    }
}
