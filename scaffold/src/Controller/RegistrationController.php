<?php

namespace App\Controller;

use App\Repository\UserRepository;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\EmailVerifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

// Registration itself ("register" Form/FormField, honeypot, rate-limiting, password hashing, verification email) is now handled by the generic "form" Block/c975L\UiBundle\Controller\FormController + App\Service\RegisterFormAction, same mechanism as "contact" - this controller only keeps what a generic Form can't do: consuming the signed email-verification link.
class RegistrationController extends AbstractController
{
    public function __construct(
        private EmailVerifier $emailVerifier,
        private PageRepository $pageRepository,
    ) {
    }

    // Used where there is no Referer to return to (a signed email-verification link, opened fresh) - the real Page if one carries a "form" Block pointing at "register", else the home page
    private function redirectAfterVerification(): RedirectResponse
    {
        $page = $this->pageRepository->findOneByFormBlockName('register');

        return null !== $page
            ? $this->redirectToRoute('page_display', ['page' => $page->getSlug()])
            : $this->redirectToRoute('page_home');
    }

// VERIFY EMAIL
    #[Route(
        path: '/verification/email',
        name: 'app_verify_email',
        methods: ['GET']
    )]
    public function verifyUserEmail(Request $request, TranslatorInterface $translator, UserRepository $userRepository): Response
    {
        $id = $request->query->get('id');
        if (null === $id) {
            return $this->redirectAfterVerification();
        }

        $user = $userRepository->find($id);

        if (null === $user) {
            return $this->redirectAfterVerification();
        }

        // validate email confirmation link, sets User::isVerified=true and persists
        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));

            return $this->redirectAfterVerification();
        }

        $this->addFlash('success', $translator->trans('label.email_address_verified', [], 'site'));

        return $this->redirectToRoute('page_home');
    }
}
