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
    }

    /**
     * Prepares and shows the my applications list page (cmd-my-applications)
     *
     * @param array args (not used)
     */
    public function get_mylist(array $args)
    {
        $myapps = array(
            'name' => 'test',
            'projectid' => 1,
            'projectidea' => 'blablabla',
            'details_url' =>  $this->mvc->dispatcher->generate_url(
                'myapplication_details',
                array('application_name' => 'test'),
                $this->request
            )
        );
        $this->data['my_applications'][] = $myapps;
    }

    /**
     * Prepares and shows the my application details page (cmd-my-application-details)
     *
     * @param array args (not used)
     */
    public function get_mydetails(array $args)
    {
    }
}

?>