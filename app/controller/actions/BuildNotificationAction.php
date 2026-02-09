<?php
declare(strict_types=1);

namespace rosasurfer\rt\controller\actions;

use RuntimeException;

use rosasurfer\ministruts\Application;
use rosasurfer\ministruts\config\ConfigInterface as Config;
use rosasurfer\ministruts\log\Logger;
use rosasurfer\ministruts\net\http\HttpResponse;
use rosasurfer\ministruts\struts\Action;
use rosasurfer\ministruts\struts\ActionForward;
use rosasurfer\ministruts\struts\Request;
use rosasurfer\ministruts\struts\Response;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;

use function rosasurfer\ministruts\isRelativePath;
use function rosasurfer\ministruts\strIsDigits;
use function rosasurfer\ministruts\strRight;
use function rosasurfer\ministruts\strRightFrom;
use function rosasurfer\ministruts\strStartsWith;

use const rosasurfer\ministruts\JSON_PRETTY_ALL;
use const rosasurfer\ministruts\L_ERROR;
use const rosasurfer\ministruts\L_NOTICE;
use const rosasurfer\ministruts\NL;

/**
 * BuildNotificationAction
 *
 * Handles notifications about new build artifacts.
 */
class BuildNotificationAction extends Action
{
    /**
     * {@inheritDoc}
     */
    public function execute(Request $request, Response $response): ?ActionForward
    {
        $starttime = microtime(true);

        [$error, $errorMsg, $repository, $artifactId] = $this->validateUserInput($request);
        if ($error) {
            return $this->sendStatus($error, $errorMsg);
        }

        [$error, $errorMsg, $response] = $this->queryGithubAPI($repository, $artifactId);
        if ($error) {
            return $this->sendStatus($error, $errorMsg);
        }

        [$error, $errorMsg, $fileName, $fileSize, $fileDigest, $downloadUrl, $branch] = $this->parseGithubResult($response);
        if ($error) {
            return $this->sendStatus($error, $errorMsg);
        }

        [$error, $errorMsg, $download] = $this->downloadArtifact($downloadUrl, $fileName, $fileSize, $fileDigest);
        if ($error) {
            return $this->sendStatus($error, $errorMsg);
        }

        // store download and meta data locally

        $metaData = json_encode(json_decode($response), JSON_PRETTY_ALL);
        $duration = sprintf('%.3f', microtime(true) - $starttime);
        Logger::log("Received build notification for repository \"$repository\", artifact id: $artifactId".NL.$metaData.NL."time: $duration sec", L_NOTICE);

        return $this->sendStatus(HttpResponse::SC_OK, 'success');
    }


    /**
     * Validate input parameters.
     *
     * @param  Request $request
     *
     * @return array{int, string, string, string} - validated inputs or an error description
     */
    protected function validateUserInput(Request $request): array
    {
        [$error, $errorMsg, $repository, $artifactId] = $empty = [0, '', '', ''];

        $input = $request->input();
        $repository = $input->get('repository');
        $artifactId = $input->get('artifact-id');

        if (!isset($repository)) {
            return [HttpResponse::SC_BAD_REQUEST, 'missing parameter "repository"'] + $empty;
        }
        if ($repository !== 'rosasurfer/mt4-mql-framework') {
            return [HttpResponse::SC_BAD_REQUEST, 'unsupported parameter "repository"'] + $empty;
        }

        if (!isset($artifactId)) {
            return [HttpResponse::SC_BAD_REQUEST, 'missing parameter "artifact-id"'] + $empty;
        }
        if (!strIsDigits($artifactId)) {
            return [HttpResponse::SC_BAD_REQUEST, 'invalid parameter "artifact-id"'] + $empty;
        }
        return [$error, $errorMsg, $repository, $artifactId];
    }


    /**
     * Query artifact meta data from the Github API.
     *
     * @param  string $repository
     * @param  string $artifactId
     *
     * @return array{int, string, string} - the raw Github response or an error description
     */
    protected function queryGithubAPI(string $repository, string $artifactId): array
    {
        [$error, $errorMsg, $content] = $empty = [0, '', ''];

        /** @var Config $config */
        $config = Application::service('config');
        $githubToken = $config->getString('github-api.token');

        try {
            $response = (new HttpClient())->request('GET', "https://api.github.com/repos/$repository/actions/artifacts/$artifactId", [
                'connect_timeout' => 10,
                'headers' => [
                    'Accept'        => 'application/json',
                    'Authorization' => "Bearer $githubToken",
                ],
            ]);

            $status = $response->getStatusCode();
            if ($status != 200) {
                throw new RuntimeException("error querying Github API: HTTP status $status");
            }
            $content = $response->getBody()->getContents();
        }
        catch (GuzzleException|RuntimeException $ex) {
            Logger::log($errorMsg = 'error querying Github API: '.get_class($ex), L_ERROR, ['exception' => $ex]);
            return [HttpResponse::SC_INTERNAL_SERVER_ERROR, $errorMsg] + $empty;
        }
        return [$error, $errorMsg, $content];
    }


    /**
     * Parse and validate the result of the Github API query.
     *
     * @param  string $response - received response
     *
     * @return array{int, string, string, int, string, string, string} - parsed artifact meta data or an error description
     */
    protected function parseGithubResult(string $response): array
    {
        [$error, $errorMsg, $fileName, $fileSize, $fileDigest, $downloadUrl, $branch] = $empty = [0, '', '', 0, '', '', ''];

        $data = json_decode($response, true);
        if (json_last_error() || !is_array($data)) {
            Logger::log('error reading artifact meta data from Github: '.json_last_error().' ('.json_last_error_msg().')', L_ERROR);
            return [HttpResponse::SC_INTERNAL_SERVER_ERROR, 'error reading artifact meta data from Github (unexpected response)'] + $empty;
        }

        $rules = [
          'name'                 => [&$fileName,    fn($v) => is_string($v) && $v !== ''],
          'size_in_bytes'        => [&$fileSize,    fn($v) => is_int($v) && $v > 0 ],
          'digest'               => [&$fileDigest,  fn($v) => is_string($v) && strStartsWith($v, 'sha256:') && strlen($v) == 71],
          'archive_download_url' => [&$downloadUrl, fn($v) => is_string($v) && strRightFrom($v, '/', -1) !== '' && filter_var($v, FILTER_VALIDATE_URL)],
        ];

        foreach ($rules as $k => [&$var, $validate]) {
            if (!$validate($value = $data[$k] ?? null)) {
                $msg = "missing or invalid field \"$k\"";
                Logger::log('unexpected JSON response from Github: '.$msg.NL.print_r($data, true), L_ERROR);
                return [HttpResponse::SC_INTERNAL_SERVER_ERROR, $msg] + $empty;
            }
            $var = $value;
        }
        $fileName .= '.'.strRightFrom($downloadUrl, '/', -1);
        $fileDigest = strRight($fileDigest, -7);

        $value = $data['workflow_run']['head_branch'] ?? null;
        if (!is_string($value)) {
            $msg = 'missing or invalid field "workflow_run/head_branch"';
            Logger::log('unexpected JSON response from Github: '.$msg.NL.print_r($data, true), L_ERROR);
            return [HttpResponse::SC_INTERNAL_SERVER_ERROR, $msg] + $empty;
        }
        $branch = $value;

        return [$error, $errorMsg, $fileName, $fileSize, $fileDigest, $downloadUrl, $branch];
    }



    /**
     * Download and validate the new Guthub artifact.
     *
     * @param  string $url
     * @param  string $fileName
     * @param  int    $fileSize
     * @param  string $fileDigest
     *
     * @return array{int, string, string} - full path of the downloaded file or an error description
     */
    protected function downloadArtifact(string $url, string $fileName, int $fileSize, string $fileDigest): array
    {
        [$error, $errorMsg, $tmpName] = $empty = [0, '', ''];

        /** @var Config $config */
        $config = Application::service('config');
        $tmpDir = $config->getString('app.dir.tmp');
        $tmpFileName = "$tmpDir/$fileName";
        $githubToken = $config->getString('github-api.token');

        if (!is_file($tmpFileName)) {
            try {
                $response = (new HttpClient())->request('GET', $url, [
                    'connect_timeout' => 10,
                    'sink' => $tmpFileName,
                    'headers' => [
                        'Authorization' => "Bearer $githubToken",
                    ],
                ]);

                $status = $response->getStatusCode();
                if ($status != 200) {
                    throw new RuntimeException("error downloading Github artifact: HTTP status $status");
                }
            }
            catch (GuzzleException|RuntimeException $ex) {
                Logger::log($errorMsg = 'error downloading Github artifact: '.get_class($ex), L_ERROR, ['exception' => $ex]);
                return [HttpResponse::SC_INTERNAL_SERVER_ERROR, $errorMsg] + $empty;
            }
        }

        // validate the file size
        $actualSize = filesize($tmpFileName);
        if ($actualSize !== $fileSize) {
            unlink($tmpFileName);
            Logger::log($errorMsg = "file size mis-match of downloaded Github artifact: expected=$fileSize vs. actual=$actualSize", L_ERROR);
            return [HttpResponse::SC_INTERNAL_SERVER_ERROR, $errorMsg] + $empty;
        }

        // validate the file hash
        $hash = hash_file('sha256', $tmpFileName);
        if ($hash !== $fileDigest) {
            unlink($tmpFileName);
            Logger::log($errorMsg = "file hash mis-match of downloaded Github artifact: expected=sha256:$fileDigest vs. actual=$hash", L_ERROR);
            return [HttpResponse::SC_INTERNAL_SERVER_ERROR, $errorMsg] + $empty;
        }

        return [$error, $errorMsg, $tmpFileName];
    }


    /**
     * Send an HTTP response status and an optional message.
     *
     * @param  int    $code
     * @param  string $response [optional]
     *
     * @return ?ActionForward
     */
    protected function sendStatus(int $code, string $response = ''): ?ActionForward
    {
        $response = trim($response);
        $response = strlen($response) ? $response.NL : null;

        switch ($code) {
            case HttpResponse::SC_OK:
                header('HTTP/1.1 200 OK', true, $code);
                echo $response;
                break;

            case HttpResponse::SC_BAD_REQUEST:
                header('HTTP/1.1 400 Bad Request', true, $code);
                echo $response;
                break;

            case HttpResponse::SC_NOT_FOUND:
                header('HTTP/1.1 404 Not Found', true, $code);
                if (!isset($response)) {
                    if ($forward = $this->findForward((string) $code)) {
                        return $forward;
                    }
                }
                echo $response ?? <<<'HTTP_404'
                <!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
                <html><head>
                <title>404 Not Found</title>
                </head><body>
                <h1>Not Found</h1>
                <p>The requested URL was not found on this server.</p>
                <hr>
                <address>...lamented the MiniStruts.</address>
                </body></html>
                HTTP_404;
                break;
        }
        return null;
    }
}
