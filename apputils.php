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
     * Extends an application object with some handy properties
     *
     * @param object com_meego_devprogram_application object
     * @return object extended com_meego_devprogram_application object
     */
    private static function extend_application($application = null)
    {
        if ($application)
        {
            // some urls
            $application->read_url = com_meego_devprogram_utils::get_url('my_application_read', array('application_guid' => $application->guid));
            $application->update_url = com_meego_devprogram_utils::get_url('my_application_update', array('application_guid' => $application->guid));
            $application->delete_url = com_meego_devprogram_utils::get_url('my_application_delete', array('application_guid' => $application->guid));
            $application->judge_url = com_meego_devprogram_utils::get_url('application_judge', array('application_guid' => $application->guid));

            // submitter's username
            $user = com_meego_devprogram_utils::get_user_by_person_guid($application->metadata->creator);

            if (is_object($user))
            {
                $application->submitter = $user->login;
            }

            // human readable decision state of the application
            $mvc = midgardmvc_core::get_instance();

            switch ($application->status)
            {
                case CMD_APPLICATION_PENDING:
                    $state = $mvc->i18n->get('label_application_state_pending');
                    $css = 'pending';
                    break;
                case CMD_APPLICATION_APPROVED:
                    $state = $mvc->i18n->get('label_application_state_approved');
                    $css = 'approved';
                    break;
                case CMD_APPLICATION_MOREINFO:
                    $state = $mvc->i18n->get('label_application_state_moreinfo');
                    $css = 'moreinfo';
                    break;
                case CMD_APPLICATION_CANCELLED:
                    $state = $mvc->i18n->get('label_application_state_cancelled');
                    $css = 'cancelled';
                    break;
                case CMD_APPLICATION_DENIED:
                    $state = $mvc->i18n->get('label_application_state_denied');
                    $css = 'denied';
                    break;
                default:
                    $state = $mvc->i18n->get('label_application_state_pending');
                    $css = 'pending';
            }

            $application->decision = ucfirst($state);
            $application->state_css = $css;

            // now check if the applicant has actually updated since the last decision of the program owner
            $application->has_update = false;

            if (   $application->status == 1
                && $application->metadata->revisor == $application->metadata->creator)
            {
                $application->has_update = true;
            }

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
            $application = self::extend_application($application);
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
                    if ($value)
                    {
                        $constraint = new midgard_query_constraint(
                            new midgard_query_property($filter),
                            '=',
                            new midgard_query_value($value)
                        );
                    }
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
            $applications[] = self::extend_application($object);
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
    public static function get_applications_by_program($program_id = null)
    {
        $filters = array('program' => $program_id);

        return self::get_applications($filters);
    }
}