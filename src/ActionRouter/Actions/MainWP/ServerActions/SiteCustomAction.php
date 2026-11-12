<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainWP\ServerActions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\LicenseLookup;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use MainWP\Dashboard\MainWP_Connect;

class SiteCustomAction extends BaseSiteMwpAction {

	public const SLUG = 'mwp_server_client_site_custom_action';

	protected function fireClientSiteAction() {
		$subActionParams = $this->action_data[ 'sub_action_params' ] ?? [];
		$subActionParams = \is_array( $subActionParams ) ? $subActionParams : [];
		// @deprecated 22.4.0 Makes sure the only override metadata sent is the fixed set below. Goes when that goes.
		unset( $subActionParams[ 'action_overrides' ] );

		return MainWP_Connect::fetch_url_authed(
			$this->getMwpSite()->siteobj,
			'extra_execution',
			[
				self::con()->prefix( 'mwp-action' ) => $this->action_data[ 'sub_action_slug' ],
				self::con()->prefix( 'mwp-params' ) => \array_merge(
					[
						// @deprecated 22.4.0 Child sites before 22.4.0 need this metadata to run the request; newer
						// child sites discard it. See ClientActionRunner::LEGACY_ENABLE_SLUG for when this goes.
						'action_overrides' => [
							'is_nonce_verify_required' => false,
						],
					],
					$subActionParams
				),
			]
		);
	}

	protected function checkResponse() :bool {
		$response = $this->clientActionResponse;
		$key = self::con()->prefix( 'mwp-action-response' );
		if ( empty( $response ) || !\is_array( $response ) || !isset( $response[ $key ] ) ) {
			throw new ActionException( sprintf( __( 'Empty response from %s client site', 'wp-simple-firewall' ), self::con()->labels->Name ) );
		}

		if ( !\is_string( $response[ $key ] ) ) {
			throw new ActionException( sprintf( __( 'Invalid response from %s client site', 'wp-simple-firewall' ), self::con()->labels->Name ) );
		}

		$result = \json_decode( $response[ $key ], true );
		if ( empty( $result ) || !\is_array( $result ) ) {
			throw new ActionException( sprintf( __( 'Invalid response from %s client site', 'wp-simple-firewall' ), self::con()->labels->Name ) );
		}

		return (bool)( $result[ 'success' ] ?? false );
	}

	protected function getMainwpActionFailureMessage() :string {
		switch ( $this->action_data[ 'sub_action_slug' ] ) {
			case LicenseLookup::SLUG:
				$msg = sprintf( __( '%s license check failed.', 'wp-simple-firewall' ), self::con()->labels->Name );
				break;
			default:
				$msg = parent::getMainwpActionFailureMessage();
				break;
		}
		return $msg;
	}

	protected function getMainwpActionSuccessMessage() :string {
		switch ( $this->action_data[ 'sub_action_slug' ] ) {
			case LicenseLookup::SLUG:
				$msg = sprintf( __( '%s license check was successful.', 'wp-simple-firewall' ), self::con()->labels->Name );
				break;
			default:
				$msg = parent::getMainwpActionSuccessMessage();
				break;
		}
		return $msg;
	}

	protected function getRequiredDataKeys() :array {
		return \array_merge(
			parent::getRequiredDataKeys(),
			[
				'sub_action_slug',
			]
		);
	}
}
