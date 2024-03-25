<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\MainWP;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

abstract class BaseMainwpPage extends Actions\Render\FullPage\BaseFullPageRender {

	public const TEMPLATE = '/pages/mainwp/mainwp_default.twig';

	abstract protected function renderMainBodyContent() :string;

	protected function getScripts() :array {
		$scripts = parent::getScripts();
		$scripts[ 35 ] = [
			'src'    => self::con()->urls->forDistJS( 'mainwp_server' ),
			'id'     => 'shield-plugin',
			'footer' => true,
		];
		return $scripts;
	}

	protected function getStyles() :array {
		$scripts = parent::getStyles();
		$scripts[ 35 ] = [
			'src' => self::con()->urls->forDistCSS( 'mainwp_server' ),
			'id'  => 'shield-plugin',
		];
		return $scripts;
	}
}