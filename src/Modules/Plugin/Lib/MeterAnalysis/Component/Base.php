<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

abstract class Base {

	use PluginControllerConsumer;

	public const SLUG = '';
	public const MINIMUM_EDITION = 'free';
	public const WEIGHT = 3;
	public const CHANNEL_CONFIG = 'config';
	public const CHANNEL_ACTION = 'action';

	protected ?bool $isProtected = null;
	private ?string $meterChannel = null;

	public static function normalizeChannelRaw( ?string $channel ) :string {
		return \strtolower( \trim( (string)$channel ) );
	}

	public static function normalizeChannel( ?string $channel ) :?string {
		$normalized = self::normalizeChannelRaw( $channel );
		return $normalized === '' ? null : $normalized;
	}

	public static function isValidChannel( ?string $channel ) :bool {
		if ( $channel === null ) {
			return true;
		}
		return \in_array( $channel, [
			self::CHANNEL_CONFIG,
			self::CHANNEL_ACTION
		], true );
	}

	public static function assertValidChannel(
		?string $channel,
		string $messageTemplate = 'Invalid channel: %s'
	) :?string {
		$normalized = self::normalizeChannel( $channel );
		if ( !self::isValidChannel( $normalized ) ) {
			throw new \InvalidArgumentException( \sprintf( $messageTemplate, (string)$normalized ) );
		}
		return $normalized;
	}

	public function build( ?string $meterChannel = null ) :array {
		$this->meterChannel = $meterChannel;
		return \array_merge(
			[
				'slug'                   => $this->slug(),
				'channel'                => $this->channel(),
				'categories'             => $this->categories(),
				'weight'                 => $this->weight(),
				'score'                  => $this->score(),
				'href_full'              => $this->hrefFull(),
				'href_full_target_blank' => $this->hrefFullTargetBlank(),
				'href_data'              => $this->hrefData(),
				'is_protected'           => $this->isProtected(),
				'is_applicable'          => $this->isApplicable(),
				'is_critical'            => $this->isCritical(),
				'is_optcfg'              => $this->isOptConfigBased(),
				'config_item'            => $this->cfgItem(),
				'text'                   => $this->text(),
			],
			$this->text(),
		);
	}

	public function channel() :string {
		return self::CHANNEL_CONFIG;
	}

	protected function text() :array {
		return [
			'title'             => $this->title(),
			'title_protected'   => $this->titleProtected(),
			'title_unprotected' => $this->titleUnprotected(),
			'desc_protected'    => $this->descProtected(),
			'desc_unprotected'  => $this->descUnprotected(),
			'fix'               => __( 'Fix', 'wp-simple-firewall' ),
		];
	}

	protected function cfgItem() :string {
		return '';
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

	protected function hrefData() :array {
		return [];
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
		return $this->isProtected ??= $this->isApplicable() && $this->testIfProtected();
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
		return ( self::con()->opts->optGet( 'sec_overview_prefs' )[ 'view_as' ] ?? 'free' ) === 'free';
	}

	protected function meterChannel() :?string {
		return $this->meterChannel;
	}
}
