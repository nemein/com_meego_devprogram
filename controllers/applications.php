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
    var $component = 'com_meego_devprogram';

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

        // by default we set this to true so that the toolbar is visible
        // we remove the toolbar on the judge form
        $this->object->can_manage = true;

        // set the application data
        $this->data['application'] = $this->object;

        // also get the program the application was submitted for
        $this->data['program'] = $this->object->programobject;
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
     * Loads the judging form
     */
    public function load_judge_form()
    {
        $this->form = midgardmvc_helper_forms_mgdschema::create($this->object, false, 'label_application_', 'tip_application_');
        $this->form->set_submit('form-submit', $this->mvc->i18n->get('command_submit'), true);

        # remove the program field
        $this->form->__unset('program');
        # remove fields that are not needed by judges
        $this->form->__unset('title');
        $this->form->__unset('summary');
        $this->form->__unset('plan');
        $this->form->__unset('project');
        $this->form->__unset('team');
        $this->form->__unset('url');
        $this->form->__unset('status');

        $field = $this->form->add_field('program', 'integer', false);
        $field->set_value($this->object->program);
        $field->set_widget('hidden');

        # checkbox for the decision
        $field = $this->form->add_field('status', 'integer', false);
        $widget = $field->set_widget('radiobuttons');
        $widget->add_label($this->mvc->i18n->get('label_application_decision'));

        $options = array
        (
            array
            (
                'description' => $this->mvc->i18n->get('label_application_decision_approve'),
                'value' => CMD_APPLICATION_APPROVED
            ),
            array
            (
                'description' => $this->mvc->i18n->get('label_application_decision_moreinfo'),
                'value' => CMD_APPLICATION_MOREINFO
            ),
            array
            (
                'description' => $this->mvc->i18n->get('label_application_decision_deny'),
                'value' => CMD_APPLICATION_DENIED
            )
        );
        $widget->set_options($options);

        // pimp the date input fields
        $this->mvc->head->add_jsfile(MIDGARDMVC_STATIC_URL . '/' . $this->component . '/js/decision.js');
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
        if (com_meego_devprogram_utils::get_current_user())
        {
            $this->data['my_applications'] = com_meego_devprogram_apputils::get_applications_of_current_user();
        }
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

        // require login
        $redirect = com_meego_devprogram_utils::get_url('my_application_create', $args);
        $user = com_meego_devprogram_utils::require_login($redirect);

        // @todo: sanity check
        $programs = com_meego_devprogram_progutils::get_programs(array('name' => $args['program_name']));
        $program = $programs[0];

        if (! is_object($program))
        {
            throw new InvalidArgumentException("Program: {$args['program_name']} con not be found");
        }

        $this->data['program'] = $program;

        // check if the user has applied for the program and
        // display a warning if yes
        // in case of multiple applications we only refer to the 1st
        $this->data['myapps'] = com_meego_devprogram_apputils::get_applications_of_current_user($program->id);

        $now = new DateTime();

        if (   $program->duedate >= $now
            && (   ! count($this->data['myapps'])
                || $program->multiple))
        {
            // we move on if user has not applied or
            // the program accepts multiple entries from the same person
            parent::get_create($args);
        }

        // load the text limiter js files
        $this->mvc->head->add_jsfile(MIDGARDMVC_STATIC_URL . '/' . $this->component . '/js/textLimit.js');
        $this->mvc->head->add_jsfile(MIDGARDMVC_STATIC_URL . '/' . $this->component . '/js/limiter_for_applications.js');
    }

    /**
     * Prepares and shows the application details page (cmd-my-application-details)
     *
     * Access: only creators of the application can read
     *         or program owners / admins
     *
     * @param array args
     */
    public function get_read(array $args)
    {
        parent::get_read($args);

        if ( ! (   com_meego_devprogram_utils::is_current_user_creator_or_admin($this->data['application'])
                || com_meego_devprogram_utils::is_current_user_creator_or_admin($this->data['program'])))
        {
            // not creator of application
            // not owner of the program the app was created for
            // not an administrator
            // nice try, but sorry, get back to index
            $this->mvc->head->relocate(com_meego_devprogram_utils::get_url('index', array()));
        }

        unset($this->data['object']);
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
        $this->data['myapps'] = false;

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
        $this->load_object($args);

        if (!  (com_meego_devprogram_utils::is_current_user_creator_or_admin($this->object)
            || com_meego_devprogram_membutils::is_current_user_member_of_provider($this->object->provider->id)))
        {
            // not an administrator
            // nice try, but sorry, get back to index
            $this->mvc->head->relocate(com_meego_devprogram_utils::get_url('index', array()));
        }

        // we remove the toolbar on the judge form
        $this->data['application']->can_manage = false;

        $this->load_judge_form();

        $this->data['form'] = $this->form;

        unset($this->data['object']);
    }

    /**
     * Handles posts from application judges
     *
     * @param array args
     */
    public function post_judge(array $args)
    {
        $this->get_judge($args);

        try
        {
            $transaction = new midgard_transaction();
            $transaction->begin();
            $this->process_form();
            $this->object->update();
            $transaction->commit();

            // Redirect to application lists of that program
            $this->mvc->head->relocate($this->data['program']->list_apps_url);
        }
        catch (midgardmvc_helper_forms_exception_validation $e)
        {
            // TODO: UImessage
        }

    }
}
?>
