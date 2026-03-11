<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Email\Support;

trait PlainTextEmailAssertions {

	protected function assertPlainTextOutputHealthy( string $text, string $label ) :void {
		$this->assertSame( \trim( $text ), $text, $label.' should be trimmed.' );
		$this->assertDoesNotMatchRegularExpression( '#</?[a-z][^>]*>#i', $text, $label.' should not contain HTML tags.' );
		$this->assertDoesNotMatchRegularExpression( '/\|\s*\|/', $text, $label.' should not contain duplicate cell separators.' );
		$this->assertDoesNotMatchRegularExpression( "/\n{3,}/", $text, $label.' should not contain runaway blank lines.' );

		foreach ( \explode( "\n", $text ) as $line ) {
			$this->assertSame( \trim( $line ), $line, $label.' contains an untrimmed line.' );
		}
	}

	protected function assertContainsTokens( string $text, array $tokens, string $label ) :void {
		foreach ( $tokens as $token ) {
			$this->assertStringContainsString(
				(string)$token,
				$text,
				\sprintf( '%s missing token "%s" (head="%s")', $label, (string)$token, $this->compactSnippet( $text ) )
			);
		}
	}

	protected function assertTokensAppearInOrder( string $text, array $tokens, string $label ) :void {
		$offset = 0;
		foreach ( $tokens as $token ) {
			$position = \strpos( $text, (string)$token, $offset );
			$this->assertNotFalse(
				$position,
				\sprintf( '%s missing ordered token "%s" after offset %d (head="%s")', $label, (string)$token, $offset, $this->compactSnippet( $text ) )
			);
			$offset = (int)$position + \strlen( (string)$token );
		}
	}
}
