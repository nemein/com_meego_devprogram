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
     * Adds some handy url properties to an application object
     *
     * @param object com_meego_devprogram_application object
     * @return object extended com_meego_devprogram_application object
     */
    private static function application_with_urls($application = null)
    {
        if ($application)
        {
            $application->read_url = com_meego_devprogram_utils::get_url('my_application_read', array('application_guid' => $application->guid));
            $application->update_url = com_meego_devprogram_utils::get_url('my_application_update', array('application_guid' => $application->guid));
            $application->delete_url = com_meego_devprogram_utils::get_url('my_application_delete', array('application_guid' => $application->guid));
            $application->judge_url = com_meego_devprogram_utils::get_url('application_judge', array('application_guid' => $application->guid));

            return $application;
        }
    }

    /**
     * Retrives a certain application specified by its guid
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
            $application = self::application_with_urls($application);
        }

        return $application;
    }

    /**
     * Retrieves applications using various filters
     *
     * @param array filters array
     *
     * @return array an array of com_meego_devprogram_application objects
     *               extended with some handy urls
     */
    private static function get_applications(array $filters)
    {
        $applications = array();

        $storage = new midgard_query_storage('com_meego_devprogram_application');
        $q = new midgard_query_select($storage);

        if (   count($filters) > 1
            || (count($filters) == 1
               && ! array_key_exists('status', $filters)))
        {
            $qc = new midgard_query_constraint_group('AND');
        }

        foreach ($filters as $filter => $value)
        {
            switch ($filter) {
                case 'creator':
                    // check if creator guid is valid
                    if (mgd_is_guid($value))
                    {
                        $constraint = new midgard_query_constraint(
                            new midgard_query_property('metadata.creator'),
                            '=',
                            new midgard_query_value($value)
                        );
                    }
                    break;
                case 'program':
                case 'status':
                    $constraint = new midgard_query_constraint(
                        new midgard_query_property($filter),
                        '=',
                        new midgard_query_value($value)
                    );
                    break;
            }

            // set the constraint
            if (is_a($qc, 'midgard_query_constraint_group'))
            {
                $qc->add_constraint($constraint);
            }
            else
            {
                $qc = $constraint;
            }
        }

        if (! array_key_exists('status', $filters))
        {
            // if status was not set explicitly then include all but cancelled applications
            $constraint = new midgard_query_constraint(
                new midgard_query_property('status'),
                '<',
                new midgard_query_value(CMD_APPLICATION_CANCELLED)
            );

            // set the constraint
            if (count($filters) >= 1)
            {
                if (! is_a($qc, 'midgard_query_constraint_group'))
                {
                    $qc = new midgard_query_constraint_group('AND');
                }
                $qc->add_constraint($constraint);
            }
            else
            {
                $qc = $constraint;
            }
        }

        $q->set_constraint($qc);
        $q->execute();

        $objects = $q->list_objects();

        foreach ($objects as $object)
        {
            $applications[] = self::application_with_urls($object);
        }

        return $applications;
    }

    /**
     * Retrieves all but cancelled applications of the given user
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

        $filters = array('creator' => $user->person, 'program' => $program_id);

        return self::get_applications($filters);
    }

    /**
     * Retrieves all but cancelled applications of the currently loged in user
     *
     * @param integer optional parameter to specify a concrete program
     * @return array an array of com_meego_devprogram_application objects
     *         null if user is not logged in
     */
    public static function get_applications_of_current_user($program_id = null)
    {
        $user = com_meego_devprogram_utils::get_current_user();

        if ($user)
        {
            $filters = array('creator' => $user->person, 'program' => $program_id);

            return self::get_applications($filters);
        }
    }

    /**
     * Retrieves all but cancelled applications by program
     *
     * @param integer id of the program
     */
    public static function get_applications_by_program($program_id = '')
    {
        $filters = array('program' => $program_id);

        return self::get_applications($filters);
    }
}