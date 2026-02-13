<?php
declare(strict_types=1);

namespace rosasurfer\rt\console;

use JsonException;
use RuntimeException;
use stdClass;
use Throwable;

use rosasurfer\ministruts\console\Command;
use rosasurfer\ministruts\console\io\Input;
use rosasurfer\ministruts\console\io\Output;
use rosasurfer\ministruts\core\proxy\Config;
use rosasurfer\ministruts\file\FileSystem;
use rosasurfer\ministruts\log\Logger;

use GuzzleHttp\Client as HttpClient;

use function rosasurfer\ministruts\isRelativePath;
use function rosasurfer\ministruts\json_decode_or_throw;
use function rosasurfer\ministruts\realpath;
use function rosasurfer\ministruts\stdout;
use function rosasurfer\ministruts\strRight;
use function rosasurfer\ministruts\strRightFrom;
use function rosasurfer\ministruts\strStartsWith;
use function rosasurfer\ministruts\toString;

use const rosasurfer\ministruts\L_ERROR;
use const rosasurfer\ministruts\L_NOTICE;
use const rosasurfer\ministruts\NL;

/**
 * UpdateMql4BuildsCommand
 *
 * The command processes all existing build notifications from the GitHub CI pipeline and updates locally stored builds.
 */
class UpdateMql4BuildsCommand extends Command
{
    /** @var string */
    const DOCOPT = <<<DOCOPT
    Process new GitHub build notifications and update locally stored builds.
    
    Usage:
      {:cmd:}  [options]
    
    Options:
       -h, --help  This help screen.
    
    DOCOPT;

    /**
     * {@inheritDoc}
     */
    protected function execute(Input $input, Output $output): int
    {
        $processed = [];
        try {
            // process new notifications
            while ($notifications = $this->updateNotifications($processed)) {
                $processed = [];
                foreach ($notifications as $notification) {
                    [$repository, $artifactId] = explode(';', $notification) + ['', ''];    // format: "{repository};{artifact-id}"

                    if (!$response  = $this->queryGithubApi($repository, $artifactId)) return 1;
                    $output->out(toString($response));

                    if (!$artifact  = $this->parseGithubResponse($response))           return 1;
                    if (!$tmpPath   = $this->downloadBuildArtifact($artifact))         return 1;
                    if (!$storePath = $this->storeBuildArtifact($artifact, $tmpPath))  return 1;
                    $output->out("stored at: $storePath");

                    $processed[] = $notification;
                }
                break;
            }
        }
        finally {
            if ($processed) {
                $this->updateNotifications($processed);
            }
        }
        return 0;
    }

    /**
     * Read/update existing build notifications.
     *
     * @param  string[] $processed [optional] - processed notifications to be deleted (default: none)
     *
     * @return string[] - new notifications
     */
    protected function updateNotifications(array $processed = []): array
    {
        $filename = Config::getString('github.build-notifications');
        if (isRelativePath($filename)) {
            $rootDir = Config::getString('app.dir.root');
            $filename = "$rootDir/$filename";
        }

        $lines = [];
        if (is_file($filename)) {
            $hFile = fopen($filename, 'c+b');
            flock($hFile, LOCK_EX);                             // exclusive read/write

            if (filesize($filename) > 0) {
                while (($line = fgets($hFile)) !== false) {     // read existing content
                    $line = trim($line);
                    if ($line == '') continue;
                    $lines[] = $line;
                }
                if ($processed) {
                    $diff = array_diff($lines, $processed);
                    if (sizeof($diff) < sizeof($lines)) {
                        $data = $diff ? join(NL, $diff).NL : '';
                        ftruncate($hFile, 0);                   // write updated content
                        rewind($hFile);
                        fwrite($hFile, $data);
                        $lines = $diff;
                    }
                }
            }
            fclose($hFile);
        }
        return $lines;
    }

    /**
     * Query artifact meta data from the Github API.
     *
     * @param  string $repository
     * @param  string $artifactId
     *
     * @return ?string - the raw GitHub response or NULL on error
     */
    protected function queryGithubApi(string $repository, string $artifactId): ?string
    {
        $githubToken = Config::getString('github.api-token');

        try {
            $response = (new HttpClient())->request('GET', "https://api.github.com/repos/$repository/actions/artifacts/$artifactId", [
                'connect_timeout' => 10,
                'headers'         => [
                    'Accept'        => 'application/json',
                    'Authorization' => "Bearer $githubToken",
                ],
            ]);
            $status = $response->getStatusCode();
            if ($status != 200) {
                throw new RuntimeException("error querying GitHub API: HTTP status $status");
            }
            return $response->getBody()->getContents();
        }
        catch (Throwable $ex) {
            Logger::log('error querying GitHub API: '.get_class($ex), L_ERROR, ['exception' => $ex]);
        }
        return null;
    }

    /**
     * Parse the response of a GitHub API query.
     *
     * @param  string $response - received response
     *
     * @return ?stdClass - artifact meta data or NULL on error
     */
    protected function parseGithubResponse(string $response): ?stdClass
    {
        try {
            $data = json_decode_or_throw($response);
        }
        catch (JsonException $ex) {
            Logger::log('error parsing GitHub response: '.get_class($ex), L_ERROR, ['exception' => $ex]);
            return null;
        }

        $artifact = new stdClass();
        $rules = [
          'name'                 => ['name',   fn($v) => is_string($v) && $v != ''],
          'size_in_bytes'        => ['size',   fn($v) => is_int($v) && $v > 0 ],
          'digest'               => ['digest', fn($v) => is_string($v) && strStartsWith($v, 'sha256:') && strlen($v) == 71],
          'archive_download_url' => ['url',    fn($v) => is_string($v) && strRightFrom($v, '/', -1) != '' && filter_var($v, FILTER_VALIDATE_URL)],
        ];
        foreach ($rules as $field => [$property, $validator]) {
            if (!$validator($value = $data->$field ?? null)) {
                Logger::log("unexpected JSON response from GitHub: missing or invalid field \"$field\"".NL.toString($data), L_ERROR);
                return null;
            }
            $artifact->$property = $value;
        }
        $artifact->name .= '.'.strRightFrom($artifact->url, '/', -1);   // prepend file extension
        $artifact->digest = strRight($artifact->digest, -7);            // cut-off algo identifier

        $branch = $data->workflow_run->head_branch ?? null;
        if (!is_string($branch)) {
            Logger::log('unexpected JSON response from GitHub: missing or invalid field "workflow_run/head_branch"'.NL.toString($data), L_ERROR);
            return null;
        }
        $artifact->branch = $branch;

        return $artifact;
    }

    /**
     * Download and validate a GitHub artifact. If the artifact already exists locally it is re-validated and the download skipped.
     *
     * @param  stdClass $artifact - artifact meta data
     *
     * @return ?string - temporary or final path of the local file; or NULL on error
     */
    protected function downloadBuildArtifact(stdClass $artifact): ?string
    {
        // check local file in storage path
        $storagePath = $this->resolveStoragePath($artifact);
        if (is_file($storagePath)) {
            if (!$error = $this->verifyArtifactFile($artifact, $storagePath)) {
                $this->output->out('stored file exists');
                return $storagePath;
            }
            $this->output->out(sprintf($error, 'stored '));
            unlink($storagePath);
        }

        // check local file in tmp. download path
        $tmpPath = $this->resolveDownloadPath($artifact);
        if (is_file($tmpPath)) {
            if (!$error = $this->verifyArtifactFile($artifact, $tmpPath)) {
                $this->output->out('downloaded file exists');
                return $tmpPath;
            }
            Logger::log(sprintf($error, 'temporary '), L_NOTICE);
            unlink($tmpPath);
        }
        else {
            FileSystem::mkDir(dirname($tmpPath));
        }

        // download the file
        $githubToken = Config::getString('github.api-token');
        try {
            $response = (new HttpClient())->request('GET', $artifact->url, [
                'connect_timeout' => 10,
                'headers' => [
                    'Authorization' => "Bearer $githubToken",
                ],
                'sink'     => $tmpPath,
                'progress' => fn(int ...$bytes) => $this->onDownloadProgress($artifact->name, ...$bytes),
            ]);
            $this->output->out(NL);

            $status = $response->getStatusCode();
            if ($status != 200) {
                throw new RuntimeException("error downloading GitHub artifact: HTTP status $status");
            }
        }
        catch (Throwable $ex) {
            Logger::log('error downloading GitHub artifact: '.get_class($ex), L_ERROR, ['exception' => $ex]);
            return null;
        }

        // verify the download
        if ($error = $this->verifyArtifactFile($artifact, $tmpPath)) {
            unlink($tmpPath);
            Logger::log(sprintf($error, 'downloaded '), L_ERROR);
            return null;
        }
        return $tmpPath;
    }

    /**
     * Callback function for the download progress.
     *
     * @param  string $fileName
     * @param  int    $totalSize
     * @param  int    $downloaded
     *
     * @return void
     */
    protected function onDownloadProgress(string $fileName, int $totalSize, int $downloaded): void
    {
        if ($totalSize > 0) {
            $percent = (int) round($downloaded / $totalSize * 100);
            stdout("\rdownloading: $fileName => {$percent}%");
        }
    }

    /**
     * Store a downloaded GitHub artifact.
     *
     * @param  stdClass $artifact - artifact meta data
     * @param  string   $tmpPath  - tmp. path of the downloaded file
     *
     * @return ?string - final storage path or NULL on error
     */
    protected function storeBuildArtifact(stdClass $artifact, string $tmpPath): ?string
    {
        $storagePath = $this->resolveStoragePath($artifact);

        if (is_file($storagePath) && realpath($storagePath) == realpath($tmpPath)) {
            return $storagePath;            // nothing to do
        }

        FileSystem::mkDir(dirname($storagePath));

        if (rename($tmpPath, $storagePath)) {
            return $storagePath;
        }
        @unlink($tmpPath);
        return null;
    }

    /**
     * Resolve the temporary download path of a GitHub artifact.
     *
     * @param  stdClass $artifact - artifact meta data
     *
     * @return string - temporary download path
     */
    protected function resolveDownloadPath(stdClass $artifact): string
    {
        $tmpDir = Config::getString('app.dir.tmp');
        if (isRelativePath($tmpDir)) {
            $rootDir = Config::getString('app.dir.root');
            $tmpDir = "$rootDir/$tmpDir";
        }
        return "$tmpDir/$artifact->name";
    }

    /**
     * Resolve the final storage path of a GitHub artifact.
     *
     * @param  stdClass $artifact - artifact meta data
     *
     * @return string - final storage path
     */
    protected function resolveStoragePath(stdClass $artifact): string
    {
        $storageDir = Config::getString('github.storage-dir');
        if (isRelativePath($storageDir)) {
            $rootDir = Config::getString('app.dir.root');
            $storageDir = "$rootDir/$storageDir";
        }
        if ($artifact->branch != 'master') {
            $storageDir .= "/$artifact->branch";
        }
        return "$storageDir/$artifact->name";
    }

    /**
     * Verify a GitHub artifact file.
     *
     * @param  stdClass $artifact - artifact meta data
     * @param  string   $filepath - artifact file
     *
     * @return ?string - NULL on success or an error message on error
     */
    protected function verifyArtifactFile(stdClass $artifact, string $filepath): ?string
    {
        if (!is_file($filepath)) {
            return "%sfile not found: \"$filepath\"";
        }

        // verify the file size
        $expected = $artifact->size;
        $actual = filesize($filepath);
        if ($expected !== $actual) {
            return "file size mis-match of %sGitHub artifact: expected=$expected vs. actual=$actual";
        }

        // verify the file hash
        $expected = $artifact->digest;
        $actual = hash_file('sha256', $filepath);
        if ($expected != $actual) {
            return "file hash mis-match of %sGitHub artifact: SHA256 expected=$expected vs. actual=$actual";
        }

        return null;
    }
}
