<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Utility;

use FernleafSystems\Wordpress\Services\Services;

class FileLockKeyApplicability {

	private bool $isWindows;

	private bool $themeFunctionsAccessible;

	public function __construct( bool $isWindows, bool $themeFunctionsAccessible ) {
		$this->isWindows = $isWindows;
		$this->themeFunctionsAccessible = $themeFunctionsAccessible;
	}

	public static function fromCurrentEnvironment() :self {
		return new self(
			Services::Data()->isWindows(),
			Services::WpFs()->isAccessibleFile( path_join( get_stylesheet_directory(), 'functions.php' ) )
		);
	}

	public function isApplicable( string $fileKey ) :bool {
		switch ( $fileKey ) {
			case 'root_webconfig':
				return $this->isWindows;

			case 'theme_functions':
				return $this->themeFunctionsAccessible;

			default:
				return true;
		}
	}

	/**
	 * @template TDefinition
	 * @param array<string,TDefinition> $definitions
	 * @return array<string,TDefinition>
	 */
	public function filterApplicableDefinitions( array $definitions ) :array {
		return \array_filter(
			$definitions,
			fn( string $fileKey ) :bool => $this->isApplicable( $fileKey ),
			\ARRAY_FILTER_USE_KEY
		);
	}

	/**
	 * @param array<array-key,mixed> $fileKeys
	 * @return list<mixed>
	 */
	public function removeNonApplicableKnownKeys( array $fileKeys ) :array {
		return \array_values( \array_filter(
			$fileKeys,
			fn( $fileKey ) :bool => !\is_string( $fileKey ) || $this->isApplicable( $fileKey )
		) );
	}
}
