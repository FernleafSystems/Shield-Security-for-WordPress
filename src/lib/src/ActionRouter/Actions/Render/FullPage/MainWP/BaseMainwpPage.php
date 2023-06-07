<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\MainWP;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

abstract class BaseMainwpPage extends Actions\Render\FullPage\BaseFullPageRender {

	public const TEMPLATE = '/pages/mainwp/mainwp_default.twig';

	abstract protected function renderMainBodyContent() :string;

	protected function getScripts() :array {
		$scripts = parent::getScripts();
		$scripts[ 35 ] = [
			'src' => $this->con()->urls->forJs( 'plugin' ),
			'id'  => 'shield-plugin',
		];
		return $scripts;
	}

	protected function getStyles() :array {
		$scripts = parent::getStyles();
		$scripts[ 35 ] = [
			'src' => $this->con()->urls->forCss( 'plugin' ),
			'id'  => 'shield-plugin',
		];
		return $scripts;
	}
}