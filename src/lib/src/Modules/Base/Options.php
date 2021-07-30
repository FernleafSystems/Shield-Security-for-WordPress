<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options\OptValueSanitize;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;

class Options {

	use ModConsumer;

	/**
	 * @var array
	 */
	protected $aOptionsValues;

	/**
	 * @var array
	 */
	protected $aOld;

	/**
	 * @var array
	 */
	protected $aRawOptionsConfigData;

	/**
	 * @var bool
	 */
	protected $bNeedSave;

	/**
	 * @var bool
	 */
	protected $bRebuildFromFile = false;

	/**
	 * @var string
	 */
	protected $aOptionsKeys;

	/**
	 * @var string
	 */
	protected $sOptionsStorageKey;

	/**
	 *  by default we load from saved
	 * @var string
	 */
	protected $bLoadFromSaved = true;

	/**
	 * @var string
	 */
	protected $sPathToConfig;

	public function __construct() {
	}

	/**
	 * @param bool $bDeleteFirst Used primarily with plugin reset
	 * @param bool $bIsPremiumLicensed
	 * @return bool
	 */
	public function doOptionsSave( $bDeleteFirst = false, $bIsPremiumLicensed = false ) {
		if ( !$this->getNeedSave() ) {
			return true;
		}
		$this->cleanOptions();
		if ( !$bIsPremiumLicensed ) {
			$this->resetPremiumOptsToDefault();
		}
		$this->setNeedSave( false );
		if ( $bDeleteFirst ) {
			Services::WpGeneral()->deleteOption( $this->getOptionsStorageKey() );
		}
		return Services::WpGeneral()->updateOption( $this->getOptionsStorageKey(), $this->getAllOptionsValues() );
	}

	/**
	 * @return bool
	 */
	public function deleteStorage() {
		$oWp = Services::WpGeneral();
		$oWp->deleteOption( $this->getConfigStorageKey() );
		return $oWp->deleteOption( $this->getOptionsStorageKey() );
	}

	public function getAllOptionsValues() :array {
		return $this->getStoredOptions();
	}

	public function getSlug() :string {
		return (string)$this->getFeatureProperty( 'slug' );
	}

	/**
	 * Returns an array of all the transferable options and their values
	 * @return array
	 */
	public function getTransferableOptions() {
		$aTransferable = [];

		foreach ( $this->getRawData_AllOptions() as $nKey => $aOptionData ) {
			if ( !isset( $aOptionData[ 'transferable' ] ) || $aOptionData[ 'transferable' ] === true ) {
				$aTransferable[ $aOptionData[ 'key' ] ] = $this->getOpt( $aOptionData[ 'key' ] );
			}
		}
		return $aTransferable;
	}

	/**
	 * Returns an array of all the options with the values for "sensitive" options masked out.
	 * @return array
	 */
	public function getOptionsMaskSensitive() {

		$aOptions = $this->getAllOptionsValues();
		foreach ( $this->getOptionsKeys() as $sKey ) {
			if ( !isset( $aOptions[ $sKey ] ) ) {
				$aOptions[ $sKey ] = $this->getOptDefault( $sKey );
			}
		}
		foreach ( $this->getRawData_AllOptions() as $nKey => $aOptDef ) {
			if ( isset( $aOptDef[ 'sensitive' ] ) && $aOptDef[ 'sensitive' ] === true ) {
				unset( $aOptions[ $aOptDef[ 'key' ] ] );
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
			function ( $sKey ) {
				$opt = $this->getRawData_SingleOption( $sKey );
				return !empty( $opt[ 'section' ] )
					   && $opt[ 'section' ] !== 'section_non_ui';
			}
		);
	}

	/**
	 * Returns an array of all the options with the values for "sensitive" options masked out.
	 * @return array
	 */
	public function getOptionsForTracking() :array {
		$opts = [];
		if ( (bool)$this->getFeatureProperty( 'tracking_exclude' ) === false ) {

			$aOptions = $this->getAllOptionsValues();
			foreach ( $this->getOptionsKeys() as $sKey ) {
				if ( !isset( $aOptions[ $sKey ] ) ) {
					$aOptions[ $sKey ] = $this->getOptDefault( $sKey );
				}
			}
			foreach ( $this->getRawData_AllOptions() as $nKey => $aOptDef ) {
				if ( !empty( $aOptDef[ 'sensitive' ] ) || !empty( $aOptDef[ 'tracking_exclude' ] ) ) {
					unset( $aOptions[ $aOptDef[ 'key' ] ] );
				}
			}
			$opts = array_diff_key( $aOptions, array_flip( $this->getVirtualCommonOptions() ) );
		}
		return $opts;
	}

	/**
	 * @param $property
	 * @return null|mixed
	 */
	public function getFeatureProperty( $property ) {
		$raw = $this->getRawData_FullFeatureConfig();
		return ( isset( $raw[ 'properties' ] ) && isset( $raw[ 'properties' ][ $property ] ) ) ? $raw[ 'properties' ][ $property ] : null;
	}

	public function getWpCliCfg() :array {
		$cfg = $this->getRawData_FullFeatureConfig();
		return array_merge(
			[
				'enabled' => true,
				'root'    => $this->getSlug(),
			],
			empty( $cfg[ 'wpcli' ] ) ? [] : $cfg[ 'wpcli' ]
		);
	}

	/**
	 * @param string
	 * @return mixed|null
	 */
	public function getDef( string $key ) {
		$cfg = $this->getRawData_FullFeatureConfig();
		return ( isset( $cfg[ 'definitions' ] ) && isset( $cfg[ 'definitions' ][ $key ] ) ) ? $cfg[ 'definitions' ][ $key ] : null;
	}

	/**
	 * @param string $req
	 * @return null|mixed
	 */
	public function getFeatureRequirement( string $req ) {
		$aReqs = $this->getRawData_Requirements();
		return ( is_array( $aReqs ) && isset( $aReqs[ $req ] ) ) ? $aReqs[ $req ] : null;
	}

	/**
	 * @return array
	 */
	public function getAdminNotices() {
		$cfg = $this->getRawData_FullFeatureConfig();
		return ( isset( $cfg[ 'admin_notices' ] ) && is_array( $cfg[ 'admin_notices' ] ) ) ? $cfg[ 'admin_notices' ] : [];
	}

	/**
	 * @return string
	 */
	public function getFeatureTagline() {
		return $this->getFeatureProperty( 'tagline' );
	}

	/**
	 * @return bool
	 */
	public function getIfLoadOptionsFromStorage() {
		return $this->bLoadFromSaved;
	}

	public function isValidOptionKey( string $key ) :bool {
		return in_array( $key, $this->getOptionsKeys() );
	}

	/**
	 * @return array[]
	 */
	public function getHiddenOptions() :array {

		$aOptionsData = [];

		foreach ( $this->getRawData_OptionsSections() as $nPosition => $aRawSection ) {

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
	 * @param string $section
	 * @return array|null
	 */
	public function getSection( string $section ) {
		$sections = $this->getSections();
		return $sections[ $section ] ?? null;
	}

	/**
	 * @param bool $bIncludeHidden
	 * @return array[]
	 */
	public function getSections( $bIncludeHidden = false ) {
		$aSections = [];
		foreach ( $this->getRawData_OptionsSections() as $aRawSection ) {
			if ( $bIncludeHidden || !isset( $aRawSection[ 'hidden' ] ) || !$aRawSection[ 'hidden' ] ) {
				$aSections[ $aRawSection[ 'slug' ] ] = $aRawSection;
			}
		}
		return $aSections;
	}

	public function getPrimarySection() :array {
		$section = [];
		foreach ( $this->getSections() as $aS ) {
			if ( isset( $aS[ 'primary' ] ) && $aS[ 'primary' ] ) {
				$section = $aS;
				break;
			}
		}
		return $section;
	}

	/**
	 * @param string $slug
	 * @return array
	 */
	public function getSection_Requirements( $slug ) {
		$aSection = $this->getSection( $slug );
		$aReqs = ( is_array( $aSection ) && isset( $aSection[ 'reqs' ] ) ) ? $aSection[ 'reqs' ] : [];
		return array_merge(
			[
				'php_min' => '5.2.4',
				'wp_min'  => '3.5.0',
			],
			$aReqs
		);
	}

	/**
	 * @param string $sSlug
	 * @return array|null
	 */
	public function getSectionHelpVideo( $sSlug ) {
		$aSection = $this->getSection( $sSlug );
		return ( is_array( $aSection ) && isset( $aSection[ 'help_video' ] ) ) ? $aSection[ 'help_video' ] : null;
	}

	/**
	 * @param string $slug
	 * @return bool
	 */
	public function isSectionReqsMet( $slug ) :bool {
		$reqs = $this->getSection_Requirements( $slug );
		return Services::Data()->getPhpVersionIsAtLeast( $reqs[ 'php_min' ] )
			   && Services::WpGeneral()->getWordpressIsAtLeastVersion( $reqs[ 'wp_min' ] );
	}

	/**
	 * @param string $optKey
	 * @return bool
	 */
	public function isOptReqsMet( $optKey ) :bool {
		return $this->isSectionReqsMet( $this->getOptProperty( $optKey, 'section' ) );
	}

	/**
	 * @return string[]
	 */
	public function getVisibleOptionsKeys() :array {
		$aKeys = [];

		foreach ( $this->getRawData_AllOptions() as $aOptionDef ) {
			if ( isset( $aOptionDef[ 'hidden' ] ) && $aOptionDef[ 'hidden' ] ) {
				continue;
			}
			$aSection = $this->getSection( $aOptionDef[ 'section' ] );
			if ( empty( $aSection ) || ( isset( $aSection[ 'hidden' ] ) && $aSection[ 'hidden' ] ) ) {
				continue;
			}

			$aKeys[] = $aOptionDef[ 'key' ];
		}

		return $aKeys;
	}

	public function getOptionsForPluginUse() :array {

		$aOptionsData = [];

		foreach ( $this->getRawData_OptionsSections() as $aRawSection ) {

			if ( isset( $aRawSection[ 'hidden' ] ) && $aRawSection[ 'hidden' ] ) {
				continue;
			}

			$aRawSection = array_merge(
				[
					'primary'       => false,
					'options'       => $this->getOptionsForSection( $aRawSection[ 'slug' ] ),
					'help_video_id' => ''
				],
				$aRawSection
			);

			if ( !empty( $aRawSection[ 'options' ] ) ) {
				$aOptionsData[] = $aRawSection;
			}
		}

		return $aOptionsData;
	}

	/**
	 * @param string $slug
	 * @return array[]
	 */
	protected function getOptionsForSection( $slug ) :array {

		$aAllOptions = [];
		foreach ( $this->getRawData_AllOptions() as $aOptionDef ) {

			if ( ( $aOptionDef[ 'section' ] != $slug ) || ( isset( $aOptionDef[ 'hidden' ] ) && $aOptionDef[ 'hidden' ] ) ) {
				continue;
			}

			if ( isset( $aOptionDef[ 'hidden' ] ) && $aOptionDef[ 'hidden' ] ) {
				continue;
			}

			$aOptionDef = array_merge(
				[
					'link_info'     => '',
					'link_blog'     => '',
					'help_video_id' => '',
					'value_options' => []
				],
				$aOptionDef
			);
			$aOptionDef[ 'value' ] = $this->getOpt( $aOptionDef[ 'key' ] );

			if ( in_array( $aOptionDef[ 'type' ], [ 'select', 'multiple_select' ] ) ) {
				$aNewValueOptions = [];
				foreach ( $aOptionDef[ 'value_options' ] as $aValueOptions ) {
					$aNewValueOptions[ $aValueOptions[ 'value_key' ] ] = __( $aValueOptions[ 'text' ], 'wp-simple-firewall' );
				}
				$aOptionDef[ 'value_options' ] = $aNewValueOptions;
			}

			$aAllOptions[] = $aOptionDef;
		}
		return $aAllOptions;
	}

	public function getAdditionalMenuItems() :array {
		return $this->getRawData_FullFeatureConfig()[ 'menu_items' ] ?? [];
	}

	public function getNeedSave() :bool {
		return (bool)$this->bNeedSave;
	}

	/**
	 * @param string $key
	 * @return mixed|null
	 */
	public function getOldValue( string $key ) {
		return $this->isOptChanged( $key ) ? $this->aOld[ $key ] : null;
	}

	/**
	 * @param string $key
	 * @param mixed  $mDefault
	 * @return mixed
	 */
	public function getOpt( string $key, $mDefault = false ) {
		$aOptionsValues = $this->getAllOptionsValues();
		if ( !isset( $aOptionsValues[ $key ] ) && $this->isValidOptionKey( $key ) ) {
			$this->setOpt( $key, $this->getOptDefault( $key, $mDefault ) );
		}
		return $this->aOptionsValues[ $key ] ?? $mDefault;
	}

	/**
	 * @param string $key
	 * @param mixed  $mDefault
	 * @return mixed|null
	 */
	public function getOptDefault( string $key, $mDefault = null ) {
		foreach ( $this->getRawData_AllOptions() as $aOption ) {
			if ( $aOption[ 'key' ] == $key ) {
				if ( isset( $aOption[ 'default' ] ) ) {
					$mDefault = $aOption[ 'default' ];
					break;
				}
				if ( isset( $aOption[ 'value' ] ) ) {
					$mDefault = $aOption[ 'value' ];
					break;
				}
			}
		}
		return $mDefault;
	}

	public function getOptDefinition( string $key ) :array {
		$def = [];
		foreach ( $this->getRawData_AllOptions() as $option ) {
			if ( $option[ 'key' ] == $key ) {
				$def = $option;
				break;
			}
		}
		return $def;
	}

	/**
	 * @param string $key
	 * @param mixed  $mValueToTest
	 * @param bool   $strict
	 * @return bool
	 */
	public function isOpt( string $key, $mValueToTest, $strict = false ) :bool {
		$mOptionValue = $this->getOpt( $key );
		return $strict ? $mOptionValue === $mValueToTest : $mOptionValue == $mValueToTest;
	}

	/**
	 * @param string $key
	 * @return string|null
	 */
	public function getOptionType( $key ) {
		$def = $this->getRawData_SingleOption( $key );
		if ( !empty( $def ) && isset( $def[ 'type' ] ) ) {
			return $def[ 'type' ];
		}
		return null;
	}

	public function getOptionsKeys() :array {
		if ( !isset( $this->aOptionsKeys ) ) {
			$this->aOptionsKeys = [];
			foreach ( $this->getRawData_AllOptions() as $aOption ) {
				$this->aOptionsKeys[] = $aOption[ 'key' ];
			}
			$this->aOptionsKeys = array_merge(
				$this->aOptionsKeys,
				$this->getCommonStandardOptions(),
				$this->getVirtualCommonOptions()
			);
		}
		return $this->aOptionsKeys;
	}

	/**
	 * @return string
	 */
	public function getPathToConfig() {
		return $this->sPathToConfig;
	}

	/**
	 * @return string
	 */
	protected function getConfigModTime() {
		return Services::WpFs()->getModifiedTime( $this->getPathToConfig() );
	}

	/**
	 * @return string
	 */
	public function getOptionsStorageKey() {
		return $this->sOptionsStorageKey;
	}

	/**
	 * @param string $key
	 * @param string $prop
	 * @return mixed|null
	 */
	public function getOptProperty( string $key, string $prop ) {
		$opt = $this->getRawData_SingleOption( $key );
		return $opt[ $prop ] ?? null;
	}

	public function getStoredOptions() :array {
		try {
			return $this->loadOptionsValuesFromStorage();
		}
		catch ( \Exception $e ) {
			return [];
		}
	}

	public function getRawData_FullFeatureConfig() :array {
		if ( empty( $this->aRawOptionsConfigData ) ) {
			$this->aRawOptionsConfigData = $this->readConfiguration();
		}
		return $this->aRawOptionsConfigData;
	}

	protected function getRawData_AllOptions() :array {
		$raw = $this->getRawData_FullFeatureConfig();
		return $raw[ 'options' ] ?? [];
	}

	protected function getRawData_OptionsSections() :array {
		$raw = $this->getRawData_FullFeatureConfig();
		return $raw[ 'sections' ] ?? [];
	}

	protected function getRawData_Requirements() :array {
		$raw = $this->getRawData_FullFeatureConfig();
		return $raw[ 'requirements' ] ?? [];
	}

	public function getRawData_SingleOption( string $key ) :array {
		foreach ( $this->getRawData_AllOptions() as $opt ) {
			if ( isset( $opt[ 'key' ] ) && ( $key == $opt[ 'key' ] ) ) {
				return $opt;
			}
		}
		return [];
	}

	public function getRebuildFromFile() :bool {
		return (bool)$this->bRebuildFromFile;
	}

	/**
	 * @param string $key
	 * @return string
	 */
	public function getSelectOptionValueText( string $key ) {
		$sText = '';
		foreach ( $this->getOptDefinition( $key )[ 'value_options' ] as $opt ) {
			if ( $opt[ 'value_key' ] == $this->getOpt( $key ) ) {
				$sText = $opt[ 'text' ];
				break;
			}
		}
		return $sText;
	}

	public function isAccessRestricted() :bool {
		$state = $this->getFeatureProperty( 'access_restricted' );
		return is_null( $state ) ? true : (bool)$state;
	}

	public function isModulePremium() :bool {
		return (bool)$this->getFeatureProperty( 'premium' );
	}

	public function isModuleRunIfWhitelisted() :bool {
		$state = $this->getFeatureProperty( 'run_if_whitelisted' );
		return is_null( $state ) ? true : (bool)$state;
	}

	public function isModuleRunUnderWpCli() :bool {
		$state = $this->getFeatureProperty( 'run_if_wpcli' );
		return is_null( $state ) ? true : (bool)$state;
	}

	public function isModuleRunIfVerifiedBot() :bool {
		return (bool)$this->getFeatureProperty( 'run_if_verified_bot' );
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
		return !empty( $this->getRawData_SingleOption( $key ) );
	}

	public function resetOptToDefault( string $key ) :self {
		return $this->setOpt( $key, $this->getOptDefault( $key ) );
	}

	/**
	 * Will traverse each premium option and set it to the default.
	 */
	public function resetPremiumOptsToDefault() {
		foreach ( $this->getRawData_AllOptions() as $aOption ) {
			if ( isset( $aOption[ 'premium' ] ) && $aOption[ 'premium' ] ) {
				$this->resetOptToDefault( $aOption[ 'key' ] );
			}
		}
	}

	public function setOptionsStorageKey( string $key ) :self {
		$this->sOptionsStorageKey = $key;
		return $this;
	}

	public function setIfLoadOptionsFromStorage( bool $bLoadFromSaved ) :self {
		$this->bLoadFromSaved = $bLoadFromSaved;
		return $this;
	}

	public function setNeedSave( bool $need ) {
		$this->bNeedSave = $need;
	}

	/**
	 * @param bool $bRebuild
	 * @return $this
	 */
	public function setRebuildFromFile( $bRebuild ) {
		$this->bRebuildFromFile = $bRebuild;
		return $this;
	}

	public function setMultipleOptions( array $options ) {
		foreach ( $options as $key => $value ) {
			$this->setOpt( $key, $value );
		}
	}

	/**
	 * @param string $key
	 * @param mixed  $newValue
	 * @return $this
	 */
	public function setOpt( $key, $newValue ) :self {

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
				$aOption = $this->getRawData_SingleOption( $key );
				if ( !empty( $aOption[ 'type' ] ) ) {
					if ( $aOption[ 'type' ] == 'boolean' && !is_bool( $newValue ) ) {
						return $this->resetOptToDefault( $key );
					}
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
	 * @param string $sOpt
	 * @param int    $nAt
	 * @return $this
	 */
	public function setOptAt( $sOpt, $nAt = null ) {
		$nAt = is_null( $nAt ) ? Services::Request()->ts() : max( 0, (int)$nAt );
		return $this->setOpt( $sOpt, $nAt );
	}

	/**
	 * Use this to directly set the option value without the risk of any recursion.
	 * @param string $sOptKey
	 * @param mixed  $mValue
	 * @return $this
	 */
	public function setOptValue( $sOptKey, $mValue ) {
		$aValues = $this->getAllOptionsValues();
		$aValues[ $sOptKey ] = $mValue;
		$this->aOptionsValues = $aValues;
		return $this;
	}

	/**
	 * @param string $key
	 * @param mixed  $mPotentialValue
	 * @return bool
	 */
	private function verifyCanSet( string $key, $mPotentialValue ) :bool {
		$valid = true;

		switch ( $this->getOptionType( $key ) ) {

			case 'integer':
				$nMin = $this->getOptProperty( $key, 'min' );
				if ( !is_null( $nMin ) ) {
					$valid = $mPotentialValue >= $nMin;
				}
				if ( $valid ) {
					$nMax = $this->getOptProperty( $key, 'max' );
					if ( !is_null( $nMax ) ) {
						$valid = $mPotentialValue <= $nMax;
					}
				}
				break;

			case 'select':
				$aPossible = array_map(
					function ( $aPoss ) {
						return $aPoss[ 'value_key' ];
					},
					$this->getOptProperty( $key, 'value_options' )
				);
				$valid = in_array( $mPotentialValue, $aPossible );
				break;

			case 'email':
				$valid = empty( $mPotentialValue ) || Services::Data()->validEmail( $mPotentialValue );
				break;
		}
		return $valid;
	}

	/**
	 * @param string $sOptionKey
	 * @param mixed  $mValue
	 * @return $this
	 */
	private function setOldOptValue( $sOptionKey, $mValue ) {
		if ( !is_array( $this->aOld ) ) {
			$this->aOld = [];
		}
		if ( !isset( $this->aOld[ $sOptionKey ] ) ) {
			$this->aOld[ $sOptionKey ] = $mValue;
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
			'help_video_options',
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

	/**
	 * @param bool $reload
	 * @return array
	 * @throws \Exception
	 */
	private function loadOptionsValuesFromStorage( bool $reload = false ) :array {

		if ( $reload || empty( $this->aOptionsValues ) ) {

			if ( $this->getIfLoadOptionsFromStorage() ) {

				$key = $this->getOptionsStorageKey();
				if ( empty( $key ) ) {
					throw new \Exception( 'Options Storage Key Is Empty' );
				}
				$this->aOptionsValues = Services::WpGeneral()->getOption( $key, [] );
			}
		}
		if ( !is_array( $this->aOptionsValues ) ) {
			$this->aOptionsValues = [];
			$this->setNeedSave( true );
		}
		return $this->aOptionsValues;
	}

	private function readConfiguration() :array {
		$cfg = Transient::Get( $this->getConfigStorageKey() );

		$bRebuild = $this->getRebuildFromFile() || empty( $cfg ) || !is_array( $cfg );
		if ( !$bRebuild ) {
			if ( !isset( $cfg[ 'meta_modts' ] ) ) {
				$cfg[ 'meta_modts' ] = 0;
			}
			$bRebuild = $this->getConfigModTime() > $cfg[ 'meta_modts' ];
		}

		if ( $bRebuild ) {
			try {
				$cfg = $this->readConfigurationJson();
			}
			catch ( \Exception $e ) {
				if ( Services::WpGeneral()->isDebug() ) {
					trigger_error( $e->getMessage() );
				}
				$cfg = [];
			}
			$cfg[ 'meta_modts' ] = $this->getConfigModTime();
			Transient::Set( $this->getConfigStorageKey(), $cfg );
		}

		$this->setRebuildFromFile( $bRebuild );
		return $cfg;
	}

	/**
	 * @return array
	 * @throws \Exception
	 */
	private function readConfigurationJson() :array {
		$cfg = json_decode( $this->readConfigurationFileContents(), true );
		if ( empty( $cfg ) || !is_array( $cfg ) ) {
			throw new \Exception( sprintf( 'Reading JSON configuration from file "%s" failed.', $this->getSlug() ) );
		}
		return $cfg;
	}

	/**
	 * @return string
	 * @throws \Exception
	 */
	private function readConfigurationFileContents() {
		if ( !$this->getConfigFileExists() ) {
			throw new \Exception( sprintf( 'Configuration file "%s" does not exist.', $this->getPathToConfig() ) );
		}
		return Services::Data()->readFileWithInclude( $this->getPathToConfig() );
	}

	public function getConfigStorageKey() :string {
		return 'shield_mod_config_'.md5(
				str_replace( wp_normalize_path( ABSPATH ), '', wp_normalize_path( $this->getPathToConfig() ) )
			);
	}

	private function getConfigFileExists() :bool {
		$sPath = $this->getPathToConfig();
		return !empty( $sPath ) && Services::WpFs()->isFile( $sPath );
	}

	/**
	 * @param string $sPathToConfig
	 * @return $this
	 */
	public function setPathToConfig( $sPathToConfig ) {
		$this->sPathToConfig = $sPathToConfig;
		return $this;
	}

	/**
	 * @param $aValues
	 * @return $this
	 */
	public function setOptionsValues( array $aValues = [] ) {
		$this->aOptionsValues = $aValues;
		$this->setNeedSave( true );
		return $this;
	}
}