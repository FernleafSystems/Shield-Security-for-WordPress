<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Privacy;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class PrivacyEraser {

	use PluginControllerConsumer;
	use ExecOnce;

	protected function run() {
		add_filter( 'wp_privacy_personal_data_erasers', [ $this, 'onWpPrivacyRegisterEraser' ] );
	}

	/**
	 * @param array[] $registered
	 */
	public function onWpPrivacyRegisterEraser( $registered ) :array {
		if ( !\is_array( $registered ) ) {
			$registered = []; // account for crap plugins that do-it-wrong.
		}
		$registered[] = [
			'eraser_friendly_name' => self::con()->labels->Name,
			'callback'             => [ $this, 'wpPrivacyErase' ],
		];
		return $registered;
	}

	/**
	 * @param string $email
	 * @param int    $page
	 * @return array
	 */
	public function wpPrivacyErase( $email, $page = 1 ) {
		$valid = Services::Data()->validEmail( $email )
				 && ( Services::WpUsers()->getUserByEmail( $email ) instanceof \WP_User );

		$result = [
			'items_removed'  => $valid,
			'items_retained' => false,
			'messages'       => $valid ? [] : [ 'Email address not valid or does not belong to a user.' ],
			'done'           => true,
		];
		return $valid ?
			apply_filters( self::con()->prefix( 'wpPrivacyErase' ), $result, $email, $page ) : $result;
	}
}