<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email;

use FernleafSystems\Wordpress\Services\Services;

abstract class EmailBase extends Base {

	protected function getRenderData() :array {
		return [
			'header' => $this->getHeaderData(),
			'body'   => $this->getBodyData(),
			'footer' => $this->getFooterData(),
			'vars'   => [
				'lang' => Services::WpGeneral()->getLocale( '-' )
			]
		];
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