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
		$con = $this->getCon();
		$mod = $this->getMod();
		$UI = $mod->getUIHandler();

		$isPremium = (bool)$con->cfg->properties[ 'enable_premium' ] ?? false;
		$showAdvanced = $con->getModule_Plugin()->isShowAdvanced();

		$opts = $this->getOptions();
		$sections = $this->buildAvailableSections();
		$notices = ( new SectionNotices() )->setCon( $con );

		foreach ( $sections as $sectionKey => $sect ) {

			if ( !empty( $sect[ 'options' ] ) ) {

				foreach ( $sect[ 'options' ] as $optKey => $option ) {
					$option[ 'is_value_default' ] = ( $option[ 'value' ] === $option[ 'default' ] );
					$isOptPremium = $option[ 'premium' ] ?? false;
					$bIsAdv = $option[ 'advanced' ] ?? false;
					if ( ( !$isOptPremium || $isPremium ) && ( !$bIsAdv || $showAdvanced ) ) {
						$sect[ 'options' ][ $optKey ] = $this->buildOptionForUi( $option );
						$sect[ 'options' ][ $optKey ][ 'is_focus' ] = $option[ 'key' ] === $this->focusOption;
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
						$sect = array_merge(
							$sect,
							$this->getMod()
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
					$sections[ $sectionKey ][ 'warnings' ] = array_merge( $warning, $notices->warnings( $sect[ 'slug' ] ) );
					$sections[ $sectionKey ][ 'critical_warnings' ] = $UI->getSectionCriticalWarnings( $sect[ 'slug' ] );
				}
			}
		}

		return $sections;
	}

	protected function buildAvailableSections() :array {
		$opts = $this->getOptions();

		$optionsData = [];

		foreach ( $opts->getSections() as $section ) {

			$section = array_merge(
				[
					'primary'   => false,
					'options'   => $this->buildOptionsForSection( $section[ 'slug' ] ),
					'beacon_id' => false,
				],
				$section
			);

			if ( !empty( $section[ 'options' ] ) ) {

				if ( $this->getCon()->labels->is_whitelabelled ) {
					$section[ 'beacon_id' ] = false;
				}

				$optionsData[] = $section;
			}
		}

		return $optionsData;
	}

	protected function buildOptionsForSection( string $section ) :array {
		$opts = $this->getOptions();

		$allOptions = [];
		foreach ( $opts->getVisibleOptions() as $optDef ) {

			if ( $optDef[ 'section' ] !== $section ) {
				continue;
			}

			$optDef = array_merge(
				[
					'link_info'     => '',
					'link_blog'     => '',
					'value_options' => [],
					'premium'       => false,
					'advanced'      => false,
					'beacon_id'     => false
				],
				$optDef
			);
			$optDef[ 'value' ] = $opts->getOpt( $optDef[ 'key' ] );

			if ( in_array( $optDef[ 'type' ], [ 'select', 'multiple_select' ] ) ) {
				$convertedOptions = [];
				foreach ( $optDef[ 'value_options' ] as $selectValues ) {
					$convertedOptions[ $selectValues[ 'value_key' ] ] = __( $selectValues[ 'text' ], 'wp-simple-firewall' );
				}
				$optDef[ 'value_options' ] = $convertedOptions;
			}

			if ( $this->getCon()->labels->is_whitelabelled ) {
				$optDef[ 'beacon_id' ] = false;
			}

			$allOptions[] = $optDef;
		}
		return $allOptions;
	}

	protected function buildOptionForUi( array $option ) :array {

		$value = $option[ 'value' ];

		switch ( $option[ 'type' ] ) {

			case 'password':
				if ( !empty( $value ) ) {
					$value = '';
				}
				break;

			case 'array':
				if ( empty( $value ) || !is_array( $value ) ) {
					$value = [];
				}

				$option[ 'rows' ] = count( $value ) + 2;
				$value = stripslashes( implode( "\n", $value ) );

				break;

			case 'comma_separated_lists':

				$converted = [];
				if ( !empty( $value ) && is_array( $value ) ) {
					foreach ( $value as $page => $params ) {
						$converted[] = $page.', '.implode( ", ", $params );
					}
				}
				$option[ 'rows' ] = count( $converted ) + 1;
				$value = implode( "\n", $converted );

				break;

			case 'multiple_select':
				if ( !is_array( $value ) ) {
					$value = [];
				}
				break;

			case 'text':
				$value = stripslashes( $this->getMod()->getTextOpt( $option[ 'key' ] ) );
				break;
		}

		$params = [
			'value'    => is_scalar( $value ) ? esc_attr( $value ) : $value,
			'disabled' => !$this->getCon()
								->isPremiumActive() && ( isset( $option[ 'premium' ] ) && $option[ 'premium' ] ),
		];
		$params[ 'enabled' ] = !$params[ 'disabled' ];
		$option = array_merge( [ 'rows' => '2' ], $option, $params );

		// add strings
		try {
			$optStrings = $this->getMod()->getStrings()->getOptionStrings( $option[ 'key' ] );
			if ( !is_array( $optStrings[ 'description' ] ) ) {
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
					$option[ 'value_options' ][ 'root_webconfig' ] .= sprintf( ' (%s)', __( 'unavailable', 'wp-simple-firewall' ) );
				}
				break;

			case 'visitor_address_source':
				$newOptions = [];
				$ipDetector = Services::IP()->getIpDetector();
				foreach ( $option[ 'value_options' ] as $valKey => $source ) {
					if ( $valKey == 'AUTO_DETECT_IP' ) {
						$newOptions[ $valKey ] = $source;
					}
					else {
						$IPs = implode( ', ', $ipDetector->getIpsFromSource( $source ) );
						if ( !empty( $IPs ) ) {
							$newOptions[ $valKey ] = sprintf( '%s (%s)', $source, $IPs );
						}
					}
				}
				$option[ 'value_options' ] = $newOptions;
				break;

			default:
				break;
		}
		return $option;
	}

	/**
	 * @deprecated 17.0
	 */
	public function setIsWhitelabelled( bool $isOrNot ) :self {
		return $this;
	}
}