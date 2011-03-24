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
        $this->object = new com_meego_devprogram_application($args['application_guid']);

        midgardmvc_core::get_instance()->head->set_title($this->object->title);
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
        return midgardmvc_core::get_instance()->dispatcher->generate_url
        (
            'my_application_details',
            array
            (
                'application_guid' => $this->object->guid
            ),
            $this->request
        );
    }

    /**
     * Returns an update url for an application
     */
    public function get_url_update()
    {
        return midgardmvc_core::get_instance()->dispatcher->generate_url
        (
            'my_application_update', array
            (
                'application_guid' => $this->object->guid
            ),
            $this->request
        );
    }

    /**
     * Loads a form
     */
    public function load_form()
    {
        $this->form = midgardmvc_helper_forms_mgdschema::create($this->object, false);
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
        $this->data['my_applications'] = com_meego_devprogram_utils::get_applications_of_current_user();
    }

    /**
     * Prepares and shows the my application details page (cmd-my-application-details)
     *
     * Access: only owners of the application can see details
     *
     * @param array args (not used)
     */
    public function get_my_application_details(array $args)
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
     * Prepares and shows the my application update page (cmd-my-application-update)
     *
     * Access: only owners of the application can see details
     *
     * @param array args (not used)
     */
    public function get_my_application_update(array $args)
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