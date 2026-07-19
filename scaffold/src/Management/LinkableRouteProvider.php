<?php

namespace App\Management;

use c975L\ConfigBundle\Management\LinkableRouteProviderInterface;

// Exposes this app's own authentication routes (login/logout/register/forgot password), so they can be picked as a SiteBundle Menu item (navbar/footer) - only routes with no required parameter are listed here, as the others (email verification, password reset) are only ever reached through a signed link and are not meant to be linked to directly
class LinkableRouteProvider implements LinkableRouteProviderInterface
{
    public function getLinkableRoutes(): array
    {
        return [
            'app_login' => [
                'label' => 'label.login',
                'translation_domain' => 'site',
            ],
        ];
    }
}
