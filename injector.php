<?php
/**
 * @package com_meego_devprogram
 * @author Ferenc Szekely, http://www.nemein.com
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

define('CMD_APPLICATION_PENDING', 0);
define('CMD_APPLICATION_MOREINFO', 1);
define('CMD_APPLICATION_CANCELLED', 2);
define('CMD_APPLICATION_APPROVED', 8);
define('CMD_APPLICATION_DECLINED', 9);

class com_meego_devprogram_injector
{
    var $component = 'com_meego_devprogram';
    var $mvc = null;

    public function __construct()
    {
        $this->mvc = midgardmvc_core::get_instance();
        $this->mvc->i18n->set_translation_domain($this->component);

        $default_language = $this->mvc->configuration->default_language;

        if (! isset($default_language))
        {
            $default_language = 'en_US';
        }

        $this->mvc->i18n->set_language($default_language, false);
    }

    /**
     * @todo: docs
     */
    public function inject_process(midgardmvc_core_request $request)
    {
        // We inject the template to provide MeeGo styling
        $request->add_component_to_chain($this->mvc->component->get($this->component), true);
        // Default title
        $this->mvc->head->set_title($this->mvc->i18n->get('title_welcome'));

        $request->set_data_item('user', false);
    }

    /**
     * Some template hack
     */
    public function inject_template(midgardmvc_core_request $request)
    {
        // Replace the default MeeGo sidebar with our own
        $route = $request->get_route();
        $route->template_aliases['content-sidebar'] = 'cmd-sidebar';
        $route->template_aliases['main-menu'] = 'cmd-main-menu';

        // set user flag
        // set create program url for the menu in the sidebar
        // set admin flag to have extra functionality later on
        if ($this->mvc->authentication->is_user())
        {
            $request->set_data_item('user', true);

            $create_program_url = $this->mvc->dispatcher->generate_url
            (
                'program_create', array(), $request
            );

            $request->set_data_item('create_program_url', $create_program_url);

            if ($this->mvc->authentication->get_user()->is_admin())
            {
                $request->set_data_item('admin', true);
            }
        }

        // set open programs url for the menu in the sidebar
        $open_programs_url = $this->mvc->dispatcher->generate_url
        (
            'open_programs', array(), $request
        );

        $request->set_data_item('open_programs_url', $open_programs_url);

        // breadcrumb update
        self::set_breadcrumb($request);

        // add some js and css  to html head if needed
        $this->add_head_elements();
    }

    /**
     * Add CSS and JS to HTML head
     */
    private function add_head_elements()
    {
        // enable jQuery and jQuery UI
        midgardmvc_core::get_instance()->head->enable_jquery();
        midgardmvc_core::get_instance()->head->enable_jquery_ui();

        // pimp the date input fields
        $this->mvc->head->add_jsfile(MIDGARDMVC_STATIC_URL . '/' . $this->component . '/js/datetimes.js');

        // jQuery UI CSS
        $this->mvc->head->add_link
        (
            array
            (
                'rel' => 'stylesheet',
                'type' => 'text/css',
                'href' => MIDGARDMVC_STATIC_URL . '/' . $this->component . '/css/devprogram-theme/jquery.ui.all.css'
            )
        );

        // our CSS
        $this->mvc->head->add_link
        (
            array
            (
                'rel' => 'stylesheet',
                'type' => 'text/css',
                'href' => MIDGARDMVC_STATIC_URL . '/' . $this->component . '/css/devprogram.css'
            )
        );
    }

    /**
     * Sets the breadcrumb
     * A bit lame way though...
     *
     * @param object midgardmvc_core_request  object to assign 'breadcrumb' for templates
     */
    public function set_breadcrumb(midgardmvc_core_request $request)
    {
        $nexturl = '';
        $breadcrumb = array();

        $cnt = 0;

        foreach ($request->argv as $arg)
        {
            $nexturl .= '/' . $arg;

            $item = array(
                'title' => ucfirst($arg),
                'localurl' => $nexturl,
                'last' => (count($request->argv) - 1 == $cnt) ? true : false
            );

            $breadcrumb[] = $item;

            ++$cnt;
        }

        $request->set_data_item('breadcrumb', $breadcrumb);
    }
}
?>
