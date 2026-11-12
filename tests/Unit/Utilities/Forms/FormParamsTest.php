<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Utilities\Forms;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Forms\FormParams;

/**
 * The form decoder reads request data, which anyone can shape. A form this plugin sends decodes exactly; anything
 * else decodes to an empty form, never to an error.
 */
class FormParamsTest extends BaseUnitTest {

	private const FORM = [
		'title' => 'Weekly',
		'scans' => [ 'malware', 'wp' ],
	];

	public function test_plain_data_is_the_form_when_nothing_is_encoded() :void {
		$data = [ 'enable_mainwp' => 'Y' ];
		$this->assertSame( $data, FormParams::Decode( $data ) );
	}

	public function test_form_sent_as_fields_is_the_form() :void {
		$this->assertSame( self::FORM, FormParams::Decode( [ 'form_params' => self::FORM ] ) );
	}

	public function test_encoded_form_decodes() :void {
		$this->assertSame( self::FORM, FormParams::Decode( [
			'form_params' => \base64_encode( (string)\json_encode( self::FORM ) ),
			'form_enc'    => [ FormParams::ENC_BASE64, FormParams::ENC_JSON ],
		] ) );
	}

	/**
	 * The fallback the options page sends when a firewall blocks the first request.
	 */
	public function test_obscured_encoded_form_decodes() :void {
		$this->assertSame( self::FORM, FormParams::Decode( [
			'form_params' => 'icwp-'.\base64_encode( (string)\json_encode( self::FORM ) ),
			'form_enc'    => [ FormParams::ENC_OBSCURE, FormParams::ENC_BASE64, FormParams::ENC_JSON ],
		] ) );
	}

	public function test_base64_query_string_decodes_by_default() :void {
		$this->assertSame( [ 'a' => '1', 'b' => '2' ], FormParams::Decode( [
			'form_params' => \base64_encode( 'a=1&b=2' ),
		] ) );
	}

	/**
	 * @dataProvider malformedForms
	 */
	public function test_malformed_form_decodes_to_an_empty_form( array $data ) :void {
		$this->assertSame( [], FormParams::Decode( $data ) );
	}

	public function malformedForms() :array {
		$encoded = \base64_encode( (string)\json_encode( self::FORM ) );
		return [
			'encodings as a string'          => [ [ 'form_params' => $encoded, 'form_enc' => 'b64' ] ],
			'encodings as a number'          => [ [ 'form_params' => $encoded, 'form_enc' => 7 ] ],
			'encodings with a non-string'    => [ [ 'form_params' => $encoded, 'form_enc' => [ 'b64', [ 'json' ] ] ] ],
			'form as a number'               => [ [ 'form_params' => 12345 ] ],
			'form as true'                   => [ [ 'form_params' => true ] ],
			'form as a float'                => [ [ 'form_params' => 1.5 ] ],
			'form that is not base64'        => [ [ 'form_params' => '%%%', 'form_enc' => [ 'b64', 'json' ] ] ],
			'json that is not an object'     => [ [ 'form_params' => \base64_encode( '"text"' ), 'form_enc' => [ 'b64', 'json' ] ] ],
			'json that does not parse'       => [ [ 'form_params' => \base64_encode( '{not json' ), 'form_enc' => [ 'b64', 'json' ] ] ],
		];
	}
}
