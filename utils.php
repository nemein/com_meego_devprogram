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
     * Checks if the user is the owner of the program (or an admin of the site)
     * If user is not logged in then it will redirect to login page
     *
     * @param string name of the program
     * @return boolean true if user is owner, false otherwise
     */
    public static function is_owner_of_program($name = '')
    {
        $retval = false;

        $user = self::require_login();

        if (is_object($user))
        {
            $program = self::get_program_by_name($name);
            if ($program)
            {
                // do the check
                if (   $mvc->authentication->is_admin()
                    || $program->metadata_creator == $user->person_guid)
                {
                    // for owners and admins return true
                    $retval = true;
                }
            }
        }

        return $retval;
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

    /**
     * Loads a program by its name
     *
     * Names are unique in the program table
     *
     * @param string name of the program
     * @return object com_meego_devprogram_program object
     */
    public static function get_program_by_name($name = '')
    {
        $program = null;

        if (strlen($name))
        {
            $storage = new midgard_query_storage('com_meego_devprogram_program');

            $q = new midgard_query_select($storage);

            $qc = new midgard_query_constraint(
                new midgard_query_property('name'),
                '=',
                new midgard_query_value($name)
            );

            $q->set_constraint($qc);
            $q->execute();

            $programs = $q->list_objects();

            if (count($programs))
            {
                $program = new com_meego_devprogram_program($programs[0]->guid);
            }
        }

        return $program;
    }

    /**
     * Retrieves programs created by user having a certain guid
     *
     * @param guid guid of the user
     * @return array an array of com_meego_devprogram_program objects
     */
    private static function get_programs_by_creator_guid($guid = '')
    {
        $programs = array();

        if (mgd_is_guid($guid))
        {
            $storage = new midgard_query_storage('com_meego_devprogram_program');

            $q = new midgard_query_select($storage);

            $qc = new midgard_query_constraint(
                new midgard_query_property('metadata.creator'),
                '=',
                new midgard_query_value($guid)
            );

            $q->set_constraint($qc);
            $q->execute();

            $programs = $q->list_objects();
        }

        return $programs;
    }

    /**
     * Retrieves all programs of a given user
     *
     * @param string login name (ie. user name) of the user
     * @return array an array of com_meego_devprogram_program objects
     */
    public static function get_programs_of_user($login = '')
    {
        $programs = array();

        if (! strlen($login))
        {
            return $programs;
        }

        // retrieve the user's guid based on the login name
        $user_guid = self::get_guid_of_user($login);

        return self::get_programs_by_creator_guid($user_guid);
    }

    /**
     * Retrieves programs of the currently loged in user
     *
     * @return array an array of com_meego_devprogram_programs objects
     *         null if user is not logged in
     */
    public static function get_programs_of_current_user()
    {
        // retrieve the user's guid based on the login name
        $user = self::require_login();

        if (! is_object($user))
        {
            return null;
        }

        return self::get_programs_by_creator_guid($user->guid);
    }

    /**
     * Returns all open device programs
     *
     * @return array array of com_meego_devprogram_program objects
     */
    public static function get_open_programs()
    {
        $now = date("Y-m-d H:i:s");

        $storage = new midgard_query_storage('com_meego_devprogram_program');

        $q = new midgard_query_select($storage);

        $qc = new midgard_query_constraint(
            new midgard_query_property('duedate'),
            '>',
            new midgard_query_value($now)
        );

        $q->set_constraint($qc);
        $q->execute();

        return $q->list_objects();
    }

    /**
     * Returns all closed device programs
     *
     * @return array array of com_meego_devprogram_program objects
     */
    public static function get_closed_programs()
    {
        $now = date("Y-m-d H:i:s");

        $storage = new midgard_query_storage('com_meego_devprogram_program');

        $q = new midgard_query_select($storage);

        $qc = new midgard_query_constraint(
            new midgard_query_property('duedate'),
            '<',
            new midgard_query_value($now)
        );

        $q->set_constraint($qc);
        $q->execute();

        return $q->list_objects();
    }

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

        return self::get_applications_by_creator_guid($user->guid);
    }

    /**
     * Retrieves all devices
     *
     * @return array array of com_meego_devprogram_device objects
     */
    public static function get_devices()
    {
        $storage = new midgard_query_storage('com_meego_devprogram_device');

        $q = new midgard_query_select($storage);
        $q->execute();

        return $q->list_objects();
    }
}