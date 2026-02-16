<?php
declare(strict_types=1);

namespace rosasurfer\rt\controller\actions;

use rosasurfer\ministruts\core\exception\FileNotFoundException;
use rosasurfer\ministruts\core\exception\RuntimeException;
use rosasurfer\ministruts\core\proxy\Config;
use rosasurfer\ministruts\net\http\HttpResponse;
use rosasurfer\ministruts\struts\Action;
use rosasurfer\ministruts\struts\ActionForward;
use rosasurfer\ministruts\struts\Request;
use rosasurfer\ministruts\struts\Response;

use function rosasurfer\ministruts\isRelativePath;

/**
 * RedirectAction
 */
class RedirectAction extends Action
{
    /**
     * Redirects to the Github download url.
     *
     * @param  Request  $request
     * @param  Response $response
     *
     * @return ?ActionForward - redirect to the download url or NULL if parameters are invalid
     */
    public function execute(Request $request, Response $response)
    {
        $input = $request->input();
        $product = $input->get('product', '');

        if ($product == 'rosasurfer/mt4-mql-framework') {
            $filename = Config::string($key = 'download.mt4-mql-framework');
            if (!strlen($filename)) throw new RuntimeException("Invalid config setting $key: \"\" (empty)");

            if (isRelativePath($filename)) {
                $rootDir = Config::string('app.dir.root');
                $filename = "$rootDir/$filename";
            }
            if (!is_file($filename)) throw new FileNotFoundException("File not found: \"$filename\"");

            $lines = file($filename, FILE_SKIP_EMPTY_LINES|FILE_IGNORE_NEW_LINES);
            if (!$lines) throw new RuntimeException("Empty file \"$filename\"");

            $url = trim($lines[0]);
            return new ActionForward('generic', $url, true);
        }


        // HTTP_404
        header('HTTP/1.1 404 Not Found', true, HttpResponse::SC_NOT_FOUND);

        // check for a pre-configured 404 response and return it
        if ($forward = $this->findForward((string) HttpResponse::SC_NOT_FOUND)) {
            return $forward;
        }

        // otherwise generate one
        echo <<<HTTP_404
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
        return null;
    }
}
