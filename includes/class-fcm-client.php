<?php
/**
 * FCM HTTP v1 client — zero external dependencies.
 *
 * Flow:
 *   1. Read service account JSON from configured file.
 *   2. Sign JWT (RS256) with private_key.
 *   3. Exchange for access_token at https://oauth2.googleapis.com/token
 *      (55-min cache in transient to avoid a round-trip on every send).
 *   4. POST to https://fcm.googleapis.com/v1/projects/{project_id}/messages:send.
 *
 * All via wp_remote_post + openssl_sign — no Guzzle, no kreait/firebase-php.
 */

defined( 'ABSPATH' ) || exit;

class FCM_Push_Client {

	const TRANSIENT_TOKEN = 'fcm_push_access_token';
	const TOKEN_URL       = 'https://oauth2.googleapis.com/token';
	const FCM_SCOPE       = 'https://www.googleapis.com/auth/firebase.messaging';

	/**
	 * Send a notification to an FCM topic.
	 *
	 * @param string $topic  Topic name (without /topics/ prefix).
	 * @param string $title  Title (already sanitised).
	 * @param string $body   Body (already sanitised).
	 * @param array  $data   Extra data payload (FCM requires all values to be strings).
	 *
	 * @return array{ok:bool, http:int, body:string|array, error?:string}
	 */
	public function send_to_topic( string $topic, string $title, string $body, array $data = [] ): array {
		$creds = $this->load_credentials();
		if ( is_wp_error( $creds ) ) {
			return [ 'ok' => false, 'http' => 0, 'body' => '', 'error' => $creds->get_error_message() ];
		}

		$token = $this->get_access_token( $creds );
		if ( is_wp_error( $token ) ) {
			return [ 'ok' => false, 'http' => 0, 'body' => '', 'error' => $token->get_error_message() ];
		}

		$project_id = $creds['project_id'];
		$endpoint   = "https://fcm.googleapis.com/v1/projects/{$project_id}/messages:send";

		$data_stringified = [];
		foreach ( $data as $k => $v ) {
			$data_stringified[ (string) $k ] = is_scalar( $v ) ? (string) $v : wp_json_encode( $v );
		}

		$message = [
			'message' => [
				'topic'        => $topic,
				'notification' => [
					'title' => $title,
					'body'  => $body,
				],
				'data'         => $data_stringified,
				'android'      => [
					'priority'     => 'high',
					'notification' => [
						'channel_id' => 'fcm-push-notify',
					],
				],
				'apns'         => [
					'payload' => [
						'aps' => [
							'sound' => 'default',
						],
					],
				],
			],
		];

		$response = wp_remote_post(
			$endpoint,
			[
				'timeout' => 15,
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json; charset=utf-8',
				],
				'body'    => wp_json_encode( $message ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return [ 'ok' => false, 'http' => 0, 'body' => '', 'error' => $response->get_error_message() ];
		}

		$http = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$json = json_decode( $raw, true );

		return [
			'ok'   => $http >= 200 && $http < 300,
			'http' => $http,
			'body' => is_array( $json ) ? $json : $raw,
		];
	}

	/**
	 * Load service account JSON. Validates against path traversal and JSON shape.
	 *
	 * @return array{project_id:string,client_email:string,private_key:string}|WP_Error
	 */
	private function load_credentials() {
		$settings = FCM_Push::get_settings();
		$path     = (string) $settings['service_account_path'];

		if ( '' === $path ) {
			return new WP_Error( 'no_credentials', 'Service account JSON not configured.' );
		}

		$real = realpath( $path );
		if ( false === $real ) {
			return new WP_Error( 'file_missing', 'Credentials file not found: ' . $path );
		}

		$content_dir = realpath( WP_CONTENT_DIR );
		if ( false === $content_dir || 0 !== strpos( $real, $content_dir ) ) {
			return new WP_Error( 'path_traversal', 'Credentials file is outside wp-content (rejected).' );
		}

		$raw = file_get_contents( $real );
		if ( false === $raw ) {
			return new WP_Error( 'unreadable', 'Could not read credentials file.' );
		}

		$json = json_decode( $raw, true );
		if ( ! is_array( $json )
			|| empty( $json['type'] )
			|| 'service_account' !== $json['type']
			|| empty( $json['project_id'] )
			|| empty( $json['client_email'] )
			|| empty( $json['private_key'] )
		) {
			return new WP_Error( 'invalid_credentials', 'Invalid JSON — not a service account.' );
		}

		return [
			'project_id'   => (string) $json['project_id'],
			'client_email' => (string) $json['client_email'],
			'private_key'  => (string) $json['private_key'],
		];
	}

	/**
	 * Get OAuth2 access token. Cached for 55 minutes in a transient.
	 *
	 * @param array{project_id:string,client_email:string,private_key:string} $creds
	 * @return string|WP_Error
	 */
	private function get_access_token( array $creds ) {
		$cached = get_transient( self::TRANSIENT_TOKEN );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$jwt = $this->build_jwt( $creds );
		if ( is_wp_error( $jwt ) ) {
			return $jwt;
		}

		$response = wp_remote_post(
			self::TOKEN_URL,
			[
				'timeout' => 15,
				'headers' => [ 'Content-Type' => 'application/x-www-form-urlencoded' ],
				'body'    => [
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $jwt,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			$err = $body['error_description'] ?? $body['error'] ?? 'Unexpected OAuth response.';
			return new WP_Error( 'oauth_failed', 'OAuth failed: ' . $err );
		}

		$token = (string) $body['access_token'];
		set_transient( self::TRANSIENT_TOKEN, $token, 55 * MINUTE_IN_SECONDS );

		return $token;
	}

	/**
	 * Build an RS256-signed JWT from the service account private key.
	 *
	 * @return string|WP_Error
	 */
	private function build_jwt( array $creds ) {
		$now = time();

		$header  = [ 'alg' => 'RS256', 'typ' => 'JWT' ];
		$payload = [
			'iss'   => $creds['client_email'],
			'scope' => self::FCM_SCOPE,
			'aud'   => self::TOKEN_URL,
			'iat'   => $now,
			'exp'   => $now + 3600,
		];

		$segments = [
			$this->base64url_encode( wp_json_encode( $header ) ),
			$this->base64url_encode( wp_json_encode( $payload ) ),
		];

		$signing_input = implode( '.', $segments );

		$signature = '';
		$key       = openssl_pkey_get_private( $creds['private_key'] );
		if ( false === $key ) {
			return new WP_Error( 'bad_key', 'Could not read private key from service account.' );
		}

		$ok = openssl_sign( $signing_input, $signature, $key, OPENSSL_ALGO_SHA256 );
		if ( PHP_VERSION_ID < 80000 && is_resource( $key ) ) {
			openssl_free_key( $key );
		}

		if ( ! $ok ) {
			return new WP_Error( 'sign_failed', 'JWT signing failed: ' . openssl_error_string() );
		}

		$segments[] = $this->base64url_encode( $signature );
		return implode( '.', $segments );
	}

	private function base64url_encode( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}
}
