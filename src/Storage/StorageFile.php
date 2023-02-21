<?php
/**
 * A PHP  class  client library to interact with Supabase Storage.
 *
 * Provides functions for handling storage buckets Files.
 *
 * @author    Zero Copy Labs
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */

namespace Supabase\Storage;

use Supabase\Util\Constants;
use Supabase\Util\Request;
use Dotenv\Dotenv;

class StorageFile
{
	/**
	 * A RESTful endpoint for querying and managing your database.
	 *
	 * @var string
	 */
	protected string $url;

	/**
	 * A header Bearer Token generated by the server in response to a login request
	 * [service key, not anon key].
	 *
	 * @var array
	 */
	protected array $headers = [];

	/**
	 * The bucket id to operate on.
	 *
	 * @var string
	 */
	protected string $bucketId;

	protected array $DEFAULT_SEARCH_OPTIONS = [
		'limit' => 100,
		'offset' => 0,
		'sortBy' => [
			'column' => 'name',
			'order' => 'asc',
		],
	];

	protected $DEFAULT_FILE_OPTIONS = [
		'cacheControl' => 3600,
		'upsert' => false,
		'contentType' => 'text/plain;charset=UTF-8',
	];

	/**
     * StorageFile constructor.
     *
     * @throws Exception
     */

	public function __construct($bucketId)
	{
		$dotenv = Dotenv::createUnsafeImmutable('../../');
		$dotenv->load();
		$api_key = getenv('API_KEY');
		$reference_id = getenv('REFERENCE_ID');
		$headers = ['Authorization' => "Bearer {$api_key}"];
		$this->url = "https://{$reference_id}.supabase.co/storage/v1";
		$this->headers = array_merge(Constants::getDefaultHeaders(), $headers);
		$this->bucketId = $bucketId;
	}

	/**
	 * Lists all the files within a bucket.
	 *
	 * @param $path The folder path.
	 * @return string Returns stdClass Object from request
	 */
	public function list($path)
	{
		$headers = $this->headers;
		$headers['content-type'] = 'application/json';
		try {
			$body = [
				'prefix' => $path,
			];

			$data = Request::request('POST', $this->url.'/object/list/'.$this->bucketId, $headers, json_encode($body));

			return $data;
		} catch (\Exception $e) {
			return $e;
		}
	}

	/**
	 * Uploads a file to an object storage bucket creating or replacing the file if it already exists.
	 *
	 * @param  string  $method  The HTTP method to use for the request.
	 * @param  string  $path  The path to the file in the bucket.
	 * @param  string  $file  The body of the file to be stored in the bucket.
	 * @param  array  $options  The options for the upload.
	 * @return string Returns stdClass Object from request
	 */
	public function uploadOrUpdate($method, $path, $file, $opts)
	{
		try {
			$options = array_merge($this->DEFAULT_FILE_OPTIONS, $opts);
			$headers = $this->headers;

			if ($method == 'POST') {
				$headers['x-upsert'] = $options['upsert'] ? 'true' : 'false';
			}

			$body = file_get_contents($file);

			$storagePath = $this->_storagePath($path);

			$data = Request::request($method, $this->url.'/object/'.$storagePath, $headers, $body);

			return $data;
		} catch (\Exception $e) {
			return $e;
		}
	}

	/**
	 * Uploads a file to an existing bucket.
	 *
	 * @param  string  $path  path The file path, including the file name. Should be of
	 *                        the format `folder/subfolder/filename.png`. The bucket must already exist before
	 *                        attempting to upload.
	 * @param  string  $file  The body of the file to be stored in the bucket.
	 * @param  array  $options  The options for the upload.
	 * @return string Returns stdClass Object from request
	 */
	public function upload($path, $file, $opts)
	{
		return $this->uploadOrUpdate('POST', $path, $file, $opts);
	}

	/**
	 * Replaces an existing file at the specified path with a new one.
	 *
	 * @param  string  $path  The relative file path. Should be of the
	 *                        format `folder/subfolder/filename.png`. The bucket must already exist before attempting to update.
	 * @param  string  $file  The body of the file to be stored in the bucket.
	 * @param  array  $options  The options for the update.
	 * @return string Returns stdClass Object from request
	 */
	public function update($path, $file, $opts)
	{
		return $this->uploadOrUpdate('PUT', $path, $file, $opts);
	}

	/**
	 * Moves an existing file to a new path in the same bucket.
	 *
	 * @param  string  $fromPath  The original file path, including the current file
	 *                            name. For example `folder/image.png`.
	 * @param  string  $toPath  The new file path, including the new file name.
	 *                          For example `folder/image-new.png`.
	 * @return string Returns stdClass Object from request
	 */
	public function move($fromPath, $toPath)
	{
		$headers = $this->headers;
		$headers['content-type'] = 'application/json';
		try {
			$body = [
				'bucketId' => $this->bucketId,
				'sourceKey' => $fromPath,
				'destinationKey' => $toPath,
			];

			$data = Request::request('POST', $this->url.'/object/move', $headers, json_encode($body));

			return $data;
		} catch (\Exception $e) {
			return   $e;
		}
	}

	/**
	 * Copies an existing file to a new path in the same bucket.
	 *
	 * @param  string  $fromPath  The original file path, including the current
	 *                            file name. For example `folder/image.png`.
	 * @param  string  $toPath  The new file path, including the new file name.
	 *                          For example `folder/image-copy.png`.
	 * @return string Returns stdClass Object from request
	 */
	public function copy($fromPath, $toPath)
	{
		$headers = $this->headers;
		$headers['content-type'] = 'application/json';
		try {
			$body = [
				'bucketId' => $this->bucketId,
				'sourceKey' => $fromPath,
				'destinationKey' => $toPath,
			];

			$data = Request::request('POST', $this->url.'/object/copy', $headers, json_encode($body));

			return $data;
		} catch (\Exception $e) {
			return $e;
		}
	}

	/**
	 * Creates a signed URL. Use a signed URL to share a file for a fixed amount of time.
	 *
	 * @param  string  $path  The file path, including the current file name. For example `folder/image.png`.
	 * @param  int  $expiresIn  The number of seconds until the signed URL expires. For example,
	 *                          `60` for a URL which is valid for one minute
	 * @param  array  $opts['download']  Triggers the file as a download if set to true. Set
	 *                                   this parameter as the name of the file if you want to trigger the download with a different filename.
	 * @param  array  $opts['transform  ']  Transform the asset before serving it to the client.
	 * @return string Returns stdClass Object from request
	 */
	public function createSignedUrl($path, $expires, $opts)
	{
		$headers = $this->headers;
		$headers['content-type'] = 'application/json';

		try {
			$body = [
				'expiresIn' => $expires,
			];
			$storagePath = $this->_storagePath($path);
			$fullUrl = $this->url.'/object/sign/'.$storagePath;
			$response = Request::request('POST', $fullUrl, $headers, json_encode($body));
			$downloadQueryParam = isset($opts['download']) ? '?download=true' : '';
			$data = urlencode($this->url.$response->signedURL.$downloadQueryParam);

			return $data;
		} catch (\Exception $e) {
			return $e;
		}
	}

	/**
	 * Creates a signed URL. Use a signed URL to share a file for a fixed amount of time.
	 *
	 * @param  string  $paths  The file path, including the current file name. For example `folder/image.png`.
	 * @param  int  $expiresIn  The number of seconds until the signed URL expires.
	 *                          For example, `60` for a URL which is valid for one minute.
	 * @param  array  $opts['download']  Triggers the file as a download if set to true. Set
	 *                                   this parameter as the name of the file if you want to trigger the download with a different filename.
	 * @param  array  $opts['transform  ']  Transform the asset before serving it to the client.
	 * @return string Returns stdClass Object from request
	 */
	public function createSignedUrls($paths, $expiresIn, $opts)
	{
		try {
			$body = [
				'paths'=> $paths,
				'expires_in'=> $expiresIn,
			];
			$fullUrl = $this->url.'/object/sign'.$this->bucketId;
			$response = Request::request('POST', $fullUrl, $this->headers, $opts, $body);
			$downloadQueryParam = $opts['download'] ? '?download=true' : '';
			$data = array_map(function ($d) use ($downloadQueryParam) {
				$d['signed_url'] = urlencode($this->url.$d->signed_url.$downloadQueryParam);

				return $d;
			}, $response);

			return $data;
		} catch (\Exception $e) {
			return $e;
		}
	}

	/**
	 * Downloads a file from a private bucket. For public buckets, make
	 * a request to the URL returned from `getPublicUrl` instead.
	 *
	 * @param  string  $path  The full path and file name of the file to be downloaded.
	 *                        For example `folder/image.png`.
	 * @param  array  $options['transform']  Transform the asset before serving it to the client.
	 * @return string Returns stdClass Object from request
	 */
	public function download($path, $options)
	{
		$headers = $this->headers;
		$url = $this->url.'/object/'.$this->bucketId.'/'.$path;
		$headers['stream'] = true;

		try {
			$data = Request::request_file($url, $headers);

			return $data;
		} catch (\Exception $e) {
			return $e;
		}
	}

	/**
	 * A simple convenience function to get the URL for an asset in a
	 * public bucket. If you do not want to use this function, you can
	 * construct the public URL by concatenating the bucket URL with
	 * the path to the asset.
	 * This function does not verify if the bucket is public.
	 * If a public URL is created for a bucket which is not public,
	 * you will not be able to download the asset.
	 *
	 * @param  string  $path  The path and name of the file to generate
	 *                        the public URL for. For example `folder/image.png`.
	 * @param  array  $options['download']  Triggers the file as a download
	 *                                      if set to true. Set this parameter as the name of the file if you want
	 *                                      to trigger the download with a different filename.
	 * @param  array  $options['transform']  Transform the asset before serving
	 *                                       it to the client.
	 * @return string Returns the public url generated
	 */
	public function getPublicUrl($path, $opts)
	{
		$storagePath = $this->_storagePath($path);
		$downloadQueryParam = isset($opts['download']) ? '?download=true' : '';

		$data = urlencode($this->url.'/object/public/'.$storagePath.$downloadQueryParam);

		return $data;
	}

	/**
	 * Deletes files within the same bucket.
	 *
	 * @param  string  $path  An array of files to delete,
	 *                        including the path and file name. For example [`'folder/image.png'`].
	 * @return string Returns stdClass Object from request
	 */
	public function remove($paths)
	{
		$headers = $this->headers;
		$headers['content-type'] = 'application/json';
		try {
			$options = ['prefixes' => $paths];
			$fullUrl = $this->url.'/object/'.$this->bucketId;
			$data = Request::request('DELETE', $fullUrl, $headers, json_encode($options));

			return $data;
		} catch (\Exception $e) {
			return $e;
		}
	}

	/**
	 * Cleans up the path to the file in the bucket.
	 *
	 * @param  string  $path  The path to the file in the bucket.
	 * @return string Returns the path to the file cleaned
	 */
	private function _storagePath($path)
	{
		$p = preg_replace('/^\/|\/$/', '', $path);
		$p = preg_replace('/\/+/', '/', $p);

		return $this->bucketId.'/'.$p;
	}
}
