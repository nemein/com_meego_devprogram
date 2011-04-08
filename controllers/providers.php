<?php
/**
 * @package com_meego_devprogram
 * @author Ferenc Szekely, http://www.nemein.com
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */
class com_meego_devprogram_controllers_providers extends midgardmvc_core_controllers_baseclasses_crud
{
    var $mvc = null;
    var $request = null;

    /**
     * Contructor
     *
     * @param object request is a midgardmvc_core_request object
     */
    public function __construct(midgardmvc_core_request $request)
    {
        $this->request = $request;
        $this->mvc = midgardmvc_core::get_instance();
        $this->data['my_providers'] = false;
    }

    /**
     * Loads a program
     */
    public function load_object(array $args)
    {
        if (array_key_exists('provider_name', $args))
        {
            $this->object = com_meego_devprogram_provutils::get_provider_by_name($args['provider_name']);
        }

        if (is_object($this->object))
        {
            midgardmvc_core::get_instance()->head->set_title($this->object->name);
        }
    }

    /**
     * Prepare a new provider
     */
    public function prepare_new_object(array $args)
    {
        $this->object = new com_meego_devprogram_provider();
    }

    /**
     * Returns a read url for a provider
     */
    public function get_url_read()
    {
        return com_meego_devprogram_utils::get_url(
            'provider_read',
            array('provider_name' => $this->object->name
        ));
    }

    /**
     * Returns an update url for a provider
     */
    public function get_url_update()
    {
        return com_meego_devprogram_utils::get_url(
            'provider_update',
            array('provider_name' => $this->object->name
        ));
    }

    /**
     * Loads a form
     */
    public function load_form()
    {
        $this->form = midgardmvc_helper_forms_mgdschema::create($this->object, false, 'label_provider_', 'tip_provider_');
        $this->form->set_submit('form-submit', $this->mvc->i18n->get('command_save'));

        # remove the name field, we will genarate it from title
        $this->form->__unset('name');
    }

    /**
     * Prepares and shows the provider list (cmd-my-providers)
     *
     * @param array args (not used)
     */
    public function get_my_providers_list(array $args)
    {
        // check if is logged in
        if (! $this->mvc->authentication->is_user())
        {
            return;
        }
        // collect all own providers
        // and those where the user is a member
        $this->data['my_providers'] = com_meego_devprogram_provutils::get_providers_of_current_user();
    }

    /**
     * Prepares and shows the list of members of a certain provider (cmd-list-memberships)
     *
     * Access: only owners of the providers can list the members
     *
     * @param array args (not used)
     */
    public function get_members_for_provider(array $args)
    {
        $this->load_object($args);

        // check if user owns the program or he / she is an admin
        if (! com_meego_devprogram_utils::is_current_user_creator_or_admin($this->object))
        {
            // nice try, redirect to standard read page
            $this->relocate_to_read();
        }

        $this->data['provider'] = $this->object;
        $this->data['index_url'] = com_meego_devprogram_utils::get_url('index', array());
        $this->data['memberships'] = com_meego_devprogram_membutils::get_memberships_by_provider($this->object->id);
    }

    /**
     * Prepares and shows the provider details page (cmd-provider-details)
     *
     * Access: anyone can read the provider details
     *         owners will get extra options on the page
     *
     * @param array args (not used)
     */
    public function get_read(array $args)
    {
        $this->load_object($args);

        $this->data['can_join'] = true;

        $this->data['provider'] = $this->object;

        $this->data['can_manage'] = com_meego_devprogram_utils::is_current_user_creator_or_admin($this->object);

        // check if provider has devices that belong t open programs
        $this->data['can_not_delete'] = com_meego_devprogram_provutils::has_provider_devices($this->object->id);

        if (com_meego_devprogram_utils::is_current_user_creator($this->object))
        {
            // owners of a program or admins should not apply for that program
            $this->data['can_join'] = false;
        }
        elseif ($this->mvc->authentication->is_user())
        {
            // check if the user has requested membership already
            // display a warning if yes
            $this->data['mymemberships'] = com_meego_devprogram_membutils::get_memberships_of_current_user($this->object->id);

            if (count($this->data['mymemberships']))
            {
                // if requested membership then we disable th join button
                $this->data['can_join'] = false;
            }
        }
    }

    /**
     * Prepares the create form
     */
    public function get_create(array $args)
    {
        // require login
        $redirect = com_meego_devprogram_utils::get_url('provider_create', $args);
        $user = com_meego_devprogram_utils::require_login($redirect);

        parent::get_create($args);
    }

    /**
     * Creates a new provider
     * But it makes sure that the name is unique
     */
    public function post_create(array $args)
    {
        $this->get_create($args);

        $user = com_meego_devprogram_utils::get_current_user();

        try
        {
            $transaction = new midgard_transaction();
            $transaction->begin();
            $this->process_form();

            // generate a unique name
            $this->object->name = com_meego_devprogram_utils::generate_unique_name($this->object);

            if (! $this->object->name)
            {
                throw new midgardmvc_exception('Could not generate a valid, unique name to a new object');
            }
            $res = $this->object->create();

            if ($res)
            {
                // create the membership object
                $membership = new com_meego_devprogram_provider_membership();

                $membership->provider = $this->object->id;
                $membership->person = $user->person;
                $membership->status = CMD_MEMBERSHIP_APPROVED;
                $membership->reason = $this->mvc->i18n->get('label_provider_original_creator');

                $membership->create();
            }
            $transaction->commit();

            // TODO: add uimessage of $e->getMessage();
            $this->relocate_to_read();
        }
        catch (midgardmvc_helper_forms_exception_validation $e)
        {
            // TODO: UImessage
        }
        catch (midgardmvc_exception $e)
        {
            // TODO: UImessage
        }
    }

    /**
     * Prepares the delete page
     */
    public function get_delete(array $args)
    {
        if (com_meego_devprogram_utils::is_current_user_creator_or_admin($this->object))
        {
            // if creator
            // or admin
            // or good enough member
            parent::get_delete($args);

            $this->data['index_url'] = com_meego_devprogram_utils::get_url('index', array());

            $this->data['can_not_delete'] = com_meego_devprogram_provutils::has_provider_devices($this->object->id);

            $this->data['delete_question'] = $this->mvc->i18n->get('question_device_delete', null, array('provider_name' => $this->object->title));
        }
        else
        {
            $redirect = com_meego_devprogram_utils::get_url('provider_read', array('provider_name' => $args['provider_name']));
            // redirect to the read page
            $this->mvc->head->relocate($redirect);
        }
    }

    /**
     * Deletes the provider
     * First it makes sure that all devices are deleted that do not belong to
     * programs
     */
    public function post_delete(array $args)
    {
        # make sure all unused (not part of any program) devices are deleted first
        # before attempting to delete a provider
        com_meego_devprogram_provutils::delete_unused_devices();
        parent::post_delete($args);
    }

    /**
     * Prepares the update
     */
    public function get_update(array $args)
    {
        if (com_meego_devprogram_utils::is_current_user_creator_or_admin($this->object))
        {
            // if creator
            // or admin
            // or good enough member
            parent::get_update($args);
        }
        else
        {
            $redirect = com_meego_devprogram_utils::get_url('provider_read', array('provider_name' => $args['provider_name']));
            // redirect to the read page
            $this->mvc->head->relocate($redirect);
        }
    }
}
?>
