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
     * Return the submitted {@link \rosasurfer\rt\model\Test} id.
     *
     * @return string|int|null
     */
    public function getId() {
        return $this->id;
    }


    /**
     * Get the {@link \rosasurfer\rt\model\Test} associated with the submitted parameters.
     *
     * @return Test|null - Test instance or NULL if an associated test was not found
     */
    public function getTest() {
        if (!isset($this->test) && is_int($this->id)) {
            $this->test = Test::dao()->findById($this->id) ?: false;
        }
        return is_bool($this->test) ? null : $this->test;
    }


    /**
     *
     */
    protected function populate() {
        $input = $this->request->input();
        $input->has('id') && $this->id = trim($input['id']);
    }


   /**
    *
    */
    public function validate() {
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
