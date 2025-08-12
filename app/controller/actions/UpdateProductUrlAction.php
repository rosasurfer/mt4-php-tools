<?php
declare(strict_types=1);

namespace rosasurfer\rt\controller\actions;

use rosasurfer\ministruts\config\ConfigInterface as Config;
use rosasurfer\ministruts\core\exception\RuntimeException;
use rosasurfer\ministruts\net\http\HttpResponse;
use rosasurfer\ministruts\struts\Action;
use rosasurfer\ministruts\struts\ActionForward;
use rosasurfer\ministruts\struts\Request;
use rosasurfer\ministruts\struts\Response;

use function rosasurfer\ministruts\isRelativePath;

use const rosasurfer\ministruts\NL;

/**
 * UpdateProductUrlAction
 *
 * Updates the download url for a product.
 */
class UpdateProductUrlAction extends Action
{
    /**
     * {@inheritDoc}
     */
    public function execute(Request $request, Response $response): ?ActionForward
    {
        $input = $request->input();
        $id = $input->get('id', '');
        $url = $input->get('url', '');

        if ($id == 'rosasurfer/mt4-mql-framework' && filter_var($url, FILTER_VALIDATE_URL)) {
            /** @var Config $config */
            $config = $this->di()['config'];

            $filename = $config->getString($key = 'redirect.mt4-mql-framework');
            if (!strlen($filename)) throw new RuntimeException("Invalid config setting $key: \"\" (empty)");

            if (isRelativePath($filename)) {
                $rootDir = $config['app.dir.root'];
                $filename = str_replace('\\', '/', "$rootDir/$filename");
            }

            file_put_contents($filename, trim($url).NL);

            header('HTTP/1.1 200', true, HttpResponse::SC_OK);
            echo 'success';
            return null;
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
