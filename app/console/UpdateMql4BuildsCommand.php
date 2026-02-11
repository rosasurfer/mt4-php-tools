<?php
declare(strict_types=1);

namespace rosasurfer\rt\console;

use JsonException;
use RuntimeException;
use stdClass;
use Throwable;

use rosasurfer\ministruts\Application;
use rosasurfer\ministruts\config\ConfigInterface as Config;
use rosasurfer\ministruts\console\Command;
use rosasurfer\ministruts\console\io\Input;
use rosasurfer\ministruts\console\io\Output;
use rosasurfer\ministruts\log\Logger;

use GuzzleHttp\Client as HttpClient;

use function rosasurfer\ministruts\echof;
use function rosasurfer\ministruts\isRelativePath;
use function rosasurfer\ministruts\json_decode_or_throw;
use function rosasurfer\ministruts\strRight;
use function rosasurfer\ministruts\strRightFrom;
use function rosasurfer\ministruts\strStartsWith;
use function rosasurfer\ministruts\toString;

use const rosasurfer\ministruts\L_ERROR;
use const rosasurfer\ministruts\NL;

/**
 * UpdateMql4BuildsCommand
 *
 * The command processes new build notifications from GitHub CI pipelines and updates the locally stored builds.
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
        // read existing notifications
        $notifications = $this->updateNotifications();

        $processed = [];
        try {
            // process new notifications
            foreach ($notifications as $notification) {     // format: "{repository};{artifact-id}"
                [$repository, $artifactId] = explode(';', $notification) + ['', ''];

                if (!$response = $this->queryGithubApi($repository, $artifactId)) return 1;
                if (!$artifact = $this->parseGithubResponse($response)) return 1;
                if (!$filepath = $this->downloadBuildArtifact($artifact->url, $artifact->name, $artifact->size, $artifact->digest)) return 1;

                echof(toString($response));
                echof($artifact);
                echof($filepath);

                // store build
                $processed[] = $notification;
            }
        }
        finally {
            // remove processed notifications
            if ($processed) {
                $this->updateNotifications($processed);
            }
        }

        $output->out('[Ok]');
        return 0;
    }

    /**
     * Read/update existing build notifications.
     *
     * @param  string[] $processed [optional] - already processed notifications to be deleted (default: none)
     *
     * @return string[] - new notifications
     */
    protected function updateNotifications(array $processed = []): array
    {
        /** @var Config $config */
        $config = Application::service('config');

        $filename = $config->getString('github.build-notifications');
        if (isRelativePath($filename)) {
            $rootDir = $config->getString('app.dir.root');
            $filename = "$rootDir/$filename";
        }

        $lines = [];
        if (is_file($filename)) {
            $lines = file($filename, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
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
        /** @var Config $config */
        $config = Application::service('config');
        $githubToken = $config->getString('github.api-token');

        try {
            $url = "https://api.github.com/repos/$repository/actions/artifacts/$artifactId";
            $response = (new HttpClient())->request('GET', $url, [
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
            Logger::log('error querying GitHub API: '.get_class($ex).NL."url: $url", L_ERROR, ['exception' => $ex]);
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
     * Download and validate a GitHub artifact.
     *
     * @param  string $url
     * @param  string $fileName
     * @param  int    $fileSize
     * @param  string $fileDigest
     *
     * @return ?string - path of the downloaded file or NULL on error
     */
    protected function downloadBuildArtifact(string $url, string $fileName, int $fileSize, string $fileDigest): ?string
    {
        /** @var Config $config */
        $config = Application::service('config');
        $githubToken = $config->getString('github.api-token');

        $tmpDir = $config->getString('app.dir.tmp');
        $tmpFileName = "$tmpDir/$fileName";
        if (is_file($tmpFileName)) {
            //unlink($tmpFileName);
        }

        if (!is_file($tmpFileName)) {
            // download the file
            try {
                $response = (new HttpClient())->request('GET', $url, [
                    'connect_timeout' => 10,
                    'sink'            => $tmpFileName,              // @todo add progress indicator
                    'headers'         => [
                        'Authorization' => "Bearer $githubToken",
                    ],
                ]);
                $status = $response->getStatusCode();
                if ($status != 200) {
                    throw new RuntimeException("error downloading GitHub artifact: HTTP status $status");
                }
            }
            catch (Throwable $ex) {
                Logger::log('error downloading GitHub artifact: '.get_class($ex).NL."url: $url", L_ERROR, ['exception' => $ex]);
                return null;
            }
        }

        // validate the file size
        $actualSize = filesize($tmpFileName);
        if ($actualSize !== $fileSize) {
            unlink($tmpFileName);
            Logger::log("file size mis-match of downloaded GitHub artifact: expected=$fileSize vs. actual=$actualSize", L_ERROR);
            return null;
        }

        // validate the file hash
        $hash = hash_file('sha256', $tmpFileName);
        if ($hash !== $fileDigest) {
            unlink($tmpFileName);
            Logger::log("file hash mis-match of downloaded GitHub artifact: SHA256 expected=$fileDigest vs. actual=$hash", L_ERROR);
            return null;
        }
        return $tmpFileName;
    }
}
