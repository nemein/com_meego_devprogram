<?php
/**
 * @package com_meego_devprogram
 * @author Ferenc Szekely, http://www.nemein.com
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */
class com_meego_devprogram_membutils extends com_meego_devprogram_utils
{
    /**
     * Extends an membership object with some handy properties
     *
     * @param object com_meego_devprogram_provider_membership object
     * @return object extended com_meego_devprogram_provider_membership object
     */
    private static function extend_membership($object = null)
    {
        $membership = new com_meego_devprogram_provider_membership($object->guid);

        if ($membership)
        {
            // some urls
            $membership->read_url = com_meego_devprogram_utils::get_url('my_membership_read', array('membership_guid' => $membership->guid));
            $membership->update_url = com_meego_devprogram_utils::get_url('my_membership_update', array('membership_guid' => $membership->guid));
            $membership->delete_url = com_meego_devprogram_utils::get_url('my_membership_delete', array('membership_guid' => $membership->guid));
            $membership->judge_url = com_meego_devprogram_utils::get_url('membership_judge', array('membership_guid' => $membership->guid));

            // submitter's username
            $user = com_meego_devprogram_utils::get_user_by_person_guid($membership->person);

            if (is_object($user))
            {
                // the login name of the person requested membership
                $membership->submitter = $user->login;
            }

            // pretty name of the provider
            $provider = new com_meego_devprogram_provider($membership->provider);
            $membership->providertitle = $provider->title;

            // human readable decision state of the application
            $mvc = midgardmvc_core::get_instance();

            switch ($membership->status)
            {
                case CMD_MEMBERSHIP_PENDING:
                    $state = $mvc->i18n->get('label_membership_state_pending');
                    $css = 'pending';
                    break;
                case CMD_MEMBERSHIP_APPROVED:
                    $state = $mvc->i18n->get('label_membership_state_approved');
                    $css = 'approved';
                    break;
                case CMD_MEMBERSHIP_MOREINFO:
                    $state = $mvc->i18n->get('label_membership_state_moreinfo');
                    $css = 'moreinfo';
                    break;
                case CMD_MEMBERSHIP_CANCELLED:
                    $state = $mvc->i18n->get('label_membership_state_cancelled');
                    $css = 'cancelled';
                    break;
                case CMD_MEMBERSHIP_DENIED:
                    $state = $mvc->i18n->get('label_membership_state_denied');
                    $css = 'denied';
                    break;
                default:
                    $state = $mvc->i18n->get('label_membership_state_pending');
                    $css = 'pending';
            }

            $membership->decision = ucfirst($state);
            $membership->state_css = $css;

            // now check if the applicant has actually updated since the last decision of the provider owner
            $membership->has_update = false;

            if (   $membership->status == 1
                && $membership->metadata->revisor == $membership->metadata->creator)
            {
                $membership->has_update = true;
            }

            return $membership;
        }
    }

    /**
     * Retrives a certain membership specified by its guid
     *
     * @param guid guid of the application
     * @return object an extended com_meego_devprogram_application object
     *                added some useful urls as new properties
     */
    public function get_membership_by_guid($guid = '')
    {
        $membership = null;

        if (mgd_is_guid($guid))
        {
            $membership = new com_meego_devprogram_provider_membership($guid);
            $membership = self::extend_membership($membership);
        }

        return $membership;
    }

    /**
     * Retrieves membership using various filters
     *
     * @param array filters array
     *
     * @return array an array of com_meego_devprogram_provider_membership objects
     *               extended with some handy properties
     */
    private static function get_memberships(array $filters)
    {
        $memberships = array();

        $storage = new midgard_query_storage('com_meego_devprogram_provider_membership');
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
                case 'provider':
                case 'person':
                    if ($value)
                    {
                        $constraint = new midgard_query_constraint(
                            new midgard_query_property($filter),
                            '=',
                            new midgard_query_value($value)
                        );
                    }
                    break;
                case 'status':
                    if ($value)
                    {
                        $constraint = new midgard_query_constraint(
                            new midgard_query_property($filter),
                            '<',
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
            // if status was not set explicitly then include all but cancelled memberships
            $constraint = new midgard_query_constraint(
                new midgard_query_property('status'),
                '<',
                new midgard_query_value(CMD_MEMBERSHIP_CANCELLED)
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

        // does not seem to work
        // @bug: $q->toggle_read_only(false);

        $objects = $q->list_objects();

        foreach ($objects as $object)
        {
            $memberships[] = self::extend_membership($object);
        }

        return $memberships;
    }

    /**
     * Retrieves all but cancelled memberships of the given user
     *
     * @param string login name (ie. user name) of the user
     * @param integer optional parameter to specify a concrete provider
     * @return array an array of com_meego_devprogram_provider_membership objects
     */
    public static function get_memberships_of_user($login = '', $provider_id = 0)
    {
        $memberships = array();

        if (! strlen($login))
        {
            return $memberships;
        }

        // retrieve the user's guid based on the login name
        $user_guid = self::get_guid_of_user($login);

        $filters = array('person' => $user->person, 'provider' => $program_id);

        return self::get_memberships($filters);
    }

    /**
     * Retrieves all but cancelled memberships of the currently loged in user
     *
     * @param integer optional parameter to specify a concrete provider
     * @param integer optional parameter to specify a status of membership
     *                the status of the membership must be less than this value
     *
     * @return array an array of com_meego_devprogram_provider_membership objects
     *         null if user is not logged in
     */
    public static function get_memberships_of_current_user($provider_id = null, $status = null)
    {
        $user = com_meego_devprogram_utils::get_current_user();

        if ($user)
        {
            $filters = array('person' => $user->person, 'provider' => $provider_id, 'status' => $status);

            return self::get_memberships($filters);
        }
    }

    /**
     * Retrieves all but cancelled memberships by provider
     *
     * @param integer id of the program
     */
    public static function get_memberships_by_provider($provider_id = null)
    {
        $filters = array('provider' => $provider_id);

        return self::get_memberships($filters);
    }

    /**
     * Determines if the curtent user is a member of the given provider
     *
     * @param int the ID of the provider
     * @return boolean
     */
    public function is_current_user_member_of_provider($provider_id = null)
    {
        $retval = false;
        $memberships = self::get_memberships_of_current_user($provider_id, CMD_MEMBERSHIP_DENIED);

        if (count($memberships))
        {
            $retval = true;
        }

        return $retval;
    }
}
