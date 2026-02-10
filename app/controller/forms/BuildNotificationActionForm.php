<?php
declare(strict_types=1);

namespace rosasurfer\rt\controller\forms;

use rosasurfer\ministruts\struts\ActionForm;

use function rosasurfer\ministruts\strIsDigits;

/**
 * BuildNotificationActionForm
 *
 * @property-read string $repository repository name
 * @property-read string $artifactId build artifact id
 */
class BuildNotificationActionForm extends ActionForm
{
    protected string $repository;
    protected string $artifactId;

    /**
     * {@inheritDoc}
     */
    protected function populate(): void
    {
        $input = $this->request->input();

        $this->repository = trim($input->get('repository', ''));
        $this->artifactId = trim($input->get('artifact-id', ''));
    }

   /**
     * {@inheritDoc}
    */
    public function validate(): bool
    {
        $request = $this->request;

        $name = 'repository';
        $repository = $this->repository;

        if ($repository == '') {
            $request->setActionError($name, "missing parameter \"$name\"");
        }
        elseif ($repository != 'rosasurfer/mt4-mql-framework') {
            $request->setActionError($name, "invalid parameter \"$name\"");
        }

        $name = 'artifact-id';
        $artifactId = $this->artifactId;

        if ($artifactId == '') {
            $request->setActionError($name, "missing parameter \"$name\"");
        }
        elseif (!strIsDigits($artifactId)) {
            $request->setActionError($name, "invalid parameter \"$name\"");
        }

        return !$request->isActionError();
    }
}
