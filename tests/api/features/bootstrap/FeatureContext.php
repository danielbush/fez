<?php

// Library used to make requests and parse xml etc
require_once(__DIR__ . '/../../client/Client.php');
require_once(__DIR__ . '/../../client/Record.php');
require_once(__DIR__ . '/../../client/utils.php');
require_once(__DIR__ . '/../../Expectation.php');
require_once(__DIR__ . '/../../setuplib.php');

// Include fez itself to help with testing and verifying the api...
require_once(__DIR__ . '/../../../../public/config.inc.php');
require_once(APP_INC_PATH . '/class.record.php');
require_once(APP_INC_PATH . '/class.api.php');
require_once(APP_INC_PATH . '/class.internal_notes.php');

use Behat\Behat\Context\ClosuredContextInterface,
    Behat\Behat\Context\TranslatedContextInterface,
    Behat\Behat\Context\BehatContext,
    Behat\Behat\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode,
    Behat\Gherkin\Node\TableNode;

use fezapi\client as c;

define('BEHAT_ERROR_REPORTING', E_ERROR | E_WARNING | E_PARSE);

/**
 * Features context.
 *
 * Every scenario (within a feature file) gets its own context object.
 *
 */
class FeatureContext extends BehatContext
{

    // Should be same as $this->VERBOSE (for access by static methods).
    // 
    // @see _constructor

    public static $VERBOSE = false;

    /**
     * @BeforeFeature
     */
    public static function beforeFeature()
    {
        // Create test users/groups if not there, etc etc.
        // Don't do reindex solr, too expensive.
        if (self::$VERBOSE) echo "beforeFeature: running setup..." . PHP_EOL;
        setup($ignoreroles=false, $solrindex=false, self::$VERBOSE);
    }

    /**
     * @BeforeScenario
     */
    public function beforeScenario()
    {
        if ($this->VERBOSE) {
            echo "beforeScenario: deleting active workflows before continuing..." . PHP_EOL;
        }
        $this->_deleteActiveWorkflows();
    }

    /**
     * @AfterScenario
     */
    public function afterScenario()
    {
        if ($this->VERBOSE) {
            echo "afterScenario: deleting active workflows before continuing..." . PHP_EOL;
        }
        $this->_deleteActiveWorkflows();
    }

    /**
     * Initializes context.
     *
     * @param array $parameters context parameters (set them up
     * through behat.yml)
     */
    public function __construct(array $parameters)
    {

        $this->fixturePath = __DIR__ . "/../../fixtures/";

        $this->useContext('clientlibcontext', new ClientLibContext($parameters, $this->fixturePath));


        // Get conf.ini (test configurations)....

        $this->inifile = __DIR__ . '/../../conf.ini';
        if (!file_exists($this->inifile)) {
            throw new Exception("Can't find conf.ini file in features directory.");
        };

        $this->conf = parse_ini_file($this->inifile,true);
        if (!$this->conf) {
            throw new Exception("Couldn't load features/conf.ini file.");
        }

        if (isset($this->conf['general']['VERBOSE'])) {
            $this->VERBOSE = $this->conf['general']['VERBOSE'];
        } else {
            $this->VERBOSE = false;
        }
        self::$VERBOSE = $this->VERBOSE;

        if ($this->VERBOSE) {
            error_reporting(E_ALL ^ E_NOTICE);
            ini_set("display_errors", 1);
        }

        // Set some convenience variables...

        $this->url = $this->conf['general']['base_uri'];

        // Build our http client...

        $this->client = new c\Client($this->inifile);

        // Other things set elsewhere...

        // The request made in this scenario (xml format); see
        // $this->get() and others.
        $this->response = NULL;
        // Basic auth is assumed throughout.  Set to a user that has
        // no groups or privileges.
        $this->username = $this->conf['credentials']['nogroups_username'];
        $this->password = $this->conf['credentials']['nogroups_password'];
        $this->response_raw = NULL; // see $this->get().
        $this->xml = NULL; // see $this->get().
        $this->json = NULL; // see $this->get().
        $this->_expectation = NULL; // see $this->expect().
    }

    /**
     * Create Expectation instance for $thing and return it.
     *
     * $this->expect('foo')->equals('bar'); // => exception
     */
    public function expect($thing)
    {
        if (!isset($this->_expectation)) {
            $this->_expectation = new Expectation();
        }
        $this->_expectation->setThing($thing);
        return $this->_expectation;
    }

    /**
     * Wrapper for making get requests.
     *
     * Sets:
     * $this->response = the response
     * $this->response_sxml = simplexml parse of the response body
     * $this->json = json parse of the response body
     *
     * TODO: this means making 2 requests, but at least it
     * will test everything.
     * TODO: put the json in
     *
     */
    public function get($uri)
    {
        if ($this->VERBOSE) {
            echo "============================================================" . PHP_EOL;
            echo "* GET $uri" . PHP_EOL;
            echo "* basic auth: " . $this->username . "/" . $this->password . PHP_EOL;
        }
        $format = 'xml';  // must be 'xml' or 'json' to trigger API
        $parse = false;   // whether to get httpful to parse as
                          // $format, can make figuring what's gone
                          // wrong harder.
        $this->response = $this->client
                               ->requestGET($uri, $format, $parse,
                                            $this->username, $this->password);
        $this->_handle_response($this->response);
        //$this->json    = $this->requestGET($url, 'json', $username, $password);
    }


    public function getDownload($uri)
    {
        if ($this->VERBOSE) {
            echo "============================================================" . PHP_EOL;
            echo "* GET download $uri" . PHP_EOL;
            echo "* basic auth: " . $this->username . "/" . $this->password . PHP_EOL;
        }
        $format = 'xml';  // must be 'xml' or 'json' to trigger API
        $parse = false;   // whether to get httpful to parse as
                          // $format, can make figuring what's gone
                          // wrong harder.
        $this->response = $this->client
                               ->requestGET($uri, $format, $parse,
                                            $this->username, $this->password);
    }

    /**
     * POST xml or json, similar to get().
     */
    public function post($uri, $body, $attachment = null, $handle_response = true)
    {
        if ($this->VERBOSE) {
            echo "============================================================" . PHP_EOL;
            echo "* POST $uri" . PHP_EOL;
            echo "* basic auth: " . $this->username . "/" . $this->password . PHP_EOL;
        }
        // See get():
        $format = 'xml';
        $parse = false;

        if ($this->VERBOSE) {
            echo "* POST body:" . PHP_EOL;
            echo $body;
            echo PHP_EOL . PHP_EOL;
        }

        $this->response = $this->client
                               ->requestPOST($uri, $body, 'xml', false,
                                             $this->username, $this->password, $attachment);
        if ($handle_response) {
            $this->_handle_response($this->response);
        }
    }

    /**
     * Helper for get() and post().
     */
    private function _handle_response()
    {
        $this->response_raw = $this->response->raw_body;
        try {
            if ($this->VERBOSE) {
                $this->response_sxml = new SimpleXMLExtended($this->response->raw_body);
                echo "VERBOSE output START: showing RESPONSE -------------" . PHP_EOL;
                echo self::indentXML($this->response_sxml) . PHP_EOL;
                echo "VERBOSE output END --------------------------------" . PHP_EOL;
            } else {
                $this->response_sxml = @new SimpleXMLExtended($this->response->raw_body);
            }
            $this->actions = c\getActions($this->response_raw);
        } catch (Exception $e) {
            $this->response_sxml    = NULL;
            if ($this->VERBOSE) {
                echo "Simple xml could not parse as xml" . PHP_EOL . PHP_EOL;
                echo "Output: START" . PHP_EOL;
                print_r($this->response_raw);
                echo PHP_EOL . PHP_EOL . "Output: END" . PHP_EOL;
            }
            throw $e;
            //throw new Exception("Got non-xml");
        }

        // TODO:
        $this->json    = null;

        return $this->response;
    }


    /**
     * Try to indent xml (for debugging purposes). Returns the xml
     * string.
     *
     * Httpful uses simplexml.
     *
     * TODO: I'm having issues getting simplexml and DOMDocument to
     * produce indented output.
     */
    public static function indentXML(SimpleXMLExtended $sxml)
    {
        $dom = new DOMDocument();
        $dom->preserveWhitespace = false;
        $dom->formatOutput = true;
        //$xml = $sxml->asXML();
        //$xml = preg_replace('/[\t\s]/',' ',preg_replace('/^\s*\n/m','',$sxml->asXML()));
        $xml = preg_replace('/^\s*\n/m', '', $sxml->asXML());
        $dom->loadXML($xml);
        return $dom->saveXML();
    }


    //============================================================
    // Helper functions

    // Run before each feature to remove workflows that didn't get
    // abandoned or completed.

    private function _deleteActiveWorkflows()
    {
        $uri = $this->url . "/my_active_workflows.php";
        $this->asSuperAdministrator();
        $this->get($uri);
        foreach ($this->response_sxml->item as $item) {
            $wfses_id = $item->wfses_id;
            $this->get($this->url . "/workflow/abandon.php?id=$wfses_id");
            //echo self::indentXML($this->response_sxml);
        }
    }

    // Hits enter_metadata to get a template for a new record but nothing more.

    private function _requestUnsavedRecordForCollection($collection, $xdis_id)
    {
        $this->workflow_new_params = array(
            'collection_pid' => $collection,
            'xdis_id' => $xdis_id,
            'xdis_id_top' => $xdis_id,
            'wft_id' => 346,
        );
        $uri = $this->url . "/workflow/new.php?" . http_build_query($this->workflow_new_params);
        $this->get($uri);
    }

    // Hits enter_metadata to get a template for a new record and
    // loads it into the client record class.

    private function _createUnsaveRecordForCollection($collection, $xdis_id)
    {
        $this->_requestUnsavedRecordForCollection($collection, $xdis_id);
        return c\Record::createFromMetadataXml($this->response_raw);
    }

    // Perform action (Publish, Save, Reject, Submit for approval) on
    // $rec using supplied actions.
    //
    // Note: $action is case insensitive.
    //
    // @param c\Record $rec  Instance of a client record (usually generated from enter_metadata)
    // @param string $action Should be 'name' of action.
    // @param array $actions Should be $this->actions which is parsed from xml when we get or post.

    private function _actionRecord(c\Record $rec, $action, array $actions)
    {
        $actions = array_filter($actions, function ($a) use ($action) {
            return preg_match("/$action/i", $a['name']);
        });
        $this->expect(count($actions))->equals(1, "We should see one action: $action");
        $actions = array_values($actions);
        $uri = $this->url . $actions[0]['uri'];

        $this->request_sxml = new SimpleXMLElement('<record>' . $this->record->toXml() . '</record>');
        $this->_removeNotRequiredFieldsFromRequest($rec); // we just want to make sure we're not posting any id's without values
        $this->post($uri, $this->request_sxml->asXml());
        if (!isset($this->response_sxml->return->pid)) {
            throw new Exception("Expecting API response with pid value.");
        }
        $pid = (string)$this->response_sxml->return->pid;
        $this->expect($pid)->matches('/^[A-z]+:[0-9]+$/');
        return $pid;
    }

    // Throw error if we don't have actions.
    //
    // $this->actions gets set AUTOMATICALLY when we get() or post().

    private function _hasActions()
    {
        if (!isset($this->actions)) {
            throw new Exception("\$this->actions needs to be set by a previous step!");
        }
    }

    // Go through all the required fields in a client record and
    // populate them with something.
    private function _populateRequiredFields(c\Record $rec)
    {
        $required = $rec->requiredFields();
        foreach ($required as $field) {
            if (empty($field->xsdmf_values)) {
                switch($field->xsdmf_html_input) {
                    case 'checkbox':
                        $field->xsdmf_values[] = "on";
                        break;
                    case 'date-year':
                        $field->xsdmf_values[] = array("year" => "2014");
                        break;
                    case 'date-full':
                        $field->xsdmf_values[] = array("day" => "12", "month" => "12", "year" => "2014");
                        break;
                    default:
                        if ($field->xsdmf_title == 'Member of Collections:') {
                            // This should be a valid PID in this case. Fedora won't ingest the record otherwise.
                            $field->xsdmf_values[] = $this->collection;
                        } else {
                            $field->xsdmf_values[] = "some value";
                        }
                        break;
                }
            }
        }
        return $required;
    }


    // Remove the fields we don't need. The reason we do this, so so we're
    // not POSTing a an xsd_display field with just an xsdmf_id and no xsdmf_value
    private function _removeNotRequiredFieldsFromRequest(c\Record $rec)
    {
        $to_be_removed = array();

        $required = $rec->requiredFields();
        foreach ($this->request_sxml->xsd_display_fields->xsd_display_field as $b) {
            if (!$rec->isRequired((int)$b->xsdmf_id)) {
                $to_be_removed[] = $b;
                // can't just do the below. Can't remove on iteration :(
                // $dom = dom_import_simplexml($b);
                // $dom->parentNode->removeChild($dom);
            }
        }

        // Not the most elegant solution. SimpleXML doesn't seem to provide modification over iteration or an
        // easy way to remove elements for that matter.
        foreach ($to_be_removed as $remove_ele) {
            foreach ($this->request_sxml->xsd_display_fields->xsd_display_field as $b) {
                if ($remove_ele->xsdmf_id == $b->xsdmf_id) {
                    $dom = dom_import_simplexml($b);
                    $dom->parentNode->removeChild($dom);
                    break;
                }
            }
        }
    }


    //============================================================
    // Step definitions

    /**
     * @Given /^this is a hello world$/
     */
    public function thisIsAHelloWorld()
    {
        //throw new PendingException();
    }

    //------------------------------------------------------------
    // Utilities

    /**
     * @Then /^I should get:$/
     */
    public function shouldBe(PyStringNode $string)
    {
        if ($string != "hello world") {
            throw new Exception("Expected hello world.");
        }
    }

    /**
     * Use this to test for any xm-formatted response.
     *
     * @Then /^I should get xml$/
     */
    public function isXml()
    {
        $this->expect($this->response->content_type)
             ->equals('application/xml');
        $this->expect($this->response_sxml)->not_equals(null);
    }

    /**
     * USe this to test a response got an ok-ish http response: 2xx,
     * 3xx (4xx and 5xx are denials and errors etc).
     *
     * @Then /^I should get no errors$/
     */
    public function getNoErrors()
    {
        $this->expect($this->response->code)->matches('/^[23]\d\d$/');
    }

    /**
     * @Given /^the http status should be (\d+)$/
     */
    public function theHttpStatusShouldBe($httpcode)
    {
        $this->expect($this->response->code)->equals($httpcode);
    }

    /**
     * @Given /^the http status should be (\d+) or (\d+)$/
     */
    public function theHttpStatusShouldBeOr($httpcode, $httpcode2)
    {
        // in some cases fez may give a 500 or a 400, depending on whether zend catches the server issue or fez does...
        try {
            $this->expect($this->response->code)->equals($httpcode);
        } catch (Exception $e) {
            $this->expect($this->response->code)->equals($httpcode2);
        }
    }

    /**
     * @Given /^I should get an error message: (.*)$$/
     */
    public function iShouldGetAnErrorMessage($msg)
    {
        switch ($msg) {
        case 'not-found':
            $this->expect($this->response_sxml->msg)
                 ->matches('/not found/');
            break;
        default:
            throw new Exception("Error message '$msg' not handled in this test, add it to this function.");
        }
    }



    /**
     * @Then /^I should get root xml node: (.*)$/
     *
     */
    public function expectRootNode($tagName)
    {
        //echo self::indentXML($this->response_sxml);
        $this->expect($this->response_sxml->getName())->equals(''.$tagName);
    }

    //------------------------------------------------------------
    // Authentication

    /**
     * @Given /^I\'m a super administrator$/
     */
    public function asSuperAdministrator()
    {
        $this->username = $this->conf['credentials']['superadmin_username'];
        $this->password = $this->conf['credentials']['superadmin_password'];
    }

    /**
     * @Given /^I\'m an administrator$/
     */
    public function asAdministrator()
    {
        $this->username = $this->conf['credentials']['admin_username'];
        $this->password = $this->conf['credentials']['admin_password'];
    }

    /**
     * @Given /^I\'m not using basic authentication$/
     */
    public function notUsingBasicAuthentication()
    {
        $this->username = null;
        $this->password = null;
        // Setting either to null should trigger the client to not set
        // basic auth at all.
    }

    /**
     * @Given /^I\'m a nonexistent user$/
     */
    public function asNonexistentUser()
    {
        $this->username = $this->conf['credentials']['nonexistent_username'];
        $this->password = $this->conf['credentials']['nonexistent_password'];
    }

    /**
     * We're authenticate but have no groups and hence no privileges
     * for accessing closed records.
     *
     * This is the default setting for tests.  Making it explicit here.
     * Gets set in __construct.
     *
     * @Given /^I\'m a user with no groups$/
     */
    public function asNoGroups()
    {
        $this->username = $this->conf['credentials']['nogroups_username'];
        $this->password = $this->conf['credentials']['nogroups_password'];
    }

    /**
     * @Given /^I\'m a viewer$/
     */
    public function asViewer()
    {
        $this->username = $this->conf['credentials']['viewer_username'];
        $this->password = $this->conf['credentials']['viewer_password'];
    }

    /**
     * @Given /^I\'m an editor$/
     */
    public function asEditor()
    {
        $this->username = $this->conf['credentials']['editor_username'];
        $this->password = $this->conf['credentials']['editor_password'];
    }

    /**
     * @Given /^I\'m an approver$/
     */
    public function asApprover()
    {
        $this->username = $this->conf['credentials']['approver_username'];
        $this->password = $this->conf['credentials']['approver_password'];
    }

    /**
     * @Given /^I\'m a lister$/
     */
    public function asLister()
    {
        $this->username = $this->conf['credentials']['lister_username'];
        $this->password = $this->conf['credentials']['lister_password'];
    }

    //------------------------------------------------------------
    // Community specific

    /**
     * @When /^getting a community from: (.*)$/
     */
    public function community($community)
    {
        $community = trim($community);
        if (!isset($this->conf[$community]['community'])) {
            throw new Exception("Can't find community: '$community.community'");
        }
        $uri = $this->url
            . '/community/'
            . $this->conf[$community]['community'];
        $this->get($uri);
    }

    /**
     * @When /^getting a non-existent community$/
     */
    public function gettingANonExistentCommunity()
    {
        if (!isset($this->conf['general']['nonexistent-community'])) {
            throw new Exception("Can't find community: general.nonexistent-community in conf.ini");
        }
        $pid = $this->conf['general']['nonexistent-community'];
        $uri = $this->url . '/community/' . $pid;
        $this->get($uri);
    }


    /**
     * @Given /^it should contain a list of collections$/
     *
     */
    public function containListOfCollections()
    {
        // We expect at least one item.
        $item = $this->response_sxml->item[0];
        $this->expect(isset($item->pid))->equals(true);
        $this->expect($item->identifier)
             ->matches('/\/collection\//');
    }

    //------------------------------------------------------------
    // Collection specific

    /**
     * @When /^getting a collection from: (.*)$/
     */
    public function collection($community)
    {
        if (!isset($this->conf[$community]['collection'])) {
            throw new Exception("Can't find community: '$community' or '$community.collection'");
        }
        $uri = $this->url
            . '/collection/'
            . $this->conf[$community]['collection'];
        $this->get($uri);
    }

    /**
     * @When /^getting a large collection from: (.*) with (\d+) rows at page (\d+)$/
     */
    public function gettingALargeCollectionFrom($community, $rows, $pager_row)
    {
        if (!isset($this->conf[$community]['large_collection'])) {
            throw new Exception("Can't find community: '$community' or '$community.large_collection'");
        }
        $uri = $this->url
            . '/collection/'
            . $this->conf[$community]['large_collection']
            . "?rows=$rows&pager_row=$pager_row";
        $this->get($uri);
    }

    /**
     * @Then /^I should get (\d+) records in the collection$/
     */
    public function countRecordsInCollection($rows)
    {
        $count = 0;
        foreach ($this->response_sxml->item as $item) {
            $count++;
            $this->expect($item->pid)->matches('/.+:\d+/');
        }
        $this->expect($count)->equals((int)$rows);
    }

    /**
     * @Then /^rows should be (\d+) and pager_row should be (\d+)$/
     */
    public function rowsShouldBeAndPagerRowShouldBe($rows, $pager_row)
    {
        if (!isset($this->response_sxml)) {
            throw new Exception("Test required getting a collection.");
        }
        $this->expect((int)$this->response_sxml->pager_row)->equals((int)$pager_row);
        $this->expect((int)$this->response_sxml->rows)->equals((int)$rows);
    }


    /**
     * @When /^getting a non-existent collection$/
     */
    public function gettingANonExistentCollection()
    {
        if (!isset($this->conf['general']['nonexistent-collection'])) {
            throw new Exception("Can't find collection: general.nonexistent-collection in conf.ini");
        }
        $pid = $this->conf['general']['nonexistent-collection'];
        $uri = $this->url . '/collection/' . $pid;
        $this->get($uri);
    }


    /**
     * @Given /^it should contain a list of records$/
     */
    public function containListOfRecords()
    {
        // We expect at least one item.
        //echo self::indentXML($this->response_sxml) . PHP_EOL;
        $item = $this->response_sxml->item[0];
        $this->expect(isset($item->pid))->equals(true);
        $this->expect($item->identifier)
             ->matches('/\/view\//');
    }

    /**
     * @Then /^I should get a list of available xdis_id types with creation uris$/
     */
    public function iShouldGetAListOfAvailableXdisIdTypesWithCreationUris()
    {
        $this->expect(isset($this->response_sxml->actions->action))
             ->equals(true);
        $this->expect((string)$this->response_sxml->actions->action[0]->uri)
             ->matches('#/workflow/new#');
        $this->expect(count($this->actions)>=1)->equals(true);
    }

    /**
     * @Given /^remove the display field \'([^\']*)\' content$/
     */
    public function removeTheDisplayFieldValue($xsd_field_name)
    {
        foreach ($this->request_sxml->xsd_display_fields->xsd_display_field as $b) {
            if ($b->xsdmf_title == $xsd_field_name) {
                $b->xsdmf_value = '';
                // $dom = dom_import_simplexml($b);
                // $dom->parentNode->removeChild($dom);
                break;
            }
        }
    }

    /**
     * @Given /^change the datastream name of \'([^\']*)\' to \'([^\']*)\'$/
     */
    public function changeTheDatastreamNameOfTo($ds_old, $ds_new)
    {
        if (!$this->request_sxml->datastreams) {
            $ds_w = $this->request_sxml->addChild('datastreams');
        }
        // <datastream_create>...</datastream_create> is used for adding new attachments
        // <datastream_edit>...</datastream_edit> is used for editing attachments

        $ds = $this->request_sxml->datastreams->addChild('datastream_edit');
        $ds->addChild('id', $ds_old);
        $ds->addChild('new_id', $ds_new);
    }

    /**
     * @Given /^change the datastream permission of \'([^\']*)\' to \'([^\']*)\' with embargo date \'([^\']*)\'$/
     */
    public function changeTheDatastreamPermission($ds_old, $ds_permission, $ds_embargo)
    {
        if (!$this->request_sxml->datastreams) {
            $ds_w = $this->request_sxml->addChild('datastreams');
        }
        $ds = $this->request_sxml->datastreams->addChild('datastream_edit');
        $ds->addChild('id', $ds_old);
        $ds->addChild('permission', $ds_permission);

        $value = explode("-", $ds_embargo);
        $child = $ds->addChild('embargo_date');
        $child->addChild('year', $value[0]);
        $child->addChild('month', $value[1]);
        $child->addChild('day', $value[2]);
    }

    /**
     * @Given /^change the datastream description of \'([^\']*)\' to \'([^\']*)\'$/
     */
    public function changeTheDatastreamDescription($ds_old, $ds_description)
    {
        if (!$this->request_sxml->datastreams) {
            $ds_w = $this->request_sxml->addChild('datastreams');
        }
        $ds = $this->request_sxml->datastreams->addChild('datastream_edit');
        $ds->addChild('id', $ds_old);
        $ds->addChild('description', $ds_description);
    }

    /**
     * @Given /remove the datastream id field$/
     */
    public function removeTheDatastreamId()
    {
        $this->request_sxml->datastreams->datastream_create->xsdmf_id = '';
    }

    //------------------------------------------------------------
    // Record specific

    /**
     * @When /^getting a record \'([^\']*)\'$/
     */
    public function getRecord($rec = "public_community.record", $published = true)
    {
        $arr = explode('.', $rec);
        if (count($arr) < 2) {
            throw new Exception("Can not find record {$rec}.");
        }
        if (!isset($this->conf[$arr[0]][$arr[1]])) {
            throw new Exception("'$rec' not set in conf.ini.");
        }
        $pid = $this->conf[$arr[0]][$arr[1]];
        $this->pid = $pid;
        $uri = $this->url . '/view/' . $pid;
        $this->get($uri);
    }

    /**
     * @When /^getting a non-existent record$/
     */
    public function getNonExistentRecord()
    {
        if (!isset($this->conf['general']['nonexistent-record'])) {
            throw new Exception("Can't find collection: general.nonexistent-record in conf.ini");
        }
        $pid = $this->conf['general']['nonexistent-record'];
        $uri = $this->url . '/view/' . $pid;
        $this->get($uri);
    }


    //------------------------------------------------------------
    // Steps related to creating a record

    /**
     * @When /^starting a new record workflow for collection in: (.+)$/
     */
    public function createRecord($community, $shouldFail = false)
    {
        if (!isset($this->conf[$community])) {
            throw new Exception("Can't find community '$community' in conf.ini");
        }
        if (!isset($this->conf[$community]['collection'])) {
            throw new Exception("Can't find collection in '$community' in conf.ini");
        }
        $collection = $this->conf[$community]['collection'];
        $this->collection = $collection;
        $xdis_id = $this->conf[$community]['xdis_id'];
        /*
        $this->workflow_new_params = array(
            'collection_pid' => $collection,
            'xdis_id' => $xdis_id,
            'xdis_id_top' => $xdis_id,
            'wft_id' => 346,
        );
        $uri = $this->url . "/workflow/new.php?" . http_build_query($this->workflow_new_params);
        $this->get($uri);
        $this->record = c\Record::createFromMetadataXml($this->response_raw);
        */
        if ($community == 'thesis') {
            // Theses have a diffferent workflow. An intermediary screen where you must select the type of action
            // get the action for 'Create Generic Record in Selected Collection'

            $this->workflow_new_params = array(
                'collection_pid' => $collection,
                'xdis_id' => $xdis_id,
                'xdis_id_top' => $xdis_id,
                'wft_id' => 346,
            );
            $uri = $this->url . "/workflow/new.php?" . http_build_query($this->workflow_new_params);
            $this->get($uri);

            // Make sure that $this->response_sxml returned actions...
            // $this->response_sxml
            if (count($this->response_sxml->actions->action) > 1) {
                $contains_actions = true;
            }
            $this->expect($contains_actions)->equals(true);
        } else {
            $this->record = $this->_createUnsaveRecordForCollection($collection, $xdis_id);
            if (!$shouldFail) {
                $this->expect((int)$this->response_sxml->wfses_id > 0)->equals(true);
                $this->wfses_id = (int)$this->response_sxml->wfses_id;
            }
        }
    }

    /**
     * @When /^starting a new record workflow with bad display id$/
     */
    public function newRecordWorkflowWithBadDisplayId()
    {
        $collection = $this->conf['public_community']['collection'];
        $xdis_id = 0;
        $this->_requestUnsavedRecordForCollection($collection, $xdis_id);
    }

    /**
     * @Then /^starting the thesis action \'([^\']*)\'$/
     */
    public function startingTheThesisAction($action_name)
    {
        foreach ($this->response_sxml->actions->action as $action) {
            if ((string)$action->name == $action_name) {
                $uri = $this->url . trim($action->uri);
            }
        }
        $this->get($uri);
    }


    /**
     * @When /^starting a new failing record workflow for collection in: (.+)$/
     */
    public function createRecordFail($community)
    {
        try {
            $this->createRecord($community, true);
        } catch (Exception $e) {
            // squelch it. We should see the expection.
            $failed = true;
        }
        $this->expect($failed)->equals(true);
    }


    /**
     * @Given /^I\'m on the collection page \'([^\']*)\'$/
     */
    public function iMOnTheCollectionPage($collection_page)
    {
        // Go to the collection view page
        if ($collection_page == '' || $collection_page == 'default.record') {
            $collection_page = "public_community.collection";
        }

        $arr = explode('.', $collection_page);
        if (count($arr) < 2) {
            throw new Exception("Can not find record {$rec}.");
        }
        $pid = $this->conf[$arr[0]][$arr[1]];
        $uri = $this->url . '/collection/' . $pid;
        $this->get($uri);
    }

    /**
     * @When /^starting a new record workflow for \'([^\']*)\'$/
     */
    public function startingANewRecordWorkflowFor($action_name)
    {
        $this->iShouldSeeElement('actions'); //just make sure it is there
        $arr = explode('.', $action_name);
        if (count($arr) < 2) {
            throw new Exception("Can not find record {$rec}.");
        }
        $action_name = $this->conf[$arr[0]][$arr[1]];

        $found = false;

        foreach ($this->response_sxml->actions->children() as $a => $b) {
            if ($a == 'action') {
                //
                if (strpos($b->name, $action_name) !== false) {
                    $wfl_url = (string)$b->uri;
                    $found = true;
                    break;
                }
            }
        }

        if ($found == false) {
            throw new Exception("Can not find workflow URI in response");
        }

        $wfl_url = $this->url . preg_replace('/\s+/', '', $wfl_url);
        $this->get($wfl_url);
    }


    /**
     * @Given /^add the link datastream \'([^\']*)\'$/
     */
    public function addTheLinkDatastream($value)
    {
        // The link datastream is just xsdmf_field DOI
        $this->addTheDisplayFieldValue('DOI', $value);
    }

    /**
     * @Given /^I should see element \'([^\']*)\'$/
     */
    public function iShouldSeeElement($arg)
    {
        if (!property_exists($this->response_sxml, $arg)) {
            throw new Exception("{$arg} was not found in the view record xml");
        }
    }

    /**
     * @When /^GETing the uri for display field \'([^\']*)\'$/
     */
    public function viewingTheUriForDisplayField($xsd_field_name)
    {
        // find the xml field
        $this->checkingTheUriForDisplayField($xsd_field_name);
        $this->get($this->current_uri);
    }

    /**
     * @When /^save the uri for display field \'([^\']*)\'$/
     */
    public function checkingTheUriForDisplayField($xsd_field_name)
    {
        // find the xml field
        foreach ($this->response_sxml->xsd_display_fields->xsd_display_field as $a => $b) {

            if ($b->xsdmf_title == $xsd_field_name) {
                // Test if there is a uri field option
                $uri = (string)$b->field_options->uri;
                if ($uri != '') {
                    $found = true;
                }
                break;
            }
        }

        if (!$found) {
            throw new Exception("Display Field not found {$xsd_field_name}");
        }
        $this->current_uri = $this->url . preg_replace('/\s+/', '', $uri);
    }

    /**
     * @When /^getting the xsdmf_id for display field \'([^\']*)\'$/
     */
    public function gettingTheIdForDisplayField($display_field_name)
    {
        $xsd_display_fields = $this->request_sxml->xsd_display_fields;
        foreach ($this->xsdmf as $field) {
            if ((string)$field->xsdmf_title == (string)$display_field_name) {
                // if the xsdmf_id already exists just add another value
                $xsdmf_id = $field->xsdmf_id;
                break;
            }
        }
        return $xsdmf_id;
    }

    /**
     * @Then /^get uri for the action \'([^\']*)\'$/
     */
    public function getUriForTheAction($action_name)
    {
        // Look at the request xml we are constructing.
        // This should be called after "Then using that response xml", but before "And using the available fields in the xml"
        foreach ($this->response_sxml->actions->action as $a => $b) {
            if (trim($b->name) == trim($action_name)) {
                $uri = (string)$b->uri;
                if ($uri != '') {
                    $found = true;
                }
                break;
            }
        }
        if (!$found) {
            throw new Exception("Display Field not found {$xsd_field_name}");
        }
        $this->uri = $this->url . preg_replace('/\s+/', '', $uri);
    }

    /**
     * @When /^getting element path uri \'([^\']*)\'$/
     */
    public function gettingElementValue($path)
    {
        //$uri = (string)$this->$sxml->workflow->datastreams->datastream[0]->purge_uri; // Doesn't work!!!
        // /workflow/datastreams/datastream/purge_uri
        $uri = $this->response_sxml->xpath($path);
        $uri = (string)$uri[0];

        if (strlen($uri) < 1) {
            throw new Exception("Could not find path");
        }
        $uri = $this->url . preg_replace('/\s+/', '', $uri);
        $this->uri = $uri;
    }


    /**
     * @Then /^POSTing that xml to our uri$/
     */
    public function postingThatXmlToOurUri()
    {
        $uri = $this->uri;
        $s = $this->request_sxml->asXML();
        $this->post($uri, $s);
    }


    /**
     * @Given /^POSTing that uri with body \'([^\']*)\'$/
     */
    public function postingThatUri($body = '')
    {
        $uri = $this->uri;
        $this->post($uri, '');
    }

    /**
     * @Given /^POSTing that uri with attachment \'([^\']*)\' as upload \'([^\']*)\'$/
     */
    public function postingThatUriWithAttachment($attachment = '', $file_num)
    {
        // This is just saying don't use the default number 0, but the one we specify
        $n = strrpos($this->current_uri, 'fileNumber=') + 11;
        $this->current_uri[$n] = $file_num;

        $response = $this->post($this->current_uri, null, $attachment);
        echo $response;
    }

    /**
     * @Given /^GETting that uri$/
     */
    public function gettingThatUri()
    {
        if (!isset($this->pid)) {
            throw new Exception("Can't run test: \$this->pid not set.");
        }
        $response = $this->get($this->uri);
    }


    /**
     * @Then /^I should get xml with element \'([^\']*)\' and content containing \'([^\']*)\'$/
     */
    public function iShouldGetXmlWithElementAndContent($element, $content)
    {
        $res_content = $this->response_sxml->xpath('//' . $element);
        $found = null;
        foreach ($res_content as $r) {
            $s = (string)$r;
            if (strpos($s, $content) !== false) {
                $found = $r;
                break;
            }
        }
        $this->expect(is_null($found))->equals(false, "Couldn't find an element with identical content.");
        $this->found = (string)$found;
    }

    /**
     * @Then /^I should get xml but not see element \'([^\']*)\'$/
     */
    public function iShouldGetXmlWithOutElement($element)
    {
        $res_content = $this->response_sxml->xpath('//' . $element);
        var_export($res_content);
        var_export(count($res_content));
        $this->expect(count($res_content))->equals(0, "Unexpectedly found element '$element'.");
    }

    /**
     * @Then /^I should get xml with element \'([^\']*)\' and content matching (\S.+\S)$/
     */
    public function iShouldGetXmlWithElementAndContentMatching($element, $pattern, $uri = null)
    {
        $res_content = $this->response_sxml->xpath('//' . $element);
        $found = null;
        foreach ($res_content as $r) {
            $s = (string)$r;
            if (preg_match($pattern, $s)) {
                $found = $r;
                break;
            }
        }
        $this->expect(is_null($found))->equals(false, "Couldn't find an element matching this patter.");
        $this->found = (string)$found;
    }

    /**
     * @Then /^I should get xsd_display fields$/
     */
    public function isXsdDisplayFields()
    {
        $x = $this->response_sxml->xsd_display_fields;
        $this->expect($x->count())->gt(0);
        // Each field should have a numeric xsdmf_id, only testing one
        // here:
        $this->expect($x->xsd_display_field[0]->xsdmf_id)
             ->matches('/^[0-9]+$/');
    }

    /**
     * @When /^posting to create a new record$/
     */
    public function createNewRecord()
    {
        if (!$this->wfses_id) {
            throw new Exception("Can't run this test: \$this->wfses_id not set.");
        }
        if (!isset($this->record)) {
            throw new Exception("Can't run this test: \$this->record not set.");
        }

        // Fill in the required fields:
        $required = $this->_populateRequiredFields($this->record);
        $xml = $this->record->toXml();

        $actions = c\byKey('name', $this->actions);
        $publish = $actions['Publish'];

        $uri = $this->url . $publish['uri'];

        $this->request_sxml = new SimpleXMLElement('<record>' . $xml . '</record>');
        $this->_removeNotRequiredFieldsFromRequest($this->record);
        $this->post($uri, $this->request_sxml->asXml());
        $this->pid = basename((string)$this->response_sxml->return->pid);
    }


    /**
     * @Then /^I should get a pid number$/
     */
    public function gotPid()
    {
        if (!isset($this->pid)) {
            throw new Exception("Can't run test: \$this->pid not set.");
        }
        $this->expect($this->pid)->matches('/^[A-z]+:[0-9]+$/');
    }

    /**
     * @Then /^the record should belong to collection in: (.+)$/
     */
    public function theNewRecordShouldBelongToCollection($community)
    {
        $community = trim($community);
        if (!isset($this->conf[$community]['collection'])) {
            throw new Exception("Can't find collection: '$community.collection'");
        }
        $collection = $this->conf[$community]['collection'];
        if (!isset($this->pid)) {
            throw new Exception("Can't run test: \$this->pid not set.");
        }
        $r = new Record();
        $recs = $r->getParents($this->pid);
        $this->expect(array_search($collection, $recs))->equals($collection);
        //$this->expect(count($recs)>0)->equals(true);
    }

    /**
     * @Then /^record \'([^\']*)\' should have group \'([^\']*)\'$/
     */
    public function recordShouldHaveGroup($rec = 'edit_security.record', $group_conf_name)
    {
        $arr = explode('.', $rec);
        if (count($arr) < 2) {
            throw new Exception("Can not find record {$rec}.");
        }
        if (!isset($this->conf['credentials'][$group_conf_name])) {
            throw new Exception("Can't find group key in conf.ini: '$group_conf_name'");
        }
        $group_name = $this->conf['credentials'][$group_conf_name];
        list($role,$_) = explode('_', $group_conf_name);
        $role = ucfirst($role);
        $gid = Group::getID($groupname);
        $pid = $this->conf[$arr[0]][$arr[1]];
        $acml_dom = Record::getACML($pid);
        //print_r($acml_dom->saveXML());
        $xpath = new DOMXPath($acml_dom);
        $res = $xpath->query("/FezACML/rule/role[@name='Creator']/Fez_Group");
        $this->expect($res->length)->equals(1, "There should be on role tag with attribute 'Creator'");
        $this->expect($res->item(0)->nodeValue)->equals($gid);
    }

    /**
     * @Then /^\'([^\']*)\' should belong to \'([^\']*)\'$/
     */
    public function theRecordShouldBelongTo($rec = 'public_community.record', $colpid)
    {
        $arr = explode('.', $rec);
        if (count($arr) < 2) {
            throw new Exception("Can not find record {$rec}.");
        }
        $pid = $this->conf[$arr[0]][$arr[1]];
        $arr = Record::getParents($pid);
        $this->expect(in_array($colpid, $arr))->equals(true);
    }



    /**
     * @Then /^the record should be created$/
     */
    public function newRecordCreated()
    {
        if (!isset($this->pid)) {
            throw new Exception("Can't run test: \$this->pid not set.");
        }
        // Test new pid exists...
        $r = new Record();
        $recs = $r->getDetailsLite($this->pid);
        $this->expect(!empty($recs))->equals(true);
        $this->expect(count($recs))->equals(1);
    }

    /**
     * @Given /^the record should be published$/
     */
    public function isPublished()
    {
        if (!isset($this->pid)) {
            throw new Exception("Can't run test: \$this->pid not set.");
        }
        $recs = Record::getDetailsLite($this->pid);
        $this->expect(!empty($recs))->equals(true);
        $this->expect(count($recs))->equals(1);
        list($first) = $recs;
        $this->expect($first['rek_status'])->equals(2);
    }

    /**
     * @Given /^the record should be unpublished$/
     */
    public function isUnpublished()
    {
        if (!isset($this->pid)) {
            throw new Exception("Can't run test: \$this->pid not set.");
        }
        $recs = Record::getDetailsLite($this->pid);
        $this->expect(!empty($recs))->equals(true);
        $this->expect(count($recs))->equals(1);
        list($first) = $recs;
        $this->expect($first['rek_status'])->not_equals(2);
    }

    /**
     * @Then /^the new record should have a subject controlled vocab item$/
     */
    public function newRecordHasContVocab()
    {
        if (!isset($this->pid)) {
            throw new Exception("Can't run test: \$this->pid not set.");
        }
        $rec = new Record($this->pid);
        $recs = $rec->getDetailsLite($this->pid);
        list($first) = $recs;
        $subjects = $rec->getSearchKeyIndexValue($this->pid, 'Subject');
        $fixture = Fixtures::display_type_instance('doc-with-cont-vocab');
        $cvo_id = $fixture->fields['subjects'][0]['cvo_id'];

        $this->expect(is_array($subjects))->equals(true);
        $this->expect(count($subjects))->equals(1);
        $this->expect(array_key_exists($cvo_id,$subjects))->equals(true);
    }


    //------------------------------------------------------------
    // Steps related to editing an existing record

    /**
     * @When /^editing a record \'([^\']*)\'$/
     */
    public function editRecord($rec = 'public_community.record')
    {

        $arr = explode('.', $rec);
        if (count($arr) < 2) {
            throw new Exception("Can not find record {$rec}.");
        }
        $pid = $this->conf[$arr[0]][$arr[1]];

        // TODO: is this the right url for editing a record?
        // TODO: workflow/edit_metadata.php ?
        $uri = $this->url . "/workflow/update.php?" .
            http_build_query(array(
                'pid'=> $pid,
                'cat'=> 'select_workflow',
                'xdis_id'=> 187,
                'wft_id'=> 393,
                'href'=> "/view/$pid",
            ));
        $this->get($uri);
    }

    /**
     * @When /^starting an edit record workflow$/
     */
    public function startingAnEditRecordWorkflow()
    {
        $collection = $this->conf['public_community']['collection'];
        $record = $this->conf['public_community']['record'];
        $uri = $this->url . "/workflow/update.php?" .
            http_build_query(array(
                'pid' => $record,
                'cat' => 'select_workflow',
                'xdis_id' => 179,
                'wft_id' => 393,
            ));
        $this->$uri = $uri; // just in case we need to access it later, stick on the object
        $this->get($uri);
        $this->pid = $record;
        //echo self::indentXML($this->response_sxml);
        if (!$this->response_sxml->wfses_id) {
            throw new Exception("wfses_id (fez_workflow_session.id) not detected in xml.");
        }
        $this->expect((int)$this->response_sxml->wfses_id)->not_equals(0);
        $this->wfses_id = $this->response_sxml->wfses_id;
    }

    /**
     * Assume /^starting an edit record workflow/
     *
     * @When /^posting to update a record title$/
     */
    public function updateRecordTitle()
    {
        if (!$this->wfses_id || !$this->pid) {
            throw new Exception("Can't run this test, one or more of: \$this->wfses_id|sta_id|pid not set.");
        }
        $display_type_instance = Fixtures::display_type_instance('document1');
        $this->display_type_instance = $display_type_instance;
        $sta_id = 2;
        $other = array(
          'workflow_button' => array('id' => 991, 'value' => 'Publish'),
          //ie: 'workflow_button_991' => 'Publish',
          'cat' => 'update_form',
        );
        $data = $display_type_instance->createRecord($this->wfses_id, $sta_id, $other);
        $xml = API::toXml($data);
        //echo $xml . PHP_EOL;
        $uri = $this->url . "/workflow/edit_metadata.php";
        $this->post($uri, $xml);
    }

    /**
     * @Then /^the updated record should show the updated title$/
     */
    public function theUpdatedRecordShouldShowTheUpdatedTitle()
    {
        if (!$this->pid || !$this->display_type_instance) {
            throw new Exception("Can't run this test: \$this->pid or \$this->display_type_instance not set.");
        }
        $r = new Record();
        $recs = $r->getDetailsLite($this->pid);
        $this->expect(count($recs))->equals(1);
        $rec = $recs[0];
        $this->expect($rec['rek_title'])
             ->equals($this->display_type_instance->fields['title']);
        //print_r($rec);
        //print_r($this->display_type_instance);
    }


    //------------------------------------------------------------
    // Workflow specific


    /**
     * @Then /^I can\'t see workflow: (.+)$/
     */
    public function iCanTSeeWorkflow($action)
    {
        $this->_hasActions();
        $found = array_filter($this->actions, function ($a) use ($action) {
            if (preg_match("/$action/i", $a['name'])) {
                return true;
            }
        });
        $this->expect(count($found))->equals(0, "There should be no '$action' action.");
    }

    /**
     * @Then /^I can see workflow: (.+)$/
     */
    public function iCanSeeWorkflow($action)
    {
        $this->_hasActions();
        $found = array_filter($this->actions, function ($a) use ($action) {
            if (preg_match("/$action/i", $a['name'])) {
                return true;
            }
        });
        $this->expect(count($found))->equals(1, "There should be no '$action' action.");
    }

    /**
     * @Then /^I can do '([^']+)' on new record for collection in: (.+)$/
     */
    public function creatingANewRecordForCollectionIn($action, $community)
    {
        if (!isset($this->conf[$community])) {
            throw new Exception("Can't find community '$community' in conf.ini");
        }
        if (!isset($this->conf[$community]['collection'])) {
            throw new Exception("Can't find collection in '$community' in conf.ini");
        }
        $this->collection = $this->conf[$community]['collection'];
        $this->xdis_id = $this->conf[$community]['xdis_id'];
        $this->record = $this->_createUnsaveRecordForCollection($this->collection, $this->xdis_id);
        $required = $this->_populateRequiredFields($this->record);
        $this->pid = $this->_actionRecord($this->record, $action, $this->actions);
    }

    /**
     * @Then /^the record should be in state: (.+)$/
     */
    public function theRecordShouldBeInStateInCreation($state)
    {
        $recs = Record::getDetailsLite($this->pid);
        $this->expect(count($recs))->equals(1);
        list($first) = $recs;
        $actual = $first['rek_status'];
        $expected = null;
        switch ($state) {
            case 'submit for approval':
                $expected = 3;
                break;
            case 'in creation':
                $expected = 4;
                break;
            case 'published':
                $expected = 2;
                break;
            default:
                throw new Exception("Don't know how to handle state '$state'");
        }
        $this->expect($actual)->equals($expected, "The sta_id's should match.");
    }

    /**
     * @Given /^the workflow should be finished$/
     */
    public function theWorkflowShouldBeFinished()
    {
        if (!isset($this->wfses_id)) {
            throw new Exception("\$this->wfses_id not defined.");
        }
        $w = getWorkflowSession($this->wfses_id);
        $this->expect(is_null($w))->equals(true);
    }

    /**
     * @When /^I try to do a workflow with bad workflow parameter$/
     */
    public function iTryToDoSomethingBad()
    {
        $wfses_id = (int)$this->response_sxml->wfses_id;
        $xdis_id = (int)$this->response_sxml->xdis_id;
        $sta_id = 7;
        $workflow = 'dfs12991'; //this is a junk value that shouldn't exist
        $workflow_val = 'Publish';

        $params = array(
            'id' => $wfses_id,
            'xdis_id' => $xdis_id,
            'sta_id' => $sta_id,
            'workflow' => $workflow,
            'workflow_val' => $workflow_val
        );
        $uri = $this->url . "/workflow/enter_metadata.php?" . http_build_query($params);
        $required = $this->_populateRequiredFields($this->record);
        $this->request_sxml = new SimpleXMLElement('<record>' . $this->record->toXml() . '</record>');
        $this->_removeNotRequiredFieldsFromRequest($this->record);
        $this->post($uri, $this->request_sxml->asXml(), null, false);
    }

    /**
     * @When /^I try to do a workflow with bad display id parameter$/
     */
    public function iTryToDoSomethingBadXdisId()
    {
        $wfses_id = (int)$this->response_sxml->wfses_id;
        $xdis_id = 0; //this is an invalid xdis_id and shouldn't link back to any display type...
        $sta_id = 7;
        $workflow = 991;
        $workflow_val = 'Publish';

        $params = array(
            'id' => $wfses_id,
            'xdis_id' => $xdis_id,
            'sta_id' => $sta_id,
            'workflow' => $workflow,
            'workflow_val' => $workflow_val
        );
        $uri = $this->url . "/workflow/enter_metadata.php?" . http_build_query($params);
        $required = $this->_populateRequiredFields($this->record);
        $this->request_sxml = new SimpleXMLElement('<record>' . $this->record->toXml() . '</record>');
        $this->_removeNotRequiredFieldsFromRequest($this->record);
        $this->post($uri, $this->request_sxml->asXml(), null, false);
    }

    /**
     * @When /^I try to do workflow: publish$/
     */
    public function iTryToDoWorkflowPublish()
    {
        //$uri = $this->getWorkflow('Publish'); // this should error out
        // to publish you need to have a uri in the form id=1067&xdis_id=179&sta_id=4&workflow=991&workflow_val=Publish
        $wfses_id = (int)$this->response_sxml->wfses_id;
        $xdis_id = (int)$this->response_sxml->xdis_id;
        $sta_id = 4;
        $workflow = 991; //This workflow id really tells fez where to go after the POST occurs and can be loaded. That one means publish.
        $workflow_val = 'Publish';

        $params = array(
            'id' => $wfses_id,
            'xdis_id' => $xdis_id,
            'sta_id' => $sta_id,
            'workflow' => $workflow,
            'workflow_val' => $workflow_val
        );
        $uri = $this->url . "/workflow/enter_metadata.php?" . http_build_query($params);
        $required = $this->_populateRequiredFields($this->record);
        $this->request_sxml = new SimpleXMLElement('<record>' . $this->record->toXml() . '</record>');
        $this->_removeNotRequiredFieldsFromRequest($this->record);
        $this->post($uri, $this->request_sxml->asXml());
    }

    /**
     * @Given /^it should be in state: unpublished$/
     */
    public function theRecordShouldBeUnpublished()
    {
        $this->_checkStatus(4);
    }

    /**
     * @Given /^it should be in state: published$/
     */
    public function theRecordShouldBepublished()
    {
        $this->_checkStatus(2);
    }

    private function _checkStatus($sta)
    {
        if (!$this->pid) {
            if (!isset($this->response_sxml->return->pid)) {
                throw new Exception("Can't run test: \$this->pid not set.");
            }
            $this->pid = $this->response_sxml->return->pid;
        }
        $recs = Record::getDetailsLite($this->pid);
        $this->expect(!empty($recs))->equals(true);
        $this->expect(count($recs))->equals(1);
        list($first) = $recs;
        $this->expect($first['rek_status'])->equals($sta); //4 is unpublished... 2 is published
    }

    /**
     * Get thes URI for the workflow with the name of the given parameter $name
     * @param  string $name the workflow name
     * @return string   the workflow URI if exists
     */
    public function getWorkflow($name)
    {
        $found = false;

        foreach ($this->response_sxml->workflows->children() as $a => $b) {
            if ($a == 'workflow') {
                if (strpos($b->w_title, $name) !== false) {
                    $wfl_url = (string)$b->w_url;
                    $found = true;
                    break;
                }
            }
        }

        if ($found == false) {
            throw new Exception("Can not find workflow URI in response");
        } else {
            return $wfl_url;
        }
    }

    /**
     * @When /^viewing the workflow \'([^\']*)\'$/
     */
    public function viewWorkflow($workflow_name)
    {
        $this->iShouldSeeElement('workflows'); //just make sure it is there
        $wfl_url = $this->getWorkflow($workflow_name);
        $wfl_url = $this->url . preg_replace('/\s+/', '', $wfl_url);
        $this->get($wfl_url);
    }

    // Danb's stuff:
    /**
     * @When /^getting active workflows$/
     */
    public function getActiveWorkflows()
    {
        $uri = $this->url . "/my_active_workflows.php";
        $this->get($uri);
    }

    /**
     * Put this in initially to delete active workflows... but we
     * should turn this into a test.
     *
     * @Then /^delete active workflows$/
     */
    public function deleteActiveWorkflows()
    {
        self::_deleteActiveWorkflows();
    }


    /**
     * @Then /^using that response xml$/
     */
    public function usingThatResponseXml()
    {
        $this->request_sxml = clone $this->response_sxml;
    }

    /**
     * @Then /^using pid from the response$/
     */
    public function usingPidFromResponse()
    {
        if (!isset($this->response_sxml)) {
            throw new Exception("\$this->response_sxml not set.");
        }
        $this->expect(!is_null($this->response_sxml->return->pid))
             ->equals(true, "Can't get pid in xpath:response/return/pid of response:" .
                      $this->response_sxml->asXml());
        $pid = (string)$this->response_sxml->return->pid;
        $this->pid = $pid;  // we should probably set this.
    }


    /**
     * @Then /^getting pid from the response$/
     */
    public function gettingPidFromResponse()
    {
        if (!isset($this->response_sxml)) {
            throw new Exception("\$this->response_sxml not set.");
        }
        $this->expect(!is_null($this->response_sxml->return->pid))
             ->equals(true, "Can't get pid in xpath:response/return/pid of response:" .
                      $this->response_sxml->asXml());
        $pid = (string)$this->response_sxml->return->pid;
        $this->pid = $pid;  // we should probably set this.
        $uri = $this->url . '/view/' . $pid;
        $this->get($uri);
    }


    /**
     * @Given /^I can GET the workflow\/new link for xdis_id in: (.+)$/
     */
    public function iCanGetTheWorkflowNewLinkForXdisIdIn($community)
    {
        if (!isset($this->conf[$community]['xdis_id'])) {
            throw new Exception("xdis_id for public_community not set in conf.ini.");
        }
        $xdis_id = (int)$this->conf[$community]['xdis_id'];
        if ($xdis_id===0) {
            throw new Exception("Have you configured an xdis_id for restricted_community in conf.ini?");
        }

        $action = null;
        foreach ($this->actions as $a) {
            if ((int)$a['xdis_id'] === $xdis_id) {
                $action = $a;
                break;
            }
        }
        $this->expect(is_null($action))->equals(false);
        $this->expect($action['uri'])->matches('#workflow/new.php#');
        $this->get($this->url . $action['uri']);
        $this->wfses_id = (int)$this->response_sxml->wfses_id;
        $this->expect(is_numeric($this->wfses_id))->equals(true);
    }

    /**
     * @Given /^I can POST to workflow\/enter_metadata link for xdis_id in: (.+)$/
     */
    public function iCanPostToWorkflowEnterMetadataLinkForXdisId($community)
    {
        $xdis_id = (int)$this->conf[$community]['xdis_id'];
        if (!$xdis_id) {
            throw new Exception("Can't find xdis_id in conf.ini under public_community.");
        }
        $action = null;
        foreach ($this->actions as $a) {
            if ((int)$a['xdis_id'] === $xdis_id) {
                $action = $a;
                break;
            }
        }
        $this->expect(is_null($action))->equals(false);
        $this->expect($action['uri'])->matches('#workflow/enter_metadata#');
        $uri = $this->url . trim($action['uri']);

        // Before we post, check the workflow session is there.
        $w = getWorkflowSession($this->wfses_id);
        $this->expect(!is_null($w))->equals(true);

        $this->post($uri, $this->request_sxml->asXML());
    }

    /**
     * @When /^update internal note with \'([^\']*)\'$/
     */
    public function postingToUpdateRecordInternalNoteWith($note_text)
    {
        $int_notes_el = $this->request_sxml->addChild('internal_notes');
        $int_notes_el->addCData($note_text);
    }

    /**
     * @Then /^internal note for this pid should be \'(.+)\'$/
     */
    public function internalNoteForThisPid($note)
    {
        if (!isset($this->pid)) {
            throw new Exception("Can't run test: \$this->pid not set.");
        }
        $actual = InternalNotes::readNote($this->pid);
        $this->expect($actual)->equals($note, "No or different internal note retrieved.");
    }

    /**
     * @Given /^the internal note is already set to \'([^\']*)\' for \'([^\']*)\'$/
     */
    public function theInternalNoteIsSetToFor($note, $rec = "public_community.record")
    {
        if ($rec == '' || $rec == 'default.record') {
            $rec = "public_community.record";
        }

        $arr = explode('.', $rec);
        if (count($arr) < 2) {
            throw new Exception("Can not find record {$rec}.");
        }
        $pid = $this->conf[$arr[0]][$arr[1]];
        InternalNotes::recordNote($pid, $note);
    }

    /**
     * @Given /^\'([^\']*)\' is set with no groups$/
     */
    public function isSetWithNoGroups($rec = "public_community.record")
    {
        $arr = explode('.', $rec);
        if (count($arr) < 2) {
            throw new Exception("Can not find record {$rec}.");
        }
        $pid = $this->conf[$arr[0]][$arr[1]];
        assign_roles_to_pid($pid, array(), false, false);
    }

    /**
     * @Given /^\'([^\']*)\' has group \'([^\']*)\' assigned to role \'([^\']*)\'$/
     *
     * Beware, this will nuke the other group/role settings for this
     * record.
     */
    public function setGroupToRole($rec = "public_community.record", $group, $role)
    {
        $arr = explode('.', $rec);
        if (count($arr) < 2) {
            throw new Exception("Can not find record {$rec}.");
        }
        if (!isset($this->conf['credentials'][$group])) {
            throw new Exception("Can't find group with key: '$group'");
        }
        $groupname = $this->conf['credentials'][$group];
        $pid = $this->conf[$arr[0]][$arr[1]];
        $gid = Group::getID($groupname);
        $role = strtolower($role);
        if ((int)$gid == 0) {
            throw new Exception("Can't find group '$group'");
        }
        assign_roles_to_pid($pid, array($role => $gid), false, false);
    }

    /**
     * @Then /^update edit reason with \'([^\']*)\'$/
     */
    public function updateEditReasonWithIMUpdatingTheReason($note_text)
    {
        $edit_reason_el = $this->request_sxml->addChild('edit_reason');
        $edit_reason_el->addCData($note_text);
    }


    /**
     * @Then /^as a new request$/
     *
     */
    public function asAnewRequest()
    {
        $this->_storeResponseDisplayFieldDetails();
        $this->request_sxml = new SimpleXMLExtended('<record><xsd_display_fields></xsd_display_fields></record>');
    }


    /**
     * @Then /^the record should have link datastream \'([^\']*)\'$/
     */
    public function theRecordShouldHaveLinkDatastream($link)
    {
        // we're making the assumption here the last response returned a pid
        $pid = (string)$this->response_sxml->return->pid;
        $uri = $this->url . '/view/' . $pid;
        $this->get($uri);

        if (strpos($this->response_sxml->related_link , $link) !== false) {
            $found = true;
        }
        $this->expect(is_null($found))->equals(false);
    }

    /**
     * @Then /^the record should have display field \'([^\']*)\' with value \'([^\']*)\'$/
     */
    public function theRecordShouldTheDisplayFieldWithValue($display_field, $value)
    {
        foreach ($this->response_sxml->xsd_display_fields->xsd_display_field as $field) {
            $title = trim((string)$field->xsdmf_title);
            if ($title == $display_field) {
                $v = trim((string)$field->xsdmf_value);
                if ($v == $value) {
                    $found = true;
                    break;
                }
            }
        }
        $this->expect(is_null($found))->equals(false);
    }


    /**
     * @Then /^using the available required fields in the xml$/
     *
     */
    public function usingTheAvailableFields()
    {
        $this->record = c\Record::createFromMetadataXml($this->response_raw);
        $required = $this->_populateRequiredFields($this->record);
        $xml = $this->record->toXml();
        $this->request_sxml = new SimpleXMLExtended('<record>' . $xml . '</record>');
        $this->_storeResponseDisplayFieldDetails();

        // Remove the fields not required - We have the display fields we need to know about on the record and on the internal object above.
        $this->_removeNotRequiredFieldsFromRequest($this->record);
    }

    private function _storeResponseDisplayFieldDetails()
    {
        // Storing the xsdmf in this object in case we use them later and alter those on the record
        foreach ($this->response_sxml->xsd_display_fields->xsd_display_field as $field) {
            $this->xsdmf[(int)$field->xsdmf_id] = new stdClass();
            $obj = $this->xsdmf[(int)$field->xsdmf_id];
            $obj->xsdmf_id = (string)$field->xsdmf_id;
            $obj->xsdmf_title = (string)$field->xsdmf_title;
            $obj->xsdmf_html_input = (string)$field->xsdmf_html_input;
            $obj->xsdel_title = (string)$field->xsdel_title;
        }
    }

    /**
     * @Given /^add the display field \'([^\']*)\' value \'([^\']*)\'$/
     */
    public function addTheDisplayFieldValue($xsdmf_title, $value, $is_date = null, $xsdel_title = null)
    {
        $xsd_display_fields = $this->request_sxml->xsd_display_fields;
        foreach ($this->xsdmf as $field) {
            if (((string)$field->xsdmf_title == (string)$xsdmf_title)
                && (!isset($xsdel_title) || ((string)$field->xsdel_title == (string)$xsdel_title))) {
                // if the xsdmf_id already exists just add another value
                foreach ($xsd_display_fields->xsd_display_field as $existing_field) {
                    if ((string)$existing_field->xsdmf_id == (string)$field->xsdmf_id) {
                        $existing_field->addChild('xsdmf_value', $value);
                        $added = true;
                        break;
                    }
                }
                if (!$added) {
                    $xsdf = $xsd_display_fields->addChild('xsd_display_field');
                    $xsdf->addChild('xsdmf_id', (int)$field->xsdmf_id);
                    if ($is_date) {
                        if ($is_date == 1) {
                            $child = $xsdf->addChild('xsdmf_value');
                            $child->addChild('year', $value);
                        } elseif ($is_date == 0) {
                            $child = $xsdf->addChild('xsdmf_value');
                            $child->addChild('year', $value[0]);
                            $child->addChild('month', $value[1]);
                            $child->addChild('day', $value[2]);
                        }
                    } else {
                        $xsdf->addChild('xsdmf_value', $value);
                    }
                }
                break;
            }
        }
    }


    /**
     * @Given /^add the display year date field \'([^\']*)\' value \'([^\']*)\'$/
     */
    public function addTheDisplayYearDateFieldValue($display_field_name, $year)
    {
        $this->addTheDisplayFieldValue($display_field_name, $year, $is_date = 1);
    }


    /**
     * @Given /^add the display date field \'([^\']*)\' value \'([^\']*)\'$/
     */
    public function addTheDisplayDateFieldValue($display_field_name, $date)
    {
        // Expectation is $date comes in the form  2007-03-03ex
        $date = explode("-", $date);
        $this->addTheDisplayFieldValue($display_field_name, $date, $is_date = 1);
    }

    /**
     * @Given /^add the display field \'([^\']*)\' from response$/
     */
    public function useExistingDisplayField($xsdmf_title)
    {
        //var_export($this->xsdmf);

        // Find the xsdmf_id for this title....

        $id = null;
        foreach ($this->xsdmf as $xsdmf_id => $details) {
            if ($xsdmf_title == (string)$details->xsdmf_title) {
                $id = $xsdmf_id;
                break;
            }
        }
        $this->expect(!is_null($id))->equals(true);

        // Find the matching xsd_display_field from the enter/edit
        // metadata response for the given xsdmf_title/xsdmf_id...

        $field = null;
        foreach ($this->response_sxml->xsd_display_fields->xsd_display_field as $f) {
            if ((int)$f->xsdmf_id == $id) {
                $field = $f;
                break;
            }
        }
        $this->expect(!is_null($field))->equals(true);

        // Now we can add it to the request...

        foreach ($field->xsdmf_value as $v) {
            $this->addTheDisplayFieldValue($xsdmf_title, (string)$v);
        }

    }

    /**
     * @Given /^add the datastream with display field id from the File Upload field being upload \'([^\']*)\' with description \'([^\']*)\' and permission \'([^\']*)\' with embargo date \'([^\']*)\'$/
     */
    public function addDatastreamDetails($datastream_num, $description, $permission, $embargo_date)
    {

        if (!$this->request_sxml->datastreams) {
            $ds_w = $this->request_sxml->addChild('datastreams');
        }
        // <datastream_create>...</datastream_create> is used for adding new attachments
        // <datastream_edit>...</datastream_edit> is used for editing attachments

        $ds = $this->request_sxml->datastreams->addChild('datastream_create');
        $ds->addChild('file_num', $datastream_num);
        //When checking the uri for display field 'File Upload'
        $ds->addChild('xsdmf_id', $this->gettingTheIdForDisplayField('File Upload'));
        $ds->addChild('permission', $permission);

        $value = explode("-", $embargo_date);
        $child = $ds->addChild('embargo_date');
        $child->addChild('year', $value[0]);
        $child->addChild('month', $value[1]);
        $child->addChild('day', $value[2]);

        $ds_desc = $ds->addChild('description');
        $ds_desc->addCData($description);
    }

    /**
     * @Then /^I should get xml with datastream name \'([^\']*)\'$/
     */
    public function iShouldGetXmlWithDatastream($arg1)
    {
        foreach ($this->response_sxml->datastreams->datastream as $ds) {
            if ($ds->id == $arg1) {
                $found = true;
                break;
            }
        }
        if ($found == false) {
            throw new Exception("Did not find the datastream");
        }
    }

    /**
     * @Then /^add the display field with xsdmf_title \'([^\']*)\' and xsdel_title \'([^\']*)\' value \'([^\']*)\'$/
     */
    public function addTheDisplayFieldWithXsdmfTitleAndXsdelTitleValue($xsdmf_title, $xsdel_title, $value)
    {
        $this->addTheDisplayFieldValue($xsdmf_title, $value, false, $xsdel_title);
    }

    /**
     * @Then /^POSTing the rejection message \'([^\']*)\'$/
     */
    public function postingTheRejectionMessage($rejection_msg)
    {
        // Rejection message comes in the xml form below....

        // <workflow>
        //     <reject>
        //         <email_body>{$email_body}</email_body>
        //     </reject>
        // </workflow>

        $this->request_sxml = new SimpleXMLExtended('<workflow><reject></reject></workflow>');
        $emailbody_el = $this->request_sxml->reject->addChild('email_body');
        $emailbody_el->addCData($rejection_msg);
        $response = $this->post($this->uri, $this->request_sxml->asXml());
    }


    /**
     * @Then /^I should be able to download the first attachment$/
     */
    public function iShouldBeAbleToDownloadTheFirstAttachment()
    {
        $uri = (string)$this->response_sxml->datastreams->datastream->href;
        if (empty($uri)) {
            throw new Exception("No datastream present in the response xml.", 1);
        }
        $this->getDownload($this->url . trim($uri));
        $this->expect(empty($this->response->raw_body))->equals(false);
    }


    /**
     * @Then /^remove all attachments$/
     */
    public function removeAttachments()
    {
        // Get attachments for this PID. Just refresh the record from fez in case we've changed it
        // purging is only available for super administrators
        $username = $this->username;
        $password = $this->password;
        $this->asSuperAdministrator();


        $pid = $this->pid;
        $uri = $this->url . '/view/' . $pid;
        $this->get($uri);
        $record = c\Record::createFromMetadataXml($this->response_raw);

        $datastreams = $record->getDatastreams();
        foreach ($datastreams as $ds) {
            $this->uri = $this->url . '/popup.php?cat=purge_datastream&pid=' . $this->pid . '&dsID=' . $ds->id;
            $this->post($this->uri, '', null, false);
        }

        $this->username = $username;
        $this->password = $password;
    }

    /**
     * @Given /^this record has no attachments$/
     */
    public function thisRecordHasNoAttachments()
    {
        $record = c\Record::createFromMetadataXml($this->response_raw);
        $datastreams = $record->getDatastreams();
        foreach ($datastreams as $ds) {
            $this->uri = $this->url . '/popup.php?cat=purge_datastream&pid=' . $this->pid . '&dsID=' . $ds->id;
            $this->post($this->uri, '', null, false);
        }
    }


    /**
     * @Given /^I see no attachments on this record$/
     */
    public function iSeeNoAttachmentsOnThisRecord()
    {
        $this->expect((count($this->response_sxml->datastreams->datastream) >= 1))->equals(false);
    }

    /**
     * @Then /^I cannot see attachment \'([^\']*)\'$/
     */
    public function iCannotSeeAttachment($attachment_name)
    {
        //error_log('start');
        //error_log($this->response_raw);
        //error_log('stop');
        $found = null;
        if (count($this->response_sxml->datastreams) > 0) {
            foreach ($this->response_sxml->datastreams->datastream as $ds) {
                if ((string)$ds->id == $attachment_name) {
                    $found = $ds;
                    break;
                }
            }
        }
        $this->expect(is_null($found))->equals(true, "Should not see attachment $attachment_name .");
    }


    /**
     * @Given /^this record is loaded with attachment \'([^\']*)\'$/
     */
    public function thisDatastreamHasTheAttachment($attachment_name)
    {
        // check for attachment $attachment_name and add it if it doesn't exist
        $record = c\Record::createFromMetadataXml($this->response_raw);
        $datastreams = $record->getDatastreams();
        foreach ($datastreams as $ds) {
            if ($ds->id == $attachment_name) {
                $found = true;
                break;
            }
        }

        // Add the attachment if we don't have it on the record already
        if (!$found) {
            $username = $this->username;
            $password = $this->password;
            $pid = $this->pid;

            // Given I'm a super administrator
            $this->asSuperAdministrator();
            // When viewing the workflow 'Update Selected Record - Generic'
            $this->viewWorkflow('Update Selected Record - Generic');
            // And using the available required fields in the xml
            $this->usingTheAvailableFields();
            // Then get uri for the action 'Save Changes'
            $this->getUriForTheAction('Save Changes');
            // When GETing the uri for display field 'File Upload'
            $this->viewingTheUriForDisplayField('File Upload');
            // And POSTing that uri with attachment 'conf.ini' as upload '1'
            $this->postingThatUriWithAttachment($attachment_name, '1');
            // And add the datastream with display field id from the File Upload field being upload '1' with description 'This is an attachment' and permission '1' with embargo date '2012-12-15'
            $this->addDatastreamDetails('1', 'This is an attachment', '1', '2012-12-15');
            // Then POSTing that xml to our uri
            $this->postingThatXmlToOurUri();
            // Then I should get xml with element 'status' and content containing '202'
            $uri = $this->url . '/view/' . $pid;
            // get back to where we were before
            $this->username = $username;
            $this->password = $password;
            $this->get($uri);
        }

    }


    /**
     * @Then /^I should be able to download the first datastream_link attachment$/
     */
    public function iShouldBeAbleToDownloadTheFirstDatastreamLinkAttachment()
    {
        $uri = (string)$this->response_sxml->item->datastream_links->datastream_link;
        if (empty($uri)) {
            throw new Exception("No datastream present in the response xml.", 1);
        }
        // The uri is already in the full form eg. http://cdu.local/...
        $this->getDownload(trim($uri));
        $this->expect(empty($this->response->raw_body))->equals(false);
    }
}

/**
 * Helper extension so we can actually add cdata to a simplexmlelement
 *
 **/
class SimpleXMLExtended extends SimpleXMLElement
{
    public function addCData($cdata_text)
    {
        $node = dom_import_simplexml($this);
        $no   = $node->ownerDocument;
        $node->appendChild($no->createCDATASection($cdata_text));
    }
}

// Get workflow session and unserialize it.
// If it doesn't exist, return null.
// Adapted from a function in fez.

function getWorkflowSession($id = null)
{
    $db = DB_API::get();

    if (empty($id)) {
        $id = Misc::GETorPOST('id');
    }
    $obj = null;
    $dbtp =  APP_TABLE_PREFIX;
    $stmt = "SELECT wfses_object FROM ".$dbtp."workflow_sessions " .
        "WHERE wfses_id=".$db->quote($id, 'INTEGER');
    try {
        $res = $db->fetchOne($stmt);
    }
    catch(Exception $ex) {
        return null;
    }
    if (empty($res)) {
        return null;
    }
    $obj = unserialize($res);
    if (!is_object($obj) || get_class($obj) != 'WorkflowStatus' ) {
        return null;
    }
    if (!$obj->change_on_refresh && !empty($wfs_id)) {
        $obj->setState($wfs_id);
    }
    if (!array_key_exists('custom_view_pid', $_GET)) {
        $_GET['custom_view_pid'] = $obj->custom_view_pid;
    }
    if (!array_key_exists('custom_view_pid', $_REQUEST)) {
        $_REQUEST['custom_view_pid'] = $obj->custom_view_pid;
    }

    $obj->setHistoryDetail(trim(@$_POST['edit_reason']));

    return $obj;
}
