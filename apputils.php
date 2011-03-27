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
     * @param guid guid of the creator user
     * @param integer optional parameter for a program id
     *
     * @return array an array of com_meego_devprogram_application objects
     */
    private static function get_applications_by_creator($guid = '', $program_id = 0)
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
            if ($program_id)
            {
                $qc->add_constraint(new midgard_query_constraint(
                    new midgard_query_property('program'),
                    '=',
                    new midgard_query_value($program_id)
                ));
            }

            $q->set_constraint($qc);
            $q->execute();

            $objects = $q->list_objects();

            foreach ($objects as $object)
            {
                $object->read_url = com_meego_devprogram_utils::get_url('my_application_read', array('application_guid' => $object->guid));
                $object->update_url = com_meego_devprogram_utils::get_url('my_application_update', array('application_guid' => $object->guid));
                $object->delete_url = com_meego_devprogram_utils::get_url('my_application_delete', array('application_guid' => $object->guid));
                $object->judge_url = com_meego_devprogram_utils::get_url('application_judge', array('application_guid' => $object->guid));

                $applications[] = $object;
            }

        }

        return $applications;
    }

    /**
     * Retrieves all open (ie. not accepted, nor declined) applications of the
     * given user
     *
     * @param string login name (ie. user name) of the user
     * @param integer optional parameter to specify a concrete program
     * @return array an array of com_meego_devprogram_application objects
     */
    public static function get_applications_of_user($login = '', $program_id = 0)
    {
        $applications = array();

        if (! strlen($login))
        {
            return $applications;
        }

        // retrieve the user's guid based on the login name
        $user_guid = self::get_guid_of_user($login);

        return self::get_applications_by_creator($user->person, $program_id);
    }

    /**
     * Retrieves applications of the currently loged in user
     *
     * @param integer optional parameter to specify a concrete program
     * @return array an array of com_meego_devprogram_application objects
     *         null if user is not logged in
     */
    public static function get_applications_of_current_user($program_id = 0)
    {
        // retrieve the user's guid based on the login name
        $user = self::require_login();

        if (! is_object($user))
        {
            return null;
        }

        return self::get_applications_by_creator($user->person, $program_id);
    }

    /**
     * Retrives an application specified by a guid
     *
     * @param guid guid of the application
     * @return object an extended com_meego_devprogram_application object
     *                added some useful urls as new properties
     */
    public function get_application_by_guid($guid = '')
    {
        $application = null;

        if (mgd_is_guid($guid))
        {
            $application = new com_meego_devprogram_application($guid);
            $application->read_url = com_meego_devprogram_utils::get_url('my_application_read', array('application_guid' => $guid));
            $application->update_url = com_meego_devprogram_utils::get_url('my_application_update', array('application_guid' => $guid));
            $application->delete_url = com_meego_devprogram_utils::get_url('my_application_delete', array('application_guid' => $guid));
            $application->judge_url = com_meego_devprogram_utils::get_url('application_judge', array('application_guid' => $guid));
        }

        return $application;
    }
}