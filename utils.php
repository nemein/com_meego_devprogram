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
     * If not logged then redirect to the login page otherwise return user
     * object
     *
     * @return object midgard_user object
     */
    public static function require_login()
    {
        $mvc = midgardmvc_core::get_instance();

        if (! $mvc->authentication->is_user())
        {
            $login_url = '/mgd:login';
            $mvc->head->relocate($login_url);
        }

        return $mvc->authentication->get_user();
    }

    /**
     * Retrieves the user guid of the user specifie by login name
     *
     * @param string login name (ie. user name) stored in midgard_user table
     * @return guid guid of the user
     */
    public static function get_guid_of_user($login = '')
    {
        $guid = null;

        $qb = new midgard_query_builder('midgard_user');
        $qb->add_constraint('login', '=', $login);

        $users = $qb->execute();

        if (count($users))
        {
            $guid = $users[0]->person_guid;
        }

        return $guid;
    }
}