<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules\{
	StringsOptions,
	StringsSections
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Utility\FileLockKeyApplicability;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class BuildOptionsForDisplay {

	use PluginControllerConsumer;

	private array $options;

	private array $sections;

	private array $values = [];

	/**
	 * False when the values come from elsewhere, such as a sync profile, so this site's files and server mustn't
	 * limit them.
	 */
	private bool $valuesAreThisSite = true;

	public function __construct( array $options = [], array $sections = [] ) {
		$this->options = $options;
		$this->sections = $sections;
	}

	/**
	 * What a setting, a choice or a whole topic is labelled when the site's plan doesn't include it.
	 */
	public static function outsidePlanLabel() :string {
		return __( 'Pro only', 'wp-simple-firewall' );
	}

	public function setValues( array $values ) :self {
		$this->values = $values;
		$this->valuesAreThisSite = false;
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
					}

					$section = \array_merge( $section, ( new StringsSections() )->getFor( $section[ 'slug' ] ) );
				}

				return $section;
			},
			$this->buildAvailableSections()
		) );

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
				fn( array $section ) => empty( $this->sections ) || \in_array( $section[ 'slug' ], $this->sections )
			)
		) );
	}

	protected function buildOptionsForSection( string $section ) :array {
		$con = self::con();
		$opts = $con->opts;

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

			$optDef[ 'value' ] = $this->optionValue( $optDef[ 'key' ] );

			if ( \in_array( $optDef[ 'type' ], [ 'select', 'multiple_select' ] ) ) {
				$available = [];
				$converted = [];
				foreach ( $optDef[ 'value_options' ] as $valueOpt ) {
					$hasAccess = $opts->optValueHasAccess( $optDef[ 'key' ], (string)$valueOpt[ 'value_key' ] );

					$converted[ $valueOpt[ 'value_key' ] ] = [
						'name'              => esc_html( __( $valueOpt[ 'text' ], 'wp-simple-firewall' ) ), // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Option choice text comes from plugin.json, so it can't be a literal here.
						'is_available'      => $hasAccess,
						// Why a choice is unavailable, as a hover title: '' when there's nothing to add.
						'unavailable_title' => $hasAccess ? '' : self::outsidePlanLabel(),
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
				$value = \stripslashes( (string)$value );
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
				'unit_label'  => $this->unitLabel( (string)( $option[ 'unit' ] ?? '' ) ),
			],
			$this->buildDefaultForUi( $option )
		);

		try {
			$option = Services::DataManipulation()->mergeArraysRecursive( $option, ( new StringsOptions() )->getFor( $option[ 'key' ] ) );
		}
		catch ( \Exception $e ) {
		}
		// A description is lines of text, whether the plugin's strings or the spec supplied it, or none at all.
		$option[ 'description' ] = \is_array( $option[ 'description' ] ?? null )
			? \array_values( $option[ 'description' ] )
			: \array_filter( [ (string)( $option[ 'description' ] ?? '' ) ] );

		return $this->addPerOptionCustomisation( $option );
	}

	private function unitLabel( string $unit ) :string {
		$labels = [
			'seconds'  => __( 'seconds', 'wp-simple-firewall' ),
			'minutes'  => __( 'minutes', 'wp-simple-firewall' ),
			'hours'    => __( 'hours', 'wp-simple-firewall' ),
			'days'     => __( 'days', 'wp-simple-firewall' ),
			'comments' => __( 'comments', 'wp-simple-firewall' ),
			'offenses' => __( 'offenses', 'wp-simple-firewall' ),
			'requests' => __( 'requests', 'wp-simple-firewall' ),
		];
		return $labels[ $unit ] ?? '';
	}

	/**
	 * Single-value options get a readable default and the raw value that "Reset to default" restores.
	 * List-style options and passwords have no meaningful one-line default, so they get neither.
	 * @return array{default_label:string,default_value:string}
	 */
	private function buildDefaultForUi( array $option ) :array {
		$default = $option[ 'default' ] ?? null;
		$label = '';

		if ( \is_scalar( $default ) ) {
			$default = (string)$default;
			switch ( $option[ 'type' ] ) {
				case 'checkbox':
					$label = $default === 'Y' ? __( 'Enabled', 'wp-simple-firewall' ) : __( 'Disabled', 'wp-simple-firewall' );
					break;
				case 'select':
					$label = wp_strip_all_tags( \html_entity_decode( (string)( $option[ 'value_options' ][ $default ][ 'name' ] ?? '' ) ) );
					break;
				case 'integer':
				case 'text':
				case 'email':
					$label = $default === '' ? __( 'Blank', 'wp-simple-firewall' ) : $default;
					break;
				default:
					break;
			}
		}

		return [
			'default_label' => $label,
			'default_value' => $label === '' ? '' : $default,
		];
	}

	private function optionValue( string $key ) {
		return \array_key_exists( $key, $this->values ) ? $this->values[ $key ] : self::con()->opts->optGet( $key );
	}

	private function addPerOptionCustomisation( array $option ) :array {
		switch ( $option[ 'key' ] ) {

			case 'file_locker':
				if ( !$this->valuesAreThisSite ) {
					break;
				}
				$applicability = FileLockKeyApplicability::fromCurrentEnvironment();
				/** @var array<array-key,mixed> $fileLockerValue */
				$fileLockerValue = $option[ 'value' ];
				$option[ 'value' ] = $applicability->removeServerUnsupportedKeys( $fileLockerValue );
				foreach ( \array_map( 'strval', \array_keys( $option[ 'value_options' ] ) ) as $fileKey ) {
					if ( !$applicability->isServerSupported( $fileKey ) ) {
						$option[ 'value_options' ][ $fileKey ][ 'name' ] .= sprintf( ' (%s)', __( 'IIS only', 'wp-simple-firewall' ) );
						$option[ 'value_options' ][ $fileKey ][ 'is_available' ] = false;
					}
					elseif ( !$applicability->isApplicable( $fileKey ) ) {
						$option[ 'value_options' ][ $fileKey ][ 'name' ] .= sprintf( ' (%s)', __( 'not found', 'wp-simple-firewall' ) );
						// A selected file that's gone missing stays tickable, so saving keeps it (and its lock).
						if ( !\in_array( $fileKey, $option[ 'value' ], true ) ) {
							$option[ 'value_options' ][ $fileKey ][ 'is_available' ] = false;
						}
					}
				}
				break;

			case 'page_params_whitelist':
				$option[ 'value' ] = \str_replace( ',', ', ', (string)$option[ 'value' ] );
				break;

			case 'importexport_secretkey':
				// need to dynamically regenerate the key for display if it's required.
				$option[ 'value' ] = self::con()->comps->import_export->getImportExportSecretKey();
				break;

			case 'user_auto_recover':
				$option[ 'value_options' ][ 'gasp' ][ 'name' ] = sprintf(
					/* translators: %s: name of the silent CAPTCHA feature */
					__( 'Protected By %s', 'wp-simple-firewall' ),
					self::con()->labels->getBrandName( 'silentcaptcha' )
				);
				break;

			case 'file_scan_areas':
				$option[ 'value_options' ][ 'wp' ][ 'name' ] = sprintf( '%s (%s)', esc_html( __( 'WP core files', 'wp-simple-firewall' ) ),
					/* translators: %s: directory path, wrapped in code tags */
					sprintf( __( 'excludes %s', 'wp-simple-firewall' ), '<code>/wp-content/</code>' ) );
				/* translators: %s: directory path, wrapped in code tags */
				$option[ 'value_options' ][ 'wpcontent' ][ 'name' ] = sprintf( __( '%s directory', 'wp-simple-firewall' ), '<code>/wp-content/</code>' );
				break;

			case 'visitor_address_source':
				$ipDetector = Services::IP()->getIpDetector();
				foreach ( \array_keys( $option[ 'value_options' ] ) as $valKey ) {
					if ( $valKey !== 'AUTO_DETECT_IP' ) {
						// The choice name is printed as HTML, and getIpsFromSource() returns each header entry as sent
						// when only its "IP:port" form validates, so anything after the colon is escaped here.
						$IPs = esc_html( \implode( ', ', $ipDetector->getIpsFromSource( $valKey ) ) );
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
