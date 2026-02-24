<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\I18n;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Symfony\Component\Filesystem\Path;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Validates printf/sprintf placeholder syntax across all source strings in the POT catalogue.
 *
 * This guards against malformed tokens (for example: `%$3s`) which are fatal on PHP 8+.
 */
class PrintfPlaceholdersValidationTest extends TestCase {

	use PluginPathsTrait;

	public function testAllCatalogueStringsContainValidPrintfPlaceholders() :void {
		$invalid = [];

		$filePath = $this->getPotCatalogueFile();
		foreach ( $this->parsePoLikeCatalogue( $filePath ) as $entry ) {
			foreach ( $this->entryStringsForValidation( $entry ) as $field => $value ) {
				$error = $this->findInvalidPrintfSpecifier( $value );
				if ( $error !== null ) {
					$invalid[] = \sprintf(
						'%s:%d [%s] %s | %s',
						$this->toRelativePath( $filePath ),
						(int)( $entry['line_map'][ $field ] ?? 0 ),
						$field,
						$error,
						$value
					);
				}
			}
		}

		$this->assertSame(
			[],
			$invalid,
			"Invalid printf placeholders detected:\n".\implode( "\n", $invalid )
		);
	}

	private function getPotCatalogueFile() :string {
		$languagesDir = $this->getPluginFilePath( 'languages' );
		$this->assertDirectoryExists( $languagesDir, 'Languages directory is required for placeholder validation.' );

		$potPath = Path::join( $languagesDir, 'wp-simple-firewall.pot' );
		$this->assertFileExists( $potPath, 'POT catalogue is required for placeholder validation.' );
		return $potPath;
	}

	/**
	 * @return array<int, array{msgid:string,msgid_plural:string,msgstr:array<string,string>,line_map:array<string,int>,is_printf:bool}>
	 */
	private function parsePoLikeCatalogue( string $filePath ) :array {
		$lines = \file( $filePath, \FILE_IGNORE_NEW_LINES );
		$this->assertIsArray( $lines, \sprintf( 'Failed to read translation file: %s', $filePath ) );

		$entries = [];
		$entry = $this->newEmptyCatalogueEntry();
		$currentField = null;

		foreach ( $lines as $idx => $lineRaw ) {
			$line = (string)$lineRaw;
			$lineNumber = $idx + 1;

			if ( \trim( $line ) === '' ) {
				$this->appendEntryIfRelevant( $entries, $entry );
				$entry = $this->newEmptyCatalogueEntry();
				$currentField = null;
				continue;
			}

			if ( \strpos( $line, '#,' ) === 0 ) {
				if ( \strpos( $line, 'php-format' ) !== false || \strpos( $line, 'c-format' ) !== false ) {
					$entry['is_printf'] = true;
				}
				continue;
			}

			if ( \preg_match( '/^msgid\s+"(.*)"$/', $line, $m ) === 1 ) {
				$entry['msgid'] = $this->decodePoStringFragment( $m[1] );
				$entry['line_map']['msgid'] = $lineNumber;
				$currentField = 'msgid';
				continue;
			}

			if ( \preg_match( '/^msgid_plural\s+"(.*)"$/', $line, $m ) === 1 ) {
				$entry['msgid_plural'] = $this->decodePoStringFragment( $m[1] );
				$entry['line_map']['msgid_plural'] = $lineNumber;
				$currentField = 'msgid_plural';
				continue;
			}

			if ( \preg_match( '/^msgstr(?:\[(\d+)\])?\s+"(.*)"$/', $line, $m ) === 1 ) {
				$index = isset( $m[1] ) && $m[1] !== '' ? $m[1] : '0';
				$field = \sprintf( 'msgstr[%s]', $index );
				$entry['msgstr'][ $index ] = $this->decodePoStringFragment( $m[2] );
				$entry['line_map'][ $field ] = $lineNumber;
				$currentField = $field;
				continue;
			}

			if ( \preg_match( '/^"(.*)"$/', $line, $m ) === 1 && !empty( $currentField ) ) {
				$decoded = $this->decodePoStringFragment( $m[1] );
				if ( $currentField === 'msgid' || $currentField === 'msgid_plural' ) {
					$entry[ $currentField ] .= $decoded;
				}
				elseif ( \preg_match( '/^msgstr\[(\d+)\]$/', $currentField, $strMatch ) === 1 ) {
					$entry['msgstr'][ $strMatch[1] ] .= $decoded;
				}
			}
		}

		$this->appendEntryIfRelevant( $entries, $entry );
		return $entries;
	}

	/**
	 * @param array{msgid:string,msgid_plural:string,msgstr:array<string,string>,line_map:array<string,int>,is_printf:bool} $entry
	 * @return array<string,string>
	 */
	private function entryStringsForValidation( array $entry ) :array {
		$strings = [];

		// Skip PO header entry.
		if ( $entry['msgid'] === '' || !$entry['is_printf'] ) {
			return $strings;
		}

		if ( $entry['msgid'] !== '' ) {
			$strings['msgid'] = $entry['msgid'];
		}
		if ( $entry['msgid_plural'] !== '' ) {
			$strings['msgid_plural'] = $entry['msgid_plural'];
		}
		foreach ( $entry['msgstr'] as $idx => $msgstr ) {
			if ( $msgstr !== '' ) {
				$strings[ \sprintf( 'msgstr[%s]', $idx ) ] = $msgstr;
			}
		}

		return $strings;
	}

	private function findInvalidPrintfSpecifier( string $value ) :?string {
		$length = \strlen( $value );
		for ( $i = 0; $i < $length; $i++ ) {
			if ( $value[ $i ] !== '%' ) {
				continue;
			}

			// Escaped percent sign.
			if ( ( $i + 1 ) < $length && $value[ $i + 1 ] === '%' ) {
				$i++;
				continue;
			}

			$candidate = \substr( $value, $i );
			$validSpecifierPattern = "/^%(?:(?:\\d+)\\$)?(?:[-+ 0#']*)?(?:(?:\\d+)|(?:\\*(?:\\d+\\$)?))?(?:\\.(?:(?:\\d+)|(?:\\*(?:\\d+\\$)?)))?[bcdeEfFgGosuxX]/";
			if ( \preg_match( $validSpecifierPattern, $candidate, $m ) === 1 ) {
				$i += \strlen( $m[0] ) - 1;
				continue;
			}

			return \sprintf(
				'invalid placeholder near "%s"',
				\substr( $candidate, 0, 24 )
			);
		}

		return null;
	}

	private function decodePoStringFragment( string $fragment ) :string {
		return (string)\stripcslashes( $fragment );
	}

	/**
	 * @param array<int, array{msgid:string,msgid_plural:string,msgstr:array<string,string>,line_map:array<string,int>,is_printf:bool}> $entries
	 * @param array{msgid:string,msgid_plural:string,msgstr:array<string,string>,line_map:array<string,int>,is_printf:bool} $entry
	 */
	private function appendEntryIfRelevant( array &$entries, array $entry ) :void {
		if ( $entry['msgid'] === '' && $entry['msgid_plural'] === '' && empty( $entry['msgstr'] ) ) {
			return;
		}
		$entries[] = $entry;
	}

	/**
	 * @return array{msgid:string,msgid_plural:string,msgstr:array<string,string>,line_map:array<string,int>,is_printf:bool}
	 */
	private function newEmptyCatalogueEntry() :array {
		return [
			'msgid' => '',
			'msgid_plural' => '',
			'msgstr' => [],
			'line_map' => [],
			'is_printf' => false,
		];
	}

	private function toRelativePath( string $absolutePath ) :string {
		// Intentional manual join: relative matching here requires normalized forward-slash strings.
		$root = \str_replace( '\\', '/', \rtrim( $this->getPluginRoot(), '/\\' ) ).'/';
		$normalizedPath = \str_replace( '\\', '/', $absolutePath );
		if ( \strpos( $normalizedPath, $root ) === 0 ) {
			return \substr( $normalizedPath, \strlen( $root ) );
		}
		return $normalizedPath;
	}
}
