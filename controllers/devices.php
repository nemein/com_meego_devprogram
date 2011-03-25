<?php
/**
 * @package com_meego_devprogram
 * @author Ferenc Szekely, http://www.nemein.com
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */
class com_meego_devprogram_controllers_devices extends midgardmvc_core_controllers_baseclasses_crud
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
        $this->data['my_devices'] = false;
    }

    /**
     * Loads a program
     */
    public function load_object(array $args)
    {
        $this->object = com_meego_devprogram_devutils::get_device_by_name($args['device_name']);

        if (is_object($this->object))
        {
            midgardmvc_core::get_instance()->head->set_title($this->object->name);
        }
    }

    /**
     * Prepare a new program
     */
    public function prepare_new_object(array $args)
    {
        $this->object = new com_meego_devprogram_device();
    }

    /**
     * Returns a read url for a device
     */
    public function get_url_read()
    {
        return com_meego_devprogram_utils::get_url(
            'device_read',
            array('device_name' => $this->object->name
        ));
    }

    /**
     * Returns an update url for a device
     */
    public function get_url_update()
    {
        return com_meego_devprogram_utils::get_url(
            'device_update',
            array('device_name' => $this->object->name
        ));
    }

    /**
     * Loads a form
     */
    public function load_form()
    {
        $this->form = midgardmvc_helper_forms_mgdschema::create($this->object, false, 'label_device_', 'tip_device_');
        $this->form->set_submit('form-submit', $this->mvc->i18n->get('command_save'));
    }

    /**
     * Prepares and shows the devices list (cmd-my-devices)
     *
     * @param array args (not used)
     */
    public function get_my_devices_list(array $args)
    {
        // check if is logged in
        if (! $this->mvc->authentication->is_user())
        {
            return;
        }
        $this->data['my_devices'] = com_meego_devprogram_devutils::get_devices_of_current_user();
    }

    /**
     * Prepares and shows the device details page (cmd-device-details)
     *
     * Access: anyone can read the device details
     *         owners will get extra options on the page
     *
     * @param array args (not used)
     */
    public function get_read(array $args)
    {
        $this->load_object($args);

        $this->load_form();

        if (! com_meego_devprogram_utils::is_current_user_creator_or_admin($this->object->guid))
        {
            $this->form->set_readonly(true);
        }

        $this->data['form'] =& $this->form;
    }

    /**
     * Creates a new device
     * But it makes sure that the name is unique
     */
    public function post_create(array $args)
    {
        parent::post_delete($args);
    }

    /**
     * Prepares the delete page
     */
    public function get_delete(array $args)
    {
        parent::get_delete($args);

        $this->data['index_url'] = com_meego_devprogram_utils::get_url('index', array());

        $this->data['can_not_delete'] = com_meego_devprogram_progutils::any_open_program_uses_device($this->object->id);

        $this->data['delete_question'] = $this->mvc->i18n->get('question_delete', null, array('device_name' => $this->object->name));
    }

    /**
     * Deletes the device
     * First it makes sure that all expired programs are deleted,
     * otherwise a device can not be deleted if it is assigned to a program
     */
    public function post_delete(array $args)
    {
        # make sure all closed (expired) programs are marked deleted
        # before attempting to delete a device
        com_meego_devprogram_progutils::delete_expired_programs();
        parent::post_delete($args);
    }
}
?>