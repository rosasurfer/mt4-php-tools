<?php
declare(strict_types=1);

namespace rosasurfer\rt\controller\actions;

use rosasurfer\ministruts\Application;
use rosasurfer\ministruts\config\ConfigInterface as Config;
use rosasurfer\ministruts\log\Logger;
use rosasurfer\ministruts\net\http\HttpResponse;
use rosasurfer\ministruts\struts\Action;
use rosasurfer\ministruts\struts\ActionForward;
use rosasurfer\ministruts\struts\Request;
use rosasurfer\ministruts\struts\Response;

use rosasurfer\rt\controller\forms\BuildNotificationActionForm;

use function rosasurfer\ministruts\isRelativePath;

use const rosasurfer\ministruts\L_NOTICE;
use const rosasurfer\ministruts\NL;

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
        $time = time();
        $repository = $form->repository;
        $artifactId = $form->artifactId;
        $data = "$time;$repository;$artifactId".NL;

        /** @var Config $config */
        $config = Application::service('config');

        $filename = $config->getString('github.build-notifications');
        if (isRelativePath($filename)) {
            $rootDir = $config->getString('app.dir.root');
            $filename = "$rootDir/$filename";
        }
        file_put_contents($filename, $data, FILE_APPEND|LOCK_EX);

        // run UpdateMql4BuildsCommand

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
