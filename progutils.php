<?php
/**
 * @package com_meego_devprogram
 * @author Ferenc Szekely, http://www.nemein.com
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */
class com_meego_devprogram_progutils extends com_meego_devprogram_utils
{
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
                if (   $user->is_admin()
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
     * Load a program by its guid or id
     *
     * @param string guid or id of the program
     * @return object an extended com_meego_devprogram_program object
     *                added some useful urls as new properties
     */
    public function get_program_by_id($id = '')
    {
        $program = null;

        if (strlen($id))
        {
            $program = new com_meego_devprogram_program($id);
            $program->read_url = com_meego_devprogram_utils::get_url('program_read', array('program_name' => $program->name));
            $program->update_url = com_meego_devprogram_utils::get_url('program_update', array('program_name' => $program->name));
            $program->delete_url = com_meego_devprogram_utils::get_url('program_delete', array('program_name' => $program->name));
        }

        return $program;
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
                // set some urls, they can come handy
                $program->read_url = com_meego_devprogram_utils::get_url('program_read', array ('program_name' => $program->name));
                $program->update_url = com_meego_devprogram_utils::get_url('program_update', array ('program_name' => $program->name));
                $program->delete_url = com_meego_devprogram_utils::get_url('program_delete', array ('program_name' => $program->name));
                $program->apply_url = com_meego_devprogram_utils::get_url('my_application_create', array ('program_name' => $program->name));
                // reformat the duedate value
                $program->deadline = date('Y-m-d', strtotime($programs[0]->duedate));
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
    private static function get_open_programs_by_creator_guid($guid = '')
    {
        $programs = array();

        if (mgd_is_guid($guid))
        {
            $now = date("Y-m-d H:i:s");

            $storage = new midgard_query_storage('com_meego_devprogram_program');

            $q = new midgard_query_select($storage);

            $qc = new midgard_query_constraint_group('AND');
            $qc->add_constraint(new midgard_query_constraint(
                new midgard_query_property('metadata.creator'),
                '=',
                new midgard_query_value($guid)
            ));
            $qc->add_constraint(new midgard_query_constraint(
                new midgard_query_property('duedate'),
                '>',
                new midgard_query_value($now)
            ));

            $q->set_constraint($qc);
            $q->execute();

            $objects = $q->list_objects();

            if (count($objects))
            {
                $mvc = midgardmvc_core::get_instance();

                foreach ($objects as $object)
                {
                    $object->read_url = com_meego_devprogram_utils::get_url('program_read', array ('program_name' => $object->name));
                    $object->update_url = com_meego_devprogram_utils::get_url('program_update', array ('program_name' => $object->name));
                    $object->delete_url = com_meego_devprogram_utils::get_url('program_delete', array ('program_name' => $object->name));
                    $object->apply_url = com_meego_devprogram_utils::get_url('my_application_create', array ('program_name' => $object->name));

                    $programs[] = $object;
                }
            }
        }

        return $programs;
    }

    /**
     * Retrieves all programs of a given user
     *
     * @param string login name (ie. user name) of the user
     * @return array an array of com_meego_devprogram_program objects
     */
    public static function get_open_programs_of_user($login = '')
    {
        $programs = array();

        if (! strlen($login))
        {
            return $programs;
        }

        // retrieve the user's guid based on the login name
        $user_guid = self::get_guid_of_user($login);

        return self::get_open_programs_by_creator_guid($user_guid);
    }

    /**
     * Retrieves programs of the currently loged in user
     *
     * @return array an array of com_meego_devprogram_programs objects
     *         null if user is not logged in
     */
    public static function get_open_programs_of_current_user()
    {
        // retrieve the user's guid based on the login name
        $user = self::require_login();

        if (! is_object($user))
        {
            return null;
        }

        return self::get_open_programs_by_creator_guid($user->person);
    }

    /**
     * Returns all open device programs
     *
     * @return array array of com_meego_devprogram_program objects
     */
    public static function get_open_programs()
    {
        $programs = false;

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

        $objects = $q->list_objects();

        if (count($objects))
        {
            $mvc = midgardmvc_core::get_instance();

            foreach ($objects as $object)
            {
                $object->read_url = com_meego_devprogram_utils::get_url('program_read', array ('program_name' => $object->name));
                $object->update_url = com_meego_devprogram_utils::get_url('program_update', array ('program_name' => $object->name));
                $object->delete_url = com_meego_devprogram_utils::get_url('program_delete', array ('program_name' => $object->name));
                $object->apply_url = com_meego_devprogram_utils::get_url('my_application_create', array ('program_name' => $object->name));

                $programs[] = $object;
            }
        }

        return $programs;
    }

    /**
     * Returns all closed device programs
     *
     * @param boolean if true then search will include delete records
     * @return array array of com_meego_devprogram_program objects
     */
    public static function get_closed_programs($deleted = false)
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
        $q->include_deleted($deleted);
        $q->execute();

        return $q->list_objects();
    }

    /**
     * Finds open programs that use a given device
     *
     * @param integer id of the device
     * @return boolean true: if device is used; false otherwise
     */
    public function any_open_program_uses_device($id = 0)
    {
        $retval = false;

        if ($id)
        {
            $now = date("Y-m-d H:i:s");

            $storage = new midgard_query_storage('com_meego_devprogram_program');

            $q = new midgard_query_select($storage);

            $qc = new midgard_query_constraint_group('AND');
            $qc->add_constraint(new midgard_query_constraint(
                new midgard_query_property('duedate'),
                '>',
                new midgard_query_value($now)
            ));
            $qc->add_constraint(new midgard_query_constraint(
                new midgard_query_property('device'),
                '=',
                new midgard_query_value($id)
            ));

            $q->set_constraint($qc);
            $q->execute();

            if ($q->get_results_count())
            {
                $retval = true;
            }
        }

        return $retval;
    }

    /**
     * Deletes all expired programs
     * A program is expired if its due date has passed
     */
    public function delete_expired_programs()
    {
        $programs = self::get_closed_programs();

        foreach($programs as $program)
        {
            $program->delete();
        }
    }
}