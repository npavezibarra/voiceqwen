<?php
namespace VoiceQwen\Audiobook;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Client for interacting with Cloudflare R2.
 */
class R2Client
{
    /**
     * @var S3Client|null
     */
    private $s3_client = null;

    /**
     * @var string
     */
    private $bucket;

    /**
     * Constructor. Initializes the S3 client using R2 settings.
     */
    public function __construct()
    {
        $account_id = get_option('voiceqwen_r2_account_id');
        $access_key = get_option('voiceqwen_r2_access_key');
        $secret_key = get_option('voiceqwen_r2_secret_key');
        $this->bucket = get_option('voiceqwen_r2_bucket_name');

        if ($account_id && $access_key && $secret_key && class_exists('Aws\S3\S3Client')) {
            $this->s3_client = new S3Client([
                'region'      => 'auto',
                'endpoint'    => "https://$account_id.r2.cloudflarestorage.com",
                'version'     => 'latest',
                'credentials' => [
                    'key'    => $access_key,
                    'secret' => $secret_key,
                ],
            ]);
        }
    }

    /**
     * Generate a presigned URL for an object.
     *
     * @param string $key The R2 object key.
     * @param string $expires Expiration time (e.g. '+2 hours').
     * @return string|false The URL or false on failure.
     */
    public function get_presigned_url(string $key, $expires = '+2 hours')
    {
        if (!$this->s3_client || !$this->bucket) {
            return false;
        }

        try {
            $cmd = $this->s3_client->getCommand('GetObject', [
                'Bucket' => $this->bucket,
                'Key'    => $key,
            ]);

            $request = $this->s3_client->createPresignedRequest($cmd, $expires);
            return (string) $request->getUri();
        } catch (AwsException $e) {
            error_log('VoiceQwen R2 Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Upload an object to R2.
     *
     * @param string $tmp_path Path to the temporary file.
     * @param string $key The destination filename in R2.
     * @param string $mime_type The MIME type of the file.
     * @return bool True on success, false on failure.
     */
    public function upload_object(string $tmp_path, string $key, string $mime_type = 'audio/wav')
    {
        if (!$this->s3_client || !$this->bucket) {
            return false;
        }

        try {
            $this->s3_client->putObject([
                'Bucket'      => $this->bucket,
                'Key'         => $key,
                'SourceFile'  => $tmp_path,
                'ContentType' => $mime_type,
            ]);
            return true;
        } catch (AwsException $e) {
            $error_data = [
                'message' => $e->getMessage(),
                'code' => $e->getAwsErrorCode(),
                'type' => $e->getAwsErrorType(),
                'response' => $e->getCommand()->getName()
            ];
            error_log('VoiceQwen R2 Upload Details: ' . print_r($error_data, true));
            return false;
        }
    }

    /**
     * List all objects in the bucket.
     *
     * @return array List of filenames.
     */
    public function list_objects()
    {
        if (!$this->s3_client || !$this->bucket) {
            return [];
        }

        try {
            $results = $this->s3_client->listObjectsV2([
                'Bucket' => $this->bucket,
            ]);

            $filenames = [];
            if (isset($results['Contents'])) {
                foreach ($results['Contents'] as $object) {
                    $filenames[] = $object['Key'];
                }
            }
            return $filenames;
        } catch (AwsException $e) {
            error_log('VoiceQwen R2 List Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Delete an object from R2.
     *
     * @param string $key The object key to delete.
     * @return bool True on success, false on failure.
     */
    public function delete_object(string $key)
    {
        if (!$this->s3_client || !$this->bucket) {
            return false;
        }

        try {
            $this->s3_client->deleteObject([
                'Bucket' => $this->bucket,
                'Key'    => $key,
            ]);
            return true;
        } catch (AwsException $e) {
            error_log('VoiceQwen R2 Delete Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Test the connection to R2.
     *
     * @return bool|string True on success, error message on failure.
     */
    public function test_connection()
    {
        if (!$this->s3_client) {
            return 'AWS SDK not loaded or missing credentials.';
        }

        if (!$this->bucket) {
            return 'Bucket name is missing.';
        }

        try {
            // Try to list objects (minified request)
            $this->s3_client->listObjectsV2([
                'Bucket'  => $this->bucket,
                'MaxKeys' => 1,
            ]);
            return true;
        } catch (AwsException $e) {
            return $e->getAwsErrorMessage() ?: $e->getMessage();
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
}
