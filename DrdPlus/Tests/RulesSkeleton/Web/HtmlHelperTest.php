<?php
declare(strict_types=1);

namespace DrdPlus\Tests\RulesSkeleton\Web;

use DrdPlus\RulesSkeleton\Web\RulesHtmlHelper;
use Granam\String\StringTools;
use Granam\WebContentBuilder\HtmlDocument;
use Gt\Dom\Element;

class HtmlHelperTest extends AbstractContentTest
{
    /**
     * @test
     * @dataProvider providePublicAndLocalLinks
     * @param string $publicLink
     * @param string $expectedLocalLink
     */
    public function I_can_turn_public_link_to_local(string $publicLink, string $expectedLocalLink): void
    {
        self::assertSame($expectedLocalLink, RulesHtmlHelper::turnToLocalLink($publicLink));
    }

    public function providePublicAndLocalLinks(): array
    {
        return [
            ['https://www.drdplus.info', 'http://www.drdplus.loc'],
            ['https://hranicar.drdplus.info', 'http://hranicar.drdplus.loc'],
        ];
    }

    /**
     * @test
     */
    public function I_can_create_id_from_any_name(): void
    {
        self::assertSame('kuala_lumpur', RulesHtmlHelper::toId('Kuala lumpur'));
        self::assertSame('krizaly_s_mrkvi', RulesHtmlHelper::toId('Křížaly s mrkví'));
    }

    /**
     * @test
     */
    public function I_can_get_filtered_tables_from_content(): void
    {
        $htmlHelper = new RulesHtmlHelper($this->getDirs());
        $allTables = $htmlHelper->findTablesWithIds($this->getHtmlDocument());
        if (!$this->isSkeletonChecked() && !$this->getTestsConfiguration()->hasTables()) {
            self::assertCount(0, $allTables);

            return;
        }
        self::assertGreaterThan(0, \count($allTables));
        self::assertEmpty($htmlHelper->findTablesWithIds($this->getHtmlDocument(), ['nonExistingTableId']));
        $someExpectedTableIds = $this->getTestsConfiguration()->getSomeExpectedTableIds();
        if (!$this->isSkeletonChecked() && !$this->getTestsConfiguration()->hasTables()) {
            self::assertCount(0, $someExpectedTableIds, 'No tables expected');

            return;
        }
        self::assertGreaterThan(0, \count($someExpectedTableIds), 'Some tables expected');
        foreach ($someExpectedTableIds as $someExpectedTableId) {
            $lowerExpectedTableId = StringTools::toSnakeCaseId($someExpectedTableId);
            self::assertArrayHasKey($lowerExpectedTableId, $allTables);
            $expectedTable = $allTables[$lowerExpectedTableId];
            self::assertInstanceOf(Element::class, $expectedTable);
            self::assertNotEmpty($expectedTable->innerHTML, "Table of ID $someExpectedTableId is empty");
            // intentionally to snake case to test proper ID case conversion
            $someCasedExpectedTableId = StringTools::toCamelCaseId($someExpectedTableId);
            $singleTable = $htmlHelper->findTablesWithIds($this->getHtmlDocument(), [$someCasedExpectedTableId]);
            self::assertCount(1, $singleTable, 'No table has been found by ID ' . $someCasedExpectedTableId);
            self::assertArrayHasKey($lowerExpectedTableId, $allTables, 'ID is expected to be lower-cased');
        }
    }

    /**
     * @test
     */
    public function Filtering_tables_by_id_does_not_crash_on_table_without_id(): void
    {
        $htmlHelper = new RulesHtmlHelper($this->getDirs());
        $allTables = $htmlHelper->findTablesWithIds(new HtmlDocument(<<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <title>Just a test</title>
  <meta charset="utf-8">
</head>
<body>
  <table>No ID here</table>
</body>
</htm>
HTML
        ));
        self::assertCount(0, $allTables);
    }

    /**
     * @test
     */
    public function Same_table_ids_are_filtered_on_tables_only_mode(): void
    {
        if (!$this->isSkeletonChecked() && !$this->getTestsConfiguration()->hasTables()) {
            self::assertCount(
                0,
                $this->getHtmlDocument()->getElementsByTagName('table'),
                'No tables with IDs expected according to tests config'
            );

            return;
        }
        $htmlHelper = new RulesHtmlHelper($this->getDirs());
        $someExpectedTableIds = $this->getTestsConfiguration()->getSomeExpectedTableIds();
        self::assertGreaterThan(0, \count($someExpectedTableIds), 'Some tables expected according to tests config');
        $tableId = \current($someExpectedTableIds);
        $tables = $htmlHelper->findTablesWithIds($this->getHtmlDocument(), [$tableId, $tableId]);
        self::assertCount(1, $tables);
    }

    /**
     * @test
     */
    public function It_will_not_add_anchor_into_anchor_with_id(): void
    {
        $htmlHelper = new RulesHtmlHelper($this->getDirs());
        $content = '<!DOCTYPE html>
<html lang="en"><body><a href="" id="someId">Foo</a></body></html>';
        $htmlDocument = new HtmlDocument($content);
        $htmlHelper->addAnchorsToIds($htmlDocument);
        self::assertSame($content, \trim($htmlDocument->saveHTML()));
    }

    /**
     * @test
     */
    public function Ids_are_turned_to_constant_like_diacritics_free_format(): void
    {
        $htmlHelper = new RulesHtmlHelper($this->getDirs());
        $originalId = 'Příliš # žluťoučký # kůň # úpěl # ďábelské # ódy';
        $htmlDocument = new HtmlDocument(<<<HTML
        <!DOCTYPE html>
<html lang="cs-CZ">
<head>
  <title></title>
  <meta charset="utf-8">
</head>
<body>
  <div class="test" id="$originalId"></div>
</body>
</htm>
HTML
        );
        $htmlHelper->replaceDiacriticsFromIds($htmlDocument);
        $divs = $htmlDocument->getElementsByClassName('test');
        self::assertCount(1, $divs);
        $div = $divs[0];
        $id = $div->id;
        self::assertNotEmpty($id);
        $expectedId = StringTools::toConstantLikeValue($originalId);
        self::assertSame($expectedId, $id);
        $this->Original_id_is_accessible_without_change_via_data_attribute($div, $originalId);
        $this->Original_id_can_be_used_as_anchor_via_inner_invisible_element($div, $originalId);
    }

    private function Original_id_is_accessible_without_change_via_data_attribute(Element $elementWithId, string $expectedOriginalId): void
    {
        $fetchedOriginalId = $elementWithId->getAttribute(RulesHtmlHelper::DATA_ORIGINAL_ID);
        self::assertNotEmpty($fetchedOriginalId);
        self::assertSame($expectedOriginalId, $fetchedOriginalId);
    }

    private function Original_id_can_be_used_as_anchor_via_inner_invisible_element(Element $elementWithId, string $expectedOriginalId): void
    {
        $invisibleIdElements = $elementWithId->getElementsByClassName(RulesHtmlHelper::INVISIBLE_ID_CLASS);
        self::assertCount(1, $invisibleIdElements);
        $invisibleIdElement = $invisibleIdElements[0];
        $invisibleId = $invisibleIdElement->id;
        self::assertNotEmpty($invisibleId);
        self::assertSame(\str_replace('#', '_', $expectedOriginalId), $invisibleId);
    }

    /**
     * @test
     */
    public function I_can_turn_public_drd_plus_links_to_locals(): void
    {
        $htmlHelper = new RulesHtmlHelper($this->getDirs());
        $htmlDocument = new HtmlDocument(<<<HTML
        <!DOCTYPE html>
<html lang="cs-CZ">
<head>
  <title></title>
  <meta charset="utf-8">
</head>
<body>
  <a href="https://foo-bar.baz.drdplus.info" id="single_link">Sub-doména na DrD+ info</a>
  <a href="https://qux.drdplus.info" id="marked_as_local">Sub-doména na DrD+ info označená jako local</a>
</body>
</htm>
HTML
        );
        /** @var Element $localizedLink */
        $htmlHelper->markExternalLinksByClass($htmlDocument);
        $htmlHelper->makeDrdPlusLinksLocal($htmlDocument);
        $localizedLink = $htmlDocument->getElementById('single_link');
        self::assertNotEmpty($localizedLink, 'No element found by ID single_link');
        self::assertSame('http://foo-bar.baz.drdplus.loc', $localizedLink->getAttribute('href'));
        /** @var Element $localizedLocalLikeLink */
        $localizedLocalLikeLink = $htmlDocument->getElementById('marked_as_local');
        self::assertNotEmpty($localizedLocalLikeLink, 'No element found by ID marked_as_local');
        self::assertSame('http://qux.drdplus.loc', $localizedLocalLikeLink->getAttribute('href'));
    }

    /**
     * @test
     */
    public function I_can_inject_iframes_with_remote_tables(): void
    {
        $htmlHelper = new RulesHtmlHelper($this->getDirs());
        $htmlDocument = new HtmlDocument(<<<HTML
        <!DOCTYPE html>
<html lang="cs-CZ">
<head>
 <title></title>
  <meta charset="utf-8">
</head>
<body>
  <a href="https://pph.drdplus.info/#tabulka_vzdalenosti">Odkaz na tabulku vzdálenosti</a>
  <a href="https://pph.drdplus.info/#tabulka_vzdalenosti">Druhý odkaz na tabulku vzdálenosti</a>
  <a href="https://pph.drdplus.info/#tabulka_casu">Odkaz na tabulku času</a>
  <a href="https://pph.drdplus.info/#tabulka_vzdalenosti">Třetí na tabulku vzdálenosti</a>
</body>
</htm>
HTML
        );
        $htmlHelper->markExternalLinksByClass($htmlDocument);
        $htmlHelper->injectIframesWithRemoteTables($htmlDocument);
        $iframes = $htmlDocument->getElementsByTagName('iframe');
        self::assertCount(1, $iframes, 'Single iframe (with tables preview) expected');
        $iframe = $iframes->current();
        self::assertSame(
            'https://pph.drdplus.info/?tables=tabulka_vzdalenosti,tabulka_casu',
            $iframe->getAttribute('src'),
            "Something is bad with iframe\n" . $iframe->outerHTML
        );
        self::assertSame('pph.drdplus.info', $iframe->id, 'Expected ID made from iframe target domain');
    }

    /**
     * @test
     */
    public function I_can_mark_external_links_by_class(): void
    {
        $htmlHelper = new RulesHtmlHelper($this->getDirs());
        $htmlDocument = new HtmlDocument(<<<HTML
        <!DOCTYPE html>
<html lang="cs-CZ">
<head>
  <title></title>
  <meta charset="utf-8">
</head>
<body>
  <a id="link_without_anchor">Link without anchor</a>
</body>
</htm>
HTML
        );
        self::assertNull($htmlDocument->body->getAttribute(RulesHtmlHelper::DATA_HAS_MARKED_EXTERNAL_URLS));
        $htmlHelper->markExternalLinksByClass($htmlDocument);
        self::assertSame('1', $htmlDocument->body->getAttribute(RulesHtmlHelper::DATA_HAS_MARKED_EXTERNAL_URLS));
        /** @var Element $linkWithoutAnchor */
        $linkWithoutAnchor = $htmlDocument->getElementById('link_without_anchor');
        self::assertFalse($linkWithoutAnchor->classList->contains(RulesHtmlHelper::EXTERNAL_URL_CLASS));
    }
}