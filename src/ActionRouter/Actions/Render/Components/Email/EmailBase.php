<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email;

use FernleafSystems\Wordpress\Services\Services;

abstract class EmailBase extends Base {

	protected function getRenderData() :array {
		return [
			'header' => $this->getHeaderData(),
			'body'   => $this->getBodyData(),
			'footer' => $this->getFooterData(),
			'imgs'   => [
				'email_logo' => $this->getEmailLogoUrl(),
			],
			'vars'   => [
				'lang' => Services::WpGeneral()->getLocale( '-' )
			]
		];
	}

	protected function getEmailLogoUrl() :string {
		$con = self::con();
		if ( $con->comps->whitelabel->isEnabled() ) {
			$wlLogoUrl = $con->opts->optGet( 'wl_login2fa_logourl' );
			return !empty( $wlLogoUrl ) ? $con->labels->url_img_logo_small : '';
		}
		return $con->urls->forImage( 'pluginlogo_banner-170x40.png' );
	}

	protected function getFooterData() :array {
		return apply_filters( 'icwp_shield_email_footer', [
			self::con()->action_router->render( Footer::class, [
				'email_flags' => $this->getEmailFlags(),
			] )
		] );
	}

	abstract protected function getBodyData() :array;

	protected function getHeaderData() :array {
		return [
			__( 'Hi !', 'wp-simple-firewall' ),
			'',
		];
	}

	protected function getEmailFlags() :array {
		return [
			'is_admin_email' => true,
		];
	}
}