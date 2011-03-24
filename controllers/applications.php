<?php
/**
 * @package com_meego_devprogram
 * @author Ferenc Szekely, http://www.nemein.com
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */
class com_meego_devprogram_controllers_applications
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
     * Prepares and shows the my applications list page (cmd-my-applications)
     *
     * Access: only users can list own applications
     *
     * @param array args (not used)
     */
    public function get_my_applications_list(array $args)
    {
        // check if is logged in
        if (! $this->mvc->authentication->is_user())
        {
            return;
        }

        $myapps = array(
            'name' => 'test1',
            'title' => 'test application',
            'projectid' => 1,
            'projectidea' => 'blablabla',
            'guid' => 'abcdefaqbcdef',
            'details_url' =>  $this->mvc->dispatcher->generate_url
            (
                'my_application_details',
                array('application_guid' => 'abcdefaqbcdef'),
                $this->request
            )
        );
        $this->data['my_applications'][] = $myapps;
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
}

?>