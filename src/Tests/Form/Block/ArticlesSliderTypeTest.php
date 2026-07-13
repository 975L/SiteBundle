<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Form\Block;

use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Form\Block\ArticlesSliderType;
use c975L\SiteBundle\Repository\PageRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Test\TypeTestCase;

// Lives under src/Tests (not a sibling tests/ dir) so it stays autoloadable by consuming apps,
// whose attribute route loader recursively reflects every class under the bundle root
class ArticlesSliderTypeTest extends TypeTestCase
{
    private PageRepository $pageRepository;

    protected function setUp(): void
    {
        $page = (new Page())->setTitle('About')->setSlug('about');
        $this->pageRepository = $this->createStub(PageRepository::class);
        $this->pageRepository->method('findAllOrdered')->willReturn([$page]);

        // TypeTestCase would otherwise create a bare, unconfigured mock for this - PHPUnit 13 flags
        // that as a notice ("no expectations configured"); a stub is the correct double for it anyway
        $this->dispatcher = $this->createStub(EventDispatcherInterface::class);

        parent::setUp();
    }

    protected function getTypes(): array
    {
        return [new ArticlesSliderType($this->pageRepository)];
    }

    // A genuinely new/empty block gets sane defaults - using the "data" form option instead would
    // silently reset these on every save (see the comment in ArticlesSliderType)
    public function testEmptyDataGetsDefaultDurationAndRatio(): void
    {
        $form = $this->factory->create(ArticlesSliderType::class, []);

        $this->assertSame(3500, $form->get('duration')->getData());
        $this->assertSame('free', $form->get('ratio')->getData());
    }

    // An existing block's stored duration/ratio must survive PRE_SET_DATA untouched
    public function testExistingDurationAndRatioAreNotOverwritten(): void
    {
        $form = $this->factory->create(ArticlesSliderType::class, ['duration' => 5000, 'ratio' => '16-9']);

        $this->assertSame(5000, $form->get('duration')->getData());
        $this->assertSame('16-9', $form->get('ratio')->getData());
    }
}
