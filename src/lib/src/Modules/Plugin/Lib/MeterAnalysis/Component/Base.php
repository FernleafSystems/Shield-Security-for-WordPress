<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

abstract class Base {

	use PluginControllerConsumer;

	public const SLUG = '';
	public const WEIGHT = 30;

	private $protected = null;

	public function build() :array {
		return [
			'slug'             => $this->slug(),
			'weight'           => $this->weight(),
			'score'            => $this->score(),
			'title'            => $this->title(),
			'protected'        => $this->protected(),
			'desc_protected'   => $this->descProtected(),
			'desc_unprotected' => $this->descUnprotected(),
			'href'             => $this->href(),
			'is_critical'      => $this->isCritical(),
			'new_window'       => !strpos( $this->href() ?? '', 'iCWP_WPSF_OffCanvas' ),
		];
	}

	abstract public function title() :string;

	abstract public function descProtected() :string;

	abstract public function descUnprotected() :string;

	protected function href() :string {
		return '';
	}

	protected function isCritical() :bool {
		return false;
	}

	protected function isProtected() :bool {
		return false;
	}

	protected function link( string $for ) :string {
		return $this->getCon()->getModule_Plugin()->getUIHandler()->getOffCanvasJavascriptLinkFor( $for );
	}

	public function protected() :bool {
		if ( is_null( $this->protected ) ) {
			$this->protected = $this->isProtected();
		}
		return $this->protected;
	}

	protected function score() :?int {
		return null;
	}

	protected function slug() :string {
		return static::SLUG;
	}

	protected function weight() :int {
		return static::WEIGHT;
	}
}