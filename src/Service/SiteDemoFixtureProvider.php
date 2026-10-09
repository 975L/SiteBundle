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
use c975L\SiteBundle\Entity\Menu;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\MenuRepository;
use c975L\SiteBundle\Repository\PageRepository;
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

// 975L's own pages, collection and navbar, shown by the bundles' demo - every visible text a "site" key so a demo seeded in Spanish reads as a Spanish site, and slugs a real small site would use
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
        private readonly PageRepository $pageRepository,
        private readonly MenuRepository $menuRepository,
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
        yield $this->services();

        yield $this->history();

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

        // Three of 975L's own sites, each card leading to the real one and showing its screenshot when the site declares it ("site/<slug>", see PlaceholderMediaProviderInterface's "keyed_images"), a picture of the pool otherwise
        $projects = [
            'resistance-haute-savoie' => ['resistance', 'https://resistance-haute-savoie.fr'],
            'papa-calin' => ['papa_calin', 'https://papa-calin.com'],
            'run-as' => ['run_as', 'https://run.as'],
        ];

        foreach ($projects as $slug => [$key, $url]) {
            yield $this->item($group, $slug, $key, $url, ++$position, $this->placeholderMediaRegistry->getImagesFor('site/' . $slug)[0] ?? $images[($position - 1) % max(1, \count($images))] ?? null);
        }
    }

    // The one page a visitor lands on, laid out as a real home page is - a hero, then one section of each kind - its title hidden behind the hero's own h1
    private function home(?string $image): Page
    {
        $date = new \DateTime(self::CREATION_HOME);

        $page = new Page()
            ->setTitle($this->trans('label.site_sample_page_home_title'))
            ->setSlug('home')
            ->setIsPublished(true)
            ->setIsIndexable(false)
            ->setIsTitleDisplayed(false)
            ->setCreation($date)
            ->setModification($date);

        $this->demoFixtureTranslator->stage($page, PageTranslator::OWNER, self::DOMAIN, ['title' => 'label.site_sample_page_home_title']);

        $page->addBlock($this->hero($image));

        $page->addBlock($this->section('feature_bar', 1, ['anchor' => 'atouts', 'eyebrow' => null, 'title' => null], [
            'items.0.title' => 'label.site_sample_home_feature_edit_title',
            'items.0.text' => 'label.site_sample_home_feature_edit_text',
            'items.1.title' => 'label.site_sample_home_feature_languages_title',
            'items.1.text' => 'label.site_sample_home_feature_languages_text',
            'items.2.title' => 'label.site_sample_home_feature_bin_title',
            'items.2.text' => 'label.site_sample_home_feature_bin_text',
            'items.3.title' => 'label.site_sample_home_feature_symfony_title',
            'items.3.text' => 'label.site_sample_home_feature_symfony_text',
        ]));

        $page->addBlock($this->section('section_features', 2, [
            'anchor' => 'back-office',
            'variant' => '',
            'cards' => [
                ['icon' => 'bundles/c975lui/icons/pen-ruler.svg'],
                ['icon' => 'bundles/c975lui/icons/layer-group.svg'],
                ['icon' => 'bundles/c975lui/icons/eye.svg'],
            ],
        ], [
            'eyebrow' => 'label.site_sample_home_features_eyebrow',
            'title' => 'label.site_sample_home_features_title',
            'intro' => 'label.site_sample_home_features_intro',
            'cards.0.title' => 'label.site_sample_home_features_edit_title',
            'cards.1.title' => 'label.site_sample_home_features_compose_title',
            'cards.2.title' => 'label.site_sample_home_features_preview_title',
        ], [
            'cards.0.text' => 'label.site_sample_home_features_edit_text',
            'cards.1.text' => 'label.site_sample_home_features_compose_text',
            'cards.2.text' => 'label.site_sample_home_features_preview_text',
        ]));

        $page->addBlock($this->section('process_steps', 3, ['anchor' => 'essayez'], [
            'eyebrow' => 'label.site_sample_home_steps_eyebrow',
            'title' => 'label.site_sample_home_steps_title',
            'steps.0.title' => 'label.site_sample_home_step_open_title',
            'steps.1.title' => 'label.site_sample_home_step_login_title',
            'steps.2.title' => 'label.site_sample_home_step_edit_title',
            'steps.3.title' => 'label.site_sample_home_step_save_title',
        ], [
            'steps.0.text' => 'label.site_sample_home_step_open_text',
            'steps.1.text' => 'label.site_sample_home_step_login_text',
            'steps.2.text' => 'label.site_sample_home_step_edit_text',
            'steps.3.text' => 'label.site_sample_home_step_save_text',
        ]));

        $page->addBlock($this->section('faq', 4, ['anchor' => 'questions', 'openFirst' => true, 'columns' => 1], [
            'title' => 'label.site_sample_home_faq_title',
            'items.0.question' => 'label.site_sample_home_faq_install_question',
            'items.1.question' => 'label.site_sample_home_faq_translate_question',
            'items.2.question' => 'label.site_sample_home_faq_code_question',
            'items.3.question' => 'label.site_sample_home_faq_license_question',
        ], [
            'items.0.answer' => 'label.site_sample_home_faq_install_answer',
            'items.1.answer' => 'label.site_sample_home_faq_translate_answer',
            'items.2.answer' => 'label.site_sample_home_faq_code_answer',
            'items.3.answer' => 'label.site_sample_home_faq_license_answer',
        ]));

        // Its button is pointed at the collection in the second pass, the page holding it having no identifier yet
        $page->addBlock($this->section('cta_band', 5, ['anchor' => 'a-vous', 'ctaUrl' => null], [
            'title' => 'label.site_sample_home_cta_title',
            'ctaLabel' => 'label.site_sample_home_cta_label',
        ], [
            'text' => 'label.site_sample_home_cta_text',
        ]));

        return $page;
    }

    // A block of a page, every text read from its key and staged for translation under the very path it is stored at ("items.0.title"), the rich ones inside their box
    /**
     * @param array<string, mixed>  $data  what is stored as typed, keys and switches alike
     * @param array<string, string> $plain path => key of a plain text
     * @param array<string, string> $rich  path => key of a rich text
     */
    private function section(string $kind, int $position, array $data, array $plain, array $rich = []): Block
    {
        foreach ($plain as $path => $key) {
            $data = $this->setPath($data, $path, $this->trans($key));
        }

        foreach ($rich as $path => $key) {
            $data = $this->setPath($data, $path, $this->richText($this->trans($key)));
        }

        $block = new Block()
            ->setKind($kind)
            ->setPosition($position)
            ->setData($data);

        $this->demoFixtureTranslator->stage($block, Translation::OWNER_BLOCK, self::DOMAIN, $plain);
        $this->demoFixtureTranslator->stage($block, Translation::OWNER_BLOCK, self::DOMAIN, $rich, $this->richText(...));

        return $block;
    }

    // Lays a value at "items.0.title", the entries of a collection being numbered the way the back office numbers them
    /**
     * @param array<string|int, mixed> $data
     *
     * @return array<string|int, mixed>
     */
    private function setPath(array $data, string $path, string $value): array
    {
        [$head, $rest] = explode('.', $path, 2) + [1 => null];
        $key = ctype_digit($head) ? (int) $head : $head;

        $data[$key] = null === $rest ? $value : $this->setPath(\is_array($data[$key] ?? null) ? $data[$key] : [], $rest, $value);

        return $data;
    }

    // What a rich-text field stores around its prose: a translation written without it is the same words in another box, and the two read as two different texts to whatever compares them
    private function richText(string $value): string
    {
        return sprintf(self::RICH_TEXT_WRAPPER, $value);
    }

    // Its two buttons are pointed at their pages in the second pass, neither page having an identifier yet
    private function hero(?string $image): Block
    {
        $hero = new Block()
            ->setKind('hero')
            ->setPosition(0)
            ->setData([
                'badge' => $this->trans('label.site_sample_page_home_badge'),
                'title' => $this->richText($this->trans('label.site_sample_home_hero_title')),
                'subtitle' => $this->richText($this->trans('label.site_sample_page_home_subtitle')),
                'primaryLabel' => $this->trans('label.site_sample_home_hero_primary'),
                'primaryUrl' => null,
                'secondaryLabel' => $this->trans('label.site_sample_home_hero_secondary'),
                'secondaryUrl' => null,
                'statValue' => null,
                'statLabel' => null,
                'anchor' => 'accueil',
                'hasBackgroundImage' => false,
                'titleLevel' => 'h1',
                'background' => 'primary',
                'mediaLayout' => 'grid',
            ]);

        // Two calls rather than one: the badge and the buttons are stored as they are typed, the title and the subtitle inside their own box
        $this->demoFixtureTranslator->stage($hero, Translation::OWNER_BLOCK, self::DOMAIN, [
            'badge' => 'label.site_sample_page_home_badge',
            'primaryLabel' => 'label.site_sample_home_hero_primary',
            'secondaryLabel' => 'label.site_sample_home_hero_secondary',
        ]);
        $this->demoFixtureTranslator->stage($hero, Translation::OWNER_BLOCK, self::DOMAIN, [
            'title' => 'label.site_sample_home_hero_title',
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

    // What 975L offers, how it works, what it made - the collection the home page's last button points at - and a way on to its story
    private function services(): Page
    {
        $page = $this->emptyPage('nos-services', 'services', self::CREATION_SERVICES);

        $page->addBlock($this->textSection('label.site_sample_page_services_lead', 0));

        $page->addBlock($this->section('section_features', 1, [
            'anchor' => 'services',
            'variant' => 'tiles',
            'cards' => [
                ['icon' => 'bundles/c975lui/icons/pen-ruler.svg'],
                ['icon' => 'bundles/c975lui/icons/layer-group.svg'],
                ['icon' => 'bundles/c975lui/icons/code.svg'],
            ],
        ], [
            'eyebrow' => 'label.site_sample_services_offer_eyebrow',
            'title' => 'label.site_sample_services_offer_title',
            'intro' => 'label.site_sample_services_offer_intro',
            'cards.0.title' => 'label.site_sample_services_custom_title',
            'cards.1.title' => 'label.site_sample_services_cms_title',
            'cards.2.title' => 'label.site_sample_services_symfony_title',
        ], [
            'cards.0.text' => 'label.site_sample_services_custom_text',
            'cards.1.text' => 'label.site_sample_services_cms_text',
            'cards.2.text' => 'label.site_sample_services_symfony_text',
        ]));

        $page->addBlock($this->section('process_steps', 2, ['anchor' => 'methode'], [
            'eyebrow' => 'label.site_sample_services_steps_eyebrow',
            'title' => 'label.site_sample_services_steps_title',
            'steps.0.title' => 'label.site_sample_services_step_talk_title',
            'steps.1.title' => 'label.site_sample_services_step_mockup_title',
            'steps.2.title' => 'label.site_sample_services_step_build_title',
            'steps.3.title' => 'label.site_sample_services_step_launch_title',
        ], [
            'steps.0.text' => 'label.site_sample_services_step_talk_text',
            'steps.1.text' => 'label.site_sample_services_step_mockup_text',
            'steps.2.text' => 'label.site_sample_services_step_build_text',
            'steps.3.text' => 'label.site_sample_services_step_launch_text',
        ]));

        $page->addBlock($this->collection(3));

        // Its button is pointed at the story in the second pass, as the home page's are
        $page->addBlock($this->section('cta_band', 4, ['anchor' => 'agence', 'ctaUrl' => null], [
            'title' => 'label.site_sample_services_cta_title',
            'ctaLabel' => 'label.site_sample_services_cta_label',
        ], [
            'text' => 'label.site_sample_services_cta_text',
        ]));

        return $page;
    }

    // Where 975L comes from: a few figures, the dates that made it, then what holds its sites together
    private function history(): Page
    {
        $page = $this->emptyPage('notre-histoire', 'history', self::CREATION_HISTORY);

        $page->addBlock($this->textSection('label.site_sample_page_history_lead', 0));

        $page->addBlock($this->section('feature_bar', 1, ['anchor' => 'chiffres', 'eyebrow' => null, 'title' => null], [
            'items.0.title' => 'label.site_sample_history_figure_year_title',
            'items.0.text' => 'label.site_sample_history_figure_year_text',
            'items.1.title' => 'label.site_sample_history_figure_sites_title',
            'items.1.text' => 'label.site_sample_history_figure_sites_text',
            'items.2.title' => 'label.site_sample_history_figure_open_source_title',
            'items.2.text' => 'label.site_sample_history_figure_open_source_text',
            'items.3.title' => 'label.site_sample_history_figure_languages_title',
            'items.3.text' => 'label.site_sample_history_figure_languages_text',
        ]));

        $page->addBlock($this->section('process_steps', 2, ['anchor' => 'dates'], [
            'eyebrow' => 'label.site_sample_history_timeline_eyebrow',
            'title' => 'label.site_sample_history_timeline_title',
            'steps.0.title' => 'label.site_sample_history_date_itu_title',
            'steps.1.title' => 'label.site_sample_history_date_agency_title',
            'steps.2.title' => 'label.site_sample_history_date_bundles_title',
            'steps.3.title' => 'label.site_sample_history_date_today_title',
        ], [
            'steps.0.text' => 'label.site_sample_history_date_itu_text',
            'steps.1.text' => 'label.site_sample_history_date_agency_text',
            'steps.2.text' => 'label.site_sample_history_date_bundles_text',
            'steps.3.text' => 'label.site_sample_history_date_today_text',
        ]));

        $page->addBlock($this->textSection('label.site_sample_page_history_body', 3));

        // Its button is pointed at the services in the second pass, as the home page's are
        $page->addBlock($this->section('cta_band', 4, ['anchor' => 'projet', 'ctaUrl' => null], [
            'title' => 'label.site_sample_history_cta_title',
            'ctaLabel' => 'label.site_sample_history_cta_label',
        ], [
            'text' => 'label.site_sample_history_cta_text',
        ]));

        return $page;
    }

    // Two sections - the shape an editor meets in the back office rather than a single wall of text
    private function page(string $slug, string $key, string $creation): Page
    {
        $page = $this->emptyPage($slug, $key, $creation);

        $page->addBlock($this->textSection('label.site_sample_page_' . $key . '_lead', 0));
        $page->addBlock($this->textSection('label.site_sample_page_' . $key . '_body', 1));

        return $page;
    }

    // A published page with its title staged for translation, and no block yet
    private function emptyPage(string $slug, string $key, string $creation): Page
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
    private function collection(int $position): Block
    {
        $block = new Block()
            ->setKind('collection')
            ->setPosition($position)
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
                // The cards 975L shows its own sites with, a screenshot above its title and text
                'variant' => 'portfolio',
            ]);

        $this->demoFixtureTranslator->stage($block, Translation::OWNER_BLOCK, self::DOMAIN, ['title' => 'label.site_sample_collection_name']);

        return $block;
    }

    // The very same demo site said in each of the other languages it declares (see DemoFixtureTranslator), a collection item left out for lack of a "site" key. The navbar comes in this pass too, its links naming their pages by the identifier the first flush hands out ("page:ID", see MenuLinkType)
    /** @return iterable<object> */
    public function getLinkedDemoFixtures(): iterable
    {
        yield from $this->demoFixtureTranslator->translations();

        $this->linkButtons();

        // Only where none exists yet: Menu::$location is unique, and a database already holding a navbar keeps its own rather than failing with the pages already written
        if (null === $this->menuRepository->findOneBy(['location' => Menu::LOCATION_NAVBAR])) {
            yield $this->navbar();
        }
    }

    // The buttons of the pages, pointed at their targets by the identifier the first flush handed out ("page:ID", resolved at render time by PageLinkLocalizer): a raw path would lose the "/demo" prefix. Written on the blocks already recorded, the flush closing this pass carrying the change
    private function linkButtons(): void
    {
        $pages = [];
        foreach (['home', 'nos-services', 'notre-histoire'] as $slug) {
            $pages[$slug] = $this->pageRepository->findOneBy(['slug' => $slug]);
            if (null === $pages[$slug]) {
                return;
            }
        }

        $services = 'page:' . $pages['nos-services']->getId();
        $history = 'page:' . $pages['notre-histoire']->getId();
        $links = [
            'home' => ['hero' => ['primaryUrl' => $services, 'secondaryUrl' => $history], 'cta_band' => ['ctaUrl' => $services . '#realisations']],
            'nos-services' => ['cta_band' => ['ctaUrl' => $history]],
            'notre-histoire' => ['cta_band' => ['ctaUrl' => $services]],
        ];

        foreach ($links as $slug => $kinds) {
            foreach ($pages[$slug]->getBlocks() as $block) {
                if (isset($kinds[$block->getKind()])) {
                    $block->setData($kinds[$block->getKind()] + $block->getData());
                }
            }
        }
    }

    // Each link carries a label of its own, shorter than its page's title - which is what a navbar asks for, and what the menu translation screen offers to translate. Left in the site's language: saying them in the others is the very task that guided project films
    private function navbar(): Menu
    {
        $menu = new Menu()->setLocation(Menu::LOCATION_NAVBAR);

        foreach (['home' => 'home', 'nos-services' => 'services', 'notre-histoire' => 'history'] as $slug => $key) {
            $page = $this->pageRepository->findOneBy(['slug' => $slug]);
            if (null === $page) {
                continue;
            }

            $menu->addBlock(new Block()
                ->setKind('menu_link')
                ->setPosition($menu->getBlocks()->count())
                ->setData([
                    'target' => 'page:' . $page->getId(),
                    'label' => $this->trans('label.site_sample_menu_' . $key),
                    'primary' => false,
                    'strong' => false,
                ]));
        }

        return $menu;
    }

    private function item(CollectionGroup $group, string $slug, string $key, string $url, int $position, ?string $image): CollectionItem
    {
        $item = new CollectionItem()
            ->setCollectionGroup($group)
            ->setTitle($this->trans('label.site_sample_project_' . $key . '_title'))
            ->setSlug($slug)
            ->setDescription($this->trans('label.site_sample_project_' . $key . '_description'))
            // An outside address, which the "/demo" prefix does not touch
            ->setUrl($url)
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
