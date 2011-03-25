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
            new midgard_query_value($user->guid)
        );

        $q->set_constraint($qc);
        $q->execute();

        if ($q->get_results_count())
        {
            $retval = true;
        }

        return $retval;
    }
}