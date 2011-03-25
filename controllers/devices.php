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
            midgardmvc_core::get_instance()->head->set_title($this->object->title);
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
        return midgardmvc_core::get_instance()->dispatcher->generate_url
        (
            'device_read',
            array('device_name' => $this->object->name),
            $this->request
        );
    }

    /**
     * Returns an update url for a device
     */
    public function get_url_update()
    {
        return midgardmvc_core::get_instance()->dispatcher->generate_url
        (
            'device_update',
            array('device_name' => $this->object->name),
            $this->request
        );
    }

    /**
     * Loads a form
     */
    public function load_form()
    {
        $this->form = midgardmvc_helper_forms::create('com_meego_devprogram_device');

        $name = $this->form->add_field('name', 'text', true);
        $name->set_value($this->object->name);
        $widget = $name->set_widget('text');
        $widget->set_label($this->mvc->i18n->get('label_name'));

        // @todo: add name tip

        $model = $this->form->add_field('model', 'text', true);
        $model->set_value($this->object->model);
        $widget = $model->set_widget('text');
        $widget->set_label($this->mvc->i18n->get('label_model'));

        // @todo: add model tip

        $type = $this->form->add_field('type', 'text', true);
        $type->set_value($this->object->type);
        $widget = $type->set_widget('text');
        $widget->set_label($this->mvc->i18n->get('label_type'));

        // @todo: add type tip

        $description = $this->form->add_field('description', 'text', true);
        $description->set_value($this->object->description);
        $widget = $description->set_widget('html');
        $widget->set_label($this->mvc->i18n->get('label_description'));

        // @todo: add description tip

        $url = $this->form->add_field('url', 'url', true);
        $url->set_value($this->object->url);
        $widget = $url->set_widget('text');
        $widget->set_label($this->mvc->i18n->get('label_url'));

        // @todo: add url tip
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
        // set owner flag
        // @todo
    }
}
?>