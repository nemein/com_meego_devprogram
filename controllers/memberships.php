<?php
/**
 * @package com_meego_devprogram
 * @author Ferenc Szekely, http://www.nemein.com
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */
class com_meego_devprogram_controllers_memberships extends midgardmvc_core_controllers_baseclasses_crud
{
    var $mvc = null;
    var $request = null;
    var $component = 'com_meego_devprogram';

    /**
     * Contructor
     *
     * @param object request is a midgardmvc_core_request object
     */
    public function __construct(midgardmvc_core_request $request)
    {
        $this->request = $request;
        $this->mvc = midgardmvc_core::get_instance();
        $this->data['my_requests'] = false;
    }

    /**
     * Loads a membership object
     */
    public function load_object(array $args)
    {
        $this->object = com_meego_devprogram_membutils::get_membership_by_guid($args['membership_guid']);

        // by default we set this to true so that the toolbar is visible
        // we remove the toolbar on the judge form
        $this->object->can_manage = true;

        // set the membership data
        $this->data['membership'] = $this->object;

        // also get the program the application was submitted for
        $this->data['provider'] = com_meego_devprogram_provutils::get_provider_by_id($this->object->provider);
    }

    /**
     * Prepare a new application
     */
    public function prepare_new_object(array $args)
    {
        $this->object = new com_meego_devprogram_provider_membership();
    }

    /**
     * Returns a read url for a membership
     */
    public function get_url_read()
    {
        return com_meego_devprogram_utils::get_url(
           'my_membership_read',
            array('membership_guid' => $this->object->guid
        ));
    }

    /**
     * Returns an update url for a membership
     */
    public function get_url_update()
    {
        return com_meego_devprogram_utils::get_url(
            'my_membership_update',
            array('membership_guid' => $this->object->guid
        ));
    }

    /**
     * Loads a form
     */
    public function load_form()
    {
        $this->form = midgardmvc_helper_forms_mgdschema::create($this->object, false, 'label_membership_', 'tip_membership_');
        $this->form->set_submit('form-submit', $this->mvc->i18n->get('command_save'));

        # remove the program field
        $this->form->__unset('provider');

        # remove additional fields that will be only filled in by other provider members
        $this->form->__unset('status');
        $this->form->__unset('remarks');

        $field = $this->form->add_field('provider', 'integer', false);
        $field->set_value($this->data['provider']->id);
        $field->set_widget('hidden');

        if (! array_key_exists('person', $this->data))
        {
            $this->data['person'] = com_meego_devprogram_utils::get_current_user()->person;
        }

        $field = $this->form->add_field('person', 'text', false);
        $field->set_value($this->data['person']);
        $field->set_widget('hidden');
    }

    /**
     * Loads the judging form
     */
    public function load_judge_form()
    {
        $this->form = midgardmvc_helper_forms_mgdschema::create($this->object, false, 'label_membership_', 'tip_membership_');
        $this->form->set_submit('form-submit', $this->mvc->i18n->get('command_submit'), true);

        # remove the program field
        $this->form->__unset('provider');
        # remove fields that are not needed by judges
        $this->form->__unset('reason');

        $field = $this->form->add_field('provider', 'integer', false);
        $field->set_value($this->data['provider']->id);
        $field->set_widget('hidden');

        # checkbox for the decision
        $field = $this->form->add_field('status', 'integer', false);
        $widget = $field->set_widget('radiobuttons');
        $widget->add_label($this->mvc->i18n->get('label_membership_decision'));

        $options = array
        (
            array
            (
                'description' => $this->mvc->i18n->get('label_membership_decision_approve'),
                'value' => CMD_MEMBERSHIP_APPROVED
            ),
            array
            (
                'description' => $this->mvc->i18n->get('label_membership_decision_moreinfo'),
                'value' => CMD_MEMBERSHIP_MOREINFO
            ),
            array
            (
                'description' => $this->mvc->i18n->get('label_membership_decision_deny'),
                'value' => CMD_MEMBERSHIP_DENIED
            )
        );
        $widget->set_options($options);

        // pimp the date input fields
        $this->mvc->head->add_jsfile(MIDGARDMVC_STATIC_URL . '/' . $this->component . '/js/decision.js');
    }

    /**
     * Prepares and shows the my membership list page (cmd-my-memberships)
     *
     * Access: only users can list own memberships
     * @param array args (not used)
     *
     */
    public function get_my_memberships_list(array $args)
    {
        // check if is logged in
        if (com_meego_devprogram_utils::get_current_user())
        {
            $this->data['my_memberships'] = com_meego_devprogram_membutils::get_memberships_of_current_user();
        }
    }

    /**
     * Prepares and shows the my pending membership list page (cmd-my-memberships)
     *
     * Access: only users can list own memberships
     * @param array args (not used)
     *
     */
    public function get_my_pending_memberships_list(array $args)
    {
        // check if user is logged in
        if (com_meego_devprogram_utils::get_current_user())
        {
            // get all requests that are pending
            $this->data['my_pending'] = com_meego_devprogram_membutils::get_memberships_of_current_user(null, CMD_MEMBERSHIP_PENDING);

            // get all requests that need more info
            $this->data['my_moreinfo'] = com_meego_devprogram_membutils::get_memberships_of_current_user(null, CMD_MEMBERSHIP_MOREINFO);

            // merge the two
            $this->data['my_memberships'] = array_merge($this->data['my_pending'], $this->data['my_moreinfo']);
        }
    }

    /**
     * Prepares the membership page
     *
     * @param array args
     */
    public function get_create(array $args)
    {
        if (! strlen($args['provider_name']))
        {
            $url = com_meego_devprogram_utils::get_url('index', $args);
            $this->mvc->head->relocate($url);
        }

        // require login
        $redirect = com_meego_devprogram_utils::get_url('my_membership_create', $args);
        $user = com_meego_devprogram_utils::require_login($redirect);

        // @todo: sanity check
        $providers = com_meego_devprogram_provutils::get_providers(array('name' => $args['provider_name']));
        $provider = $providers[0];

        if (! is_object($provider))
        {
            throw new InvalidArgumentException("Provider: {$args['provider_name']} con not be found");
        }

        $this->data['provider'] = $provider;
        $this->data['person'] = $user->person;

        // check if the user has applied for the program and
        // display a warning if yes
        $this->data['mymemberships'] = com_meego_devprogram_membutils::get_memberships_of_current_user($provider->id);

        if (! count($this->data['mymemberships']))
        {
            // we move on if user has not member yet
            parent::get_create($args);
        }
    }

    /**
     * Prepares and shows the membership details page (cmd-my-membership-details)
     *
     * Access: only creators of the membership can read
     *         or provider owners / admins
     *
     * @param array args
     */
    public function get_read(array $args)
    {
        parent::get_read($args);

        if ( ! (   com_meego_devprogram_utils::is_current_user_creator_or_admin($this->data['membership'])
                || com_meego_devprogram_utils::is_current_user_creator_or_admin($this->data['provider'])))
        {
            // not creator of membership
            // not owner of the provider
            // not an administrator
            // nice try, but sorry, get back to index
            $this->mvc->head->relocate(com_meego_devprogram_utils::get_url('index', array()));
        }

        unset($this->data['object']);
    }

    /**
     * Prepares and shows the my membership update page (cmd-my-membership-update)
     *
     * Access: only owners of the membership can update
     *
     * @param array args
     */
    public function get_update(array $args)
    {
        // set myapps to be able to show a warning
        $this->data['mymemberships'] = false;

        parent::get_update($args);
    }

    /**
     * Prepares the delete page
     */
    public function get_delete(array $args)
    {
        $this->data['can_not_delete'] = true;

        $this->load_object($args);

        $redirect = com_meego_devprogram_utils::get_url(
            'my_membership_delete',
            array('membership_guid' => $this->object->guid
        ));

        $user = com_meego_devprogram_utils::require_login($redirect);
        $this->data['person'] = $user->person;

        if (com_meego_devprogram_utils::is_current_user_creator_or_admin($this->object))
        {
            parent::get_delete($args);

            $this->data['index_url'] = com_meego_devprogram_utils::get_url('index', array());
            $this->data['can_not_delete'] = false;
            $this->data['delete_question'] = $this->mvc->i18n->get('question_membership_delete', null, array('provider_name' => $this->data['provider']->title));
        }
        else
        {
            // nice try...
            $this->mvc->head->relocate(com_meego_devprogram_utils::get_url('index', array()));
        }
    }

    /**
     * Prepares and shows the application approval page
     *
     * Access: only owners of the programs can judge applications
     *
     * @param array args
     */
    public function get_judge(array $args)
    {
        $this->load_object($args);

        if (   ! (com_meego_devprogram_utils::is_current_user_creator_or_admin($this->object)
            || com_meego_devprogram_membutils::is_current_user_member_of_provider($this->data['provider']->id)))
        {
            // not creator of the membership
            // not owner of the provider
            // not an administrator
            // nice try, but sorry, get back to index
            $this->mvc->head->relocate(com_meego_devprogram_utils::get_url('index', array()));
        }

        // we remove the toolbar on the judge form
        $this->data['membership']->can_manage = false;

        $this->load_judge_form();

        $this->data['form'] = $this->form;

        unset($this->data['object']);
    }

    /**
     * Handles posts from application judges
     *
     * @param array args
     */
    public function post_judge(array $args)
    {
        $this->get_judge($args);

        try
        {
            $transaction = new midgard_transaction();
            $transaction->begin();
            $this->process_form();
            $this->object->update();
            $transaction->commit();

            // Redirect to application lists of that program
            $this->mvc->head->relocate($this->data['provider']->list_memberships_url);
        }
        catch (midgardmvc_helper_forms_exception_validation $e)
        {
            // TODO: UImessage
        }

    }
}
?>
