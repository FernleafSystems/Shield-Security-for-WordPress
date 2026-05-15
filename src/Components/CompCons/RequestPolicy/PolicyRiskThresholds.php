<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\RequestPolicy;

class PolicyRiskThresholds {

	public const SENSITIVITY_LENIENT = 'lenient';
	public const SENSITIVITY_BALANCED = 'balanced';
	public const SENSITIVITY_AGGRESSIVE = 'aggressive';

	public const DEFAULT_SENSITIVITY = self::SENSITIVITY_BALANCED;

	public const CATEGORY_WINDOWS = [
		PolicyEvidence::TYPE_AUTH_ABUSE       => '15m',
		PolicyEvidence::TYPE_PROBE_ABUSE      => '15m',
		PolicyEvidence::TYPE_RATE_ABUSE       => '15m',
		PolicyEvidence::TYPE_FIREWALL_ABUSE   => '24h',
		PolicyEvidence::TYPE_CONTENT_ABUSE    => '24h',
		PolicyEvidence::TYPE_IP_ENFORCEMENT   => '24h',
	];

	private const THRESHOLDS = [
		self::SENSITIVITY_LENIENT    => [
			PolicyEvidence::TYPE_AUTH_ABUSE     => [ 'suspicious' => 2, 'hostile' => 5 ],
			PolicyEvidence::TYPE_PROBE_ABUSE    => [ 'suspicious' => 4, 'hostile' => 8 ],
			PolicyEvidence::TYPE_RATE_ABUSE     => [ 'suspicious' => 2, 'hostile' => 4 ],
			PolicyEvidence::TYPE_FIREWALL_ABUSE => [ 'suspicious' => 2, 'hostile' => 5 ],
			PolicyEvidence::TYPE_CONTENT_ABUSE  => [ 'suspicious' => 2, 'hostile' => 5 ],
			PolicyEvidence::TYPE_IP_ENFORCEMENT => [ 'suspicious' => 4, 'hostile' => 8 ],
		],
		self::SENSITIVITY_BALANCED   => [
			PolicyEvidence::TYPE_AUTH_ABUSE     => [ 'suspicious' => 1, 'hostile' => 3 ],
			PolicyEvidence::TYPE_PROBE_ABUSE    => [ 'suspicious' => 2, 'hostile' => 4 ],
			PolicyEvidence::TYPE_RATE_ABUSE     => [ 'suspicious' => 1, 'hostile' => 2 ],
			PolicyEvidence::TYPE_FIREWALL_ABUSE => [ 'suspicious' => 1, 'hostile' => 2 ],
			PolicyEvidence::TYPE_CONTENT_ABUSE  => [ 'suspicious' => 1, 'hostile' => 3 ],
			PolicyEvidence::TYPE_IP_ENFORCEMENT => [ 'suspicious' => 2, 'hostile' => 4 ],
		],
		self::SENSITIVITY_AGGRESSIVE => [
			PolicyEvidence::TYPE_AUTH_ABUSE     => [ 'suspicious' => 1, 'hostile' => 2 ],
			PolicyEvidence::TYPE_PROBE_ABUSE    => [ 'suspicious' => 1, 'hostile' => 2 ],
			PolicyEvidence::TYPE_RATE_ABUSE     => [ 'suspicious' => 1, 'hostile' => 1 ],
			PolicyEvidence::TYPE_FIREWALL_ABUSE => [ 'suspicious' => 1, 'hostile' => 1 ],
			PolicyEvidence::TYPE_CONTENT_ABUSE  => [ 'suspicious' => 1, 'hostile' => 2 ],
			PolicyEvidence::TYPE_IP_ENFORCEMENT => [ 'suspicious' => 1, 'hostile' => 1 ],
		],
	];

	public static function normaliseSensitivity( string $sensitivity ) :string {
		return \in_array( $sensitivity, [
			self::SENSITIVITY_LENIENT,
			self::SENSITIVITY_BALANCED,
			self::SENSITIVITY_AGGRESSIVE,
		], true ) ? $sensitivity : self::DEFAULT_SENSITIVITY;
	}

	public static function threshold( string $sensitivity, string $category, string $band ) :int {
		$sensitivity = self::normaliseSensitivity( $sensitivity );
		return self::THRESHOLDS[ $sensitivity ][ $category ][ $band ];
	}
}
