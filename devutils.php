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

    /**
     * Determines if the current user has registered device(s)
     *
     * @return boolean true, if user has devices, false otherwise
     */
    public static function user_has_device()
    {
        $retval = false;

        $user = self::require_login();

        $storage = new midgard_query_storage('com_meego_devprogram_device');

        $q = new midgard_query_select($storage);

        $qc = new midgard_query_constraint(
            new midgard_query_property('metadata.creator'),
            '=',
            new midgard_query_value($user->person)
        );

        $q->set_constraint($qc);
        $q->execute();

        if ($q->get_results_count())
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
            $storage = new midgard_query_storage('com_meego_devprogram_device');

            $q = new midgard_query_select($storage);

            $qc = new midgard_query_constraint(
                new midgard_query_property('name'),
                '=',
                new midgard_query_value($name)
            );

            $q->set_constraint($qc);
            $q->execute();

            $devices = $q->list_objects();

            if (count($devices))
            {
                $device = new com_meego_devprogram_device($devices[0]->guid);
                $device->read_url = com_meego_devprogram_utils::get_url('device_read', array ('device_name' => $device->name));
                $device->update_url = com_meego_devprogram_utils::get_url('device_update', array ('device_name' => $device->name));
                $device->delete_url = com_meego_devprogram_utils::get_url('device_delete', array ('device_name' => $device->name));
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
            $storage = new midgard_query_storage('com_meego_devprogram_device');

            $q = new midgard_query_select($storage);

            $qc = new midgard_query_constraint(
                new midgard_query_property('metadata.creator'),
                '=',
                new midgard_query_value($guid)
            );

            $q->set_constraint($qc);
            $q->execute();

            // add read, delete, update urls to objects
            $objects = $q->list_objects();

            if (count($objects))
            {
                $mvc = midgardmvc_core::get_instance();

                foreach ($objects as $object)
                {
                    $object->read_url = com_meego_devprogram_utils::get_url('device_read', array ('device_name' => $object->name));
                    $object->update_url = com_meego_devprogram_utils::get_url('device_update', array ('device_name' => $object->name));
                    $object->delete_url = com_meego_devprogram_utils::get_url('device_delete', array ('device_name' => $object->name));

                    $devices[] = $object;
                }
            }
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
        $user = self::require_login();

        if (! is_object($user))
        {
            return null;
        }

        return self::get_devices_by_creator_guid($user->person);
    }
}