<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Describes the early loader's state for the settings page.
 */
class EarlyLoadingStatus {

	use PluginControllerConsumer;

	/**
	 * @return string[]
	 */
	public function notices() :array {
		$early = self::con()->comps->early_loading;
		$state = $early->state();
		$now = Services::Request()->ts();

		$notices = [];
		if ( $state->status === State::INSTALLED && $state->confirmed ) {
			$notices[] = __( 'Early loading is active: PHP runs Shield before WordPress on every request.', 'wp-simple-firewall' );
		}
		elseif ( $state->isPending( $now ) ) {
			$notices[] = sprintf(
				__( 'Early loading is set up. PHP can take up to %s minutes to start using it.', 'wp-simple-firewall' ),
				(int)\ceil( ( $state->settledAt() - $now )/60 )
			);
		}
		elseif ( $state->status === State::MANUAL ) {
			$directives = $early->manualDirectives();
			$notices[] = $state->confirmed ?
				__( 'Early loading is active through your own PHP configuration.', 'wp-simple-firewall' )
				: \implode( ' ', [
					__( "This server's PHP configuration can't be changed automatically.", 'wp-simple-firewall' ),
					sprintf( __( 'Add %s to the php.ini for this site, or %s to its PHP-FPM pool, then reload PHP.', 'wp-simple-firewall' ),
						sprintf( '<code>%s</code>', esc_html( $directives[ 'ini' ] ) ),
						sprintf( '<code>%s</code>', esc_html( $directives[ 'pool' ] ) )
					),
				] );
		}
		elseif ( $state->status === State::REMOVING ) {
			$notices[] = $state->mechanism === Mechanism::MANUAL ?
				__( "Remove the auto_prepend_file line you added for Shield from the PHP configuration. Shield removes its loader file once PHP stops using it.", 'wp-simple-firewall' )
				: __( 'Early loading has been removed from the PHP configuration. Shield removes its loader file once PHP stops using it.', 'wp-simple-firewall' );
		}
		return $notices;
	}

	/**
	 * @return string[]
	 */
	public function warnings() :array {
		$state = self::con()->comps->early_loading->state();

		$warnings = [];
		if ( $state->status === State::INSTALLED && !$state->confirmed && !$state->isPending( Services::Request()->ts() ) ) {
			$warnings[] = __( "Early loading is set up, but PHP isn't running it. The server may ignore .htaccess or .user.ini settings.", 'wp-simple-firewall' );
		}
		if ( $state->error !== '' ) {
			$warnings[] = $this->errorMessage( $state->error, $state->errorFile );
		}
		return $warnings;
	}

	public function errorMessage( string $reason, string $file ) :string {
		switch ( $reason ) {
			case InstallException::MULTISITE:
				$msg = __( "Early loading isn't available on WordPress multisite yet.", 'wp-simple-firewall' );
				break;
			case InstallException::FILESYSTEM_NOT_DIRECT:
				$msg = __( "Early loading couldn't be set up because WordPress can't write files directly on this server.", 'wp-simple-firewall' );
				break;
			case InstallException::UNSAFE_PATH:
			case InstallException::HOST_PREPEND_NOT_ABSOLUTE:
				$msg = __( "Early loading couldn't be set up because a file path can't be written safely to the PHP configuration.", 'wp-simple-firewall' );
				break;
			case InstallException::CONFLICT:
				$msg = sprintf( __( 'Early loading wasn\'t set up because another product already sets auto_prepend_file in %s.', 'wp-simple-firewall' ),
					sprintf( '<code>%s</code>', esc_html( $file ) ) );
				break;
			case InstallException::MALFORMED_MARKERS:
				$msg = sprintf( __( "Shield's early loading section in %s is damaged. Remove it by hand, then try again.", 'wp-simple-firewall' ),
					sprintf( '<code>%s</code>', esc_html( $file ) ) );
				break;
			case InstallException::LOOPBACK_UNAVAILABLE:
				$msg = __( "Early loading wasn't set up because this site can't make requests to itself, so the change couldn't be checked safely.", 'wp-simple-firewall' );
				break;
			case InstallException::STUB_WRITE_FAILED:
				$msg = __( "Early loading couldn't be set up because Shield couldn't write its loader file to the WordPress folder.", 'wp-simple-firewall' );
				break;
			case InstallException::STUB_SMOKE_FAILED:
				$msg = __( "Early loading wasn't set up because the web server returned an error when running Shield's loader file.", 'wp-simple-firewall' );
				break;
			case InstallException::CONFIG_WRITE_FAILED:
				$msg = sprintf( __( "Early loading couldn't be set up because %s couldn't be updated.", 'wp-simple-firewall' ),
					sprintf( '<code>%s</code>', esc_html( $file ) ) );
				break;
			case InstallException::INSTALL_INTERRUPTED:
				$msg = __( 'Early loading was undone automatically because setting it up was interrupted before it could be checked.', 'wp-simple-firewall' );
				break;
			case InstallException::SITE_FAILED_REVERTED:
				$msg = __( 'Early loading was undone automatically because the site stopped responding normally after the change.', 'wp-simple-firewall' );
				break;
			default:
				$msg = __( "Early loading couldn't be set up.", 'wp-simple-firewall' );
				break;
		}
		return $msg;
	}
}
