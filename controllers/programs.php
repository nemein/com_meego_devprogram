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
        return midgardmvc_core::get_instance()->dispatcher->generate_url
        (
            'program_read',
            array('program_name' => $this->object->name),
            $this->request
        );
    }

    /**
     * Returns an update url for a program
     */
    public function get_url_update()
    {
        return midgardmvc_core::get_instance()->dispatcher->generate_url
        (
            'my_program_update', array
            (
                'program_name' => $this->object->name
            ),
            $this->request
        );
    }

    /**
     * Loads a form
     */
    public function load_form()
    {
        $this->form = midgardmvc_helper_forms::create('com_meego_devprogram_program');

        $name = $this->form->add_field('name', 'text', true);
        $name->set_value($this->object->name);
        $widget = $name->set_widget('text');
        $widget->set_label($this->mvc->i18n->get('label_name'));

        // @todo: add name tip

        $title = $this->form->add_field('title', 'text', true);
        $title->set_value($this->object->title);
        $widget = $title->set_widget('text');
        $widget->set_label($this->mvc->i18n->get('label_title'));

        // @todo: add title tip

        $summary = $this->form->add_field('description', 'text', true);
        $summary->set_value($this->object->description);
        $widget = $summary->set_widget('textarea');
        $widget->set_label($this->mvc->i18n->get('label_summary'));

        // @todo: add summary tip

        $description = $this->form->add_field('description', 'text', true);
        $description->set_value($this->object->description);
        $widget = $description->set_widget('html');
        $widget->set_label($this->mvc->i18n->get('label_description'));

        // @todo: add description tip

        $devices = com_meego_devprogram_devutils::get_devices();
        foreach ($devices as $device)
        {
            $device_options[] = array
            (
                'description' => $device->name,
                'value' => $device->id
            );
        }
        $device = $this->form->add_field('device', 'integer');
        $device->set_value($this->object->device);
        $widget = $device->set_widget('selectoption');
        $widget->set_label($this->mvc->i18n->get('label_device'));
        $widget->set_options($device_options);

        // @todo: add device tip

        $duedate = $this->form->add_field('duedate', 'datetime', true);
        $object_end = $this->object->duedate;
        if ($object_end->getTimestamp() <= 0)
        {
            $new_end = new DateTime('last day of next month');
            $object_end->setTimestamp($new_end->getTimestamp());
        }
        $duedate->set_value($object_end);
        $widget = $duedate->set_widget('date');
        $widget->set_label($this->mvc->i18n->get('label_duedate'));

        // @todo: add due date tip

        $url = $this->form->add_field('url', 'url', true);
        $url->set_value($this->object->url);
        $widget = $url->set_widget('text');
        $widget->set_label($this->mvc->i18n->get('label_url'));

        // @todo: add url tip
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

        $myprograms = array(
            'name' => 'test1',
            'title' => 'test program',
            'projectid' => 1,
            'projectidea' => 'blablabla',
            'details_url' =>  $this->mvc->dispatcher->generate_url
            (
                'program_read',
                array('program_name' => 'test'),
                $this->request
            )
        );
        $this->data['my_programs'][] = $myprograms;
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
        // set owner flag
        // @todo
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
        $this->data['programs'] = com_meego_devprogram_progutils::get_closed_programs();
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