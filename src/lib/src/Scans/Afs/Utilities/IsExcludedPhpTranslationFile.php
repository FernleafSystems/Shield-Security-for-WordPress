<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Utilities;

use FernleafSystems\Wordpress\Services\Services;

class IsExcludedPhpTranslationFile {

	public function check( string $path ) :bool {
		$FS = Services::WpFs();

		$excluded = false;

		$normal = wp_normalize_path( $path );
		if ( \str_starts_with( trailingslashit( \dirname( $normal ) ), trailingslashit( wp_normalize_path( path_join( WP_CONTENT_DIR, 'languages' ) ) ) )
			 && \preg_match( '#^(?:[a-z_-]+-)?[a-z]{2}(?:_[A-Z]{2})?(?:_(?:in)?formal)?\.l10n\.php$#', \basename( $normal ) )
		) {

			$content = \trim( (string)$FS->getFileContent( $normal ) );
			if ( !empty( $content ) && \str_starts_with( $content, '<?php' ) ) {

				$hasAllExpectedContent = true;
				foreach ( $this->expectedStringLiterals() as $expectedString ) {
					if ( !\str_contains( $content, $expectedString ) ) {
						$hasAllExpectedContent = false;
						break;
					}
				}

				$excluded = $hasAllExpectedContent && $this->hasExpectedTokensOnly( $content );
			}
		}
		return $excluded;
	}

	protected function hasExpectedTokensOnly( string $content ) :bool {
		$allowableLiteralTokens = $this->allowableLiteralTokens();
		$allowableTokenNames = $this->allowableTokenNames();

		// Map token numbers to names for quicker lookup.
		$nameNumberMap = [];
		$namedTokensCounts = [];

		$hasValidTokensOnly = true;
		foreach ( \token_get_all( $content ) as $t ) {
			if ( \is_array( $t ) ) {

				if ( !isset( $nameNumberMap[ $t[ 0 ] ] ) ) {
					$name = \token_name( $t[ 0 ] );
					if ( isset( $allowableTokenNames[ $name ] ) ) {
						$nameNumberMap[ $t[ 0 ] ] = $name;
						$namedTokensCounts[ $name ] = 1;
					}
					else {
						$hasValidTokensOnly = false;
						break;
					}
				}
				else {
					/**
					 * For some named tokens, there's a limit to how often it may appear in the file.
					 * If it appears too often, it's likely to be a translation file of a different format,
					 * or not a true translation file at all.
					 */
					$name = $nameNumberMap[ $t[ 0 ] ];
					$namedTokensCounts[ $name ]++;

					if ( $allowableTokenNames[ $name ] > 0 && $namedTokensCounts[ $name ] > $allowableTokenNames[ $name ] ) {
						$hasValidTokensOnly = false;
						break;
					}
				}
			}
			elseif ( !\is_array( $t ) && !\in_array( $t, $allowableLiteralTokens ) ) {
				$hasValidTokensOnly = false;
				break;
			}
		}
		return $hasValidTokensOnly;
	}

	protected function allowableLiteralTokens() :array {
		return [
			'[',
			']',
			'.',
			',',
			';'
		];
	}

	protected function allowableTokenNames() :array {
		return [
			'T_OPEN_TAG'                 => 1,
			'T_RETURN'                   => 1,
			'T_WHITESPACE'               => -1,
			'T_CONSTANT_ENCAPSED_STRING' => -1,
			'T_DOUBLE_ARROW'             => -1,
		];
	}

	protected function expectedStringLiterals() :array {
		return [
			'x-generator',
			'GlotPress/',
			'translation-revision-date',
		];
	}
}