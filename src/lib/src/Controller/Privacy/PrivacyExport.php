<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Privacy;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class PrivacyExport {

	use PluginControllerConsumer;
	use ExecOnce;

	protected function run() {
		add_filter( 'wp_privacy_personal_data_exporters', [ $this, 'onWpPrivacyRegisterExporter' ] );
	}

	/**
	 * @param array[] $registered
	 */
	public function onWpPrivacyRegisterExporter( $registered ) :array {
		if ( !\is_array( $registered ) ) {
			$registered = []; // account for crap plugins that do-it-wrong.
		}
		$registered[] = [
			'exporter_friendly_name' => self::con()->labels->Name,
			'callback'               => [ $this, 'wpPrivacyExport' ],
		];
		return $registered;
	}

	/**
	 * @param string $email
	 * @param int    $page
	 */
	public function wpPrivacyExport( $email, $page = 1 ) :array {
		$valid = Services::Data()->validEmail( $email )
				 && ( Services::WpUsers()->getUserByEmail( $email ) instanceof \WP_User );
		return [
			'data' => $valid ? apply_filters( self::con()->prefix( 'wpPrivacyExport' ), [], $email, $page ) : [],
			'done' => true,
		];
	}
}