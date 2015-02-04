<?php

namespace fezapi\client;

require_once(__DIR__ . '/../../../public/include/class.api.php');

/**
 * A class used to represent fez records on the client-side.
 */
class Record extends \StdClass
{

    /**
     * Create an instance of this class using xml received from
     * enter/edit_metadata xml output.
     *
     * The assumption is this output contains all the information need
     * to create or edit a record of a given display type - since that is what
     * it's used for.
     *
     * Intended to replace logic in the following steps:
     *   @Given /^add the display field \'([^\']*)\' from response$/
     *   @Given /^add the display field \'([^\']*)\' value to \'([^\']*)\'$/
     *   @Then /^using the available fields in the xml$/
     *   @create* scenarios in general
     *
     */
    public static function createFromMetadataXml($xml)
    {


        $sxml = new \SimpleXmlElement($xml);

        // There's a chance the xml is an error response and not the metadata...
        if (!isset($sxml->xsd_display_fields)) {
            if (isset($sxml->status)) {
                throw new \Exception("Could not process response.  Status code is: " . (string)$sxml->status);
            } else {
                throw new \Exception("Could not process response.  Xml is: " . $xml);
            }
        }

        $instance = new self();
        $instance->xsdmf_id = array();
        $instance->by_xsdmf_title = array();

        // Build xsd_display_fields...

        foreach ($sxml->xsd_display_fields->xsd_display_field as $field) {

            $o = new \stdClass();
            $xsdmf_title = (string)$field->xsdmf_title;
            $xsdmf_id = (int)$field->xsdmf_id;

            $o->xsdmf_id = (string)$field->xsdmf_id;
            $o->xsdmf_title = (string)$field->xsdmf_title;
            $o->xsdmf_html_input = trim((string)$field->xsdmf_html_input);
            $o->xsdmf_required = (string)$field->xsdmf_required === '1' ? true : false;
            $o->xsdmf_multiple = (string)$field->xsdmf_multiple === '1' ? true : false;

            // Store one or more values.  For text fields and the
            // like, we'd only ever take the first value.
            // Corresponds to xsdmf_value in the xml produced by
            // enter/edit_metadata.

            $o->xsdmf_values = array();

            // Handle possibility of multiple xsdmf_value's.
            if (isset($field->xsdmf_value)) {
                foreach ($field->xsdmf_value as $v) {
                    $v = trim((string)$v);
                    if (!empty($v)) {
                        // If the value is a date put it in the correct format.
                        if ($o->xsdmf_html_input == 'date-full') {
                            $date_parts = explode('-', $v);
                            $o->xsdmf_values[]  = array("day" => $date_parts[2], "month" => $date_parts[1], "year" => $date_parts[0]);
                        } elseif ($o->xsdmf_html_input == 'date-year') {
                            $o->xsdmf_values[] = array("year" => $v);
                        } else {
                            $o->xsdmf_values[] = (string)$v;
                        }
                    }
                }
            }

            $instance->xsdmf_id[$xsdmf_id] = $o;
            // Titles could be duplicated.  Push on an indexed array
            // in case there is more than one.
            $instance->by_xsdmf_title[$xsdmf_title][] = $o;
        }

        // Datastreams
        if (count($sxml->datastreams->datastream) > 0) {
            foreach ($sxml->datastreams->datastream as $ds) {
                $i = new \stdClass();

                $i->id = (string)$ds->id;
                $i->mimetype = (string)$ds->mimetype;
                $i->embargo_date = (string)$ds->embargo_date;
                $i->description = (string)$ds->description;
                $i->href = (string)$ds->href;
                $i->preservation_metadata = (string)$ds->preservation_metadata;
                $instance->datastreams[] = $i;
            }
        } else {
            $instance->datastreams = array();
        }
        return $instance;
    }

    // Append an additional xsdmf_value to this xsdmf_id.

    public function appendValue($xsdmf_id, $value)
    {
        if (!isset($this->xsdmf_id[$xsdmf_id])) {
            throw new Exception("xsdmf_id: $xsdmf_id not in this document.");
        }
        $this->xsdmf_id[$xsdmf_id][] = $value;
    }

    // Replace all existing xsdmf_values with a single value for this
    // xsdmf_id.

    public function setValue($xsdmf_id, $value)
    {
        if (!isset($this->xsdmf_id[$xsdmf_id])) {
            throw new Exception("xsdmf_id: $xsdmf_id not in this document.");
        }
        $this->xsdmf_id[$xsdmf_id] = array();
        $this->xsdmf_id[$xsdmf_id][] = $value;
    }

    // Remove all xsdmf_values for this xsdmf_id.

    public function clearValue($xsdmf_id)
    {
        if (!isset($this->xsdmf_id[$xsdmf_id])) {
            throw new Exception("xsdmf_id: $xsdmf_id not in this document.");
        }
        $this->xsdmf_id[$xsdmf_id] = array();
    }

    // Return only the required fields.

    public function requiredFields() {
        return array_filter(
            $this->xsdmf_id,
            function($field) {
                return $field->xsdmf_required;
            }
        );
    }


    public function getDatastreams()
    {
        return $this->datastreams;
    }

    public function isRequired($xsdmf_id)
    {
        $req = $this->requiredFields();
        $found = false;
        foreach ($req as $field) {
            if ((int)$field->xsdmf_id == $xsdmf_id) {
                $found = true;
                break;
            }
        }
        return $found;
    }

    /**
     * Generate the xml that would be used to post to
     * enter/edit_metadata.
     *
     * NOTE: to POST to enter/edit_metadata, you will need to set some
     * additional query parameters:
     * $_REQUEST['id']; // wfses_id
     * $_REQUEST['xdis_id']; // TODO: get this when we create the object
     * $_REQUEST['sta_id'];
     * $_REQUEST['workflow'];
     * $_REQUEST['workflow_val'];
     *
     */
    public function toXml()
    {
        // TODO - Consider only adding those fields with have xsdmf_values as they are required for any id.
        $xsd_display_fields = array_map(function($field) {
            return array(
                'xsdmf_id' => $field->xsdmf_id,
                'xsdmf_title' => $field->xsdmf_title,
                'xsdmf_values' => $field->xsdmf_values,
            );
        }, $this->xsdmf_id);
        return \API::toXml(
            array('xsd_display_fields' => $xsd_display_fields),
            function($tagname, array $children) {
                switch($tagname) {
                case 'xsd_display_fields':
                    return array('xsd_display_field', false);
                case 'xsdmf_values':
                    return array('xsdmf_value', true);
                }
            }
        );
    }

}

