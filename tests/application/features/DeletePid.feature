# features/DeletePid.feature
@javascript @insulated
Feature: Test that deleted pid works correctly

  @jet
  Scenario: I login as admin, create a pid, then delete it and check it is not longer accessiable
    Given I login as administrator
    And I go to the test collection list page
    And I select "Journal Article" from "xdis_id_top"
    And I press "Create"
    And I fill in "Title" with "Security Test Name 2012"
    And I fill in "Journal name" with "Security Test Journal Publication"
    And I fill in "Author 1" with "Security Test Writer Name"
    And I select "Article" from "Sub-type"
    And I check "Copyright Agreement"
    And I select "2010" from "Publication date"
    And I press "Publish"
    And I follow "More options"
    And I follow "Delete Selected Record"
    And I fill in "History Detail" with "Testing record deletion"
    And I press "Delete"
    And I should see "This record has been deleted."
    And I should not see "Title"
    And I should not see "Journal Name"
    And I should not see "Sub-type"
    And I turn off waiting checks
    And I follow "Detailed History"
    And I switch to window "_impact"
    And I should see "Delete Selected Record"
    And I press "Close"
    And I switch to window ""
    And I turn on waiting checks
    And I follow "Logout"
    When I move backward one page
    And I should see "This record has been deleted."
    And I should not see "Title"
    And I should not see "Journal Name"
    And I should not see "Sub-type"
    And I should not see "Detailed History"
    And I fill in "Search Entry" with "title:(\"Security Test Name 2012\")"
    And I press "search_entry_submit"
    Then I should see "(0 results found)"
