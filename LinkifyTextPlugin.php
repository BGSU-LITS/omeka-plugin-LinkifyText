<?php
/**
 * Omeka Linkify Text Plugin
 *
 * @author John Kloor <kloor@bgsu.edu>
 * @copyright 2017 Bowling Green State University Libraries
 * @license MIT
 */

/**
 * Omeka Linkify Text Plugin: Plugin Class
 *
 * @package LinkifyText
 */
class LinkifyTextPlugin extends Omeka_Plugin_AbstractPlugin
{
    /**
     * @var array Plugin hooks.
     */
    protected $_hooks = array(
        'install',
        'uninstall',
        'config',
        'config_form'
    );

    /**
     * @var array Plugin options.
     */
    protected $_options = array(
        'linkify_text_elements' => ''
    );

    /**
     * An object of the Linkify class used to linkify text.
     * @var object
     */
    private $linkify;

    /**
     * Sets up hooks and filters for the plugin.
     */
    public function setUp()
    {
        // Get the key of the option used to store the elements.
        $key = key($this->_options);

        // Get all of the elements to be linkified.
        $elements = unserialize((string) get_option($key));

        // If there are elements, add the linkifyText filter to each.
        if (is_array($elements)) {
            foreach ($elements as $element) {
                add_filter(
                    array('Display', 'Item', $element[0], $element[1]),
                    array($this, 'linkifyText')
                );
            }
        }

        // Perform normal setup of hooks and filters.
        parent::setUp();
    }

    /**
     * Hook to plugin installation.
     */
    public function hookInstall()
    {
        $this->_installOptions();
    }

    /**
     * Hook to plugin uninstallation.
     */
    public function hookUninstall()
    {
        $this->_uninstallOptions();
    }

    /**
     * Hooks to process submission of the config form for the plugin.
     * @param array $args The arguments from the rooter to this method
     */
    public function hookConfig($args)
    {
        // Selected elements will be added to this array.
        $elements = array();

        // Get the key of the option used to store the elements.
        $key = key($this->_options);

        // Check if elements were submitted.
        if (isset($args['post'][$key])) {
            // Get a reference to the Element table to lookup elements.
            $table = get_db()->getTable('Element');

            // Loop through IDs of each element submitted.
            foreach ($args['post'][$key] as $id) {
                // Find the element and add set name and element name to array.
                $element = $table->find($id);

                $elements[] = array(
                    $element->getElementSet()->name,
                    $element->name
                );
            }
        }

        // Store the list of elements in a serialized form.
        set_option($key, serialize($elements));
    }

    /**
     * Hooks to output the configuration form for the plugin.
     */
    public function hookConfigForm()
    {
        // Get the Element table, and list of all elements from that table.
        $table = get_db()->getTable('Element');
        $pairs = $table->findPairsForSelectForm();

        // Stores the IDs of each previously selected element.
        $ids = array();

        // Get the key of the option used to store the elements.
        $key = key($this->_options);

        // Get all of the elements currently set to be linkified.
        $elements = unserialize(get_option($key));

        // Check that there are elements selected.
        if (is_array($elements)) {
            // Loop through those elements.
            foreach ($elements as $element) {
                // Find the element within the table.
                $element = $table->findByElementSetNameAndElementName(
                    $element[0],
                    $element[1]
                );

                // If found, add the ID of the element to the list of IDs.
                if ($element) {
                    $ids[] = $element->id;
                }
            }
        }

        // Loop through each element set within the list of all elements.
        foreach ($pairs as $set => $options) {
            // Output a field of checkboxes to select from the elements within
            // the set, checking those previously selected by default.
            echo '<div class="field"><h2>' . $set . '</h2>';
            echo get_view()->formMultiCheckbox($key, $ids, null, $options, '');
            echo '</div>';
        }
    }

    /**
     * Hook to linkify the specified text if it is not already HTML.
     * @param string $text The text to be linkify.
     * @param array $args An array of arguments provided to the hook.
     * @return string The text, possibly linkified.
     */
    public function linkifyText($text, $args)
    {
        // Check that there is text, and it is not HTML.
        if (
            trim((string) $text) != '' &&
            empty($args['element_text']['html'])
        ) {
            // If the linkifier has not been loaded, load it now.
            if (empty($this->linkify)) {
                require 'vendor/autoload.php';
                $this->linkify = new \Misd\Linkify\Linkify;
            }

            // Linkify the text.
            $text = $this->linkify->process($text);
        }

        return $text;
    }
}
