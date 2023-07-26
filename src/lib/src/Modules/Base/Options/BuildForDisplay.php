<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class BuildForDisplay {

	use ModConsumer;

	private $focusOption;

	private $focusSection;

	public function __construct( string $focusSection = '', string $focusOption = '' ) {
		$this->focusSection = $focusSection;
		$this->focusOption = $focusOption;
	}

	/**
	 * Will initiate the plugin options structure for use by the UI builder.
	 * It doesn't set any values, just populates the array created in buildOptions()
	 * with values stored.
	 * It has to handle the conversion of stored values to data to be displayed to the user.
	 */
	public function standard() :array {
		$con = $this->con();

		$isPremium = (bool)$con->cfg->properties[ 'enable_premium' ] ?? false;
		$showAdvanced = $con->getModule_Plugin()->isShowAdvanced();

		$opts = $this->getOptions();
		$sections = $this->buildAvailableSections();
		$notices = new SectionNotices();

		foreach ( $sections as $sectionKey => $sect ) {

			if ( !empty( $sect[ 'options' ] ) ) {

				foreach ( $sect[ 'options' ] as $optKey => $opt ) {
					$opt[ 'is_value_default' ] = $opt[ 'value' ] === $opt[ 'default' ];
					$isOptPremium = ( $opt[ 'premium' ] ?? false ) || !empty( $opt[ 'cap' ] );
					$isAdv = $opt[ 'advanced' ] ?? false;
					if ( ( !$isOptPremium || $isPremium ) && ( !$isAdv || $showAdvanced ) ) {
						$sect[ 'options' ][ $optKey ] = $this->buildOptionForUi( $opt );
						$sect[ 'options' ][ $optKey ][ 'is_focus' ] = $opt[ 'key' ] === $this->focusOption;
					}
					else {
						unset( $sect[ 'options' ][ $optKey ] );
					}
				}

				if ( empty( $sect[ 'options' ] ) ) {
					unset( $sections[ $sectionKey ] );
				}
				else {
					try {
						$sect = \array_merge(
							$sect,
							$this->mod()
								 ->getStrings()
								 ->getSectionStrings( $sect[ 'slug' ] )
						);
					}
					catch ( \Exception $e ) {
					}
					$sections[ $sectionKey ] = $sect;
				}

				$sections[ $sectionKey ][ 'is_focus' ] = $sect[ 'slug' ] === $this->focusSection;

				if ( isset( $sections[ $sectionKey ] ) ) {
					$warning = [];
					if ( !$opts->isSectionReqsMet( $sect[ 'slug' ] ) ) {
						$warning[] = __( 'Unfortunately your WordPress and/or PHP versions are too old to support this feature.', 'wp-simple-firewall' );
					}
					$sections[ $sectionKey ][ 'notices' ] = $notices->notices( $sect[ 'slug' ] );
					$sections[ $sectionKey ][ 'warnings' ] = \array_merge( $warning, $notices->warnings( $sect[ 'slug' ] ) );
					$sections[ $sectionKey ][ 'critical_warnings' ] = $notices->critical( $sect[ 'slug' ] );
				}
			}
		}

		return $sections;
	}

	protected function buildAvailableSections() :array {
		$opts = $this->getOptions();

		$optionsData = [];

		foreach ( $opts->getSections() as $section ) {

			$section = \array_merge(
				[
					'primary'   => false,
					'options'   => $this->buildOptionsForSection( $section[ 'slug' ] ),
					'beacon_id' => false,
				],
				$section
			);

			if ( !empty( $section[ 'options' ] ) ) {

				if ( $this->con()->labels->is_whitelabelled ) {
					$section[ 'beacon_id' ] = false;
				}

				$optionsData[] = $section;
			}
		}

		return $optionsData;
	}

	protected function buildOptionsForSection( string $section ) :array {
		$con = $this->con();
		$opts = $this->getOptions();

		$isPremiumActive = $con->isPremiumActive();

		$allOptions = [];
		foreach ( $opts->getVisibleOptions() as $optDef ) {

			if ( $optDef[ 'section' ] !== $section ) {
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

			$optDef[ 'value' ] = $opts->getOpt( $optDef[ 'key' ] );

			if ( \in_array( $optDef[ 'type' ], [ 'select', 'multiple_select' ] ) ) {
				$available = [];
				$converted = [];
				foreach ( $optDef[ 'value_options' ] as $valueOpt ) {

					$isDisabled = ( !empty( $valueOpt[ 'premium' ] ) && !$isPremiumActive )
								  || ( !empty( $valueOpt[ 'cap' ] ) && !$con->caps->hasCap( $valueOpt[ 'cap' ] ) );

					$converted[ $valueOpt[ 'value_key' ] ] = [
						'name'         => esc_html( __( $valueOpt[ 'text' ], 'wp-simple-firewall' ) ),
						'is_available' => !$isDisabled,
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
		$con = $this->con();

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

			case 'comma_separated_lists':
				$converted = [];
				if ( !empty( $value ) && \is_array( $value ) ) {
					foreach ( $value as $page => $params ) {
						$converted[] = $page.', '.\implode( ", ", $params );
					}
				}
				$option[ 'rows' ] = \count( $converted ) + 1;
				$value = \implode( "\n", $converted );

				break;

			case 'multiple_select':
				if ( !\is_array( $value ) ) {
					$value = [];
				}
				break;

			case 'text':
				$value = \stripslashes( $this->mod()->getTextOpt( $option[ 'key' ] ) );
				break;
		}

		$isOptDisabled = ( !empty( $option[ 'premium' ] ) && !$con->isPremiumActive() )
						 || ( !empty( $option[ 'cap' ] ) && !$con->caps->hasCap( $option[ 'cap' ] ) );
		$params = [
			'value'    => \is_scalar( $value ) ? esc_attr( $value ) : $value,
			'disabled' => $isOptDisabled,
		];
		$params[ 'enabled' ] = !$params[ 'disabled' ];
		$option = \array_merge( [ 'rows' => '2' ], $option, $params );

		// add strings
		try {
			$optStrings = $this->mod()->getStrings()->getOptionStrings( $option[ 'key' ] );
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

			case 'file_locker':
				if ( !Services::Data()->isWindows() ) {
					$option[ 'value_options' ][ 'root_webconfig' ][ 'name' ] .= sprintf( ' (%s)', __( 'IIS only', 'wp-simple-firewall' ) );
					$option[ 'value_options' ][ 'root_webconfig' ][ 'is_available' ] = false;
				}
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