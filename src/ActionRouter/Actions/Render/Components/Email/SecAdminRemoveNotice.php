<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email;

class SecAdminRemoveNotice extends EmailBase {

	public const SLUG = 'email_sec_admin_remove_notice';
	public const TEMPLATE = '/email/sec_admin_remove_notice.twig';

	protected function getBodyData() :array {
		return [
			'strings' => [
				'notification'      => __( 'This is an email notification to inform you that the Security Admin restriction has been removed.', 'wp-simple-firewall' ),
				'method'            => __( 'This was done using a confirmation email sent to the Security Administrator email address.', 'wp-simple-firewall' ),
				'restrictions'      => __( 'All restrictions imposed by the Security Admin module have been lifted.', 'wp-simple-firewall' ),
				'reinstate_notice'  => __( "Please understand that to reinstate the Security Admin features, you'll need to provide a new Security Admin PIN.", 'wp-simple-firewall' ),
				'thank_you'         => __( 'Thank you.', 'wp-simple-firewall' ),
			],
		];
	}
}
