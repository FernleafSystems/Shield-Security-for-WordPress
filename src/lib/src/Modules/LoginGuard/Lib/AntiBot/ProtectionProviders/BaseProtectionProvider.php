<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\ProtectionProviders;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

abstract class BaseProtectionProvider {

	use ModConsumer;

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
	 * @param LoginGuard\Lib\AntiBot\FormProviders\BaseFormProvider $formProvider
	 * @return string
	 */
	abstract public function buildFormInsert( $formProvider );

	public function setAsInsertBuilt() :self {
		$this->factorBuilt = true;
		return $this;
	}

	/**
	 * @param LoginGuard\Lib\AntiBot\FormProviders\BaseFormProvider $form
	 * @throws \Exception
	 */
	abstract public function performCheck( $form );

	/**
	 * @param bool $tested
	 * @return $this
	 */
	public function setFactorTested( bool $tested ) {
		$this->factorTested = $tested;
		return $this;
	}

	/**
	 * @return $this
	 */
	protected function processFailure() {
		remove_filter( 'authenticate', 'wp_authenticate_username_password', 20 );  // wp-includes/user.php
		remove_filter( 'authenticate', 'wp_authenticate_email_password', 20 );  // wp-includes/user.php
		$this->getCon()->fireEvent( 'login_block' );
		return $this;
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