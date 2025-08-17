<?php
declare(strict_types=1);

namespace rosasurfer\rt\controller\actions;

//use rosasurfer\ministruts\config\ConfigInterface as Config;
//use rosasurfer\ministruts\core\exception\RuntimeException;
use rosasurfer\ministruts\log\Logger;
use rosasurfer\ministruts\net\http\HttpResponse;
use rosasurfer\ministruts\struts\Action;
use rosasurfer\ministruts\struts\ActionForward;
use rosasurfer\ministruts\struts\Request;
use rosasurfer\ministruts\struts\Response;

use function rosasurfer\ministruts\isRelativePath;
use function rosasurfer\ministruts\strIsDigits;
use function rosasurfer\ministruts\strStartsWithI;

use const rosasurfer\ministruts\L_NOTICE;
use const rosasurfer\ministruts\NL;

/**
 * NotificationAction
 *
 * Handles notifications of new build artifacts.
 */
class NotificationAction extends Action
{
    /**
     * {@inheritDoc}
     */
    public function execute(Request $request, Response $response): ?ActionForward
    {
        $input = $request->input();
        $repository = $input->get('repository', '');
        $artifactId = $input->get('artifact-id', '');

        if (empty($repository)) {
            return $this->sendStatus(HttpResponse::SC_BAD_REQUEST, 'invalid parameter "repository"');
        }
        if (!strIsDigits($artifactId)) {
            return $this->sendStatus(HttpResponse::SC_BAD_REQUEST, 'invalid parameter "artifact-id"');
        }
        if ($repository != 'rosasurfer/mt4-mql-framework') {
            return $this->sendStatus(HttpResponse::SC_OK, 'unsupported repository');
        }

        // fetch artifact data


        ///** @var Config $config */
        //$config = $this->di()['config'];
        //
        //$filename = $config->getString($key = 'download.mt4-mql-framework');
        //if (!strlen($filename)) throw new RuntimeException("Invalid config setting $key: \"\" (empty)");
        //
        //if (isRelativePath($filename)) {
        //    $rootDir = $config['app.dir.root'];
        //    $filename = str_replace('\\', '/', "$rootDir/$filename");
        //}
        //
        //file_put_contents($filename, $artifactId.NL);
        Logger::log("Received build notification for repository \"$repository\", artifact id: $artifactId", L_NOTICE);

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
