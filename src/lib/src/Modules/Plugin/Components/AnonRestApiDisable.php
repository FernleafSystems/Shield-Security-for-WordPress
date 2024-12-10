<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Components;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\ArrayOps;
use FernleafSystems\Wordpress\Services\Services;

class AnonRestApiDisable {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function canRun() :bool {
		return self::con()->opts->optIs( 'disable_anonymous_restapi', 'Y' );
	}

	protected function run() {
		add_action( 'init', function () {
			if ( !Services::WpUsers()->isUserLoggedIn() ) {
				add_filter( 'rest_authentication_errors', [ $this, 'disableAnonymousRestApi' ], 99 );
			}
		} );
	}

	/**
	 * Understand that if $mCurrentStatus is null, no check has been made. If true, something has
	 * authenticated the request, and if WP_Error, then an error is already present
	 * @param \WP_Error|true|null $mStatus
	 * @return \WP_Error
	 */
	public function disableAnonymousRestApi( $mStatus ) {

		$namespace = Services::Rest()->getNamespace();
		if ( !empty( $namespace ) && $mStatus !== true && !is_wp_error( $mStatus ) ) {
			$con = self::con();

			$exclusions = \array_unique( \array_merge(
				ArrayOps::CleanStrings(
					apply_filters( 'shield/anonymous_rest_api_exclusions', $con->opts->optGet( 'api_namespace_exclusions' ) ),
					'#[^\da-z_-]#i'
				)
			) );

			if ( !\in_array( $namespace, $exclusions ) ) {
				$mStatus = new \WP_Error(
					'shield_block_anon_restapi',
					apply_filters( 'shield/anonymous_rest_api/disabled_message',
						sprintf( __( 'Anonymous access to the WordPress REST API has been restricted by %s.', 'wp-simple-firewall' ), $con->labels->Name ),
						$namespace
					),
					[ 'status' => rest_authorization_required_code() ]
				);

				$con->fireEvent(
					'block_anonymous_restapi',
					[ 'audit_params' => [ 'namespace' => $namespace ] ]
				);
			}
		}

		return $mStatus;
	}
}