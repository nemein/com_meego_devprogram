<?php
/**
 * @package com_meego_devprogram
 * @author Ferenc Szekely, http://www.nemein.com
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */
class com_meego_devprogram_controllers_applications extends midgardmvc_core_controllers_baseclasses_crud
{
    var $mvc = null;
    var $request = null;

    /**
     * Contructor
     *
     * @param object request is a midgardmvc_core_request object
     */
    public function __construct(midgardmvc_core_request $request)
    {
        $this->request = $request;
        $this->mvc = midgardmvc_core::get_instance();
        $this->data['my_applications'] = false;
    }

    /**
     * Loads an application
     */
    public function load_object(array $args)
    {
        $this->object = com_meego_devprogram_apputils::get_application_by_guid($args['application_guid']);

        // also get the program the application was submitted for
        $this->data['program'] = com_meego_devprogram_progutils::get_program_by_id($this->object->program);
    }

    /**
     * Prepare a new application
     */
    public function prepare_new_object(array $args)
    {
        $this->object = new com_meego_devprogram_application();
    }

    /**
     * Returns a read url for an application
     */
    public function get_url_read()
    {
        return com_meego_devprogram_utils::get_url(
           'my_application_read',
            array('application_guid' => $this->object->guid
        ));
    }

    /**
     * Returns an update url for an application
     */
    public function get_url_update()
    {
        return com_meego_devprogram_utils::get_url(
            'my_application_update',
            array('application_guid' => $this->object->guid
        ));
    }

    /**
     * Loads a form
     */
    public function load_form()
    {
        $this->form = midgardmvc_helper_forms_mgdschema::create($this->object, false, 'label_application_', 'tip_application_');
        $this->form->set_submit('form-submit', $this->mvc->i18n->get('command_save'));

        # remove the program field
        $this->form->__unset('program');
        # remove additional fields that will be only filled in by program owners (judges)
        $this->form->__unset('devicesn');
        $this->form->__unset('status');
        $this->form->__unset('remarks');

        $field = $this->form->add_field('program', 'integer', false);
        $field->set_value($this->data['program']->id);
        $field->set_widget('hidden');
    }

    /**
     * Prepares and shows the my applications list page (cmd-my-applications)
     *
     * Access: only users can list own applications
     * @param array args (not used)
     *
     */
    public function get_my_applications_list(array $args)
    {
        // check if is logged in
        if (! $this->mvc->authentication->is_user())
        {
            return;
        }
        $this->data['my_applications'] = com_meego_devprogram_apputils::get_applications_of_current_user();
    }

    /**
     * Prepares the application page
     *
     * @param array args
     */
    public function get_create(array $args)
    {
        if (! strlen($args['program_name']))
        {
            $url = com_meego_devprogram_utils::get_url('index', $args);
            $this->mvc->head->relocate($url);
        }

        $redirect = com_meego_devprogram_utils::get_url('my_application_create', $args);
        $user = com_meego_devprogram_utils::require_login($redirect);

        $program = com_meego_devprogram_progutils::get_program_by_name($args['program_name']);

        if (! is_object($program))
        {
            throw new InvalidArgumentException("Program: {$args['program_name']} con not be found");
        }

        $this->data['program'] = $program;

        // check if the user has applied for the program and
        // display a warning if yes
        // in case of multiple applications we only refer to the 1st
        $this->data['myapps'] = com_meego_devprogram_apputils::get_applications_of_current_user($program->id);

        if (   ! count($this->data['myapps'])
            || $program->multiple)
        {
            // we move on if user has not applied or
            // the program accepts multiple entries from the same person
            parent::get_create($args);
        }
    }

    /**
     * Prepares and shows the application details page (cmd-my-application-details)
     *
     * Access: only owners of the application can read
     *
     * @param array args
     */
    public function get_read(array $args)
    {
        parent::get_read($args);

        unset($this->data['object']);

        $this->data['application'] = $this->object;
    }

    /**
     * Prepares and shows the my application update page (cmd-my-application-update)
     *
     * Access: only owners of the application can update
     *
     * @param array args
     */
    public function get_update(array $args)
    {
        // set myapps to be able to show a warning
        $this->data['myapps'] = false;//com_meego_devprogram_apputils::get_applications_of_current_user($program->id);

        parent::get_update($args);
    }

    /**
     * Prepares the delete page
     */
    public function get_delete(array $args)
    {
        parent::get_delete($args);

        $this->data['index_url'] = com_meego_devprogram_utils::get_url('index', array());

        $this->data['can_not_delete'] = false;

        $this->data['delete_question'] = $this->mvc->i18n->get('question_application_delete', null, array('program_name' => $this->data['program']->title));
    }

    /**
     * Prepares and shows the application approval page
     *
     * Access: only owners of the programs can judge applications
     *
     * @param array args
     */
    public function get_judge(array $args)
    {
    }
}
?>