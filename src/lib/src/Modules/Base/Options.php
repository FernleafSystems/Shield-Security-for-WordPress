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

	public function getAllOptionsValues() :array {
		try {
			$values = self::con()->opts->getFor( $this->mod() );
			if ( $values === null ) {
				throw new \Exception( 'No shared-stored options available' );
			}
		}
		catch ( \Exception $e ) {
			try {
				$values = ( new Options\Storage() )->setMod( $this->mod() )->loadOptions();
			}
			catch ( \Exception $e ) {
				$values = [];
			}
			self::con()->opts->setFor( $this->mod(), $values );
		}
		return $values;
	}

	/**
	 * Returns an array of all the transferable options and their values
	 */
	public function getTransferableOptions() :array {
		$transferable = [];
		foreach ( $this->cfg()->options as $option ) {
			if ( $option[ 'transferable' ] ?? true ) {
				$transferable[ $option[ 'key' ] ] = $this->getOpt( $option[ 'key' ] );
			}
		}
		return $transferable;
	}

	/**
	 * Returns an array of all the options with the values for "sensitive" options masked out.
	 */
	public function getOptionsMaskSensitive() :array {

		$opts = $this->getAllOptionsValues();
		foreach ( $this->getOptionsKeys() as $key ) {
			if ( !isset( $opts[ $key ] ) ) {
				$opts[ $key ] = $this->getOptDefault( $key );
			}
		}
		foreach ( $this->cfg()->options as $optDef ) {
			if ( isset( $optDef[ 'sensitive' ] ) && $optDef[ 'sensitive' ] === true ) {
				unset( $opts[ $optDef[ 'key' ] ] );
			}
		}
		return \array_diff_key( $opts, \array_flip( $this->getVirtualCommonOptions() ) );
	}

	/**
	 * @return string[]
	 */
	public function getOptionsForWpCli() :array {
		return \array_filter(
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
		if ( !$this->mod()->cfg->properties[ 'tracking_exclude' ] ) {

			$options = $this->getAllOptionsValues();
			foreach ( $this->getOptionsKeys() as $key ) {
				if ( !isset( $options[ $key ] ) ) {
					$options[ $key ] = $this->getOptDefault( $key );
				}
			}
			foreach ( $this->cfg()->options as $optDef ) {
				if ( !empty( $optDef[ 'sensitive' ] ) || !empty( $optDef[ 'tracking_exclude' ] ) ) {
					unset( $options[ $optDef[ 'key' ] ] );
				}
			}
			$opts = \array_diff_key( $options, \array_flip( $this->getVirtualCommonOptions() ) );
		}
		return $opts;
	}

	/**
	 * @return mixed|null
	 */
	public function getFeatureProperty( string $property ) {
		return $this->cfg()->properties[ $property ] ?? null;
	}

	/**
	 * @return mixed|null
	 */
	public function getDef( string $key ) {
		return $this->cfg()->definitions[ $key ] ?? null;
	}

	public function getEvents() :array {
		return \is_array( $this->getDef( 'events' ) ) ? $this->getDef( 'events' ) : [];
	}

	public function getAdminNotices() :array {
		return $this->cfg()->admin_notices ?? [];
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
				if ( !\is_array( $value ) ) {
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
				$valid = \is_array( $value );
				break;
			case 'integer':
				$valid = \is_numeric( $value );
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
		$optionsData = [];

		foreach ( $this->cfg()->sections as $rawSection ) {

			// if hidden isn't specified we skip
			if ( !isset( $rawSection[ 'hidden' ] ) || !$rawSection[ 'hidden' ] ) {
				continue;
			}
			foreach ( $this->cfg()->options as $rawOption ) {

				if ( $rawOption[ 'section' ] != $rawSection[ 'slug' ] ) {
					continue;
				}
				$optionsData[ $rawOption[ 'key' ] ] = $this->getOpt( $rawOption[ 'key' ] );
			}
		}
		return $optionsData;
	}

	public function getSection( string $section ) :?array {
		return $this->getSections()[ $section ] ?? null;
	}

	/**
	 * @return array[]
	 */
	public function getSections( bool $includeHidden = false ) :array {
		$sections = [];
		foreach ( $this->cfg()->sections as $section ) {
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

	public function getSection_Requirements( string $slug ) :array {
		$section = $this->getSection( $slug );
		return \array_merge(
			[
				'php_min' => '7.2',
				'wp_min'  => '5.7',
			],
			( \is_array( $section ) && isset( $section[ 'reqs' ] ) ) ? $section[ 'reqs' ] : []
		);
	}

	/**
	 * @return array[]
	 */
	public function getVisibleOptions() :array {
		return \array_filter(
			$this->cfg()->options,
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
		return \array_map( function ( $optDef ) {
			return $optDef[ 'key' ];
		}, $this->getVisibleOptions() );
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

		if ( $value === null || !$this->isValidOptionValueType( $key, $value ) ) {
			$this->resetOptToDefault( $key );
		}

		$cap = $this->optCap( $key );
		if ( empty( $cap ) || self::con()->caps->hasCap( $cap ) ) {
			$value = $this->getAllOptionsValues()[ $key ] ?? $mDefault;
		}
		else {
			$value = $this->getOptDefault( $key, $mDefault );
		}

		return $this->ensureOptValueType( $key, $value );
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
		return $this->cfg()->options[ $key ] ?? [];
	}

	public function optCap( string $key ) :?string {
		$def = $this->getOptDefinition( $key );
		return $def[ 'cap' ] ?? null;
	}

	/**
	 * @param mixed $mValueToTest
	 */
	public function isOpt( string $key, $mValueToTest, bool $strict = false ) :bool {
		return $strict ? $this->getOpt( $key ) === $mValueToTest : $this->getOpt( $key ) == $mValueToTest;
	}

	public function getOptionType( string $key ) :?string {
		return $this->getOptDefinition( $key )[ 'type' ] ?? null;
	}

	public function getOptionsKeys() :array {
		if ( !isset( $this->aOptionsKeys ) ) {
			$this->aOptionsKeys = \array_merge(
				\array_keys( $this->cfg()->options ),
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
		return $this->mod()->cfg;
	}

	public function getRawData_FullFeatureConfig() :array {
		return $this->cfg()->getRawData();
	}

	public function getSelectOptionValueKeys( string $key ) :array {
		$keys = [];
		foreach ( $this->getOptDefinition( $key )[ 'value_options' ] as $opt ) {
			$keys[] = $opt[ 'value_key' ];
		}
		return $keys;
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
		return \is_array( $this->aOld ) && isset( $this->aOld[ $key ] );
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
		foreach ( $this->cfg()->options as $opt ) {
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
				->setMod( $this->mod() )
				->run( $key, $newValue );
			$this->preSetOptChecks( $key, $newValue );
			$verified = true;
		}
		catch ( \Exception $e ) {
			$verified = false;
		}

		if ( $verified ) {
			// Here we try to ensure that values that are repeatedly changed properly reflect their changed
			// states, as they may be reverted to their original state and we "think" it's been changed.
			$valueIsDifferent = \serialize( $mCurrent ) !== \serialize( $newValue );
			// basically if we're actually resetting back to the original value
			$isResetting = $valueIsDifferent && $this->isOptChanged( $key )
						   && ( \serialize( $this->getOldValue( $key ) ) === \serialize( $newValue ) );

			if ( $valueIsDifferent && $this->verifyCanSet( $key, $newValue ) ) {
				$this->setNeedSave( true );

				//Load the config and do some pre-set verification where possible. This will slowly grow.
				if ( $this->getOptionType( $key ) === 'boolean' && !\is_bool( $newValue ) ) {
					return $this->resetOptToDefault( $key );
				}
				$this->setOldOptValue( $key, $mCurrent )
					 ->setOptValue( $key, $newValue );
			}

			if ( $isResetting ) {
				unset( $this->aOld[ $key ] );
			}
		}

		return $this;
	}

	/**
	 * @param mixed $newValue
	 * @throws \Exception
	 */
	protected function preSetOptChecks( string $key, $newValue ) {
	}

	public function preSave() :void {
	}

	/**
	 * Use this to directly set the option value without the risk of any recursion.
	 * @param mixed $value
	 * @return $this
	 */
	protected function setOptValue( string $key, $value ) {
		$values = $this->getAllOptionsValues();
		$values[ $key ] = $value;
		return $this->setOptionsValues( $values );
	}

	/**
	 * @param mixed $mPotentialValue
	 */
	private function verifyCanSet( string $key, $mPotentialValue ) :bool {
		$valid = true;

		switch ( $this->getOptionType( $key ) ) {

			case 'integer':
				$min = $this->getOptProperty( $key, 'min' );
				if ( $min !== null ) {
					$valid = $mPotentialValue >= $min;
				}
				if ( $valid ) {
					$max = $this->getOptProperty( $key, 'max' );
					if ( $max !== null ) {
						$valid = $mPotentialValue <= $max;
					}
				}
				break;

			case 'array':
				$valid = \is_array( $mPotentialValue );
				break;

			case 'select':
				$valid = \in_array( $mPotentialValue, \array_map(
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
	 * @param mixed $value
	 * @return $this
	 */
	private function setOldOptValue( string $key, $value ) {
		if ( !\is_array( $this->aOld ) ) {
			$this->aOld = [];
		}
		if ( !isset( $this->aOld[ $key ] ) ) {
			$this->aOld[ $key ] = $value;
		}
		return $this;
	}

	public function unsetOpt( string $key ) {
		$values = $this->getAllOptionsValues();
		unset( $values[ $key ] );
		$this->setOptionsValues( $values );
	}

	protected function getCommonStandardOptions() :array {
		return [];
	}

	protected function getVirtualCommonOptions() :array {
		return [
			'dismissed_notices',
			'ui_track',
			'xfer_excluded',
		];
	}

	/**
	 * @return string[]
	 */
	public function getXferExcluded() :array {
		return \is_array( $this->getOpt( 'xfer_excluded' ) ) ? $this->getOpt( 'xfer_excluded' ) : [];
	}

	/**
	 * @return $this
	 */
	public function setOptionsValues( array $values = [] ) {

		$values = \array_intersect_key(
			$values,
			\array_merge(
				\array_flip( $this->getOptionsKeys() ),
				\array_flip( $this->getVirtualCommonOptions() )
			)
		);

		if ( isset( $this->aOptionsValues ) ) {
			$this->aOptionsValues = $values;
		}

		self::con()->opts->setFor( $this->mod(), $values );

		$this->setNeedSave( true );

		return $this;
	}
}