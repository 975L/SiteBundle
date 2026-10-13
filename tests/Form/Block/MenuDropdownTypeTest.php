<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Form\Block;

use c975L\SiteBundle\Form\Block\MenuDropdownType;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

class MenuDropdownTypeTest extends TypeTestCase
{
    protected function setUp(): void
    {
        // A stub rather than TypeTestCase's bare mock, which PHPUnit 13 flags as a notice (see MenuLinkTypeTest)
        $this->dispatcher = $this->createStub(EventDispatcherInterface::class);

        parent::setUp();
    }

    // The "constraints" option of the label comes from the validator extension
    #[\Override]
    protected function getExtensions(): array
    {
        return [new ValidatorExtension(Validation::createValidator())];
    }

    // The title the navbar shows and who sees the whole menu, the same audiences as a link's
    public function testLabelAndVisibilityFieldsArePresent(): void
    {
        $form = $this->factory->create(MenuDropdownType::class);

        $this->assertTrue($form->has('label'));
        $this->assertTrue($form->get('label')->getConfig()->getRequired());
        $this->assertTrue($form->has('visibility'));
        $this->assertSame(
            ['label.menu_link_visibility_all' => 'all', 'label.menu_link_visibility_guests' => 'guests', 'label.menu_link_visibility_members' => 'members'],
            $form->get('visibility')->getConfig()->getOption('choices')
        );
    }

    // The "hideInApp" field (see MenuDropdown.html.twig / _navbar.scss's .menu-item--web-only) leaves the whole menu out of the installed app - optional, so a menu saved before it existed stays everywhere, and told by its own help
    public function testHideInAppFieldIsPresentAndOptional(): void
    {
        $form = $this->factory->create(MenuDropdownType::class);

        $this->assertTrue($form->has('hideInApp'));
        $this->assertFalse($form->get('hideInApp')->getConfig()->getRequired());
        $this->assertSame('help.menu_dropdown_hide_in_app', $form->get('hideInApp')->getConfig()->getOption('help'));
    }
}
