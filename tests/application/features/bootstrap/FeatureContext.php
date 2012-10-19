<?php

use Behat\Behat\Context\ClosuredContextInterface,
    Behat\Behat\Context\TranslatedContextInterface,
    Behat\Behat\Context\BehatContext,
    Behat\Behat\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode,
    Behat\Gherkin\Node\TableNode;
use Behat\Behat\Event\SuiteEvent,
    Behat\Behat\Event\ScenarioEvent,
    Behat\Behat\Event\StepEvent;
use Behat\Behat\Context\Step\Given,
    Behat\Behat\Context\Step\When,
    Behat\Behat\Context\Step\Then;

use Behat\MinkExtension\Context\MinkContext;

//
// Require 3rd-party libraries here:
//
//   require_once 'PHPUnit/Autoload.php';
//   require_once 'PHPUnit/Framework/Assert/Functions.php';
//

require_once ('../../public/config.inc.php');
require_once 'LoginHelper.php';
//require_once 'LinksAMRHelper.php';


require_once(APP_INC_PATH . 'class.auth.php');
require_once(APP_INC_PATH . 'class.fulltext_queue.php');
require_once(APP_INC_PATH . 'class.wok_queue.php');


require_once(APP_INC_PATH . 'class.links_amr_queue.php');
require_once(APP_INC_PATH . 'class.eventum.php');
require_once(APP_INC_PATH . 'class.record.php');

define("TEST_LINKS_AMR_FULL_PID", "UQ:35267");
define("TEST_LINKS_AMR_EMPTY_PID", "UQ:26148");


define("TEST_LINKS_AMR_UT", "000177619700002");


/**
 * @var string An example Journal Article publication pid in the system you can perform non-destructive tests on
 */
define("TEST_JOURNAL_ARTICLE_PID", "UQ:10400");

/**
 * @var string An example collection pid in the system you can perform non-destructive tests on
 */
define("TEST_COLLECTION_PID", "UQ:9761");

/**
 * @var string An example org unit name so you can test on it
 */
define("TEST_ORG_UNIT_NAME", "Mathematics");

/**
 * @var string An example person in the above TEST_ORG_UNIT_NAME so you can test on it
 */
define("TEST_ORG_UNIT_NAME_USERNAME", "maebilli");


/**
 * Features context.
 */
class FeatureContext extends MinkContext
{

  /**
   * Screenshot directory
   *
   * @var string
   */
  private $screenshotDir;

  /**
   * Id of Xvfb screen (ex : ":99")
   *
   * @var string
   */
  private $screenId;

  /**
   * If this current step is a modal step
   *
   * @var string
   */
  private $isModal;



  /**
     * Initializes context.
     * Every scenario gets it's own context object.
     *
     * @param   array   $parameters     context parameters (set them up through behat.yml)
     */
    public function __construct(array $parameters)
    {
        // Initialize your context here
      $this->isModal = false;
      $behatchDir = str_replace("/features/bootstrap/notifiers", "",__DIR__);
      $this->screenshotDir = isset($parameters["debug"]['screenshot_dir']) ? $parameters["debug"]['screenshot_dir'] : $behatchDir;
      $this->screenId = isset($parameters["debug"]['screen_id']) ? $parameters["debug"]['screen_id'] : ":0";
    }

//
// Place your definition and hook methods here:
//
//    /**
//     * @Given /^I have done something with "([^"]*)"$/
//     */
//    public function iHaveDoneSomethingWith($argument)
//    {
//        doSomethingWith($argument);
//    }
//

    /**
     * @Given /^I click "([^"]*)"$/
     */
    public function iClick($field) {
        $element = $this->getSession()->getPage()->findField($field);
        if (null === $element) {
            throw new exception($field." not found in fields or links");
        }
        $element->click();
    }

//$hooks->afterStep('', function($event) {
//  $environment = $event->getEnvironment();
//  if ($environment->getParameter('browser') == 'phantomjs' && $event->getResult() == StepEvent::FAILED) {
//    $environment->getClient()->findById('BEHAT_STATE')->setValue('failshot.png');
//  }
//});


  /**
   * Wait a specified number of seconds
   *
   * @Then /^(?:|I )wait for a bit$/
   */
  public function waitForABit()
  {
    sleep(10);

    return;
  }


  /**
   * Wait until the solr queue is empty and the solr processing has finished
   *
   * @AfterStep
   *
   */
  public function waitForSolrAfter()
  {
    if (APP_SOLR_INDEXER == "ON") {
      for ($x = 0; $x<30; $x++) {
        $finished = FulltextQueue::isFinishedProcessing();
        if ($finished == true) {
          return;
        }
        sleep(1);
      }
      return;
    }
  }

  /**
   * Wait until the solr queue is empty and the solr processing has finished
   *
   * @BeforeStep
   *
   */
  public function waitForSolrBefore()
  {
    if (APP_SOLR_INDEXER == "ON") {
      for ($x = 0; $x<30; $x++) {
        $finished = FulltextQueue::isFinishedProcessing();
        if ($finished == true) {
          return;
        }
        sleep(1);
      }
    }
    return;
  }



  /**
   * Wait until the solr queue is empty and the solr processing has finished
   *
   * @Then /^(?:|I )wait for solr$/
   *
   */
  public function waitForSolr()
  {
    if (APP_SOLR_INDEXER == "ON") {
      for ($x = 0; $x<30; $x++) {
        $finished = FulltextQueue::isFinishedProcessing();
        if ($finished == true) {
          return;
        }
        sleep(1);
      }
    }
    return;
  }



  /**
     * Wait a specified number of seconds
     *
     * @Then /^(?:|I )wait for "([^"]*)" seconds$/
     */
    public function waitForSeconds($secs)
    {
        sleep($secs);
        return;
    }

    /**
     * @Given /^I login as administrator$/
     */
    public function iLoginAsAdministrator()
    {
        $lh = new loginHelper;
        $lh->iLoginAsAdministrator($this);

    }
    /**
     * @Given /^I login as UPO$/
     */
    public function iLoginAsUPO()
    {
        $lh = new loginHelper;
        $lh->iLoginAsUPO($this);
    }

    /**
     * @Given /^I login as user no groups$/
     */
    public function iLoginAsUserNoGroups()
    {
        $lh = new loginHelper;
        $lh->iLoginAsUserNoGroups($this);

    }

    /**
     * @Given /^I login as thesis officer$/
     */
    public function iLoginAsThesisOfficer()
    {
        $lh = new loginHelper;
        $lh->iLoginAsThesisOfficer($this);

    }

    /**
     * @Given /^I login as super administrator$/
     */
    public function iLoginAsSuperAdministrator()
    {
        $lh = new loginHelper;
        $lh->iLoginAsSuperAdministrator($this);

    }

  /**
   * Disable waiting checks while doing steps involving modals
   *
   * @Then /^(?:|I )turn off waiting checks$/
   */
  public function turnOffWaitingChecks()
  {
    $this->isModal = true;
    return;
  }

  /**
   * Enable waiting checks while doing steps not involving modals
   *
   * @Then /^(?:|I )turn on waiting checks$/
   */
  public function turnOnWaitingChecks()
  {
    $this->isModal = false;
    return;
  }


  /**
   * Pauses the scenario until the user presses a key. Useful when debugging a scenario.
   *
   * @Then /^(?:|I )put a breakpoint$/
   */
  public function iPutABreakpoint()
  {
    fwrite(STDOUT, "\033[s    \033[93m[Breakpoint] Press \033[1;93m[RETURN]\033[0;93m to continue...\033[0m");
    while (fgets(STDIN, 1024) == '') {}
    fwrite(STDOUT, "\033[u");

    return;
  }

  /**
   * Saving a screenshot
   *
   * @When /^I save a screenshot in "([^"]*)"$/
   */
  public function iSaveAScreenshotIn($imageFilename)
  {
    sleep(1);
    $this->saveScreenshot($imageFilename);
  }

  /**
   * Checks that an element that should be on every page exists and waits for it, or 10 seconds before proceeding
   *
   * @AfterStep
   */
  public function waitForSearchEntryBoxToAppear(StepEvent $event)
  {
    // Check this isn't a modal popup
//    $popupText = $this->assertPopupMessage('');
//    if (!$this->getSession()->getDriver()->wdSession->getAlert()) {
    if (!($this->getSession()->getDriver() instanceof Behat\Mink\Driver\GoutteDriver) &&
      !($this->getSession()->getDriver() instanceof Behat\Mink\Driver\ZombieDriver)) {

      if (!$this->isModal) {
//        echo "apparently i am NOT modal";
//      $stepTitle = $event->getStep()->getTitle()
//      if ($event->getStep()->getTitle()
        $this->getSession()->wait(10000, "dojo.byId('powered-by')");
        /*$javascriptError = ($this->getSession()->evaluateScript("return window.jsErrors"));
        if (!empty($javascriptError)) {
          throw new Exception("Javascript Error: ".$javascriptError[0]);
        }*/
      }
    }
//      $this->isModal = false;
//    }
//    $this->getSession()->wait(10000, "$('search_entry').length > 0");
  }


  /**
   * Save a screenshot when failing
   * This uses Xvfb
   *
   * @AfterStep
   */
  public function failScreenshots(StepEvent $event)
  {
    if (!($this->getSession()->getDriver() instanceof Behat\Mink\Driver\GoutteDriver) &&
      !($this->getSession()->getDriver() instanceof Behat\Mink\Driver\ZombieDriver)) {
      if($event->getResult() == StepEvent::FAILED)
      {
        $scenarioName = str_replace(" ", "_", $event->getStep()->getParent()->getTitle());
        $this->saveScreenshot(sprintf("fail_%s_%s.png", time(), $scenarioName));
      }
    }
  }

  /**
   * Saving the screenshot
   *
   * @param string $filename
   * @throws Exception
   */
  public function saveScreenshot($filename)
  {
    if($filename == '')
    {
      throw new \Exception("You must provide a filename for the screenshot.");
    }

    if(!is_dir($this->screenshotDir))
    {
      throw new \Exception(sprintf("The directory %s does not exist.", $this->screenshotDir));
    }

    if(!is_writable($this->screenshotDir))
    {
      throw new \Exception(sprintf("The directory %s is not writable.", $this->screenshotDir));
    }

    if($this->screenId == null)
    {
      throw new \Exception("You must provide a screen ID in behat.yml.");
    }

    //is this display available ?
    exec(sprintf("xdpyinfo -display %s >/dev/null 2>&1 && echo OK || echo KO", $this->screenId), $output);
    if(sizeof($output) == 1 && $output[0] == "OK")
    {
      //screen capture
      echo "Saving failed test screenshot out to ".$filename."\n";
      exec(sprintf("DISPLAY=%s import -window root %s/%s", $this->screenId, rtrim($this->screenshotDir, '/'), $filename), $output, $return);
      if(sizeof($output) != 1 || $output[0] !== "OK")
      {
        throw new \Exception(sprintf("Screenshot was not saved :\n%s", implode("\n", $output)));
      }
    }
    else
    {
      throw new \Exception(sprintf("Screen %s is not available.", $this->screenId));
    }
  }



  /**
   * @when /^(?:|I )confirm the popup$/
   */
  public function confirmPopup()
  {
    $this->getSession()->getDriver()->wdSession->accept_alert();
  }

  /**
   * @when /^(?:|I )cancel the popup$/
   */
  public function cancelPopup()
  {
    $this->getSession()->getDriver()->wdSession->dismiss_alert();
  }

  /**
   * @When /^(?:|I )should see "([^"]*)" in popup$/
   *
   * @param string $message
   *
   * @return bool
   */
  public function assertPopupMessage($message)
  {
    return $message == $this->getSession()->getDriver()->wdSession->getAlert_text();


  }

  /**
   * @When /^(?:|I )fill "([^"]*)" in popup$/
   *
   * @param string $test
   */
  public function setPopupText($test)
  {
    $this->getSession()->getDriver()->wdSession->postAlert_text($test);
  }

  /**
   * @When /^I go to the "([^"]+)" page$/
   */
  public function iGoToThePage($page)
  {
    $pageObjName = 'Page_' . str_replace(' ', '', $page);
    if (class_exists($pageObjName)) {
      $page = new $pageObjName($this);
    } else {
      throw new exception('Page not found');
    }
  }

    /**
     * @Then /^should see valid JSON$/
     */
    public function shouldSeeValidJSON()
    {
        $json = $this->getSession()->getPage()->getContent();
        $data = json_decode($json);
        if ($data===null) {
            throw new Exception("Response was not JSON" );
        };
    }

    /**
     * @Then /^I should see button "([^"]*)"$/
     */
    public function iShouldSeeButton($buttonName) {
        $fieldElements = $this->getSession()->getPage()->findButton($buttonName, array('field', 'id|name|value|label'));
        if ($fieldElements===null) {
            throw new Exception("Button not found" );
        };
    }

    /**
     * @Then /^I switch to window "([^"]*)"$/
     * null returns to original window
     * Possible works on title, by internal JavaScript "name," or by JavaScript variable. Only tested on "internal JavaScript name"
     */
    public function iSwitchToWindow($name) {
        $this->getSession()->switchToWindow($name);
    }

    /**
     * @Then /^I should see text "([^"]*)" in code$/
     */
    public function iShouldSeeTextInCode($text) {
        $pageContent = $this->getSession()->getPage()->getContent();
        $pos = strpos($pageContent, $text);
        if ($pos===false) {
            throw new Exception("Text not found in code" );
        };
    }

    /**
     * @Then /^I should not see text "([^"]*)" in code$/
     */
    public function iShouldNotSeeTextInCode($text) {
        $pageContent = $this->getSession()->getPage()->getContent();
        $pos = strpos($pageContent, $text);
        if ($pos!==false) {
            throw new Exception("Text found in code" );
        };
    }
    /**
     * @Given /^I go to the test journal article view page$/
     */
    public function iGoToTheTestJournalArticleViewPage()
    {
      $this->visit("/view/".TEST_JOURNAL_ARTICLE_PID);
    }

    /**
     * @Given /^I go to the test collection list page$/
     */
    public function iGoToTheTestCollectionListPage()
    {
      $this->visit("/collection/".TEST_COLLECTION_PID);
    }


  /**
   * @Given /^I select the test org unit$/
   */
  public function iSelectTheTestOrgUnit()
  {
    $this->selectOption('org_unit_id', TEST_ORG_UNIT_NAME);
  }

  /**
   * @Given /^I select the test org unit username$/
   */
  public function iSelectTheTestOrgUnitUsername()
  {
    $this->iClick(TEST_ORG_UNIT_NAME_USERNAME);
  }

  /**
   * @Then /^I should see the test org unit username message$/
   */
  public function iShouldSeeTheTestOrgUnitUsernameMessage() {
    $this->assertPageContainsText("Currently acting as: ".TEST_ORG_UNIT_NAME_USERNAME);
  }


  /**
   * @Given /^I choose the "([^"]*)" group for the "([^"]*)" role$/
   */
  public function iChooseTheGroupForTheRole($group, $role)
  {

    if (APP_FEDORA_BYPASS == 'ON') {
      //    And I select "10" from "role"
      $roleId = Auth::getRoleIDByTitle($role);
      $this->selectOption('role', $roleId);
      $this->selectOption('groups_type', 'Fez_Group');
      $this->selectOption('internal_group_list', $group);
      $this->pressButton('Add');
    } else {
      $this->selectOption($role.' Fez Group helper', $group);
      $this->pressButton($role.' Fez Group copy left');
    }

  }

    /**
     * @Given /^I add "([^"]*)" to the WOK queue$/
     */
    public function iAddToTheWokQueue($item)
    {
        $wOKQueue = WokQueue::get();
        $wOKQueue->add($item);
    }



  /**
   * @Given /^I send a empty pid to Links AMR that will get back an existing ISI Loc pid$/
   */
  public function iSendAEmptyPidToLinksAmrThatWillGetBackAnExistingIsiLocPid()
  {
    $queue = new LinksAmrQueue();
    $queue->sendToLinksAmr(array(TEST_LINKS_AMR_EMPTY_PID));
  }

  /**
   * @Then /^the empty Links AMR test pid should not get the ISI Loc$/
   */
  public function theEmptyLinksAmrTestPidShouldNotGetTheIsiLoc()
  {
    $isi_loc = Record::getSearchKeyIndexValue(TEST_LINKS_AMR_EMPTY_PID, "ISI Loc");
    if ($isi_loc != '') {
      throw new Exception("ISI Loc isn't empty for pid");
    }
  }

  /**
   * @Given /^helpdesk system should have an email with the ISI Loc and pid in the subject line$/
   */
  public function helpdeskSystemShouldHaveAnEmailWithTheIsiLocAndPidInTheSubjectLine()
  {
    $issues = Eventum::getLinksIssues(TEST_LINKS_AMR_EMPTY_PID, TEST_LINKS_AMR_UT);
    if (count($issues) == 0) {
      throw new Exception("Can't find the helpdesk issue");
    }
  }

    /**
     * @Given /^I see "([^"]*)" id or wait for "([^"]*)" seconds$/
     */
    public function iSeeIdOrWaitForSeconds($see, $wait)
    {
        echo $wait.' '."dojo.byId('$see')";
        $this->getSession()->wait($wait*1000, "dojo.byId('$see')");
    }

    /**
     * @Given /^I check there are no Javascript errors$/
     *
     * This is currently redundant due to the fact this check is done on all non modal pages
     */
    public function iCheckThereAreNoJavascriptErrors()
    {
        $javascriptError = ($this->getSession()->evaluateScript("return window.jsErrors"));
        if (!empty($javascriptError)) {
            throw new Exception("Javascript Error: ".$javascriptError[0]);
        }
    }


}
