<?php

use Behat\Behat\Context\BehatContext,
    Behat\Behat\Exception\PendingException;

use fezapi\client as client;

class ClientLibContext extends BehatContext
{
    public function __construct(array $parameters, $fixturePath)
    {
        $this->fixturePath = $fixturePath;
    }

    /**
     * @Given /^DisplayType loads metadata: (.+)$/
     */
    public function displaytypeloadsmetadata($filename)
    {
        $xml = file_get_contents($this->fixturePath . '/' . $filename);
        $d = client\Record::createFromMetadataXml($xml);
    }

    /**
     * @Given /^Workflow loads metadata: (.+)$/
     */
    public function workflowloadsmetadata($filename)
    {
        $xml = file_get_contents($this->fixturePath . '/' . $filename);
        $d = client\getActions($xml);
    }

    /**
     * @Given /Workflow loads collection: (.+)$/
     */
    public function workflowloadscollection($filename)
    {
        $xml = file_get_contents($this->fixturePath . '/' . $filename);
        $d = client\getActions($xml);
    }
}