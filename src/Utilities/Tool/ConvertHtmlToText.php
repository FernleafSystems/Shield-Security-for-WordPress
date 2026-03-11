<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool;

class ConvertHtmlToText {

	public function run( string $content ) :string {
		$text = $this->normalizeNewlines( $content );
		$text = $this->removeNonContentBlocks( $text );
		$text = $this->removeInterTagWhitespace( $text );
		$text = $this->convertLinks( $text );
		$text = $this->applyStructuralConversions( $text );
		$text = \strip_tags( $text );
		$text = $this->decodeEntities( $text );
		return $this->normalizeWhitespace( $text );
	}

	private function applyStructuralConversions( string $text ) :string {
		$replacements = [
			'/<img\b[^>]*>/i'                                    => '',
			'/<hr\b[^>]*\/?>/i'                                 => "\n",
			'/<br\b[^>]*\/?>/i'                                 => "\n",
			'/<p\b[^>]*>/i'                                     => '',
			'/<\/p>/i'                                          => "\n\n",
			'/<(?:div|section|article|header|footer)\b[^>]*>/i' => '',
			'/<\/(?:div|section|article|header|footer)>/i'      => "\n",
			'/<h[1-6]\b[^>]*>/i'                                => "\n\n",
			'/<\/h[1-6]>/i'                                     => "\n\n",
			'/<(?:ul|ol|dl)\b[^>]*>/i'                          => "\n",
			'/<\/(?:ul|ol|dl)>/i'                               => "\n",
			'/<li\b[^>]*>/i'                                    => "\n* ",
			'/<\/li>/i'                                         => '',
			'/<dt\b[^>]*>/i'                                    => "\n* ",
			'/<\/dt>/i'                                         => "\n",
			'/<dd\b[^>]*>/i'                                    => "\n  ",
			'/<\/dd>/i'                                         => "\n",
			'/<table\b[^>]*>/i'                                 => "\n\n",
			'/<\/table>/i'                                      => "\n\n",
			'/<tr\b[^>]*>/i'                                    => "\n",
			'/<\/tr>/i'                                         => "\n",
			'/<(?:td|th)\b[^>]*>/i'                             => '',
			'/<\/(?:td|th)>/i'                                  => ' | ',
		];

		foreach ( $replacements as $pattern => $replacement ) {
			$text = $this->replace( $pattern, $replacement, $text );
		}

		return $text;
	}

	private function convertLinks( string $text ) :string {
		return (string)\preg_replace_callback(
			'/<a\b[^>]*href\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))[^>]*>(.*?)<\/a>/is',
			function ( array $matches ) :string {
				$href = $this->normalizeInlineText( $matches[ 1 ] ?: ( $matches[ 2 ] ?: ( $matches[ 3 ] ?: '' ) ) );
				$label = $this->normalizeInlineText( \strip_tags( $matches[ 4 ] ?? '' ) );

				if ( $href === '' ) {
					return $label;
				}
				if ( $label === '' || $label === $href ) {
					return $href;
				}

				return \sprintf( '%s (%s)', $label, $href );
			},
			$text
		);
	}

	private function decodeEntities( string $text ) :string {
		$text = \html_entity_decode( $text, \ENT_QUOTES | \ENT_HTML5, 'UTF-8' );
		return \str_replace( "\u{00A0}", ' ', $text );
	}

	private function normalizeInlineText( string $text ) :string {
		$text = $this->decodeEntities( $text );
		$text = $this->replace( '/[ \t\r\n]+/', ' ', $text );
		return \trim( $text );
	}

	private function normalizeNewlines( string $text ) :string {
		return \str_replace( [ "\r\n", "\r" ], "\n", $text );
	}

	private function normalizeWhitespace( string $text ) :string {
		$text = $this->replace( '/[ \t]+/', ' ', $text );
		$text = $this->replace( '/ *\n */', "\n", $text );

		$lines = \array_map(
			function ( string $line ) :string {
				$line = $this->replace( '/\s*\|\s*/', ' | ', $line );
				$line = \trim( $line );
				$line = \trim( $line, '|' );
				$line = \trim( $line );
				$line = $this->replace( '/ {2,}/', ' ', $line );
				return $line;
			},
			\explode( "\n", $text )
		);

		$text = \implode( "\n", $lines );
		$text = $this->replace( "/\n{3,}/", "\n\n", $text );

		return \trim( $text );
	}

	private function removeNonContentBlocks( string $text ) :string {
		$patterns = [
			'/<!--.*?-->/s',
			'/<(?:head|style|script|noscript|svg)\b[^>]*>.*?<\/(?:head|style|script|noscript|svg)>/is',
		];

		foreach ( $patterns as $pattern ) {
			$text = $this->replace( $pattern, '', $text );
		}

		return $text;
	}

	private function removeInterTagWhitespace( string $text ) :string {
		return $this->replace( '/>\s+</', '><', $text );
	}

	private function replace( string $pattern, string $replacement, string $subject ) :string {
		return (string)( \preg_replace( $pattern, $replacement, $subject ) ?? $subject );
	}
}
