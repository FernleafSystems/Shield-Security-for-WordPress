<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support;

trait InvestigatePageAssertions {

	private function investigateDomXPath( string $html ) :\DOMXPath {
		return $this->createDomXPathFromHtml( $html );
	}

	private function assertInvestigateDatatableCount( \DOMXPath $xpath, int $expectedCount, string $label ) :void {
		$this->assertXPathCount( $xpath, '//*[@data-investigation-table="1"]', $expectedCount, $label );
	}

	private function assertInvestigateOverviewLabel( \DOMXPath $xpath, string $overviewLabel, string $label ) :void {
		$this->assertXPathExists(
			$xpath,
			'//th[normalize-space()="'.$overviewLabel.'"]',
			$label
		);
	}

	private function assertInvestigateTableTypeByCount( \DOMXPath $xpath, string $tableType, int $count, string $label ) :void {
		$this->assertXPathCount(
			$xpath,
			'//*[@data-table-type="'.$tableType.'"]',
			$count > 0 ? 1 : 0,
			$label
		);
	}

	private function assertInvestigateSubjectTypeByCount( \DOMXPath $xpath, string $subjectType, int $count, string $label ) :void {
		$this->assertXPathCount(
			$xpath,
			'//*[@data-subject-type="'.$subjectType.'"]',
			$count > 0 ? 1 : 0,
			$label
		);
	}
}
