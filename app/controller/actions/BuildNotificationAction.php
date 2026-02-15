<?php
declare(strict_types=1);

namespace rosasurfer\rt\controller\actions;

use rosasurfer\ministruts\core\proxy\Config;
use rosasurfer\ministruts\log\Logger;
use rosasurfer\ministruts\net\http\HttpResponse;
use rosasurfer\ministruts\struts\Action;
use rosasurfer\ministruts\struts\ActionForward;
use rosasurfer\ministruts\struts\Request;
use rosasurfer\ministruts\struts\Response;
use rosasurfer\rt\controller\forms\BuildNotificationActionForm;

use function rosasurfer\ministruts\ddd;
use function rosasurfer\ministruts\isRelativePath;

use const rosasurfer\ministruts\L_NOTICE;
use const rosasurfer\ministruts\NL;
use const rosasurfer\ministruts\WINDOWS;

/**
 * BuildNotificationAction
 *
 * Handles notifications about new GitHub build artifacts.
 */
class BuildNotificationAction extends Action
{
    /**
     * {@inheritDoc}
     */
    public function execute(Request $request, Response $response): ?ActionForward
    {
        Logger::log('GitHub build notification', L_NOTICE);

        /** @var BuildNotificationActionForm $form */
        $form = $this->form;
        if (!$form->validate()) {
            $errors = join(NL, $request->getActionErrors());
            return $this->sendStatus(HttpResponse::SC_BAD_REQUEST, $errors);
        }

        // store artifact details
        $repository = $form->repository;
        $artifactId = $form->artifactId;
        $data = "$repository;$artifactId".NL;

        $rootDir = Config::string('app.dir.root');
        $filename = Config::string('github.build-notifications');
        if (isRelativePath($filename)) {
            $filename = "$rootDir/$filename";
        }
        file_put_contents($filename, $data, FILE_APPEND|LOCK_EX);

        // launch downloader in background (detached from web server)
        if (WINDOWS) $cmd = 'start "" /b calc.exe';
        else         $cmd = "php '$rootDir/bin/cmd/updateMql4Builds.php' </dev/null >/dev/null 2>&1 &";
        pclose(popen($cmd, 'rb'));

        return $this->sendStatus(HttpResponse::SC_OK, 'success');
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
        $response = $response != '' ? $response.NL : null;

        header('Content-Type: text/plain; charset=utf-8');

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
                echo $response ?? 'not found';
                break;
        }
        return null;
    }
}
