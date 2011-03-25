<?php
/**
 * @package com_meego_devprogram
 * @author Ferenc Szekely, http://www.nemein.com
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */
class com_meego_devprogram_apputils extends com_meego_devprogram_utils
{
    /**
     * Retrieves applications created by user having guid
     *
     * @param guid guid of the user
     * @return array an array of com_meego_devprogram_application objects
     */
    private static function get_applications_by_creator_guid($guid = '')
    {
        $applications = array();

        if (mgd_is_guid($guid))
        {
            $storage = new midgard_query_storage('com_meego_devprogram_application');

            $q = new midgard_query_select($storage);

            $qc = new midgard_query_constraint_group('AND');

            $qc->add_constraint(new midgard_query_constraint(
                new midgard_query_property('metadata.creator'),
                '=',
                new midgard_query_value($guid)
            ));
            $qc->add_constraint(new midgard_query_constraint(
                new midgard_query_property('status'),
                '<',
                new midgard_query_value(CMD_APPLICATION_CANCELLED)
            ));

            $q->set_constraint($qc);
            $q->execute();

            $applications = $q->list_objects();
        }

        return $applications;
    }

    /**
     * Retrieves all open (ie. not accepted, nor declined) applications of the
     * given user
     *
     * @param string login name (ie. user name) of the user
     * @return array an array of com_meego_devprogram_application objects
     */
    public static function get_applications_of_user($login = '')
    {
        $applications = array();

        if (! strlen($login))
        {
            return $applications;
        }

        // retrieve the user's guid based on the login name
        $user_guid = self::get_guid_of_user($login);

        return self::get_applications_by_creator_guid($user_guid);
    }

    /**
     * Retrieves applications of the currently loged in user
     *
     * @return array an array of com_meego_devprogram_application objects
     *         null if user is not logged in
     */
    public static function get_applications_of_current_user()
    {
        // retrieve the user's guid based on the login name
        $user = self::require_login();

        if (! is_object($user))
        {
            return null;
        }

        return self::get_applications_by_creator_guid($user->person);
    }
}