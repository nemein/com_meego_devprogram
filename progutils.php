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
    private static function extend_program($object = null)
    {
        // q->toggle_readonly(false) does not work so we need a new object
        $program = new com_meego_devprogram_program($object->guid);

        // set some urls, they can come handy
        $program->open_programs_url = com_meego_devprogram_utils::get_url('open_programs', array ());
        $program->read_url = com_meego_devprogram_utils::get_url('program_read', array ('program_name' => $program->name));
        $program->update_url = com_meego_devprogram_utils::get_url('program_update', array ('program_name' => $program->name));
        $program->delete_url = com_meego_devprogram_utils::get_url('program_delete', array ('program_name' => $program->name));
        $program->apply_url = com_meego_devprogram_utils::get_url('my_application_create', array ('program_name' => $program->name));

        // reformat the duedate value so that templates and controllers don't have to bother
        $program->deadline = date('Y-m-d', strtotime($program->duedate));

        // create a placeholder for applications to this program by the curent user
        $program->myapps = array();

        // if current user is owner then we can add more goodies
        $user = com_meego_devprogram_utils::get_current_user();

        if (is_object($user))
        {
            $program->read_my_url = com_meego_devprogram_utils::get_url('my_program_read', array ('program_name' => $program->name));
            $program->list_apps_url = com_meego_devprogram_utils::get_url('program_applications', array ('program_name' => $program->name));
            // set the number of apps (all but the cancelled ones) under this program
            $program->number_of_applications = com_meego_devprogram_apputils::get_count_applications_by_program($program->id);

            // gather all apps of this user to this program
            //$program->myapps = com_meego_devprogram_apputils::get_applications_of_current_user($object->id);
        }

        // get the provider of the device and set a new property to the program
        $devices = com_meego_devprogram_devutils::get_devices(array('id' => $program->device));
        $providers = com_meego_devprogram_provutils::get_providers(array('guid' => $devices[0]->providerobject->guid));

        $program->provider = $providers[0];

        // by default everybody can apply
        $program->can_apply = true;

        // current date and time
        $now = new DateTime();

        if ($object->duedate < $now)
        {
            // set the flag so we can show a user friendly status box
            $program->closed = true;
        }

        if (   com_meego_devprogram_utils::is_current_user_creator($object)
            || (   count($program->myapps)
                && ! $object->multiple)
            || ($object->duedate < $now))
        {
            // owners of a program or admins should not apply for that program
            // or
            // if user applied then we disable further applications
            // unless the program accepts multiple entries from the same person
            $program->can_apply = false;
        }

        return $program;
    }

    /**
     * Load a program by its guid or id
     *
     * @param string guid or id of the program
     * @return object an extended com_meego_devprogram_program object
     *                added some useful urls as new properties
     */
    public function get_program_by_id($id = 0)
    {
        $program = null;

        if ($id)
        {
            $program = self::get_programs(array('id' => $id));
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
        $programs = array();

        $storage = new midgard_query_storage('com_meego_devprogram_program');

        $q = new midgard_query_select($storage);

        if (count($filters) > 1)
        {
            $qc = new midgard_query_constraint_group('AND');
        }

        foreach ($filters as $filter => $value)
        {
            switch ($filter)
            {
                case 'deleted':
                    if ($value)
                    {
                        $q->include_deleted(true);
                    }
                    break;
                case 'creator':
                    // check if the value is a real guid
                    if (mgd_is_guid($value))
                    {
                        $constraint = new midgard_query_constraint(
                            new midgard_query_property('metadata.creator'),
                            '=',
                            new midgard_query_value($value)
                        );
                    }
                    break;
                case 'id':
                case 'name':
                case 'device':
                    $constraint = new midgard_query_constraint(
                        new midgard_query_property($filter),
                        '=',
                        new midgard_query_value($value)
                    );
                    break;
                case 'status':
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
                    break;
                case 'daysleft':
                    // duedate < now + value
                    $now = new DateTime();
                    $limit = $now->add(new DateInterval('P' . $value . 'D'));

                    $constraint = new midgard_query_constraint(
                        new midgard_query_property('duedate'),
                        '<',
                        new midgard_query_value($limit->format('Y-m-d'))
                    );

                    break;
            }
            // set the constraint
            (count($filters) > 1) ? $qc->add_constraint($constraint) : $qc = $constraint;
        }

        $q->set_constraint($qc);

        $q->add_order(new midgard_query_property('metadata.created'), SORT_DESC);

        $q->execute();

        // does not seem to work
        // @bug: $q->toggle_read_only(false);

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
        $programs = array();

        // retrieve the user's guid based on the login name
        $user = self::require_login();

        if (! is_object($user))
        {
            return null;
        }

        // all devices from this user and by fellow members of the same provider
        $memberships = com_meego_devprogram_membutils::get_memberships_of_current_user();

        foreach($memberships as $membership)
        {
            // check status
            if ($membership->status == CMD_MEMBERSHIP_APPROVED)
            {
                // get device objects
                $devices = com_meego_devprogram_devutils::get_devices(array('provider' => $membership->provider));

                // find all programs belonging all devices found before
                foreach ($devices as $device)
                {
                    $filters = array('status' => CMD_PROGRAM_OPEN, 'device' => $device->id);
                    $programs = array_merge($programs, self::get_programs($filters));
                }
            }
        }

        return $programs;
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
        $programs = self::get_programs(array('status' => CMD_PROGRAM_CLOSED));

        foreach($programs as $program)
        {
            $program->delete();
        }
    }

    /**
     * Retrieves the latest program
     *
     * @return object extended com_meego_devprogram_program object
     */
    public function get_latest_program()
    {
        $program = null;
        $programs = self::get_programs(array('status' => CMD_PROGRAM_OPEN));

        if (count($programs))
        {
            $program = array_shift($programs);
        }

        return $program;
    }

    /**
     * Retrieves the programs which are soon to be closed
     * The days left for closing can be configured
     *
     * @return object extended com_meego_devprogram_program object
     */
    public function get_closing_programs($daysleft = 0)
    {
        $mvc = midgardmvc_core::get_instance();

        if (! $daysleft)
        {
            $daysleft = $mvc->configuration->daysleft;
        }

        $programs = self::get_programs(array('status' => CMD_PROGRAM_OPEN, 'daysleft' => $daysleft));

        return $programs;
    }
}
