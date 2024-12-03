<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules\{
	StringsOptions,
	StringsSections
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class BuildOptionsForDisplay {

	use PluginControllerConsumer;

	private string $focusOption = '';

	private string $focusSection = '';

	private array $options;

	private array $sections;

	public function __construct( array $options = [], array $sections = [] ) {
		$this->options = $options;
		$this->sections = $sections;
	}

	public function setFocusOption( string $optKey ) :self {
		$this->focusOption = $optKey;
		return $this;
	}

	public function setFocusSection( string $sectionKey ) :self {
		$this->focusSection = $sectionKey;
		return $this;
	}

	/**
	 * Will initiate the plugin options structure for use by the UI builder.
	 * It doesn't set any values, just populates the array created in buildOptions()
	 * with values stored.
	 * It has to handle the conversion of stored values to data to be displayed to the user.
	 */
	public function standard() :array {
		// ensures firewall parameters are in correct format for display. This can be removed ~19.3+
		self::con()->comps->opts_lookup->getFirewallParametersWhitelist();

		$sections = \array_filter( \array_map(
			function ( array $section ) {

				if ( empty( $section[ 'options' ] ) ) {
					$section = null;
				}
				else {
					foreach ( $section[ 'options' ] as $optKey => $opt ) {
						$opt[ 'is_value_default' ] = $opt[ 'value' ] === $opt[ 'default' ];
						$section[ 'options' ][ $optKey ] = $this->buildOptionForUi( $opt );
						$section[ 'options' ][ $optKey ][ 'is_focus' ] = $opt[ 'key' ] === $this->focusOption;
					}

					$notices = new SectionNotices();
					$section = \array_merge( $section, ( new StringsSections() )->getFor( $section[ 'slug' ] ) );
					$section[ 'is_focus' ] = $section[ 'slug' ] === $this->focusSection;
					$section[ 'notices' ] = $notices->notices( $section[ 'slug' ] );
					$section[ 'warnings' ] = $notices->warnings( $section[ 'slug' ] );
					$section[ 'critical_warnings' ] = $notices->critical( $section[ 'slug' ] );
				}

				return $section;
			},
			$this->buildAvailableSections()
		) );

		$hasFocus = \count( \array_filter( $sections, function ( array $section ) {
				return $section[ 'is_focus' ];
			} ) ) > 0;
		if ( !$hasFocus ) {
			$sections[ \key( $sections ) ][ 'is_focus' ] = true;
		}

		return $sections;
	}

	protected function buildAvailableSections() :array {
		return \array_filter( \array_map(
			function ( array $nonHiddenSection ) {

				$optionsForSection = $this->buildOptionsForSection( $nonHiddenSection[ 'slug' ] );
				if ( empty( $optionsForSection ) ) {
					$nonHiddenSection = null;
				}
				else {
					$nonHiddenSection = \array_merge( [
						'primary'   => false,
						'options'   => $optionsForSection,
						'beacon_id' => false,
					], $nonHiddenSection );

					if ( self::con()->labels->is_whitelabelled ) {
						$nonHiddenSection[ 'beacon_id' ] = false;
					}
				}

				return $nonHiddenSection;
			},
			\array_filter(
				self::con()->cfg->configuration->sections,
				function ( array $section ) {
					return empty( $this->sections ) || \in_array( $section[ 'slug' ], $this->sections );
				}
			)
		) );
	}

	protected function buildOptionsForSection( string $section ) :array {
		$con = self::con();
		$opts = $con->opts;

		$isPremiumActive = $con->isPremiumActive();

		$allOptions = [];

		foreach ( $section === 'section_hidden' ? [] : $con->cfg->configuration->optsForSection( $section ) as $optDef ) {

			if ( $optDef[ 'section' ] !== $section
				 || ( !empty( $this->options ) && !\in_array( $optDef[ 'key' ], $this->options ) )
			) {
				continue;
			}

			$optDef = \array_merge( [
				'link_info'     => '',
				'link_blog'     => '',
				'value_options' => [],
				'premium'       => false,
				'advanced'      => false,
				'beacon_id'     => false
			], $optDef );

			$optDef[ 'value' ] = $opts->optGet( $optDef[ 'key' ] );

			if ( \in_array( $optDef[ 'type' ], [ 'select', 'multiple_select' ] ) ) {
				$available = [];
				$converted = [];
				foreach ( $optDef[ 'value_options' ] as $valueOpt ) {

					$converted[ $valueOpt[ 'value_key' ] ] = [
						'name'         => esc_html( __( $valueOpt[ 'text' ], 'wp-simple-firewall' ) ),
						'is_available' => $opts->optHasAccess( $optDef[ 'key' ] ),
					];

					if ( $converted[ $valueOpt[ 'value_key' ] ][ 'is_available' ] ) {
						$available[] = $valueOpt[ 'value_key' ];
					}
				}
				$optDef[ 'value_options' ] = $converted;

				/** For multi-selects, only show available options as checked on. */
				if ( \is_array( $optDef[ 'value' ] ) ) {
					$optDef[ 'value' ] = \array_intersect( $optDef[ 'value' ], $available );
				}
			}

			if ( $con->labels->is_whitelabelled ) {
				$optDef[ 'beacon_id' ] = false;
			}

			$allOptions[] = $optDef;
		}
		return $allOptions;
	}

	protected function buildOptionForUi( array $option ) :array {
		$con = self::con();

		$value = $option[ 'value' ];

		switch ( $option[ 'type' ] ) {

			case 'password':
				if ( !empty( $value ) ) {
					$value = '';
				}
				break;

			case 'array':
				if ( empty( $value ) || !\is_array( $value ) ) {
					$value = [];
				}
				$option[ 'rows' ] = \count( $value ) + 2;
				$value = \stripslashes( \implode( "\n", $value ) );
				break;

			case 'multiple_select':
				if ( !\is_array( $value ) ) {
					$value = [];
				}
				break;

			case 'text':
				$value = \stripslashes( $con->opts->optGet( $option[ 'key' ] ) );
				break;
		}

		$isOptAvailable = $con->opts->optHasAccess( $option[ 'key' ] );
		$option = \array_merge(
			[ 'rows' => '2' ],
			$option,
			[
				'value'       => \is_scalar( $value ) ? esc_attr( $value ) : $value,
				'unavailable' => !$isOptAvailable,
				'disabled'    => !$isOptAvailable,
			]
		);

		try {
			$optStrings = ( new StringsOptions() )->getFor( $option[ 'key' ] );
			if ( !\is_array( $optStrings[ 'description' ] ) ) {
				$optStrings[ 'description' ] = [ $optStrings[ 'description' ] ];
			}
			$option = Services::DataManipulation()->mergeArraysRecursive( $option, $optStrings );
		}
		catch ( \Exception $e ) {
		}

		return $this->addPerOptionCustomisation( $option );
	}

	private function addPerOptionCustomisation( array $option ) :array {
		switch ( $option[ 'key' ] ) {

			case 'enable_logger':
				if ( self::con()->comps->opts_lookup->enabledTrafficLimiter() ) {
					$option[ 'disabled' ] = true;
					$option[ 'description' ][] = __( 'Request logging is required when you have activated Traffic Rate Limiting.', 'wp-simple-firewall' );
				}
				break;

			case 'file_locker':
				if ( !Services::Data()->isWindows() ) {
					$option[ 'value_options' ][ 'root_webconfig' ][ 'name' ] .= sprintf( ' (%s)', __( 'IIS only', 'wp-simple-firewall' ) );
					$option[ 'value_options' ][ 'root_webconfig' ][ 'is_available' ] = false;
				}
				if ( !Services::WpFs()->isAccessibleFile( path_join( get_stylesheet_directory(), 'functions.php' ) ) ) {
					$option[ 'value_options' ][ 'theme_functions' ][ 'is_available' ] = false;
				}
				break;

			case 'page_params_whitelist':
				$option[ 'value' ] = \str_replace( ',', ', ', (string)$option[ 'value' ] );
				break;

			case 'importexport_secretkey':
				// need to dynamically regenerate the key for display if it's required.
				$option[ 'value' ] = self::con()->comps->import_export->getImportExportSecretKey();
				break;

			case 'file_scan_areas':
				$option[ 'value_options' ][ 'wp' ][ 'name' ] = sprintf( '%s (%s)', esc_html( __( 'WP core files', 'wp-simple-firewall' ) ),
					sprintf( __( 'excludes %s', 'wp-simple-firewall' ), '<code>/wp-content/</code>' ) );
				$option[ 'value_options' ][ 'wpcontent' ][ 'name' ] = sprintf( __( '%s directory', 'wp-simple-firewall' ), '<code>/wp-content/</code>' );
				break;

			case 'visitor_address_source':
				$ipDetector = Services::IP()->getIpDetector();
				foreach ( \array_keys( $option[ 'value_options' ] ) as $valKey ) {
					if ( $valKey !== 'AUTO_DETECT_IP' ) {
						$IPs = \implode( ', ', $ipDetector->getIpsFromSource( $valKey ) );
						if ( empty( $IPs ) ) {
							unset( $option[ 'value_options' ][ $valKey ] );
						}
						else {
							$option[ 'value_options' ][ $valKey ][ 'name' ] = sprintf( '%s (%s)',
								$option[ 'value_options' ][ $valKey ][ 'name' ],
								$IPs
							);
						}
					}
				}
				break;

			default:
				break;
		}
		return $option;
	}
}