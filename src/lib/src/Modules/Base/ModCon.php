<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\HookTimings;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;

/**
 * @property bool $is_booted
 */
abstract class ModCon extends DynPropertiesClass {

	use Modules\PluginControllerConsumer;
	use Shield\Crons\PluginCronsConsumer;

	public const SLUG = '';

	/**
	 * @var Config\ModConfigVO
	 */
	public $cfg;

	/**
	 * @var Shield\Modules\Base\Processor
	 */
	private $oProcessor;

	/**
	 * @var Shield\Modules\Base\Options
	 */
	private $opts;

	/**
	 * @var Shield\Modules\Base\WpCli
	 */
	private $wpCli;

	/**
	 * @var AdminNotices
	 */
	private $adminNotices;

	/**
	 * @throws \Exception
	 */
	public function __construct( Config\ModConfigVO $cfg ) {
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

	protected function moduleReadyCheck() :bool {
		try {
			$ready = ( new Lib\CheckModuleRequirements() )
				->setMod( $this )
				->run();
		}
		catch ( \Exception $e ) {
			$ready = false;
		}
		return $ready;
	}

	protected function setupHooks() {
		add_action( 'init', [ $this, 'onWpInit' ], HookTimings::INIT_MOD_CON_DEFAULT );
		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ] );
		add_action( self::con()->prefix( 'pre_options_store' ), function () {
			$this->onConfigChanged();
		} );

		$this->collateRuleBuilders();
		$this->setupCronHooks();
		$this->setupCustomHooks();
	}

	protected function collateRuleBuilders() {
		add_filter( 'shield/collate_rule_builders', function ( array $builders ) {
			return \array_merge( $builders, \array_map(
				function ( $class ) {
					/** @var Shield\Rules\Build\BuildRuleBase $theClass */
					$theClass = new $class();
					$theClass->setMod( $this );
					return $theClass;
				},
				\array_filter( $this->enumRuleBuilders() )
			) );
		} );
	}

	protected function enumRuleBuilders() :array {
		return [];
	}

	protected function setupCustomHooks() {
	}

	protected function doPostConstruction() {
	}

	/**
	 * @return false|Shield\Modules\Base\Upgrade|mixed
	 */
	public function getUpgradeHandler() {
		return $this->loadModElement( 'Upgrade' );
	}

	public function onRunProcessors() {
		if ( $this->cfg->properties[ 'auto_load_processor' ] ) {
			$this->loadProcessor();
		}
		try {
			if ( !$this->cfg->properties[ 'skip_processor' ] && $this->isModuleEnabled() && $this->isReadyToExecute() ) {
				$this->doExecuteProcessor();
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

	protected function doExecuteProcessor() {
		$this->getProcessor()->execute();
	}

	public function onWpLoaded() {
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
	 * Override this and adapt per feature
	 * @return Shield\Modules\Base\Processor|mixed
	 */
	protected function loadProcessor() {
		if ( !isset( $this->oProcessor ) ) {
			try {
				$class = $this->findElementClass( 'Processor' );
			}
			catch ( \Exception $e ) {
				return null;
			}
			$this->oProcessor = new $class( $this );
		}
		return $this->oProcessor;
	}

	public function getOptionsStorageKey() :string {
		return self::con()->prefixOption( $this->cfg->properties[ 'storage_key' ] ).'_options';
	}

	/**
	 * @return Shield\Modules\Base\Processor|\FernleafSystems\Utilities\Logic\ExecOnce|mixed
	 */
	public function getProcessor() {
		return $this->loadProcessor();
	}

	public function getUrl_OptionsConfigPage() :string {
		return self::con()->plugin_urls->modCfg( $this );
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
		/** @var Shield\Modules\Plugin\Options $pluginOpts */
		$pluginOpts = $con->getModule_Plugin()->opts();

		if ( !$this->moduleReadyCheck() ) {
			$enabled = false;
		}
		elseif ( $this->cfg->properties[ 'auto_enabled' ] ) {
			// Auto enabled modules always run regardless
			$enabled = true;
		}
		elseif ( $pluginOpts->isPluginGloballyDisabled() ) {
			$enabled = false;
		}
		elseif ( self::con()->this_req->is_force_off ) {
			$enabled = false;
		}
		elseif ( $this->cfg->properties[ 'premium' ] && !$con->isPremiumActive() ) {
			$enabled = false;
		}
		else {
			$enabled = $this->isModOptEnabled();
		}

		return $enabled;
	}

	public function isModOptEnabled() :bool {
		return $this->opts()->isOpt( $this->getEnableModOptKey(), 'Y' )
			   || $this->opts()->isOpt( $this->getEnableModOptKey(), true, true );
	}

	public function getEnableModOptKey() :string {
		return 'enable_'.$this->cfg->slug;
	}

	public function getMainFeatureName() :string {
		return __( $this->cfg->properties[ 'name' ], 'wp-simple-firewall' );
	}

	/**
	 * @return array{title: string, subtitle: string, description: array}
	 */
	public function getDescriptors() :array {
		return [
			'title'       => $this->getMainFeatureName(),
			'subtitle'    => __( $this->cfg->properties[ 'tagline' ] ?? '', 'wp-simple-firewall' ),
			'description' => [],
		];
	}

	public function getModSlug( bool $prefix = true ) :string {
		return $prefix ? self::con()->prefix( $this->cfg->slug ) : $this->cfg->slug;
	}

	/**
	 * @return $this
	 */
	public function clearLastErrors() {
		return $this->setLastErrors();
	}

	/**
	 * @return string|array
	 */
	public function getLastErrors( bool $asString = false, string $glue = " " ) {
		$errors = $this->opts()->getOpt( 'last_errors' );
		if ( !\is_array( $errors ) ) {
			$errors = [];
		}
		return $asString ? \implode( $glue, $errors ) : $errors;
	}

	public function hasLastErrors() :bool {
		return \count( $this->getLastErrors() ) > 0;
	}

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
	 * @param array|string $mErrors
	 * @return $this
	 */
	public function setLastErrors( $mErrors = [] ) {
		if ( !\is_array( $mErrors ) ) {
			if ( \is_string( $mErrors ) ) {
				$mErrors = [ $mErrors ];
			}
			else {
				$mErrors = [];
			}
		}
		$this->opts()->setOpt( 'last_errors', $mErrors );
		return $this;
	}

	/**
	 * @return string[]
	 */
	public function getDismissedNotices() :array {
		$notices = $this->opts()->getOpt( 'dismissed_notices' );
		return \is_array( $notices ) ? $notices : [];
	}

	public function getUiTrack() :Lib\Components\UiTrack {
		$a = $this->opts()->getOpt( 'ui_track' );
		return ( new Lib\Components\UiTrack() )->applyFromArray( \is_array( $a ) ? $a : [] );
	}

	public function setDismissedNotices( array $dis ) {
		$this->opts()->setOpt( 'dismissed_notices', $dis );
	}

	public function setUiTrack( Lib\Components\UiTrack $UI ) {
		$this->opts()->setOpt( 'ui_track', $UI->getRawData() );
	}

	/**
	 * Handle any required actions after particular configuration changes.
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
	 * @deprecated 18.5
	 */
	public function preProcessOptions() {
	}

	/**
	 * @deprecated 18.5
	 */
	public function doPrePluginOptionsSave() {
	}

	public function onPluginDeactivate() {
	}

	public function isAccessRestricted() :bool {
		return $this->cfg->properties[ 'access_restricted' ] && !self::con()->isPluginAdmin();
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
	 * @return null|Shield\Modules\Base\Options|mixed
	 */
	public function opts() {
		return $this->opts ?? $this->opts = $this->loadModElement( 'Options' );
	}

	/**
	 * @return Shield\Modules\Base\WpCli|mixed
	 */
	public function getWpCli() {
		return $this->wpCli ?? $this->wpCli = $this->loadModElement( 'WpCli' );
	}

	/**
	 * @return null|Shield\Modules\Base\Strings
	 */
	public function getStrings() {
		return $this->loadStrings()->setMod( $this );
	}

	public function getAdminNotices() {
		return $this->adminNotices ?? $this->adminNotices = $this->loadModElement( 'AdminNotices' );
	}

	/**
	 * @return Shield\Modules\Base\Strings|mixed
	 */
	protected function loadStrings() {
		return $this->loadModElement( 'Strings' );
	}

	/**
	 * @return false|Shield\Modules\ModConsumer|mixed
	 */
	private function loadModElement( string $class ) {
		$element = false;
		try {
			$C = $this->findElementClass( $class );
			/** @var Shield\Modules\ModConsumer $element */
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

		$roots = \array_map( function ( $root ) {
			return \rtrim( $root, '\\' ).'\\';
		}, $this->getNamespaceRoots() );

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

	protected function getBaseNamespace() :string {
		return __NAMESPACE__;
	}

	protected function getNamespace() :string {
		return ( new \ReflectionClass( $this ) )->getNamespaceName();
	}

	protected function getNamespaceRoots() :array {
		return [
			$this->getNamespace(),
			$this->getBaseNamespace()
		];
	}

	/**
	 * Saves the options to the WordPress Options store.
	 * @return void
	 * @deprecated 8.4
	 */
	public function savePluginOptions() {
	}
}