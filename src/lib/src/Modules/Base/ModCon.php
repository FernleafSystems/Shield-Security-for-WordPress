<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\{
	Controller\Config\Modules\ModConfigVO,
	Crons,
	Modules
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\HookTimings;

/**
 * @property bool $is_booted
 */
class ModCon extends DynPropertiesClass {

	use Modules\PluginControllerConsumer;
	use Crons\PluginCronsConsumer;

	public const SLUG = '';

	/**
	 * @var ModConfigVO
	 */
	public $cfg;

	/**
	 * @var Modules\Base\Processor
	 */
	private $oProcessor;

	/**
	 * @var Modules\Base\Options
	 */
	private $opts;

	/**
	 * @var Modules\Base\WpCli
	 */
	private $wpCli;

	/**
	 * @throws \Exception
	 */
	public function __construct( ModConfigVO $cfg ) {
		$this->cfg = $cfg;
	}

	/**
	 * @throws \Exception
	 */
	public function boot() {
		if ( !$this->is_booted ) {
			$this->is_booted = true;
			$this->doPostConstruction();
			$this->setupHooks();
		}
	}

	protected function doPostConstruction() {
	}

	protected function setupHooks() {
		add_action( 'init', [ $this, 'onWpInit' ], HookTimings::INIT_MOD_CON_DEFAULT );
		$this->setupCronHooks();
	}

	public function onRunProcessors() {
		try {
			if ( $this->isModuleEnabled() && $this->isReadyToExecute() ) {
				$this->getProcessor()->execute();
			}
		}
		catch ( \Exception $e ) {
		}
	}

	/**
	 * @throws \Exception
	 */
	protected function isReadyToExecute() :bool {
		return !\is_null( $this->getProcessor() );
	}

	/**
	 * @return Processor|mixed
	 * @throws \Exception
	 */
	public function getProcessor() {
		if ( !isset( $this->oProcessor ) ) {
			$class = $this->findElementClass( 'Processor' );
			$this->oProcessor = new $class( $this );
		}
		return $this->oProcessor;
	}

	public function onWpInit() {
		$con = self::con();

		add_action( 'cli_init', function () {
			try {
				$this->getWpCli()->execute();
			}
			catch ( \Exception $e ) {
			}
		} );

		// GDPR
		if ( $con->isPremiumActive() ) {
			add_filter( $con->prefix( 'wpPrivacyExport' ), [ $this, 'onWpPrivacyExport' ], 10, 3 );
			add_filter( $con->prefix( 'wpPrivacyErase' ), [ $this, 'onWpPrivacyErase' ], 10, 3 );
		}
	}

	/**
	 * @return $this
	 */
	public function setIsMainFeatureEnabled( bool $enable ) {
		$this->opts()->setOpt( $this->getEnableModOptKey(), $enable ? 'Y' : 'N' );
		return $this;
	}

	public function isModuleEnabled() :bool {
		$con = self::con();
		if ( $con->comps->opts_lookup->isPluginGloballyDisabled() ) {
			$enabled = false;
		}
		elseif ( $con->this_req->is_force_off ) {
			$enabled = false;
		}
		else {
			$enabled = $this->isModOptEnabled();
		}
		return $enabled;
	}

	public function isModOptEnabled() :bool {
		return $this->opts()->isOpt( $this->getEnableModOptKey(), 'Y' );
	}

	public function getEnableModOptKey() :string {
		return 'enable_'.$this->cfg->slug;
	}

	/**
	 * @deprecated 19.1
	 */
	public function getTextOpt( string $key ) :string {
		$txt = $this->opts()->getOpt( $key, 'default' );
		if ( $txt == 'default' ) {
			$txt = $this->getTextOptDefault( $key );
		}
		return __( $txt, 'wp-simple-firewall' );
	}

	public function getTextOptDefault( string $key ) :string {
		return 'Undefined Text Opt Default';
	}

	/**
	 * @deprecated 19.1
	 */
	public function onConfigChanged() :void {
	}

	/**
	 * @deprecated 18.5
	 */
	public function saveModOptions() {
		self::con()->opts->store();
	}

	/**
	 * See plugin controller for the nature of $aData wpPrivacyExport()
	 * @param array  $exportItems
	 * @param string $email
	 * @param int    $page
	 * @return array
	 */
	public function onWpPrivacyExport( $exportItems, $email, $page = 1 ) {
		return $exportItems;
	}

	/**
	 * See plugin controller for the nature of $aData wpPrivacyErase()
	 * @param array  $data
	 * @param string $email
	 * @param int    $page
	 * @return array
	 */
	public function onWpPrivacyErase( $data, $email, $page = 1 ) {
		return $data;
	}

	/**
	 * @return null|Modules\Base\Options|mixed
	 */
	public function opts() {
		return $this->opts ?? $this->opts = $this->loadModElement( 'Options' );
	}

	/**
	 * @return Modules\Base\WpCli|mixed
	 */
	public function getWpCli() {
		return $this->wpCli ?? $this->wpCli = $this->loadModElement( 'WpCli' );
	}

	/**
	 * @return Modules\ModConsumer
	 */
	public function getStrings() {
		$str = $this->loadModElement( 'Strings' );
		$str->setMod( $this );
		return $str;
	}

	/**
	 * @return false|Modules\Base\Upgrade|mixed
	 */
	public function getUpgradeHandler() {
		return $this->loadModElement( 'Upgrade' );
	}

	/**
	 * @return false|Modules\ModConsumer|mixed
	 */
	private function loadModElement( string $class ) {
		$element = false;
		try {
			$C = $this->findElementClass( $class );
			/** @var Modules\ModConsumer $element */
			$element = @\class_exists( $C ) ? new $C() : false;
			if ( \method_exists( $element, 'setMod' ) ) {
				$element->setMod( $this );
			}
		}
		catch ( \Exception $e ) {
		}
		return $element;
	}

	/**
	 * @throws \Exception
	 */
	public function findElementClass( string $element ) :string {
		$theClass = null;

		$roots = \array_map(
			function ( $root ) {
				return \rtrim( $root, '\\' ).'\\';
			},
			[
				( new \ReflectionClass( $this ) )->getNamespaceName(),
				'\FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield',
				__NAMESPACE__
			]
		);

		foreach ( $roots as $NS ) {
			$maybe = $NS.$element;
			if ( @\class_exists( $maybe ) ) {
				if ( ( new \ReflectionClass( $maybe ) )->isInstantiable() ) {
					$theClass = $maybe;
					break;
				}
			}
		}

		if ( $theClass === null ) {
			throw new \Exception( sprintf( 'Could not find class for element "%s".', $element ) );
		}
		return $theClass;
	}

	protected function getNamespaceRoots() :array {
		return [
			( new \ReflectionClass( $this ) )->getNamespaceName(),
			'\FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield',
			__NAMESPACE__
		];
	}

	/**
	 * Saves the options to the WordPress Options store.
	 * @return void
	 * @deprecated 8.4
	 */
	public function savePluginOptions() {
	}

	/**
	 * @return Modules\Base\Strings|mixed
	 * @deprecated 19.1
	 */
	protected function loadStrings() {
		return $this->loadModElement( 'Strings' );
	}

	/**
	 * @deprecated 19.1
	 */
	protected function moduleReadyCheck() :bool {
		return true;
	}

	/**
	 * @deprecated 19.1
	 */
	public function getMainFeatureName() :string {
		return __( $this->cfg->properties[ 'name' ], 'wp-simple-firewall' );
	}

	/**
	 * @deprecated 19.1
	 */
	protected function getNamespace() :string {
		return ( new \ReflectionClass( $this ) )->getNamespaceName();
	}

	/**
	 * @deprecated 19.1
	 */
	protected function getBaseNamespace() :string {
		return __NAMESPACE__;
	}

	/**
	 * @deprecated 19.1
	 */
	public function getModSlug( bool $prefix = true ) :string {
		return $prefix ? self::con()->prefix( $this->cfg->slug ) : $this->cfg->slug;
	}

	/**
	 * @deprecated 19.1
	 */
	public function getOptionsStorageKey() :string {
		return self::con()->prefix( $this->cfg->properties[ 'storage_key' ], '_' ).'_options';
	}

	/**
	 * @deprecated 19.1
	 */
	public function name() :string {
		return __( $this->cfg->properties[ 'name' ], 'wp-simple-firewall' );
	}
}