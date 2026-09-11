<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

use c975L\SiteBundle\Entity\CollectionGroup;
use c975L\SiteBundle\Entity\CollectionItem;
use c975L\SiteBundle\Entity\Page;
use c975L\UiBundle\Contract\DemoFixtureLinkerInterface;
use c975L\UiBundle\Contract\DemoFixtureProviderInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Entity\Translation;
use c975L\UiBundle\Registry\PlaceholderMediaRegistry;
use c975L\UiBundle\Service\DemoFixtureTranslator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

/**
 * The made-up pages and collection a demo site is browsed for.
 *
 * Menus are deliberately left out. A site holds one menu per location - one navbar, one footer - so a dataset
 * adding its own would either collide with the site's or replace what it navigates by, and a demo site's
 * navigation is its own content, not something a reload may put back where it thinks it belongs. Same reading as
 * everywhere else here: this dataset only ever adds what it can take back.
 *
 * Everything a visitor reads is a key of the "site" domain rather than a sentence, so a demo seeded in Spanish
 * reads as a Spanish site. The slugs are ordinary words a small site would use, not "demo-" prefixed ones: a demo
 * is worth nothing if it does not look like the real thing.
 */
class SiteDemoFixtureProvider implements DemoFixtureLinkerInterface, DemoFixtureProviderInterface
{
    // The catalogue every sample text is read from, in the language the site is written in and in each of the others
    private const string DOMAIN = 'site';

    // What a rich-text block wraps its prose in, so a translation is stored exactly as the text it stands for
    private const string RICH_TEXT_WRAPPER = '<div>%s</div>';

    // Written down rather than taken from the clock: a demo site is reloaded often, and a date moving with each reload would have "published three days ago" say something else in every take of the same recorded sequence
    private const string CREATION_HOME = '2026-01-08';
    private const string CREATION_SERVICES = '2026-02-04';
    private const string CREATION_FORMER_OFFER = '2026-02-12';
    private const string CREATION_HISTORY = '2026-03-11';

    public function __construct(
        private readonly DemoFixtureTranslator $demoFixtureTranslator,
        private readonly TranslatorInterface $translator,
        private readonly PlaceholderMediaRegistry $placeholderMediaRegistry,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * The pages carry their blocks through the ORM cascade (see Page::$blocks, "cascade: persist"/"remove"), so a
     * page taken back by a reload leaves with them. The collection is the other way round: CollectionItem owns the
     * relation and nothing cascades off the group, so each item is yielded - and so recorded - on its own.
     */
    public function getDemoFixtures(): iterable
    {
        $images = $this->placeholderMediaRegistry->getImages();

        // "home" is what SiteBundle serves "/" from (see PageController::home): without it a demo answers 404 at its own front door. Its slug is unique, so a database already holding a home page refuses the whole load rather than half of it
        yield $this->home($images[0] ?? null);

        // The page the collection is read under: a collection is browsed through a "collection" block naming it as its source, and one left out of every page would only ever be back-office material
        $services = $this->page('nos-services', 'services', self::CREATION_SERVICES);
        $services->addBlock($this->collection());

        yield $services;

        yield $this->page('notre-histoire', 'history', self::CREATION_HISTORY);

        // One page already in the bin, which is what a bin is: a site that never binned anything shows an empty
        // screen where the two things a bin is for - putting a page back, or removing it for good - have nothing to
        // act on. Unpublished with it, the way trashing a page unpublishes it (see PageCrudController::trash)
        yield $this->page('ancienne-offre', 'former_offer', self::CREATION_FORMER_OFFER)
            ->setIsPublished(false)
            ->setIsDeleted(true);

        $group = new CollectionGroup()
            ->setName($this->trans('label.site_sample_collection_name'))
            ->setSlug('realisations');

        yield $group;

        $position = 0;

        foreach (['workshop', 'renovation', 'signage'] as $index => $key) {
            yield $this->item($group, $key, ++$position, $images[$index % max(1, \count($images))] ?? null);
        }
    }

    /**
     * The one page a visitor lands on, so it carries what the others do not: a picture, and a heading that says where
     * they are. Everything on it is ordinary back-office material - a hero and two alerts - precisely so that a
     * visitor can open it in the editor and recognise what they have just been reading.
     *
     * Alerts rather than sections for the two sentences that say what a demo is: what they tell a visitor is exactly
     * what an alert is for, and a visitor wanting to see a block edited will reach for the one that stands out.
     */
    private function home(?string $image): Page
    {
        $date = new \DateTime(self::CREATION_HOME);

        $page = new Page()
            ->setTitle($this->trans('label.site_sample_page_home_title'))
            ->setSlug('home')
            ->setIsPublished(true)
            ->setIsIndexable(false)
            ->setCreation($date)
            ->setModification($date);

        $this->demoFixtureTranslator->stage($page, PageTranslator::OWNER, self::DOMAIN, ['title' => 'label.site_sample_page_home_title']);

        $page->addBlock($this->hero($image));
        $page->addBlock($this->alert('info', 'label.site_sample_page_home_lead', 1));
        $page->addBlock($this->alert('warning', 'label.site_sample_page_home_body', 2));

        return $page;
    }

    // The keys an "alert" carries in the back office, "info" for what a demo offers and "warning" for what it takes back. The key is taken rather than the words, the block being staged for translation from the very key it was written from
    private function alert(string $type, string $contentKey, int $position): Block
    {
        $block = new Block()
            ->setKind('alert')
            ->setPosition($position)
            ->setData([
                'type' => $type,
                'content' => sprintf(self::RICH_TEXT_WRAPPER, $this->trans($contentKey)),
                'cssClasses' => null,
            ]);

        $this->demoFixtureTranslator->stage($block, Translation::OWNER_BLOCK, self::DOMAIN, ['content' => $contentKey], $this->richText(...));

        return $block;
    }

    // What a rich-text field stores around its prose: a translation written without it is the same words in another box, and the two read as two different texts to whatever compares them
    private function richText(string $value): string
    {
        return sprintf(self::RICH_TEXT_WRAPPER, $value);
    }

    // No button: a link stored in a block's data is a raw path, and a demo served under a prefix would send whoever clicks it back to the site around it
    private function hero(?string $image): Block
    {
        $hero = new Block()
            ->setKind('hero')
            ->setPosition(0)
            ->setData([
                'badge' => $this->trans('label.site_sample_page_home_badge'),
                'title' => '<div>' . $this->trans('label.site_sample_page_home_title') . '</div>',
                'subtitle' => '<div>' . $this->trans('label.site_sample_page_home_subtitle') . '</div>',
                'primaryLabel' => null,
                'primaryUrl' => null,
                'secondaryLabel' => null,
                'secondaryUrl' => null,
                'statValue' => null,
                'statLabel' => null,
                'anchor' => 'accueil',
                'hasBackgroundImage' => false,
                'titleLevel' => 'h1',
                'background' => 'primary',
                'mediaLayout' => 'grid',
            ]);

        // Two calls rather than one: the badge is stored as it is typed, the title and the subtitle inside their own box
        $this->demoFixtureTranslator->stage($hero, Translation::OWNER_BLOCK, self::DOMAIN, ['badge' => 'label.site_sample_page_home_badge']);
        $this->demoFixtureTranslator->stage($hero, Translation::OWNER_BLOCK, self::DOMAIN, [
            'title' => 'label.site_sample_page_home_title',
            'subtitle' => 'label.site_sample_page_home_subtitle',
        ], $this->richText(...));

        // A site declaring no placeholder gets the hero without its picture, which the template renders as readily
        if (null !== $image) {
            $file = $this->temporaryCopy($image);
            if (null !== $file) {
                // setFile() returns nothing, unlike the setters around it, so the two calls stay apart
                $media = new Media()->setAlt($this->trans('label.site_sample_page_home_title'));
                $media->setFile($file);

                $this->demoFixtureTranslator->stage($media, Translation::OWNER_MEDIA, self::DOMAIN, ['alt' => 'label.site_sample_page_home_title']);

                $hero->addMedia($media);
            }
        }

        return $hero;
    }

    // Two sections apiece - the shape an editor meets in the back office rather than a single wall of text; "nos-services" gets its collection block on top of them
    private function page(string $slug, string $key, string $creation): Page
    {
        $date = new \DateTime($creation);

        $page = new Page()
            ->setTitle($this->trans('label.site_sample_page_' . $key . '_title'))
            ->setSlug($slug)
            ->setIsPublished(true)
            ->setIsIndexable(false)
            ->setCreation($date)
            ->setModification($date);

        $this->demoFixtureTranslator->stage($page, PageTranslator::OWNER, self::DOMAIN, ['title' => 'label.site_sample_page_' . $key . '_title']);

        $page->addBlock($this->textSection('label.site_sample_page_' . $key . '_lead', 0));
        $page->addBlock($this->textSection('label.site_sample_page_' . $key . '_body', 1));

        return $page;
    }

    // The keys a "text_section" carries in the back office, filled or left null exactly as a saved one is
    private function textSection(string $contentKey, int $position): Block
    {
        $block = new Block()
            ->setKind('text_section')
            ->setPosition($position)
            ->setData([
                'title' => null,
                'slug' => '',
                'content' => sprintf(self::RICH_TEXT_WRAPPER, $this->trans($contentKey)),
                'image' => null,
                'eyebrow' => null,
                'tone' => 'normal',
                'background' => null,
                'cssClasses' => null,
            ]);

        $this->demoFixtureTranslator->stage($block, Translation::OWNER_BLOCK, self::DOMAIN, ['content' => $contentKey], $this->richText(...));

        return $block;
    }

    // The keys a "collection" carries in the back office, its source naming the group yielded below - resolved at render time by CollectionItemSourceProvider, so the order the two are recorded in does not matter
    private function collection(): Block
    {
        $block = new Block()
            ->setKind('collection')
            ->setPosition(2)
            ->setData([
                'anchor' => 'realisations',
                'source' => 'site.collection.realisations',
                'limit' => null,
                'order' => '',
                'eyebrow' => null,
                'title' => $this->trans('label.site_sample_collection_name'),
                'linkLabel' => null,
                'linkUrl' => null,
                'detailPage' => null,
                'variant' => '',
            ]);

        $this->demoFixtureTranslator->stage($block, Translation::OWNER_BLOCK, self::DOMAIN, ['title' => 'label.site_sample_collection_name']);

        return $block;
    }

    // The very same demo site said in each of the other languages it declares, its keys already written there (see DemoFixtureTranslator). A collection item is left out, the demo dataset holding no "site" key for one
    /** @return iterable<object> */
    public function getLinkedDemoFixtures(): iterable
    {
        return $this->demoFixtureTranslator->translations();
    }

    private function item(CollectionGroup $group, string $key, int $position, ?string $image): CollectionItem
    {
        $item = new CollectionItem()
            ->setCollectionGroup($group)
            ->setTitle($this->trans('label.site_sample_project_' . $key . '_title'))
            ->setSlug($key)
            ->setDescription($this->trans('label.site_sample_project_' . $key . '_description'))
            // No url, for the same reason the hero carries no button - and "#" would be worse than none: the card renders a button labelled with it, and the portfolio variant takes it for a real link
            ->setUrl(null)
            ->setPosition($position);

        // A site declaring no placeholder leaves the card without its picture rather than with a broken one - the collection block renders it either way, which an empty collection would not
        if (null !== $image) {
            $file = $this->temporaryCopy($image);
            if (null !== $file) {
                $item->setFile($file);
            }
        }

        return $item;
    }

    /**
     * VichUploader moves the file it is handed, so what it gets is a copy: the placeholder itself is read by every
     * other showcase of the site, and would be gone after the first load.
     *
     * A ReplacingFile rather than a plain File, which UploadHandler::hasUploadedFile() leaves silently ignored -
     * the row would be written with no file name and nothing would reach the disk.
     */
    private function temporaryCopy(string $publicPath): ?ReplacingFile
    {
        $source = $this->projectDir . '/public/' . $publicPath;
        if (!is_file($source)) {
            return null;
        }

        $target = sys_get_temp_dir() . '/c975l-demo-' . uniqid() . '-' . basename($publicPath);

        return copy($source, $target) ? new ReplacingFile($target, true, true, true) : null;
    }

    private function trans(string $key): string
    {
        return $this->translator->trans($key, [], self::DOMAIN);
    }
}
