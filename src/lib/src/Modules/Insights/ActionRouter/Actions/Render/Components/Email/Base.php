<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Email;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;
use FernleafSystems\Wordpress\Services\Services;

abstract class Base extends Actions\Render\BaseRender {

	use Actions\Traits\AuthNotRequired;

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
			$this->getCon()
				 ->getModule_Insights()
				 ->getActionRouter()
				 ->render( Footer::SLUG )
		] );
	}

	abstract protected function getBodyData() :array;

	protected function getHeaderData() :array {
		return [
			__( 'Hi !', 'wp-simple-firewall' ),
			'',
		];
	}
}