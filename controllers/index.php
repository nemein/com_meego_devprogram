<?php
/**
 * @package com_meego_devprogram
 * @author Ferenc Szekely, http://www.nemein.com
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */
class com_meego_devprogram_controllers_index
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
    }

    /**
     * Prepares and shows the index page (cmd-index)
     *
     * @param array args (not used)
     */
    public function get_index(array $args)
    {
        $this->data['latest'] = com_meego_devprogram_progutils::get_latest_program();
        $this->data['closing'] = com_meego_devprogram_progutils::get_closing_programs();
    }
}

?>