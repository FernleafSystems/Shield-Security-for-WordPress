<?php

class ICWP_WPSF_OptionsVO extends ICWP_WPSF_Foundation {

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
	 * @var boolean
	 */
	protected $bNeedSave;

	/**
	 * @var boolean
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
	protected $sOptionsEncoding;

	/**
	 * @var string
	 */
	protected $sPathToConfig;

	/**
	 */
	public function __construct() {
	}

	/**
	 * @return bool
	 */
	public function cleanTransientStorage() {
		return $this->loadWp()->deleteTransient( $this->getSpecTransientStorageKey() );
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
			$this->loadWp()->deleteOption( $this->getOptionsStorageKey() );
		}
		return $this->loadWp()->updateOption( $this->getOptionsStorageKey(), $this->getAllOptionsValues() );
	}

	/**
	 * @return bool
	 */
	public function doOptionsDelete() {
		$oWp = $this->loadWp();
		$oWp->deleteTransient( $this->getSpecTransientStorageKey() );
		return $oWp->deleteOption( $this->getOptionsStorageKey() );
	}

	/**
	 * @return array
	 */
	public function getAllOptionsValues() {
		return $this->getStoredOptions();
	}

	/**
	 * @return string
	 */
	public function getSlug() {
		return $this->getFeatureProperty( 'slug' );
	}

	/**
	 * Returns an array of all the transferable options and their values
	 * @return array
	 */
	public function getTransferableOptions() {
		$aTransferable = array();

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
		foreach ( $this->getRawData_AllOptions() as $nKey => $aOptionData ) {
			if ( isset( $aOptionData[ 'sensitive' ] ) && $aOptionData[ 'sensitive' ] === true ) {
				unset( $aOptions[ $aOptionData[ 'key' ] ] );
			}
		}
		return $aOptions;
	}

	/**
	 * @param $sProperty
	 * @return null|mixed
	 */
	public function getFeatureProperty( $sProperty ) {
		$aRawConfig = $this->getRawData_FullFeatureConfig();
		return ( isset( $aRawConfig[ 'properties' ] ) && isset( $aRawConfig[ 'properties' ][ $sProperty ] ) ) ? $aRawConfig[ 'properties' ][ $sProperty ] : null;
	}

	/**
	 * @param string
	 * @return null|array
	 */
	public function getFeatureDefinition( $sDefinition ) {
		$aRawConfig = $this->getRawData_FullFeatureConfig();
		return ( isset( $aRawConfig[ 'definitions' ] ) && isset( $aRawConfig[ 'definitions' ][ $sDefinition ] ) ) ? $aRawConfig[ 'definitions' ][ $sDefinition ] : null;
	}

	/**
	 * @param string $sReq
	 * @return null|mixed
	 */
	public function getFeatureRequirement( $sReq ) {
		$aReqs = $this->getRawData_Requirements();
		return ( is_array( $aReqs ) && isset( $aReqs[ $sReq ] ) ) ? $aReqs[ $sReq ] : null;
	}

	/**
	 * @return array
	 */
	public function getAdminNotices() {
		$aRawConfig = $this->getRawData_FullFeatureConfig();
		return ( isset( $aRawConfig[ 'admin_notices' ] ) && is_array( $aRawConfig[ 'admin_notices' ] ) ) ? $aRawConfig[ 'admin_notices' ] : array();
	}

	/**
	 * @return string
	 */
	public function getFeatureTagline() {
		return $this->getFeatureProperty( 'tagline' );
	}

	/**
	 * @return boolean
	 */
	public function getIfLoadOptionsFromStorage() {
		return $this->bLoadFromSaved;
	}

	/**
	 * Determines whether the given option key is a valid option
	 * @param string
	 * @return boolean
	 */
	public function isValidOptionKey( $sOptionKey ) {
		return in_array( $sOptionKey, $this->getOptionsKeys() );
	}

	/**
	 * @return array[]
	 */
	public function getHiddenOptions() {

		$aOptionsData = array();

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
	 * @param string $sSlug
	 * @return array|null
	 */
	public function getSection( $sSlug ) {
		$aSections = $this->getSections();
		return isset( $aSections[ $sSlug ] ) ? $aSections[ $sSlug ] : null;
	}

	/**
	 * @param bool $bIncludeHidden
	 * @return array[]
	 */
	public function getSections( $bIncludeHidden = false ) {
		$aSections = array();
		foreach ( $this->getRawData_OptionsSections() as $aRawSection ) {
			if ( $bIncludeHidden || !isset( $aRawSection[ 'hidden' ] ) || !$aRawSection[ 'hidden' ] ) {
				$aSections[ $aRawSection[ 'slug' ] ] = $aRawSection;
			}
		}
		return $aSections;
	}

	/**
	 * @return array
	 */
	public function getPrimarySection() {
		$aSec = array();
		foreach ( $this->getSections() as $aS ) {
			if ( isset( $aS[ 'primary' ] ) && $aS[ 'primary' ] ) {
				$aSec = $aS;
				break;
			}
		}
		return $aSec;
	}

	/**
	 * @param string $sSlug
	 * @return array
	 */
	public function getSection_Requirements( $sSlug ) {
		$aSection = $this->getSection( $sSlug );
		$aReqs = ( is_array( $aSection ) && isset( $aSection[ 'reqs' ] ) ) ? $aSection[ 'reqs' ] : array();
		return array_merge(
			array(
				'php_min' => '5.2.4',
				'wp_min'  => '3.5.0',
			),
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
	 * @param string $sSectionSlug
	 * @return bool
	 */
	public function isSectionReqsMet( $sSectionSlug ) {
		$aReqs = $this->getSection_Requirements( $sSectionSlug );
		$bMet = $this->loadDP()->getPhpVersionIsAtLeast( $aReqs[ 'php_min' ] )
				&& $this->loadWp()->getWordpressIsAtLeastVersion( $aReqs[ 'wp_min' ] );
		return $bMet;
	}

	/**
	 * @param string $sOptKey
	 * @return bool
	 */
	public function isOptReqsMet( $sOptKey ) {
		return $this->isSectionReqsMet( $this->getOptProperty( $sOptKey, 'section' ) );
	}

	/**
	 * @return array
	 */
	public function getOptionsForPluginUse() {

		$aOptionsData = array();

		foreach ( $this->getRawData_OptionsSections() as $aRawSection ) {

			if ( isset( $aRawSection[ 'hidden' ] ) && $aRawSection[ 'hidden' ] ) {
				continue;
			}

			$aRawSection = array_merge(
				array(
					'primary'       => false,
					'options'       => $this->getOptionsForSection( $aRawSection[ 'slug' ] ),
					'help_video_id' => ''
				),
				$aRawSection
			);

			if ( !empty( $aRawSection[ 'options' ] ) ) {
				$aOptionsData[] = $aRawSection;
			}
		}

		return $aOptionsData;
	}

	/**
	 * @param string $sSectionSlug
	 * @return array[]
	 */
	protected function getOptionsForSection( $sSectionSlug ) {

		$aAllOptions = array();
		foreach ( $this->getRawData_AllOptions() as $aOptionDef ) {

			if ( ( $aOptionDef[ 'section' ] != $sSectionSlug ) || ( isset( $aOptionDef[ 'hidden' ] ) && $aOptionDef[ 'hidden' ] ) ) {
				continue;
			}

			if ( isset( $aOptionDef[ 'hidden' ] ) && $aOptionDef[ 'hidden' ] ) {
				continue;
			}

			$aOptionDef = array_merge(
				array(
					'link_info'     => '',
					'link_blog'     => '',
					'help_video_id' => '',
					'value_options' => array()
				),
				$aOptionDef
			);
			$aOptionDef[ 'value' ] = $this->getOpt( $aOptionDef[ 'key' ] );

			if ( in_array( $aOptionDef[ 'type' ], array( 'select', 'multiple_select' ) ) ) {
				$aNewValueOptions = array();
				foreach ( $aOptionDef[ 'value_options' ] as $aValueOptions ) {
					$aNewValueOptions[ $aValueOptions[ 'value_key' ] ] = $aValueOptions[ 'text' ];
				}
				$aOptionDef[ 'value_options' ] = $aNewValueOptions;
			}

			$aAllOptions[] = $aOptionDef;
		}
		return $aAllOptions;
	}

	/**
	 * @return array
	 */
	public function getAdditionalMenuItems() {
		return $this->getRawData_MenuItems();
	}

	/**
	 * @return string
	 */
	public function getNeedSave() {
		return $this->bNeedSave;
	}

	/**
	 * @param string $sKey
	 * @return mixed|null
	 */
	public function getOldValue( $sKey ) {
		return $this->isOptChanged( $sKey ) ? $this->aOld[ $sKey ] : null;
	}

	/**
	 * @param string $sOptionKey
	 * @param mixed  $mDefault
	 * @return mixed
	 */
	public function getOpt( $sOptionKey, $mDefault = false ) {
		$aOptionsValues = $this->getAllOptionsValues();
		if ( !isset( $aOptionsValues[ $sOptionKey ] ) && $this->isValidOptionKey( $sOptionKey ) ) {
			$this->setOpt( $sOptionKey, $this->getOptDefault( $sOptionKey, $mDefault ) );
		}
		return isset( $this->aOptionsValues[ $sOptionKey ] ) ? $this->aOptionsValues[ $sOptionKey ] : $mDefault;
	}

	/**
	 * @param string $sOptionKey
	 * @param mixed  $mDefault
	 * @return mixed|null
	 */
	public function getOptDefault( $sOptionKey, $mDefault = null ) {
		foreach ( $this->getRawData_AllOptions() as $aOption ) {
			if ( $aOption[ 'key' ] == $sOptionKey ) {
				if ( isset( $aOption[ 'value' ] ) ) {
					return $aOption[ 'value' ];
				}
				else if ( isset( $aOption[ 'default' ] ) ) {
					return $aOption[ 'default' ];
				}
			}
		}
		return $mDefault;
	}

	/**
	 * @param string $sOptionKey
	 * @return array
	 */
	public function getOptDefinition( $sOptionKey ) {
		$aDef = array();
		foreach ( $this->getRawData_AllOptions() as $aOption ) {
			if ( $aOption[ 'key' ] == $sOptionKey ) {
				$aDef = $aOption;
				break;
			}
		}
		return $aDef;
	}

	/**
	 * @param         $sKey
	 * @param mixed   $mValueToTest
	 * @param boolean $bStrict
	 * @return bool
	 */
	public function getOptIs( $sKey, $mValueToTest, $bStrict = false ) {
		$mOptionValue = $this->getOpt( $sKey );
		return $bStrict ? $mOptionValue === $mValueToTest : $mOptionValue == $mValueToTest;
	}

	/**
	 * @param string $sKey
	 * @return string|null
	 */
	public function getOptionType( $sKey ) {
		$aDef = $this->getRawData_SingleOption( $sKey );
		if ( !empty( $aDef ) && isset( $aDef[ 'type' ] ) ) {
			return $aDef[ 'type' ];
		}
		return null;
	}

	/**
	 * @return string
	 */
	public function getOptionsEncoding() {
		return empty( $this->sOptionsEncoding ) ? 'json' : $this->sOptionsEncoding;
	}

	/**
	 * @return array
	 */
	public function getOptionsKeys() {
		if ( !isset( $this->aOptionsKeys ) ) {
			$this->aOptionsKeys = array();
			foreach ( $this->getRawData_AllOptions() as $aOption ) {
				$this->aOptionsKeys[] = $aOption[ 'key' ];
			}
			$this->aOptionsKeys = array_merge( $this->aOptionsKeys, $this->getCommonStandardOptions() );
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
		return $this->loadFS()->getModifiedTime( $this->getPathToConfig() );
	}

	/**
	 * @return string
	 */
	public function getOptionsStorageKey() {
		return $this->sOptionsStorageKey;
	}

	/**
	 * @param string $sOptKey
	 * @param string $sProperty
	 * @return mixed|null
	 */
	public function getOptProperty( $sOptKey, $sProperty ) {
		$aOpt = $this->getRawData_SingleOption( $sOptKey );
		return ( is_array( $aOpt ) && isset( $aOpt[ $sProperty ] ) ) ? $aOpt[ $sProperty ] : null;
	}

	/**
	 * @return array
	 */
	public function getStoredOptions() {
		try {
			return $this->loadOptionsValuesFromStorage();
		}
		catch ( Exception $oE ) {
			return array();
		}
	}

	/**
	 * @return array
	 */
	public function getRawData_FullFeatureConfig() {
		if ( empty( $this->aRawOptionsConfigData ) ) {
			$this->aRawOptionsConfigData = $this->readConfiguration();
		}
		return $this->aRawOptionsConfigData;
	}

	/**
	 * Return the section of the Raw config that is the "options" key only.
	 * @return array
	 */
	protected function getRawData_AllOptions() {
		$aRaw = $this->getRawData_FullFeatureConfig();
		return ( isset( $aRaw[ 'options' ] ) && is_array( $aRaw[ 'options' ] ) ) ? $aRaw[ 'options' ] : array();
	}

	/**
	 * Return the section of the Raw config that is the "options" key only.
	 * @return array
	 */
	protected function getRawData_OptionsSections() {
		$aAllRawOptions = $this->getRawData_FullFeatureConfig();
		return isset( $aAllRawOptions[ 'sections' ] ) ? $aAllRawOptions[ 'sections' ] : array();
	}

	/**
	 * Return the section of the Raw config that is the "options" key only.
	 * @return array
	 */
	protected function getRawData_Requirements() {
		$aAllRawOptions = $this->getRawData_FullFeatureConfig();
		return isset( $aAllRawOptions[ 'requirements' ] ) ? $aAllRawOptions[ 'requirements' ] : array();
	}

	/**
	 * Return the section of the Raw config that is the "options" key only.
	 * @return array
	 */
	protected function getRawData_MenuItems() {
		$aAllRawOptions = $this->getRawData_FullFeatureConfig();
		return isset( $aAllRawOptions[ 'menu_items' ] ) ? $aAllRawOptions[ 'menu_items' ] : array();
	}

	/**
	 * Return the section of the Raw config that is the "options" key only.
	 * @param string $sOptionKey
	 * @return array
	 */
	public function getRawData_SingleOption( $sOptionKey ) {
		foreach ( $this->getRawData_AllOptions() as $aOption ) {
			if ( isset( $aOption[ 'key' ] ) && ( $sOptionKey == $aOption[ 'key' ] ) ) {
				return $aOption;
			}
		}
		return null;
	}

	/**
	 * @return boolean
	 */
	public function getRebuildFromFile() {
		return $this->bRebuildFromFile;
	}

	/**
	 * @return bool
	 */
	public function isAccessRestricted() {
		$bAccessRestricted = $this->getFeatureProperty( 'access_restricted' );
		return is_null( $bAccessRestricted ) ? true : (bool)$bAccessRestricted;
	}

	/**
	 * @return bool
	 */
	public function isModulePremium() {
		return (bool)$this->getFeatureProperty( 'premium' );
	}

	/**
	 * @return bool
	 */
	public function isModuleRunIfWhitelisted() {
		return (bool)$this->getFeatureProperty( 'run_if_whitelisted' );
	}

	/**
	 * @return bool
	 */
	public function isModuleRunIfVerifiedBot() {
		return (bool)$this->getFeatureProperty( 'run_if_verified_bot' );
	}

	/**
	 * @param string $sKey
	 * @return bool
	 */
	public function isOptChanged( $sKey ) {
		return is_array( $this->aOld ) && isset( $this->aOld[ $sKey ] );
	}

	/**
	 * @param string $sOptionKey
	 * @return bool true if premium is set and true, false otherwise.
	 */
	public function isOptPremium( $sOptionKey ) {
		return (bool)$this->getOptProperty( $sOptionKey, 'premium' );
	}

	/**
	 * @param string $sOptionKey
	 * @return boolean
	 */
	public function resetOptToDefault( $sOptionKey ) {
		return $this->setOpt( $sOptionKey, $this->getOptDefault( $sOptionKey ) );
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

	/**
	 * @param string $sKey
	 * @return $this
	 */
	public function setOptionsStorageKey( $sKey ) {
		$this->sOptionsStorageKey = $sKey;
		return $this;
	}

	/**
	 * @param boolean $bLoadFromSaved
	 * @return $this
	 */
	public function setIfLoadOptionsFromStorage( $bLoadFromSaved ) {
		$this->bLoadFromSaved = $bLoadFromSaved;
		return $this;
	}

	/**
	 * @param boolean $bNeed
	 */
	public function setNeedSave( $bNeed ) {
		$this->bNeedSave = $bNeed;
	}

	/**
	 * @param string $sOptionsEncoding
	 * @return $this
	 */
	public function setOptionsEncoding( $sOptionsEncoding ) {
		$this->sOptionsEncoding = $sOptionsEncoding;
		return $this;
	}

	/**
	 * @param boolean $bRebuild
	 * @return $this
	 */
	public function setRebuildFromFile( $bRebuild ) {
		$this->bRebuildFromFile = $bRebuild;
		return $this;
	}

	/**
	 * @param array $aOptions
	 */
	public function setMultipleOptions( $aOptions ) {
		if ( is_array( $aOptions ) ) {
			foreach ( $aOptions as $sKey => $mValue ) {
				$this->setOpt( $sKey, $mValue );
			}
		}
	}

	/**
	 * @param string $sOptKey
	 * @param mixed  $mNewValue
	 * @return mixed
	 */
	public function setOpt( $sOptKey, $mNewValue ) {

		// We can't use getOpt() to find the current value since we'll create an infinite loop
		$aOptionsValues = $this->getAllOptionsValues();
		$mCurrent = isset( $aOptionsValues[ $sOptKey ] ) ? $aOptionsValues[ $sOptKey ] : null;

		$mNewValue = $this->ensureOptValueState( $sOptKey, $mNewValue );

		// Here we try to ensure that values that are repeatedly changed properly reflect their changed
		// states, as they may be reverted back to their original state and we "think" it's been changed.
		$bValueIsDifferent = serialize( $mCurrent ) !== serialize( $mNewValue );
		// basically if we're actually resetting back to the original value
		$bIsResetting = $bValueIsDifferent && $this->isOptChanged( $sOptKey )
						&& ( serialize( $this->getOldValue( $sOptKey ) ) === serialize( $mNewValue ) );

		if ( $bValueIsDifferent && $this->verifyCanSet( $sOptKey, $mNewValue ) ) {
			$this->setNeedSave( true );

			//Load the config and do some pre-set verification where possible. This will slowly grow.
			$aOption = $this->getRawData_SingleOption( $sOptKey );
			if ( !empty( $aOption[ 'type' ] ) ) {
				if ( $aOption[ 'type' ] == 'boolean' && !is_bool( $mNewValue ) ) {
					return $this->resetOptToDefault( $sOptKey );
				}
			}
			$this->setOldOptValue( $sOptKey, $mCurrent );
			$this->aOptionsValues[ $sOptKey ] = $mNewValue;
		}

		if ( $bIsResetting ) {
			unset( $this->aOld[ $sOptKey ] );
		}

		return true;
	}

	/**
	 * Ensures that set options values are of the correct type
	 * @param string $sOptKey
	 * @param mixed  $mValue
	 * @return mixed
	 */
	private function ensureOptValueState( $sOptKey, $mValue ) {
		switch ( $this->getOptionType( $sOptKey ) ) {
			case 'integer':
				$mValue = (int)$mValue;
				break;

			case 'text':
			case 'email':
				$mValue = (string)$mValue;
				break;
		}
		return $mValue;
	}

	/**
	 * @param string $sOptKey
	 * @param mixed  $mPotentialValue
	 * @return bool
	 */
	private function verifyCanSet( $sOptKey, $mPotentialValue ) {
		$bValid = true;

		switch ( $this->getOptionType( $sOptKey ) ) {

			case 'integer':
				$nMin = $this->getOptProperty( $sOptKey, 'min' );
				if ( !is_null( $nMin ) ) {
					$bValid = $mPotentialValue >= $nMin;
				}
				$nMax = $this->getOptProperty( $sOptKey, 'max' );
				if ( !is_null( $nMax ) ) {
					$bValid = $mPotentialValue <= $nMax;
				}
				break;

			case 'email':
				$bValid = empty( $mPotentialValue ) || $this->loadDP()->validEmail( $mPotentialValue );
				break;
		}
		return $bValid;
	}

	/**
	 * @param string $sOptionKey
	 * @param mixed  $mValue
	 * @return $this
	 */
	private function setOldOptValue( $sOptionKey, $mValue ) {
		if ( !is_array( $this->aOld ) ) {
			$this->aOld = array();
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
		return array( 'help_video_options' );
	}

	/**
	 */
	private function cleanOptions() {
		if ( !empty( $this->aOptionsValues ) && is_array( $this->aOptionsValues ) ) {
			$this->aOptionsValues = array_intersect_key(
				$this->getAllOptionsValues(),
				array_flip( $this->getOptionsKeys() )
			);
		}
	}

	/**
	 * @param bool $bReload
	 * @return array|mixed
	 * @throws Exception
	 */
	private function loadOptionsValuesFromStorage( $bReload = false ) {

		if ( $bReload || empty( $this->aOptionsValues ) ) {

			if ( $this->getIfLoadOptionsFromStorage() ) {

				$sStorageKey = $this->getOptionsStorageKey();
				if ( empty( $sStorageKey ) ) {
					throw new Exception( 'Options Storage Key Is Empty' );
				}
				$this->aOptionsValues = $this->loadWp()->getOption( $sStorageKey, array() );
			}
		}
		if ( !is_array( $this->aOptionsValues ) ) {
			$this->aOptionsValues = array();
			$this->setNeedSave( true );
		}
		return $this->aOptionsValues;
	}

	/**
	 * @return array
	 */
	private function readConfiguration() {
		$oWp = $this->loadWp();

		$sTransientKey = $this->getSpecTransientStorageKey();
		$aConfig = $oWp->getTransient( $sTransientKey );

		$bRebuild = $this->getRebuildFromFile() || empty( $aConfig );
		if ( !$bRebuild && !empty( $aConfig ) && is_array( $aConfig ) ) {

			if ( !isset( $aConfig[ 'meta_modts' ] ) ) {
				$aConfig[ 'meta_modts' ] = 0;
			}
			$bRebuild = $this->getConfigModTime() > $aConfig[ 'meta_modts' ];
		}

		if ( $bRebuild ) {
			try {
				$aConfig = $this->readConfigurationJson();
			}
			catch ( Exception $oE ) {
				if ( WP_DEBUG ) {
					trigger_error( $oE->getMessage() );
				}
				$aConfig = array();
			}
			$aConfig[ 'meta_modts' ] = $this->getConfigModTime();
			$oWp->setTransient( $sTransientKey, $aConfig, DAY_IN_SECONDS );
		}

		$this->setRebuildFromFile( $bRebuild );
		return $aConfig;
	}

	/**
	 * @return array
	 * @throws Exception
	 */
	private function readConfigurationJson() {
		$aConfig = json_decode( $this->readConfigurationFileContents(), true );
		if ( empty( $aConfig ) ) {
			throw new Exception( sprintf( 'Reading JSON configuration from file "%s" failed.', $this->getSlug() ) );
		}
		return $aConfig;
	}

	/**
	 * @return string
	 * @throws Exception
	 */
	private function readConfigurationFileContents() {
		if ( !$this->getConfigFileExists() ) {
			throw new Exception( sprintf( 'Configuration file "%s" does not exist.', $this->getPathToConfig() ) );
		}
		return $this->loadDP()->readFileContentsUsingInclude( $this->getPathToConfig() );
	}

	/**
	 * @return string
	 */
	private function getSpecTransientStorageKey() {
		return 'icwp_'.md5( $this->getPathToConfig() );
	}

	/**
	 * @return bool
	 */
	private function getConfigFileExists() {
		$sPath = $this->getPathToConfig();
		return !empty( $sPath ) && $this->loadFS()->isFile( $sPath );
	}

	/**
	 * @param string $sPathToConfig
	 * @return $this
	 */
	public function setPathToConfig( $sPathToConfig ) {
		$this->sPathToConfig = $sPathToConfig;
		return $this;
	}
}