<?php
/**
 * @package com_meego_devprogram
 * @author Ferenc Szekely, http://www.nemein.com
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */
class com_meego_devprogram_devutils extends com_meego_devprogram_utils
{
    /**
     * Adds some handy properties to device object
     *
     * @param object com_meego_devprogram_device object
     * @return object extended com_meego_devprogram_device object
     */
    private function extend_device($object = null)
    {
        // q->toggle_readonly(false) does not work so we need a new object
        $device = new com_meego_devprogram_device($object->guid);

        $device->read_url = com_meego_devprogram_utils::get_url('device_read', array ('device_name' => $device->name));
        $device->update_url = com_meego_devprogram_utils::get_url('device_update', array ('device_name' => $device->name));
        $device->delete_url = com_meego_devprogram_utils::get_url('device_delete', array ('device_name' => $device->name));

        $mvc = midgardmvc_core::get_instance();

        // set the pretty name of the device platform
        $device->prettyplatform = $mvc->configuration->platforms[$device->platform];

        // set the provider of the device
        $device->providerobject = new com_meego_devprogram_provider($device->provider);

        return $device;
    }

    /**
     * Retrieves all devices
     *
     * @param array filters, possible members: deleted, creator, name, provider
     *              if multiple members used then we do a logical AND with them
     * @return array array of com_meego_devprogram_device objects
     */
    public static function get_devices(array $filters)
    {
        $devices = null;

        $storage = new midgard_query_storage('com_meego_devprogram_device');

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
                case 'name':
                case 'provider':
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
                $devices[] = self::extend_device($object);
            }
        }

        return $devices;
    }

    /**
     * Determines if the current user has registered device(s)
     * or the providers the user belongs to have registered device(s)
     *
     * @return boolean true, if user has devices, false otherwise
     */
    public static function user_has_device()
    {
        $retval = false;

        $user = self::require_login();

        $devices = self::get_devices_of_current_user();

        if (count($devices))
        {
            $retval = true;
        }

        return $retval;
    }

    /**
     * Retrieves a device by its name
     *
     * @param string name
     * @return object com_meego_debprogram device object extended with
     *                some useful urls
     */
    public function get_device_by_name($name = '')
    {
        $device = null;

        if (strlen($name))
        {
            $devices = self::get_devices(array('name' => $name));

            if (count($devices))
            {
                $device = $devices[0];
            }
        }

        return $device;
    }

    /**
     * Retrieves devices created by user having the guid
     *
     * @param guid guid of the user
     * @return array an array of com_meego_devprogram_device objects
     */
    private static function get_devices_by_creator_guid($guid = '')
    {
        $devices = array();

        if (mgd_is_guid($guid))
        {
            $devices = self::get_devices(array('creator' => $guid));
        }

        return $devices;
    }

    /**
     * Retrieves devices created by the given user
     *
     * @param string login name (ie. user name) of the user
     * @return array an array of com_meego_devprogram_device objects
     */
    public static function get_devices_of_user($login = '')
    {
        $devices = array();

        if (! strlen($login))
        {
            return $devices;
        }

        // retrieve the user's guid based on the login name
        $user_guid = self::get_guid_of_user($login);

        return self::get_devices_by_creator_guid($user_guid);
    }

    /**
     * Retrieves devices of the currently loged in user
     *
     * @return array an array of com_meego_devprogram_device objects
     *         null if user is not logged in
     */
    public static function get_devices_of_current_user()
    {
        $devices = array();

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
                // get provider objects and add it to the array
                $devices = array_merge($devices, self::get_devices(array('provider' => $membership->provider)));
            }
        }

        return $devices;
    }
}
