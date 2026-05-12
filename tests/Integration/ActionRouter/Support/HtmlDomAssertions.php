<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support;

trait HtmlDomAssertions {

	private function createDomXPathFromHtml( string $html ) :\DOMXPath {
		$doc = new \DOMDocument();
		$previous = \libxml_use_internal_errors( true );
		try {
			$doc->loadHTML(
				'<?xml encoding="utf-8" ?>'.$html,
				\LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
			);
		}
		finally {
			\libxml_clear_errors();
			\libxml_use_internal_errors( $previous );
		}

		return new \DOMXPath( $doc );
	}

	private function nodeOuterHtml( \DOMNode $node ) :string {
		$doc = $node->ownerDocument;
		return $doc instanceof \DOMDocument ? (string)$doc->saveHTML( $node ) : '';
	}

	/**
	 * @return array<string,mixed>
	 */
	private function decodeJsonAttribute( \DOMNode $node, string $attribute, string $label ) :array {
		$this->assertInstanceOf( \DOMElement::class, $node, $label.' node type contract' );
		/** @var \DOMElement $node */

		$raw = \trim( (string)$node->getAttribute( $attribute ) );
		$this->assertNotSame( '', $raw, $label.' attribute should not be empty' );

		$decoded = \json_decode(
			\html_entity_decode( $raw, \ENT_QUOTES | \ENT_HTML5, 'UTF-8' ),
			true
		);
		$this->assertIsArray( $decoded, $label.' JSON decode contract' );
		return $decoded;
	}

	private function assertXPathExists( \DOMXPath $xpath, string $query, string $label ) :\DOMNode {
		$nodes = $xpath->query( $query );
		$this->assertNotFalse( $nodes, $label.' query failed: '.$query );
		$this->assertGreaterThan( 0, $nodes->length, $label.' missing for query: '.$query );

		return $nodes->item( 0 );
	}

	private function assertXPathCount( \DOMXPath $xpath, string $query, int $expectedCount, string $label ) :void {
		$nodes = $xpath->query( $query );
		$this->assertNotFalse( $nodes, $label.' query failed: '.$query );
		$this->assertSame(
			$expectedCount,
			$nodes->length,
			$label.' expected count '.$expectedCount.' for query: '.$query
		);
	}
}
