<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support;

trait InvestigateRoutePanelAssertions {

	abstract private function assertRouteRenderOutputHealthy( array $payload, string $label ) :string;

	abstract private function createDomXPathFromHtml( string $html ) :\DOMXPath;

	abstract private function decodeJsonAttribute( \DOMNode $node, string $attribute, string $label ) :array;

	abstract private function assertXPathExists( \DOMXPath $xpath, string $query, string $label ) :\DOMNode;

	abstract private function assertXPathCount( \DOMXPath $xpath, string $query, int $expectedCount, string $label ) :void;

	private function assertInvestigateRoutePreloadsSubjectPanel(
		array $payload,
		string $label,
		string $subjectKey,
		string $layerTitle,
		string $panelContentQuery
	) :void {
		$xpath = $this->createRoutePanelXPath( $payload, $label );
		$this->assertPreloadedRoutePanelShell( $xpath, $label, $subjectKey, $layerTitle );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-drill-layer="1"]//*[@data-investigate-panel-content="1"]//*[@data-investigate-subject-header="1"]',
			$label.' should preload the subject header inside the shared panel content'
		);
		$this->assertXPathExists(
			$xpath,
			$panelContentQuery,
			$label.' should preload the routed panel body'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-drill-layer="1"]//*[@data-investigate-panel-content="1"]//*[@data-inner-page-body-shell="1"]',
			0,
			$label.' should not embed a nested inner-page shell'
		);
	}

	private function assertInvestigateRoutePreloadsLookupPanel(
		array $payload,
		string $label,
		string $subjectKey,
		string $layerTitle,
		string $lookupFieldName
	) :void {
		$xpath = $this->createRoutePanelXPath( $payload, $label );
		$this->assertPreloadedRoutePanelShell( $xpath, $label, $subjectKey, $layerTitle );
		$this->assertXPathExists(
			$xpath,
			\sprintf(
				'//*[@data-drill-layer="1"]//*[@data-investigate-panel-content="1"]//form[@data-investigate-panel-form="1"]//*[@name="%s"]',
				$lookupFieldName
			),
			$label.' should preload the lookup form inside the shared panel content'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-drill-layer="1"]//*[@data-investigate-panel-content="1"]//*[@data-inner-page-body-shell="1"]',
			0,
			$label.' should not embed a nested inner-page shell'
		);
	}

	private function assertRoutePanelLayerState( \DOMXPath $xpath, string $label ) :\DOMNode {
		$this->assertXPathExists(
			$xpath,
			'//*[@data-drill-layer="0" and contains(concat(" ", normalize-space(@class), " "), " drill-layer--compact ")]',
			$label.' should compact the subject layer'
		);

		return $this->assertXPathExists(
			$xpath,
			'//*[@data-drill-layer="1" and not(contains(concat(" ", normalize-space(@class), " "), " drill-layer--hidden "))]',
			$label.' should expose the panel layer'
		);
	}

	private function createRoutePanelXPath( array $payload, string $label ) :\DOMXPath {
		return $this->createDomXPathFromHtml(
			$this->assertRouteRenderOutputHealthy( $payload, $label )
		);
	}

	private function assertPreloadedRoutePanelShell(
		\DOMXPath $xpath,
		string $label,
		string $subjectKey,
		string $layerTitle
	) :void {
		$panelLayer = $this->assertRoutePanelLayerState( $xpath, $label );
		$panelHeader = $this->decodeJsonAttribute( $panelLayer, 'data-drill-layer-header', $label.' panel header' );

		$this->assertSame(
			$layerTitle,
			(string)( $panelHeader[ 'title' ] ?? '' ),
			$label.' should expose the canonical layer title'
		);
		$this->assertXPathExists(
			$xpath,
			\sprintf(
				'//*[@data-drill-layer="1"]//*[@data-investigate-panel="1" and @data-investigate-panel-subject="%s" and @data-investigate-panel-loaded="1"]',
				$subjectKey
			),
			$label.' should preload the shared investigate panel wrapper'
		);
	}
}
