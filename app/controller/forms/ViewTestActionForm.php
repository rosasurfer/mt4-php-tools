<?php
declare(strict_types=1);

namespace rosasurfer\rt\controller\forms;

use rosasurfer\ministruts\struts\ActionForm;
use rosasurfer\rt\model\Test;

use function rosasurfer\ministruts\strIsDigits;


/**
 * ViewTestActionForm
 */
class ViewTestActionForm extends ActionForm {


    /** @var string|int - submitted Test id */
    protected $id;

    /** @var Test|bool|null [transient] - Test instance or FALSE if a test with the submitted id was not found */
    protected $test = null;


    /**
     * Return the submitted {@link Test} id.
     *
     * @return string|int|null
     */
    public function getId() {
        return $this->id;
    }


    /**
     * Get the {@link Test} associated with the submitted parameters.
     *
     * @return ?Test - Test instance or NULL if an associated test was not found
     */
    public function getTest() {
        if (!isset($this->test) && is_int($this->id)) {
            $this->test = Test::dao()->findById($this->id) ?: false;
        }
        return is_bool($this->test) ? null : $this->test;
    }


    /**
     * {@inheritDoc}
     */
    protected function populate(): void {
        $input = $this->request->input();
        $this->id = trim($input->get('id', ''));
    }


   /**
     * {@inheritDoc}
    */
    public function validate(): bool {
        $request = $this->request;
        $id = $this->id;

        if     (!strlen($id))      $request->setActionError('id', 'Invalid test id.');
        elseif (!strIsDigits($id)) $request->setActionError('id', 'Invalid test id.');
        else {
            $this->id = (int) $id;
            if (!$this->getTest()) $request->setActionError('id', 'Unknown test.');
        }
        return !$request->isActionError();
    }
}
