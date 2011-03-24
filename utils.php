<?php
/**
 * @package com_meego_devprogram
 * @author Ferenc Szekely, http://www.nemein.com
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */
 class com_meego_devprogram_utils
{
    /**
     * Requires a user to be logged in
     * If not logged in then displays a message a provides a link
     */
    public function require_login()
    {
        $mvc = midgardmvc_core::get_instance();

        if (! $mvc->authentication->is_user())
        {
            $login_url = '/mgd:login';
            $mvc->head->relocate($login_url);
        }
    }
}