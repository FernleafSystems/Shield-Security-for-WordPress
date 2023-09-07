<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

abstract class Base {

	use PluginControllerConsumer;

	public const SLUG = '';
	public const MINIMUM_EDITION = 'free';
	public const WEIGHT = 3;

	protected $isProtected = null;

	public function build() :array {
		return [
			'slug'                   => $this->slug(),
			'categories'             => $this->categories(),
			'weight'                 => $this->weight(),
			'score'                  => $this->score(),
			'title'                  => $this->title(),
			'title_protected'        => $this->titleProtected(),
			'title_unprotected'      => $this->titleUnprotected(),
			'desc_protected'         => $this->descProtected(),
			'desc_unprotected'       => $this->descUnprotected(),
			'href_offcanvas'         => $this->hrefOffCanvas(),
			'href_full'              => $this->hrefFull(),
			'href_full_target_blank' => $this->hrefFullTargetBlank(),
			'is_protected'           => $this->isProtected(),
			'is_applicable'          => $this->isApplicable(),
			'is_critical'            => $this->isCritical(),
			'is_optcfg'              => $this->isOptConfigBased(),
		];
	}

	public function title() :string {
		return $this->isProtected() ? $this->titleProtected() : $this->titleUnprotected();
	}

	protected function titleProtected() :string {
		return '';
	}

	protected function titleUnprotected() :string {
		return '';
	}

	abstract public function descProtected() :string;

	abstract public function descUnprotected() :string;

	protected function href() :string {
		return $this->hrefFull();
	}

	protected function hrefOffCanvas() :string {
		return '';
	}

	protected function hrefFull() :string {
		return '';
	}

	protected function hrefFullTargetBlank() :bool {
		return true;
	}

	protected function isCritical() :bool {
		return false;
	}

	protected function isApplicable() :bool {
		return true;
	}

	protected function isOptConfigBased() :bool {
		return false;
	}

	protected function testIfProtected() :bool {
		return false;
	}

	protected function isProtected() :bool {
		if ( \is_null( $this->isProtected ) ) {
			$this->isProtected = $this->isApplicable() && $this->testIfProtected();
		}
		return $this->isProtected;
	}

	protected function categories() :array {
		return [ __( 'Security', 'wp-simple-firewall' ) ];
	}

	protected function score() :int {
		return $this->isProtected() ? $this->weight() : 0;
	}

	protected function slug() :string {
		return static::SLUG;
	}

	protected function weight() :int {
		return static::WEIGHT;
	}

	protected function isViewAsFree() :bool {
		return ( self::con()->getModule_Plugin()->opts()
					 ->getOpt( 'sec_overview_prefs' )[ 'view_as' ] ?? 'free' ) === 'free';
	}
}