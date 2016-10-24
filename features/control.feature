Feature: users of the ccc command should be able to decide to git push or not using a flag.

  Scenario: avoiding the final git push
    When I run 'ccc --no-push'
    Then 'composer update --no-dev' should have been called
    And 'git push' should not have been called
