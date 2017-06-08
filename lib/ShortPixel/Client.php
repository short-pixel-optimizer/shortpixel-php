<?php

namespace ShortPixel;


class Client {

	private $config;
	private $options;
	private $client;

	public static function API_URL() {
		return "https://api.shortpixel.com/v2/";
	}

	public static function API_ENDPOINT() {
		return "reducer.php";
	}

	public static function API_UPLOAD_ENDPOINT() {
		//return self::API_URL() . "/v2/post-reducer-dev.php";
		return "post-reducer.php";
	}

	public static function userAgent() {
		$user_agent = \GuzzleHttp\default_user_agent();

		return $user_agent . " ShortPixel/" . VERSION;
	}

	private static function caBundle() {
		return dirname( __DIR__ ) . "/data/shortpixel.crt";
	}

	function __construct() {
		$this->config = [
			'base_uri' => Client::API_URL(),
			'timeout'  => 60,
			'headers'  => [
				'User-Agent' => Client::userAgent()
			]
		];
		$this->client = new \GuzzleHttp\Client( $this->config );
	}

	/**
	 * Does the CURL request to the ShortPixel API
	 *
	 * @param       $method 'post' or 'get'
	 * @param null  $body   - the POST fields
	 * @param array $header - HTTP headers
	 *
	 * @return array - metadata from the API
	 * @throws ConnectionException
	 */
	function request( $method, $body = null, $header = [] ) {
		foreach ( $body as $key => $val ) {
			if ( $val === null ) {
				unset( $body[ $key ] );
			}
		}

		$retUrls  = [ "body" => [], "headers" => [], "fileMappings" => [] ];
		$retPend  = [ "body" => [], "headers" => [], "fileMappings" => [] ];
		$retFiles = [ "body" => [], "headers" => [], "fileMappings" => [] ];

		if ( isset( $body["urllist"] ) ) {
			$retUrls = $this->requestInternal( $method, $body, $header );
		}
		if ( isset( $body["pendingURLs"] ) ) {
			unset( $body["urllist"] );
			//some files might have already been processed as relaunches in the given max time
			foreach ( $retUrls["body"] as $url ) {
				//first remove it from the files list as the file was uploaded properly
				if ( $url->Status->Code != - 102 && $url->Status->Code != - 106 ) {
					$notExpired[] = $url;
					if ( ! isset( $body["pendingURLs"][ $url->OriginalURL ] ) ) {
						$lala = "cucu";
					} else {
						$unsetPath = $body["pendingURLs"][ $url->OriginalURL ];
					}
					if ( isset( $body["files"] ) && ( $key = array_search( $unsetPath, $body["files"] ) ) !== false ) {
						unset( $body["files"][ $key ] );
					}
				}
				//now from the pendingURLs if we already have an answer with urllist
				if ( isset( $body["pendingURLs"][ $url->OriginalURL ] ) ) {
					$retUrls["fileMappings"][ $url->OriginalURL ] = $body["pendingURLs"][ $url->OriginalURL ];
					unset( $body["pendingURLs"][ $url->OriginalURL ] );
				}
			}
			if ( count( $body["pendingURLs"] ) ) {
				$retPend = $this->requestInternal( $method, $body, $header );
				if ( isset( $body["files"] ) ) {
					$notExpired = [];
					foreach ( $retPend['body'] as $detail ) {
						if ( $detail->Status->Code != - 102 ) { // -102 is expired, means we need to resend the image through post
							$notExpired[] = $detail;
							$unsetPath    = $body["pendingURLs"][ $detail->OriginalURL ];
							if ( ( $key = array_search( $unsetPath, $body["files"] ) ) !== false ) {
								unset( $body["files"][ $key ] );
							}
						}
					}
					$retPend['body'] = $notExpired;
				}
			}
		}
		if ( isset( $body["files"] ) && count( $body["files"] ) ) {
			unset( $body["pendingURLs"] );
			$retFiles = $this->requestInternal( $method, $body, $header );
		}

		$body = isset( $retUrls["body"]->Status )
			? $retUrls["body"]
			: ( isset( $retPend["body"]->Status )
				? $retPend["body"]
				: ( isset( $retFiles["body"]->Status )
					? $retFiles["body"] :
					array_merge( $retUrls["body"], $retPend["body"], $retFiles["body"] ) ) );

		return (object) [
			"body"         => $body,
			"headers"      => array_unique( array_merge( $retUrls["headers"], $retPend["headers"], $retFiles["headers"] ) ),
			"fileMappings" => array_merge( $retUrls["fileMappings"], $retPend["fileMappings"], $retFiles["fileMappings"] )
		];
	}

	function requestInternal( $method, $body = null, $header = [] ) {
		$files = $urls = false;

		if ( isset( $body["urllist"] ) ) { //images are sent as a list of URLs
			$this->prepareJSONRequest( $body, $header );
			$uri = Client::API_ENDPOINT();
		} elseif ( isset( $body["pendingURLs"] ) ) {
			//prepare the pending items request
			$urls      = [];
			$fileCount = 1;
			foreach ( $body["pendingURLs"] as $url => $path ) {
				$urls[ "url" . $fileCount ] = $url;
				$fileCount ++;
			}
			$pendingURLs = $body["pendingURLs"];
			unset( $body["pendingURLs"] );
			$body["file_urls"] = $urls;
			$this->prepareJSONRequest( $body, $header );
			$uri = Client::API_UPLOAD_ENDPOINT();
		} elseif ( isset( $body["files"] ) ) {
			$this->prepareMultiPartRequest( $body, $header );
			$uri = Client::API_UPLOAD_ENDPOINT();
		} else {
			return [ "body" => [], "headers" => [], "fileMappings" => [] ];
		}


		$response = $this->client->request( $method, $uri, $this->options );
		//spdbgd(rawurldecode($body['urllist'][1]), "body");


		$status  = $response->getStatusCode();
		$headers = $response->getHeaders();
		$body    = $response->getBody();

		$details = json_decode( $body );

		if ( getenv( "SHORTPIXEL_DEBUG" ) ) {
			$info = '';
			if ( is_array( $details ) ) {
				foreach ( $details as $det ) {
					$info .= $det->Status->Code . " " . $det->OriginalURL . ( isset( $det->localPath ) ? "({$det->localPath})" : "" ) . "\n";
				}
			} else {
				$info = $response;
			}
			@file_put_contents( dirname( __DIR__ ) . '/splog.txt', "\nURL Statuses: \n" . $info . "\n", FILE_APPEND );
		}
		if ( ! $details ) {
			$message = sprintf(
				"Error while parsing response: %s (#%d)",
				PHP_VERSION_ID >= 50500 ? json_last_error_msg() : "Error",
				json_last_error() );
			$details = (object) [
				"message" => $message,
				"error"   => "ParseError"
			];
		}

		$fileMappings = [];
		if ( $files ) {
			$fileMappings = [];
			foreach ( $details as $detail ) {
				if ( isset( $detail->Key ) && isset( $files[ $detail->Key ] ) ) {
					$fileMappings[ $detail->OriginalURL ] = $files[ $detail->Key ];
				}
			}
		} elseif ( $urls ) {
			$fileMappings = $pendingURLs;
		}

		if ( getenv( "SHORTPIXEL_DEBUG" ) ) {
			$info = '';
			foreach ( $fileMappings as $key => $val ) {
				$info .= "$key -> $val\n";
			}
			@file_put_contents( dirname( __DIR__ ) . '/splog.txt', "\nFile mappings: \n" . $info . "\n", FILE_APPEND );
		}

		if ( $status >= 200 && $status <= 299 ) {
			return [ "body" => $details, "headers" => $headers, "fileMappings" => $fileMappings ];
		}

		throw Exception::create( $details->message, $details->error, $status );
	}

	protected function prepareJSONRequest( $body, $header ) {
		//to escape the + from "+webp"
		if ( $body["convertto"] ) {
			$body["convertto"] = urlencode( $body["convertto"] );
		}
		if ( $body["urllist"] ) {
			$body["urllist"] = array_map( 'rawurlencode', $body["urllist"] );
		}
		$this->options = [
			'json'    => $body,
			'headers' => array_merge( $this->client->getConfig( 'headers' ), $header )
		];
	}

	protected function prepareMultiPartRequest( $body, $header ) {
		$files     = [];
		$fileCount = 1;
		foreach ( $body["files"] as $filePath ) {
			$files[ "file" . $fileCount ] = $filePath;
			$fileCount ++;
		}
		unset( $body["files"] );
		$body["file_paths"] = json_encode( $files );
		$this->options      = [
			'headers'   => array_merge( $this->client->getConfig( 'headers' ), $header ),
			'multipart' => []
		];
		foreach ( $body as $name => $content ) {
			$this->options['multipart'] = [
				'name'     => $name,
				'contents' => $content
			];
		}
		foreach ( $files as $name => $path ) {
			switch ( true ) {
				case false === $path = realpath( filter_var( $path ) ):
				case ! is_file( $path ):
				case ! is_readable( $path ):
					continue; // or return false, throw new InvalidArgumentException
			}
			$this->options['multipart'] = [
				'name'     => $name,
				'contents' => fopen( $path, 'r' ),
				'filename' => basename( $path )
			];
		}

	}

	function download( $sourceURL, $target ) {
		try {
			$this->client->request( 'GET', $sourceURL, [ 'save_to' => $target ] );
		} catch ( Exception $e ) {
			// Log the error or something
			return false;
		}

		return true;
	}
}
