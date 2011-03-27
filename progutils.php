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
     * Adds some handy properties to program object
     *
     * @param object com_meego_devprogram_program object
     * @return object extended com_meego_devprogram_program object
     */
    private static function extend_program($program = null)
    {
        // set some urls, they can come handy
        $program->read_url = com_meego_devprogram_utils::get_url('program_read', array ('program_name' => $program->name));
        $program->update_url = com_meego_devprogram_utils::get_url('program_update', array ('program_name' => $program->name));
        $program->delete_url = com_meego_devprogram_utils::get_url('program_delete', array ('program_name' => $program->name));
        $program->apply_url = com_meego_devprogram_utils::get_url('my_application_create', array ('program_name' => $program->name));
        // reformat the duedate value
        $program->deadline = date('Y-m-d', strtotime($program->duedate));

        return $program;
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
            $program = self::extend_program(new com_meego_devprogram_program($id));
        }

        return $program;
    }

    /**
     * Retrieves programs using various filters
     *
     * @param array filters, possible members: name, creator, status
     *              if multiple members used then we do a logical AND with them
     * @return object com_meego_devprogram_program object
     */
    public static function get_programs(array $filters)
    {
        $programs = null;

        $storage = new midgard_query_storage('com_meego_devprogram_program');

        $q = new midgard_query_select($storage);

        if (count($filters) > 1)
        {
            $qc = new midgard_query_constraint_group('AND');
        }

        foreach ($filters as $filter => $value)
        {
            // include deleted items
            if (   $filter == 'deleted'
                && $value)
            {
                $q->include_deleted(true);
            }

            // check for creator filter
            if ($filter == 'creator')
            {
                // check if the value is a real guid
                if (mgd_is_guid($value))
                {
                    $constraint = new midgard_query_constraint(
                        new midgard_query_property('metadata.creator'),
                        '=',
                        new midgard_query_value($value)
                    );
                }
            }

            // check for name filter
            if ($filter == 'name')
            {
                $constraint = new midgard_query_constraint(
                    new midgard_query_property('name'),
                    '=',
                    new midgard_query_value($value)
                );
            }

            // check for device filter
            if ($filter == 'device')
            {
                $constraint = new midgard_query_constraint(
                    new midgard_query_property('device'),
                    '=',
                    new midgard_query_value($value)
                );
            }

            // check for status filter
            if ($filter == 'status')
            {
                // current date and time
                $now = date("Y-m-d H:i:s");

                switch ($value) {
                    case CMD_PROGRAM_CLOSED:
                        $type = '<';
                        break;
                    case CMD_PROGRAM_OPEN:
                    default:
                        $type = '>';
                }
                $constraint = new midgard_query_constraint(
                    new midgard_query_property('duedate'),
                    $type,
                    new midgard_query_value($now)
                );
            }

            // set the constraint
            (count($filters) > 1) ? $qc->add_constraint($constraint) : $qc = $constraint;
        }

        $q->set_constraint($qc);
        $q->execute();

        $objects = $q->list_objects();

        if (count($objects))
        {
            foreach ($objects as $object)
            {
                $programs[] = self::extend_program($object);
            }
        }

        return $programs;
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
            $programs = self::get_programs(array('name' => $name));

            if (   is_array($programs)
                && isset($programs[0]))
            {
                // do the creator check
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
        $guid = self::get_guid_of_user($login);

        $filters = array('status' => CMD_PROGRAM_OPEN, 'creator' => $guid);

        return self::get_programs($filters);
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

        $filters = array('status' => CMD_PROGRAM_OPEN, 'creator' => $user->person);

        return self::get_programs($filters);
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
            $filters = array('status' => CMD_PROGRAM_OPEN, 'device' => $id);
            $programs = self::get_programs($filters);

            if (count($programs))
            {
                $retval = true;
            }

            unset($filters, $programs);
        }

        return $retval;
    }

    /**
     * Deletes all expired programs
     * A program is expired if its due date has passed
     */
    public function delete_expired_programs()
    {
        $programs = get_programs(array('status' => CMD_PROGRAM_CLOSED));

        foreach($programs as $program)
        {
            $program->delete();
        }
    }
}