<?php
/**
 * @package com_meego_devprogram
 * @author Ferenc Szekely, http://www.nemein.com
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */
class com_meego_devprogram_utils
{
    /**
     * Requires a user to be logged in
     * If not logged then redirect to the login page otherwise return user
     * object
     *
     * @param string optional redirect page after succesful login
     * @return object midgard_user object
     */
    public static function require_login($redirect = '')
    {
        $mvc = midgardmvc_core::get_instance();

        if (! $mvc->authentication->is_user())
        {
            $login_url = '/mgd:login';

            if (strlen($redirect))
            {
                $login_url .= '?redirect=' . $redirect;
            }

            $mvc->head->relocate($login_url);
        }

        return $mvc->authentication->get_user();
    }

    /**
     * Retrieves the user guid of the user specifie by login name
     *
     * @param string login name (ie. user name) stored in midgard_user table
     * @return guid guid of the user
     */
    public static function get_guid_of_user($login = '')
    {
        $guid = null;

        $qb = new midgard_query_builder('midgard_user');
        $qb->add_constraint('login', '=', $login);

        $users = $qb->execute();

        if (count($users))
        {
            $guid = $users[0]->person;
        }

        return $guid;
    }

    /**
     * Returns urls based on routes
     *
     * @param string route
     * @param array arguments of the action
     * @return string url
     */
    public function get_url($route = '', $args)
    {
        $mvc = midgardmvc_core::get_instance();
        return $mvc->dispatcher->generate_url($route, $args, $mvc->dispatcher->get_request());
    }

    /**
     * Checks if the currently logged in user is a creator of the object
     * or an administrator
     *
     * @param object any object that is part of the schemas
     * @return boolean
     */
    public function is_current_user_creator_or_admin($object = null)
    {
        $retval = false;

        $mvc = midgardmvc_core::get_instance();

        if ($mvc->authentication->is_user())
        {
            if ($mvc->authentication->get_user()->is_admin())
            {
                $retval = true;
            }
            elseif (is_object($object))
            {
                $user = $mvc->authentication->get_user();

                if ($object->metadata->creator == $user->person)
                {
                    $retval = true;
                }
            }
        }

        return $retval;
    }

    /**
     * A simple way to generate a unique name for an object.
     *
     * It determines the class of the object, looks up similar objects in db
     * and generates a new name based on the title.
     *
     * Alters the generated name as long as it does not become unique by
     * adding a date to the end of it.
     *
     * @param object any object that is defined in the schema
     * @return string a new name
     *
     */
    public function generate_unique_name($newobject)
    {
        $name = null;

        if (is_object($newobject))
        {
            $names = array();
            $class = get_class($newobject);
            $qb = new midgard_query_builder($class);
            $objects = $qb->execute();

            // fill in an array with current names
            foreach ($objects as $object)
            {
                $names[] = $object->name;
            }
            $unique = false;

            $name = strtolower($newobject->title);
            $name = str_replace(' ', '-', $name);

            do {
                if (array_search($name, $names) === false)
                {
                    $unique = true;
                }
                else
                {
                    $name .= '-' . date("Ymd");
                }
            } while (! $unique);
        }

        return $name;
    }
}