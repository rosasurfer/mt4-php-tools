<?php
declare(strict_types=1);

namespace rosasurfer\rt\console;

use rosasurfer\ministruts\console\Command;
use rosasurfer\ministruts\console\io\Input;
use rosasurfer\ministruts\console\io\Output;

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
     * @param  Input  $input
     * @param  Output $output
     *
     * @return int - execution status (0 for success)
     */
    protected function execute(Input $input, Output $output): int
    {
        $output->out('[Ok]');

        // --- old -------------------------------------------------------------------------------------------------------------------------
        //[$error, $errorMsg, $response] = $this->queryGithubAPI($repository, $artifactId);
        //if ($error) {
        //    return $this->sendStatus($error, $errorMsg);
        //}
        //
        //[$error, $errorMsg, $fileName, $fileSize, $fileDigest, $downloadUrl, $branch] = $this->parseGithubResult($response);
        //if ($error) {
        //    return $this->sendStatus($error, $errorMsg);
        //}
        //
        //[$error, $errorMsg, $download] = $this->downloadArtifact($downloadUrl, $fileName, $fileSize, $fileDigest);
        //if ($error) {
        //    return $this->sendStatus($error, $errorMsg);
        //}
        //
        // store download and meta data locally

        return 0;
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

        ///** @var Config $config */
        //$config = Application::service('config');
        //$githubToken = $config->getString('github-api.token');
        //
        //try {
        //    $response = (new HttpClient())->request('GET', "https://api.github.com/repos/$repository/actions/artifacts/$artifactId", [
        //        'connect_timeout' => 10,
        //        'headers' => [
        //            'Accept'        => 'application/json',
        //            'Authorization' => "Bearer $githubToken",
        //        ],
        //    ]);
        //
        //    $status = $response->getStatusCode();
        //    if ($status != 200) {
        //        throw new RuntimeException("error querying Github API: HTTP status $status");
        //    }
        //    $content = $response->getBody()->getContents();
        //}
        //catch (GuzzleException|RuntimeException $ex) {
        //    Logger::log($errorMsg = 'error querying Github API: '.get_class($ex), L_ERROR, ['exception' => $ex]);
        //    return [HttpResponse::SC_INTERNAL_SERVER_ERROR, $errorMsg] + $empty;
        //}

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

        //$data = json_decode($response, true);
        //if (json_last_error() || !is_array($data)) {
        //    Logger::log('error reading artifact meta data from Github: '.json_last_error().' ('.json_last_error_msg().')', L_ERROR);
        //    return [HttpResponse::SC_INTERNAL_SERVER_ERROR, 'error reading artifact meta data from Github (unexpected response)'] + $empty;
        //}
        //
        //$rules = [
        //  'name'                 => [&$fileName,    fn($v) => is_string($v) && $v !== ''],
        //  'size_in_bytes'        => [&$fileSize,    fn($v) => is_int($v) && $v > 0 ],
        //  'digest'               => [&$fileDigest,  fn($v) => is_string($v) && strStartsWith($v, 'sha256:') && strlen($v) == 71],
        //  'archive_download_url' => [&$downloadUrl, fn($v) => is_string($v) && strRightFrom($v, '/', -1) !== '' && filter_var($v, FILTER_VALIDATE_URL)],
        //];
        //
        //foreach ($rules as $k => [&$var, $validate]) {
        //    if (!$validate($value = $data[$k] ?? null)) {
        //        $msg = "missing or invalid field \"$k\"";
        //        Logger::log('unexpected JSON response from Github: '.$msg.NL.print_r($data, true), L_ERROR);
        //        return [HttpResponse::SC_INTERNAL_SERVER_ERROR, $msg] + $empty;
        //    }
        //    $var = $value;
        //}
        //$fileName .= '.'.strRightFrom($downloadUrl, '/', -1);
        //$fileDigest = strRight($fileDigest, -7);
        //
        //$value = $data['workflow_run']['head_branch'] ?? null;
        //if (!is_string($value)) {
        //    $msg = 'missing or invalid field "workflow_run/head_branch"';
        //    Logger::log('unexpected JSON response from Github: '.$msg.NL.print_r($data, true), L_ERROR);
        //    return [HttpResponse::SC_INTERNAL_SERVER_ERROR, $msg] + $empty;
        //}
        //$branch = $value;

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

        ///** @var Config $config */
        //$config = Application::service('config');
        //$tmpDir = $config->getString('app.dir.tmp');
        //$tmpFileName = "$tmpDir/$fileName";
        //$githubToken = $config->getString('github-api.token');
        //
        //if (!is_file($tmpFileName)) {
        //    try {
        //        $response = (new HttpClient())->request('GET', $url, [
        //            'connect_timeout' => 10,
        //            'sink' => $tmpFileName,
        //            'headers' => [
        //                'Authorization' => "Bearer $githubToken",
        //            ],
        //        ]);
        //
        //        $status = $response->getStatusCode();
        //        if ($status != 200) {
        //            throw new RuntimeException("error downloading Github artifact: HTTP status $status");
        //        }
        //    }
        //    catch (GuzzleException|RuntimeException $ex) {
        //        Logger::log($errorMsg = 'error downloading Github artifact: '.get_class($ex), L_ERROR, ['exception' => $ex]);
        //        return [HttpResponse::SC_INTERNAL_SERVER_ERROR, $errorMsg] + $empty;
        //    }
        //}
        //
        //// validate the file size
        //$actualSize = filesize($tmpFileName);
        //if ($actualSize !== $fileSize) {
        //    unlink($tmpFileName);
        //    Logger::log($errorMsg = "file size mis-match of downloaded Github artifact: expected=$fileSize vs. actual=$actualSize", L_ERROR);
        //    return [HttpResponse::SC_INTERNAL_SERVER_ERROR, $errorMsg] + $empty;
        //}
        //
        //// validate the file hash
        //$hash = hash_file('sha256', $tmpFileName);
        //if ($hash !== $fileDigest) {
        //    unlink($tmpFileName);
        //    Logger::log($errorMsg = "file hash mis-match of downloaded Github artifact: expected=sha256:$fileDigest vs. actual=$hash", L_ERROR);
        //    return [HttpResponse::SC_INTERNAL_SERVER_ERROR, $errorMsg] + $empty;
        //}

        return [$error, $errorMsg, $tmpName];
    }


}
