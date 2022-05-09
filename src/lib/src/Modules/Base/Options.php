<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Config\ModConfigVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options\OptValueSanitize;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Options {

	use ModConsumer;

	private $cfgLoader;

	private $optsStorage;

	/**
	 * @var array
	 */
	protected $aOptionsValues;

	/**
	 * @var array
	 */
	protected $aOld;

	/**
	 * @var bool
	 */
	protected $bNeedSave = false;

	/**
	 * @var string
	 */
	protected $aOptionsKeys;

	public function __construct() {
	}

	/**
	 * @param bool $deleteFirst Used primarily with plugin reset
	 * @param bool $isPremium
	 * @return bool
	 */
	public function doOptionsSave( $deleteFirst = false, $isPremium = false ) {
		if ( !$this->getNeedSave() ) {
			return true;
		}
		$this->cleanOptions();
		if ( !$isPremium ) {
			$this->resetPremiumOptsToDefault();
		}
		$this->setNeedSave( false );

		return $this->getOptsStorage()->storeOptions( $this->getAllOptionsValues(), $deleteFirst );
	}

	public function deleteStorage() {
		$this->getOptsStorage()->deleteOptions();
	}

	public function getAllOptionsValues() :array {
		if ( !isset( $this->aOptionsValues ) ) {
			try {
				$this->aOptionsValues = $this->getOptsStorage()->loadOptions();
			}
			catch ( \Exception $e ) {
				$this->aOptionsValues = [];
				$this->setNeedSave( true );
			}
		}
		return $this->aOptionsValues;
	}

	/**
	 * Returns an array of all the transferable options and their values
	 */
	public function getTransferableOptions() :array {
		$transferable = [];

		foreach ( $this->getRawData_AllOptions() as $option ) {
			if ( $option[ 'transferable' ] ?? true ) {
				$transferable[ $option[ 'key' ] ] = $this->getOpt( $option[ 'key' ] );
			}
		}
		return $transferable;
	}

	/**
	 * Returns an array of all the options with the values for "sensitive" options masked out.
	 * @return array
	 */
	public function getOptionsMaskSensitive() {

		$aOptions = $this->getAllOptionsValues();
		foreach ( $this->getOptionsKeys() as $key ) {
			if ( !isset( $aOptions[ $key ] ) ) {
				$aOptions[ $key ] = $this->getOptDefault( $key );
			}
		}
		foreach ( $this->getRawData_AllOptions() as $optDef ) {
			if ( isset( $optDef[ 'sensitive' ] ) && $optDef[ 'sensitive' ] === true ) {
				unset( $aOptions[ $optDef[ 'key' ] ] );
			}
		}
		return array_diff_key( $aOptions, array_flip( $this->getVirtualCommonOptions() ) );
	}

	/**
	 * @return string[]
	 */
	public function getOptionsForWpCli() :array {
		return array_filter(
			$this->getOptionsKeys(),
			function ( $key ) {
				$opt = $this->getOptDefinition( $key );
				return !empty( $opt[ 'section' ] ) && $opt[ 'section' ] !== 'section_non_ui';
			}
		);
	}

	/**
	 * Returns an array of all the options with the values for "sensitive" options masked out.
	 */
	public function getOptionsForTracking() :array {
		$opts = [];
		if ( !$this->getMod()->cfg->properties[ 'tracking_exclude' ] ) {

			$options = $this->getAllOptionsValues();
			foreach ( $this->getOptionsKeys() as $key ) {
				if ( !isset( $options[ $key ] ) ) {
					$options[ $key ] = $this->getOptDefault( $key );
				}
			}
			foreach ( $this->getRawData_AllOptions() as $optDef ) {
				if ( !empty( $optDef[ 'sensitive' ] ) || !empty( $optDef[ 'tracking_exclude' ] ) ) {
					unset( $options[ $optDef[ 'key' ] ] );
				}
			}
			$opts = array_diff_key( $options, array_flip( $this->getVirtualCommonOptions() ) );
		}
		return $opts;
	}

	/**
	 * @return mixed|null
	 */
	public function getFeatureProperty( string $property ) {
		return ( $this->getRawData_FullFeatureConfig()[ 'properties' ] ?? [] )[ $property ] ?? null;
	}

	/**
	 * @return mixed|null
	 */
	public function getDef( string $key ) {
		return ( $this->getRawData_FullFeatureConfig()[ 'definitions' ] ?? [] )[ $key ] ?? null;
	}

	public function getEvents() :array {
		return is_array( $this->getDef( 'events' ) ) ? $this->getDef( 'events' ) : [];
	}

	public function getFeatureRequirement( string $req ) :array {
		return $this->getRawData_Requirements()[ $req ] ?? [];
	}

	public function getAdminNotices() :array {
		return $this->getRawData_FullFeatureConfig()[ 'admin_notices' ] ?? [];
	}

	public function isValidOptionKey( string $key ) :bool {
		return in_array( $key, $this->getOptionsKeys() );
	}

	public function ensureOptValueType( string $key, $value ) {
		switch ( $this->getOptionType( $key ) ) {
			case 'boolean':
				$value = (bool)$value;
				break;
			case 'integer':
				$value = (int)$value;
				break;
			case 'text':
				$value = (string)$value;
				break;
			case 'array':
			case 'multiple_select':
				if ( !is_array( $value ) ) {
					$value = (array)$value;
				}
				break;
			default:
				break;
		}
		return $value;
	}

	public function isValidOptionValueType( string $key, $value ) :bool {
		switch ( $this->getOptionType( $key ) ) {
			case 'array':
			case 'multiple_select':
				$valid = is_array( $value );
				break;
			case 'integer':
				$valid = is_numeric( $value );
				break;
			default:
				$valid = true;
				break;
		}
		return $valid;
	}

	/**
	 * @return array[]
	 */
	public function getHiddenOptions() :array {

		$aOptionsData = [];

		foreach ( $this->getRawData_OptionsSections() as $aRawSection ) {

			// if hidden isn't specified we skip
			if ( !isset( $aRawSection[ 'hidden' ] ) || !$aRawSection[ 'hidden' ] ) {
				continue;
			}
			foreach ( $this->getRawData_AllOptions() as $aRawOption ) {

				if ( $aRawOption[ 'section' ] != $aRawSection[ 'slug' ] ) {
					continue;
				}
				$aOptionsData[ $aRawOption[ 'key' ] ] = $this->getOpt( $aRawOption[ 'key' ] );
			}
		}
		return $aOptionsData;
	}

	/**
	 * @return array|null
	 */
	public function getSection( string $section ) {
		return $this->getSections()[ $section ] ?? null;
	}

	/**
	 * @param bool $includeHidden
	 * @return array[]
	 */
	public function getSections( $includeHidden = false ) {
		$sections = [];
		foreach ( $this->getRawData_OptionsSections() as $section ) {
			if ( $includeHidden || empty( $section[ 'hidden' ] ) ) {
				$sections[ $section[ 'slug' ] ] = $section;
			}
		}
		return $sections;
	}

	public function getPrimarySection() :array {
		$theSection = [];
		foreach ( $this->getSections() as $section ) {
			if ( $section[ 'primary' ] ?? false ) {
				$theSection = $section;
				break;
			}
		}
		return $theSection;
	}

	/**
	 * @param string $slug
	 */
	public function getSection_Requirements( $slug ) :array {
		$section = $this->getSection( $slug );
		return array_merge(
			[
				'php_min' => '7.0',
				'wp_min'  => '3.7',
			],
			( is_array( $section ) && isset( $section[ 'reqs' ] ) ) ? $section[ 'reqs' ] : []
		);
	}

	/**
	 * @param string $slug
	 */
	public function isSectionReqsMet( $slug ) :bool {
		$reqs = $this->getSection_Requirements( $slug );
		return Services::Data()->getPhpVersionIsAtLeast( $reqs[ 'php_min' ] )
			   && Services::WpGeneral()->getWordpressIsAtLeastVersion( $reqs[ 'wp_min' ] );
	}

	public function isOptReqsMet( string $key ) :bool {
		return $this->isSectionReqsMet( $this->getOptProperty( $key, 'section' ) );
	}

	/**
	 * @return array[]
	 */
	public function getVisibleOptions() :array {
		return array_filter(
			$this->getRawData_AllOptions(),
			function ( $optDef ) {
				if ( $optDef[ 'hidden' ] ?? false ) {
					return null;
				}
				$section = $this->getSection( $optDef[ 'section' ] );
				if ( empty( $section ) || ( $section[ 'hidden' ] ?? false ) ) {
					return null;
				}
				return $optDef;
			}
		);
	}

	/**
	 * @return string[]
	 */
	public function getVisibleOptionsKeys() :array {
		return array_map( function ( $optDef ) {
			return $optDef[ 'key' ];
		}, $this->getVisibleOptions() );
	}

	public function getAdditionalMenuItems() :array {
		return $this->getRawData_FullFeatureConfig()[ 'menu_items' ] ?? [];
	}

	public function getNeedSave() :bool {
		return $this->bNeedSave;
	}

	/**
	 * @param string $key
	 * @return mixed|null
	 */
	public function getOldValue( string $key ) {
		return $this->isOptChanged( $key ) ? $this->aOld[ $key ] : null;
	}

	/**
	 * @param mixed $mDefault
	 * @return mixed
	 */
	public function getOpt( string $key, $mDefault = false ) {
		$value = $this->getAllOptionsValues()[ $key ] ?? null;

		if ( is_null( $value ) || !$this->isValidOptionValueType( $key, $value ) ) {
			$value = $this->getOptDefault( $key, $mDefault );
			$this->setOpt( $key, $value );
		}

		return $this->ensureOptValueType( $key, $this->aOptionsValues[ $key ] ?? $mDefault );
	}

	/**
	 * @param mixed $mDefault
	 * @return mixed|null
	 */
	public function getOptDefault( string $key, $mDefault = null ) {
		$def = $this->getOptDefinition( $key );
		return $def[ 'default' ] ?? ( $def[ 'value' ] ?? $mDefault );
	}

	public function getOptDefinition( string $key ) :array {
		return $this->getRawData_AllOptions()[ $key ] ?? [];
	}

	/**
	 * @param mixed $mValueToTest
	 * @param bool  $strict
	 */
	public function isOpt( string $key, $mValueToTest, $strict = false ) :bool {
		return $strict ? $this->getOpt( $key ) === $mValueToTest : $this->getOpt( $key ) == $mValueToTest;
	}

	/**
	 * @return string|null
	 */
	public function getOptionType( string $key ) {
		return $this->getOptDefinition( $key )[ 'type' ] ?? null;
	}

	public function getOptionsKeys() :array {
		if ( !isset( $this->aOptionsKeys ) ) {
			$this->aOptionsKeys = array_merge(
				array_keys( $this->getRawData_AllOptions() ),
				$this->getCommonStandardOptions(),
				$this->getVirtualCommonOptions()
			);
		}
		return $this->aOptionsKeys;
	}

	/**
	 * @return mixed|null
	 */
	public function getOptProperty( string $key, string $prop ) {
		return $this->getOptDefinition( $key )[ $prop ] ?? null;
	}

	public function cfg() :ModConfigVO {
		return $this->getMod()->cfg;
	}

	public function getRawData_FullFeatureConfig() :array {
		// TODO: use the cfg directly throughout instead of via array
		return empty( $this->aRawOptionsConfigData ) ? $this->cfg()->getRawData() : $this->aRawOptionsConfigData;
	}

	protected function getRawData_AllOptions() :array {
		return $this->getRawData_FullFeatureConfig()[ 'options' ] ?? [];
	}

	protected function getRawData_OptionsSections() :array {
		return $this->getRawData_FullFeatureConfig()[ 'sections' ] ?? [];
	}

	protected function getRawData_Requirements() :array {
		return $this->getRawData_FullFeatureConfig()[ 'requirements' ] ?? [];
	}

	public function getSelectOptionValueText( string $key ) :string {
		$text = '';
		foreach ( $this->getOptDefinition( $key )[ 'value_options' ] as $opt ) {
			if ( $opt[ 'value_key' ] == $this->getOpt( $key ) ) {
				$text = $opt[ 'text' ];
				break;
			}
		}
		return $text;
	}

	public function isOptAdvanced( string $key ) :bool {
		return (bool)$this->getOptProperty( $key, 'advanced' );
	}

	public function isOptChanged( string $key ) :bool {
		return is_array( $this->aOld ) && isset( $this->aOld[ $key ] );
	}

	public function isOptPremium( string $key ) :bool {
		return (bool)$this->getOptProperty( $key, 'premium' );
	}

	public function optExists( string $key ) :bool {
		return !empty( $this->getOptDefinition( $key ) );
	}

	public function resetOptToDefault( string $key ) :self {
		return $this->setOpt( $key, $this->getOptDefault( $key ) );
	}

	/**
	 * Will traverse each premium option and set it to the default.
	 */
	public function resetPremiumOptsToDefault() {
		foreach ( $this->getRawData_AllOptions() as $opt ) {
			if ( $opt[ 'premium' ] ?? false ) {
				$this->resetOptToDefault( $opt[ 'key' ] );
			}
		}
	}

	public function setNeedSave( bool $need ) {
		$this->bNeedSave = $need;
	}

	public function setMultipleOptions( array $options ) {
		foreach ( $options as $key => $value ) {
			$this->setOpt( $key, $value );
		}
	}

	/**
	 * @param mixed $newValue
	 * @return $this
	 */
	public function setOpt( string $key, $newValue ) :self {

		// NOTE: can't use getOpt() for current value as it'll create infinite loop
		$mCurrent = $this->getAllOptionsValues()[ $key ] ?? null;

		try {
			$newValue = ( new OptValueSanitize() )
				->setMod( $this->getMod() )
				->run( $key, $newValue );
			$verified = true;
		}
		catch ( \Exception $e ) {
			$verified = false;
		}

		if ( $verified ) {
			// Here we try to ensure that values that are repeatedly changed properly reflect their changed
			// states, as they may be reverted back to their original state and we "think" it's been changed.
			$bValueIsDifferent = serialize( $mCurrent ) !== serialize( $newValue );
			// basically if we're actually resetting back to the original value
			$bIsResetting = $bValueIsDifferent && $this->isOptChanged( $key )
							&& ( serialize( $this->getOldValue( $key ) ) === serialize( $newValue ) );

			if ( $bValueIsDifferent && $this->verifyCanSet( $key, $newValue ) ) {
				$this->setNeedSave( true );

				//Load the config and do some pre-set verification where possible. This will slowly grow.
				if ( $this->getOptionType( $key ) === 'boolean' && !is_bool( $newValue ) ) {
					return $this->resetOptToDefault( $key );
				}
				$this->setOldOptValue( $key, $mCurrent )
					 ->setOptValue( $key, $newValue );
			}

			if ( $bIsResetting ) {
				unset( $this->aOld[ $key ] );
			}
		}

		return $this;
	}

	/**
	 * @return $this
	 */
	public function setOptAt( string $key ) {
		return $this->setOpt( $key, Services::Request()->ts() );
	}

	/**
	 * Use this to directly set the option value without the risk of any recursion.
	 * @param mixed $value
	 * @return $this
	 */
	protected function setOptValue( string $key, $value ) {
		$values = $this->getAllOptionsValues();
		$values[ $key ] = $value;
		$this->aOptionsValues = $values;
		return $this;
	}

	/**
	 * @param mixed $mPotentialValue
	 */
	private function verifyCanSet( string $key, $mPotentialValue ) :bool {
		$valid = true;

		switch ( $this->getOptionType( $key ) ) {

			case 'integer':
				$min = $this->getOptProperty( $key, 'min' );
				if ( !is_null( $min ) ) {
					$valid = $mPotentialValue >= $min;
				}
				if ( $valid ) {
					$max = $this->getOptProperty( $key, 'max' );
					if ( !is_null( $max ) ) {
						$valid = $mPotentialValue <= $max;
					}
				}
				break;

			case 'array':
				$valid = is_array( $mPotentialValue );
				break;

			case 'select':
				$valid = in_array( $mPotentialValue, array_map(
					function ( $valueOptions ) {
						return $valueOptions[ 'value_key' ];
					},
					$this->getOptProperty( $key, 'value_options' )
				) );
				break;

			case 'email':
				$valid = empty( $mPotentialValue ) || Services::Data()->validEmail( $mPotentialValue );
				break;
		}
		return $valid;
	}

	/**
	 * @param string $key
	 * @param mixed  $value
	 * @return $this
	 */
	private function setOldOptValue( $key, $value ) {
		if ( !is_array( $this->aOld ) ) {
			$this->aOld = [];
		}
		if ( !isset( $this->aOld[ $key ] ) ) {
			$this->aOld[ $key ] = $value;
		}
		return $this;
	}

	/**
	 * @param string $sOptionKey
	 * @return mixed
	 */
	public function unsetOpt( $sOptionKey ) {
		unset( $this->aOptionsValues[ $sOptionKey ] );
		$this->setNeedSave( true );
		return true;
	}

	/** PRIVATE STUFF */

	/**
	 * @return array
	 */

	protected function getCommonStandardOptions() {
		return [];
	}

	protected function getVirtualCommonOptions() :array {
		return [
			'dismissed_notices',
			'ui_track',
			'xfer_excluded',
			'cfg_version'
		];
	}

	/**
	 * @return string[]
	 */
	public function getXferExcluded() :array {
		return is_array( $this->getOpt( 'xfer_excluded' ) ) ? $this->getOpt( 'xfer_excluded' ) : [];
	}

	private function cleanOptions() {
		if ( !empty( $this->aOptionsValues ) && is_array( $this->aOptionsValues ) ) {
			$this->aOptionsValues = array_intersect_key(
				$this->getAllOptionsValues(),
				array_merge(
					array_flip( $this->getOptionsKeys() ),
					array_flip( $this->getVirtualCommonOptions() )
				)
			);
		}
	}

	public function getConfigLoader() :Config\LoadConfig {
		if ( empty( $this->cfgLoader ) ) {
			$this->cfgLoader = new Config\LoadConfig( $this->getMod()->getSlug() );
		}
		return $this->cfgLoader;
	}

	private function getOptsStorage() :Options\Storage {
		if ( empty( $this->optsStorage ) ) {
			$this->optsStorage = ( new Options\Storage() )->setMod( $this->getMod() );
		}
		return $this->optsStorage;
	}

	/**
	 * @return $this
	 */
	public function setOptionsValues( array $values = [] ) {
		$this->aOptionsValues = $values;
		$this->setNeedSave( true );
		return $this;
	}
}