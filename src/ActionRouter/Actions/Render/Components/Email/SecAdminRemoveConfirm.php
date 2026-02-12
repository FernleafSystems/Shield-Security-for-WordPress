<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email;

class SecAdminRemoveConfirm extends EmailBase {

	public const SLUG = 'email_sec_admin_remove_confirm';
	public const TEMPLATE = '/email/sec_admin_remove_confirm.twig';

	protected function getBodyData() :array {
		return [
			'hrefs'   => [
				'confirmation_link' => esc_url( $this->action_data[ 'confirmation_href' ] ), // Internally generated via noncedPluginAction(); template uses |raw
			],
			'strings' => [
				'requested'            => sprintf( __( 'A WordPress user (%s) has requested to remove the Security Admin restriction.', 'wp-simple-firewall' ),
					$this->action_data[ 'username' ] ),
				'purpose'              => __( 'The purpose of this email is to confirm this action.', 'wp-simple-firewall' ),
				'click_confirm'        => __( 'Please click the link below to confirm the removal of all Security Admin restrictions.', 'wp-simple-firewall' ),
				'same_browser_warning' => sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ),
					__( 'This link must be opened in the same browser that was used to make this original request.', 'wp-simple-firewall' ) ),
				'reinstate_notice'     => __( "Please understand that to reinstate the Security Admin features, you'll need to provide a new Security Admin PIN.", 'wp-simple-firewall' ),
				'thank_you'            => __( 'Thank you.', 'wp-simple-firewall' ),
			],
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'username',
			'confirmation_href',
		];
	}
}
