<?php
namespace rosasurfer\xtrade\controller\forms;

use rosasurfer\ministruts\ActionForm;
use rosasurfer\ministruts\Request;

use rosasurfer\xtrade\model\metatrader\Test;


/**
 * ViewTestActionForm
 */
class ViewTestActionForm extends ActionForm {


    /** @var string|int - submitted Test id */
    protected $id;

    /** @var Test */
    protected $test;


    /**
     * Return the submitted {@link Test} id.
     *
     * @return string|int|null
     */
    public function getId() {
        return $this->id;
    }


    /**
     * Return the {@link Test} to view.
     *
     * @return Test
     */
    public function getTest() {
        return $this->test;
    }


    /**
     * {@inheritdoc}
     */
    protected function populate(Request $request) {
        $this->id = trim($request->getParameter('id'));
    }


   /**
    * {@inheritdoc}
    *
    * @return bool - whether or not the submitted parameters are valid
    */
    public function validate() {
        $request = $this->request;
        $id = $this->id;

        if     (!strLen($id))      $request->setActionError('id', 'Invalid test id.');
        elseif (!strIsDigits($id)) $request->setActionError('id', 'Invalid test id.');
        else {
            $this->id   = (int) $id;
            $this->test = Test::dao()->getById($this->id);
            if (!$this->test)      $request->setActionError('id', 'Unknown test id.');
        }
        return !$request->isActionError();
    }
}
