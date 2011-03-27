<?php
/**
 * @package com_meego_devprogram
 * @author Ferenc Szekely, http://www.nemein.com
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */
class com_meego_devprogram_controllers_programs extends midgardmvc_core_controllers_baseclasses_crud
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
        $this->data['user_has_device'] = false;
        $this->data['programs'] = false;
        $this->data['my_programs'] = false;
    }

    /**
     * Loads a program
     */
    public function load_object(array $args)
    {
        $this->object = com_meego_devprogram_progutils::get_program_by_name($args['program_name']);

        if (is_object($this->object))
        {
            $this->data['program'] = $this->object;
            midgardmvc_core::get_instance()->head->set_title($this->object->title);
        }
    }

    /**
     * Prepare a new program
     */
    public function prepare_new_object(array $args)
    {
        $this->object = new com_meego_devprogram_program();
    }

    /**
     * Returns a read url for a program
     */
    public function get_url_read()
    {
        return com_meego_devprogram_utils::get_url('program_read', array('program_name' => $this->object->name));
    }

    /**
     * Returns an update url for a program
     */
    public function get_url_update()
    {
        return com_meego_devprogram_utils::get_url('program_update', array('program_name' => $this->object->name));
    }

    /**
     * Loads a form
     */
    public function load_form()
    {
        $this->form = midgardmvc_helper_forms_mgdschema::create($this->object, false, 'label_program_', 'tip_program_');

        # we have to alter the submit button and three fields on the form
        $this->form->set_submit('form-submit', $this->mvc->i18n->get('command_save'));

        # remove the name field, we will genarate it from title
        $this->form->__unset('name');

        # change the default widget of duedate field
        $object_end = $this->object->duedate;
        if ($object_end->getTimestamp() <= 0)
        {
            $new_end = new DateTime('last day of next month');
            $object_end->setTimestamp($new_end->getTimestamp());
        }

        $duedate = new midgardmvc_helper_forms_field_text('duedate', true);
        $duedate->set_value($object_end);
        $widget = $duedate->set_widget('date');
        $widget->set_label($this->mvc->i18n->get('label_program_duedate'));

        $this->form->__set('duedate', $duedate);

        # change the default widget of device field
        $devices = com_meego_devprogram_devutils::get_devices();
        foreach ($devices as $device)
        {
            $device_options[] = array
            (
                'description' => $device->name,
                'value' => $device->id
            );
        }

        $device = new midgardmvc_helper_forms_field_integer('device', true);
        $device->set_value($this->object->device);
        $widget = $device->set_widget('selectoption');
        $widget->set_label($this->mvc->i18n->get('label_program_device'));

        if (is_array($device_options))
        {
            $widget->set_options($device_options);
        }

        $this->form->__set('device', $device);
    }

    /**
     * Prepares and shows the program list page (cmd-my-programs)
     *
     * @param array args (not used)
     */
    public function get_my_programs_list(array $args)
    {
        // check if is logged in
        if (! $this->mvc->authentication->is_user())
        {
            return;
        }

        $this->data['user_has_device'] = com_meego_devprogram_devutils::user_has_device();

        if (! $this->data['user_has_device'])
        {
            return false;
        }

        $this->data['my_programs'] = com_meego_devprogram_progutils::get_open_programs_of_current_user();
    }

    /**
     * Processes the newly submitted prgram
     * Generates name from title
     * Checks if name is unique
     *
     * @param array args
     */
    public function post_create(array $args)
    {
        $this->get_create($args);
        try
        {
            $transaction = new midgard_transaction();
            $transaction->begin();
            $this->process_form();

            // generate a unique name
            $this->object->name = com_meego_devprogram_utils::generate_unique_name($this->object);

            if (! $this->object->name)
            {
                throw new midgardmvc_exception('Could not generate a valid, unique name to a new object');
            }
            $res = $this->object->create();
            $transaction->commit();

            // TODO: add uimessage of $e->getMessage();
            $this->relocate_to_read();
        }
        catch (midgardmvc_helper_forms_exception_validation $e)
        {
            // TODO: UImessage
        }
        catch (midgardmvc_exception $e)
        {
            // TODO: UImessage
        }
    }

    /**
     * Prepares and shows the program details page (cmd-program-details)
     *
     * Access: anyone can read the program details
     *         owners will get extra options on the page
     *
     * @param array args (not used)
     */
    public function get_read(array $args)
    {
        $this->data['myapps'] = null;
        $this->data['can_apply'] = true;

        $this->load_object($args);

        if (com_meego_devprogram_utils::is_current_user_creator_or_admin($this->object))
        {
            // owners of a program or admins should not apply for that program
            $this->data['can_apply'] = false;
        }
        elseif ($this->mvc->authentication->is_user())
        {
            // check if the user has applied for the program and
            // display a warning if yes
            // in case of multiple applications we only refer to the 1st
            $this->data['myapps'] = com_meego_devprogram_apputils::get_applications_of_current_user($this->object->id);

            if (   count($this->data['myapps'])
                && ! $this->object->multiple)
            {
                // if applied then we disable further applications
                // unless the program accepts multiple entries from the same person
                $this->data['can_apply'] = false;
            }
        }
    }

    /**
     * Prepares loading the update page
     * Checks if the user is logged in and is the owner of the program
     *
     * @param array args
     */
    public function get_update(array $args)
    {
        $this->load_object($args);

        if (! com_meego_devprogram_utils::is_current_user_creator_or_admin($this->object))
        {
            // nice try; could throw an exception, but we will just redirect
            $this->relocate_to_read();
        }

        parent::get_update($args);
    }

    /**
     * Prepares and shows the program list page (cmd-list-programs)
     *
     * Access: anyone can list open programs
     *
     * @param array args (not used)
     */
    public function get_open_programs_list(array $args)
    {
        $this->data['type'] = 'open';
        $this->data['index_url'] = com_meego_devprogram_utils::get_url('index', array());
        $this->data['programs'] = com_meego_devprogram_progutils::get_open_programs();
    }

    /**
     * Prepares and shows the program list page (cmd-list-programs)
     *
     * Access: anyone can list closed programs
     *
     * @param array args (not used)
     */
    public function get_closed_programs_list(array $args)
    {
        $this->data['type'] = 'closed';
        $this->data['index_url'] = com_meego_devprogram_utils::get_url('index', array());
        $this->data['programs'] = com_meego_devprogram_progutils::get_closed_programs(true);
    }

    /**
     * Prepares and shows the list of application for a certain program (cmd-list-application)
     *
     * Access: only owners of the program can list the applications
     *
     * @param array args (not used)
     */
    public function get_applications_for_program(array $args)
    {
        // check if is logged in
        if (! $this->mvc->authentication->is_user())
        {
            return;
        }
        // check if user owns the program
        // @todo
    }

    /**
     * Prepares and shows an application details page (cmd-application-details)
     *
     * Access: only owners of the program can see details of the application
     *
     * @param array args (not used)
     */
    public function get_application_details(array $args)
    {
        // check if is logged in
        if (! $this->mvc->authentication->is_user())
        {
            return;
        }
        // check if user owns the program
        // @todo
    }
}
?>