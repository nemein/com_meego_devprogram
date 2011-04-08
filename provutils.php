<?php
/**
 * @package com_meego_devprogram
 * @author Ferenc Szekely, http://www.nemein.com
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */
class com_meego_devprogram_provutils extends com_meego_devprogram_utils
{
    /**
     * Adds some handy properties to provider object
     *
     * @param object com_meego_devprogram_provider object
     * @return object extended com_meego_devprogram_provider object
     */
    private function extend_provider($object = null)
    {
        // q->toggle_readonly(false) does not work so we need a new object
        $provider = new com_meego_devprogram_provider($object->guid);

        $provider->read_url = com_meego_devprogram_utils::get_url('provider_read', array ('provider_name' => $provider->name));
        $provider->update_url = com_meego_devprogram_utils::get_url('provider_update', array ('provider_name' => $provider->name));
        $provider->delete_url = com_meego_devprogram_utils::get_url('provider_delete', array ('provider_name' => $provider->name));
        $provider->join_url = com_meego_devprogram_utils::get_url('my_membership_create', array ('provider_name' => $provider->name));

        // if current user is owner then we can add more goodies
        $user = com_meego_devprogram_utils::get_current_user();

        if (is_object($user))
        {
            if ($provider->metadata->creator == $user->person)
            {
                $provider->list_memberships_url = com_meego_devprogram_utils::get_url('provider_members', array ('provider_name' => $provider->name));
                // set the number of members (all but the cancelled ones) of this provider
                $provider->number_of_members = count(com_meego_devprogram_membutils::get_memberships_by_provider($provider->id));
            }
        }

        return $provider;
    }

    /**
     * Retrieves all providers
     *
     * @param array filters, possible members: deleted, creator, name, provider
     *              if multiple members used then we do a logical AND with them
     * @return array array of com_meego_devprogram_device objects
     */
    public static function get_providers(array $filters)
    {
        $providers = null;

        $storage = new midgard_query_storage('com_meego_devprogram_provider');

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
                case 'guid':
                case 'name':
                case 'primarycontactname':
                case 'primarycontactemail':
                    $constraint = new midgard_query_constraint(
                        new midgard_query_property($filter),
                        '=',
                        new midgard_query_value($value)
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
                $providers[] = self::extend_provider($object);
            }
        }

        return $providers;
    }

    /**
     * Determines if the current user has any provider(s)
     *
     * @return boolean true, if user has provider(s), false otherwise
     */
    public static function user_has_providers()
    {
        $retval = false;

        $providers = self::get_providers_of_current_user();

        if (count($providers))
        {
            $retval = true;
        }

        return $retval;
    }

    /**
     * Retrieves a provider by its name
     *
     * @param string name
     * @return object com_meego_debprogram device object extended with
     *                some useful urls
     */
    public function get_provider_by_name($name = '')
    {
        $provider = null;

        if (strlen($name))
        {
            $providers = self::get_providers(array('name' => $name));

            if (count($providers))
            {
                $provider = $providers[0];
            }
        }

        return $provider;
    }

    /**
     * Retrieves a provider by its ID
     *
     * @param integer id of the provider
     * @return object com_meego_devprogram_provider object extended with
     *                some useful properties
     */
    public function get_provider_by_id($id = 0)
    {
        $provider = null;

        if ($id)
        {
            $providers = self::get_providers(array('id' => $id));

            if (count($providers))
            {
                $provider = $providers[0];
            }
        }

        return $provider;
    }

    /**
     * Retrieves providers created by user having the guid
     *
     * @param guid guid of the user
     * @return array an array of com_meego_devprogram_provider objects
     */
    private static function get_providers_by_creator_guid($guid = '')
    {
        $providers = array();

        if (mgd_is_guid($guid))
        {
            $providers = self::get_providers(array('creator' => $guid));
        }

        return $providers;
    }

    /**
     * Retrieves providers created by the given user
     *
     * @param string login name (ie. user name) of the user
     * @return array an array of com_meego_devprogram_provider objects
     */
    public static function get_providers_of_user($login = '')
    {
        $providers = array();

        if (! strlen($login))
        {
            return $providers;
        }

        // retrieve the user's guid based on the login name
        $user_guid = self::get_guid_of_user($login);

        return self::get_provider_by_creator_guid($user_guid);
    }

    /**
     * Retrieves providers of the currently loged in user
     *
     * @return array an array of com_meego_devprogram_provider objects
     *         null if user is not logged in
     */
    public static function get_providers_of_current_user()
    {
        $providers = array();

        $user = self::require_login();

        if (! is_object($user))
        {
            return null;
        }

        // get providers based on memberships
        $memberships = com_meego_devprogram_membutils::get_memberships_of_current_user();

        foreach($memberships as $membership)
        {
            // check status
            if ($membership->status == CMD_MEMBERSHIP_APPROVED)
            {
                // get provider objects and add it to the array
                $providers[] = self::get_provider_by_id($membership->provider);
            }
        }

        return $providers;
    }

    /**
     * Finds devices that belong to the given provider
     *
     * @param integer id of the provider
     * @return boolean true: if provider has devices; false otherwise
     */
    public function has_provider_devices($id = 0)
    {
        $retval = false;

        if ($id)
        {
            $filters = array('provider' => $id);
            $devices = com_meego_devprogram_devutils::get_devices($filters);

            if (count($devices))
            {
                $retval = true;
            }

            unset($filters, $devices);
        }

        return $retval;
    }
}
