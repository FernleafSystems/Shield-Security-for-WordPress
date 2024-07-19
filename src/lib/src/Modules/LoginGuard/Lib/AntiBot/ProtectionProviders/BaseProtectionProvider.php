<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\ProtectionProviders;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\FormProviders;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

abstract class BaseProtectionProvider {

	use PluginControllerConsumer;

	private $factorTested = false;

	protected $factorBuilt = false;

	/**
	 * @var string[]
	 */
	protected $enqueueHandles = [];

	public function __construct() {
		add_action( 'wp_loaded', [ $this, 'setup' ], 0 ); // 0 to ensure WPS Hide Login doesn't fire before us.
		add_action( 'wp_footer', [ $this, 'maybeDequeueScript' ] );
	}

	public function setup() {
	}

	public function isFactorTested() :bool {
		return $this->factorTested;
	}

	/**
	 * @param FormProviders\BaseFormProvider $formProvider
	 */
	abstract public function buildFormInsert( $formProvider ) :string;

	/**
	 * @param FormProviders\BaseFormProvider $formProvider
	 * @throws \Exception
	 */
	abstract public function performCheck( $formProvider );

	public function setAsInsertBuilt() {
		$this->factorBuilt = true;
	}

	/**
	 * @return $this
	 */
	public function setFactorTested( bool $tested ) {
		$this->factorTested = $tested;
		return $this;
	}

	protected function processFailure() {
		remove_filter( 'authenticate', 'wp_authenticate_username_password', 20 );  // wp-includes/user.php
		remove_filter( 'authenticate', 'wp_authenticate_email_password', 20 );  // wp-includes/user.php
		self::con()->fireEvent( 'login_block' );
	}

	public function maybeDequeueScript() {
		if ( !$this->isFactorJsRequired() ) {
			foreach ( $this->enqueueHandles as $handle ) {
				wp_dequeue_script( $handle );
			}
		}
	}

	protected function isFactorJsRequired() :bool {
		return $this->factorBuilt;
	}
}