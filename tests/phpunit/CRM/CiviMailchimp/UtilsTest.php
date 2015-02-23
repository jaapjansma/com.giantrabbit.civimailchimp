<?php

require_once 'CiviTest/CiviUnitTestCase.php';

/**
 * Tests for the CRM_CiviMailchimp_Utils class.
 */
class CRM_CiviMailchimp_UtilsTest extends CiviUnitTestCase {

  static function setUpBeforeClass() {
    // Use the Mailchimp API test class for all the tests.
    CRM_Core_BAO_Setting::setItem('CRM_CiviMailchimpTest', 'CiviMailchimp Preferences', 'mailchimp_api_class');
  }

  function setUp() {
    parent::setUp();
  }

  function tearDown() {
    // Normally, we wouldn't want to truncate any tables as it makes running
    // the tests slower and opens the door for writing test that aren't self-
    // sufficient, but we're forced into this as CiviUnitTestCase forces a 
    // quickCleanup on civicrm_contact in its tearDown. :(
    $this->quickCleanup(array('civicrm_email'));
    parent::tearDown();
  }

  function testInitiateMailchimpApiCall() {
    $mailchimp = CRM_CiviMailchimp_Utils::initiateMailchimpApiCall();
    $this->assertObjectHasAttribute('lists', $mailchimp);
  }

  function testGetLists() {
    $lists = CRM_CiviMailchimp_Utils::getLists();
    $this->assertCount(3, $lists);

    $list_ids = array('MailchimpListsTestListA', 'MailchimpListsTestListB');
    $lists = CRM_CiviMailchimp_Utils::getLists($list_ids);
    $this->assertCount(2, $lists);

    $list_ids = array('InvalidList');
    $lists = CRM_CiviMailchimp_Utils::getLists($list_ids);
    $this->assertCount(0, $lists);
  }

  function testGetInterestGroups() {
    $list_id = 'MailchimpListsTestListA';
    $interest_groups = CRM_CiviMailchimp_Utils::getInterestGroups($list_id);
    $this->assertCount(3, $interest_groups['MailchimpTestInterestGroupingA']);
    $this->assertEquals('Test Interest Group A', $interest_groups['MailchimpTestInterestGroupingA']['MailchimpTestInterestGroupA']);
    $this->assertEquals('Test Interest Group B', $interest_groups['MailchimpTestInterestGroupingA']['MailchimpTestInterestGroupB']);
    $this->assertEquals('Test Interest Group C', $interest_groups['MailchimpTestInterestGroupingA']['MailchimpTestInterestGroupC']);
  }

  function testFormatListsAsSelectOptions() {
    $mailchimp_lists = CRM_CiviMailchimp_Utils::getLists();
    $list_options = CRM_CiviMailchimp_Utils::formatListsAsSelectOptions($mailchimp_lists);
    $this->assertCount(4, $list_options);
    $this->assertEquals('- select a list -', $list_options['']);
    $this->assertEquals('Test List A', $list_options['MailchimpListsTestListA']);
    $this->assertEquals('Test List B', $list_options['MailchimpListsTestListB']);
    $this->assertEquals('Test List C', $list_options['MailchimpListsTestListC']);
  }

  function testFormatInterestGroupsLookup() {
    $mailchimp_lists = CRM_CiviMailchimp_Utils::getLists();
    $interest_groups_lookup = CRM_CiviMailchimp_Utils::formatInterestGroupsLookup($mailchimp_lists);
    $this->assertCount(3, $interest_groups_lookup['MailchimpListsTestListA']);
    $this->assertEquals('Test Interest Group A', $interest_groups_lookup['MailchimpListsTestListA']['MailchimpTestInterestGroupingA_MailchimpTestInterestGroupA']);
    $this->assertEquals('Test Interest Group B', $interest_groups_lookup['MailchimpListsTestListA']['MailchimpTestInterestGroupingA_MailchimpTestInterestGroupB']);
    $this->assertEquals('Test Interest Group C', $interest_groups_lookup['MailchimpListsTestListA']['MailchimpTestInterestGroupingA_MailchimpTestInterestGroupC']);
    $this->assertArrayNotHasKey('MailchimpListsTestListB', $interest_groups_lookup);
    $this->assertArrayNotHasKey('MailchimpListsTestListC', $interest_groups_lookup);
  }

  function testGetMailchimpMergeFields() {
    // Test that default Merge Fields are accessible.
    $merge_fields = CRM_CiviMailchimp_Utils::getMailchimpMergeFields();
    $this->assertCount(2, $merge_fields);
    $this->assertEquals('first_name', $merge_fields['FNAME']);
    $this->assertEquals('last_name', $merge_fields['LNAME']);
    // Test that custom Merge Field settings are accessible.
    $custom_merge_fields_setting['MailchimpListsTestListA'] = array(
      'FIRSTNAME' => 'first_name',
      'LASTNAME' => 'last_name',
    );
    CRM_Core_BAO_Setting::setItem($custom_merge_fields_setting, 'CiviMailchimp Preferences', 'mailchimp_merge_fields');
    $merge_fields = CRM_CiviMailchimp_Utils::getMailchimpMergeFields('MailchimpListsTestListA');
    $this->assertCount(2, $merge_fields);
    $this->assertEquals('first_name', $merge_fields['FIRSTNAME']);
    $this->assertEquals('last_name', $merge_fields['LASTNAME']);
    // Remove the custom merge fields setting.
    $custom_merge_fields_setting = array();
    CRM_Core_BAO_Setting::setItem($custom_merge_fields_setting, 'CiviMailchimp Preferences', 'mailchimp_merge_fields');
  }

  function testFormatMailchimpMergeVars() {
    $merge_fields = CRM_CiviMailchimp_Utils::getMailchimpMergeFields();
    $contact_id = $this->individualCreate();
    $contact = CRM_CiviMailchimp_Utils::getContactById($contact_id);
    $merge_vars = CRM_CiviMailchimp_Utils::formatMailchimpMergeVars($merge_fields, $contact);
    $this->assertCount(2, $merge_vars);
    $this->assertEquals($contact->first_name, $merge_vars['FNAME']);
    $this->assertEquals($contact->last_name, $merge_vars['LNAME']);
    $this->assertArrayNotHasKey('new-email', $merge_vars);
    $updated_mailchimp_email = 'foo@test.com';
    $merge_vars = CRM_CiviMailchimp_Utils::formatMailchimpMergeVars($merge_fields, $contact, $updated_mailchimp_email);
    $this->assertCount(3, $merge_vars);
    $this->assertEquals($contact->first_name, $merge_vars['FNAME']);
    $this->assertEquals($contact->last_name, $merge_vars['LNAME']);
    $this->assertEquals($updated_mailchimp_email, $merge_vars['new-email']);
  }

  function testInterestGroupingsMergeVar() {
    $mailchimp_interest_groups = array(
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupA',
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupC',
    );
    $mailchimp_sync_setting = $this->createTestGroupAndSyncSettings('Test Group testInterestGroupingsMergeVar', 'MailchimpListsTestListA', $mailchimp_interest_groups);
    $groupings_merge_var = CRM_CiviMailchimp_Utils::interestGroupingsMergeVar($mailchimp_sync_setting->mailchimp_list_id);
    $this->assertEquals('MailchimpTestInterestGroupingA', $groupings_merge_var[0]['id']);
    $this->assertEquals('Test Interest Group A', $groupings_merge_var[0]['groups'][0]);
    $this->assertEquals('Test Interest Group C', $groupings_merge_var[0]['groups'][1]);
  }

  function testCreateContactFromMailchimpRequest() {
    $request_data = CRM_CiviMailchimp_Page_WebhookTest::sampleRequestSubscribeOrProfileUpdate();
    $contact = CRM_CiviMailchimp_Utils::createContactFromMailchimpRequest($request_data);
    $location_type = CRM_Core_BAO_LocationType::getDefault();
    $this->assertEquals('Individual', $contact->contact_type);
    $this->assertEquals($request_data['merges']['FNAME'], $contact->first_name);
    $this->assertEquals($request_data['merges']['LNAME'], $contact->last_name);
    $this->assertEquals($request_data['email'], $contact->email[0]->email);
    $this->assertEquals(1, $contact->email[0]->is_primary);
    $this->assertEquals($location_type->id, $contact->email[0]->location_type_id);
  }

  function testUpdateContactFromMailchimpRequest() {
    $request_data = CRM_CiviMailchimp_Page_WebhookTest::sampleRequestSubscribeOrProfileUpdate();
    $contact = CRM_CiviMailchimp_Utils::createContactFromMailchimpRequest($request_data);
    $rand = rand();
    $request_data['merges']['FNAME'] = "CiviNew{$rand}";
    $request_data['merges']['LNAME'] = "MailchimpNew{$rand}";
    $updated_contact = CRM_CiviMailchimp_Utils::updateContactFromMailchimpRequest($request_data, $contact);
    $this->assertEquals($contact->id, $updated_contact->id);
    $this->assertEquals($request_data['merges']['FNAME'], $updated_contact->first_name);
    $this->assertEquals($request_data['merges']['LNAME'], $updated_contact->last_name);
  }

  function testDetermineMailchimpEmailForContact() {
    $params = array(
      'first_name' => 'MCFirst',
      'last_name' => 'MCLast',
      'contact_type' => 'Individual',
      'do_not_email' => TRUE,
    );
    $initial_contact = CRM_Contact_BAO_Contact::add($params);
    $primary_email = new CRM_Core_BAO_Email();
    $primary_email->contact_id = $initial_contact->id;
    $primary_email->email = 'mcfirst_mclast_primary@civicrm.org';
    $primary_email->is_primary = TRUE;
    $primary_email->save();
    $bulk_email = new CRM_Core_BAO_Email();
    $bulk_email->contact_id = $initial_contact->id;
    $bulk_email->email = 'mcfirst_mclast_bulk@civicrm.org';
    $bulk_email->is_bulkmail = TRUE;
    $bulk_email->save();
    $contact = CRM_CiviMailchimp_Utils::getContactById($initial_contact->id);
    // Test that a contact with do_not_mail does not return an email address.
    $mailchimp_email = CRM_CiviMailchimp_Utils::determineMailchimpEmailForContact($contact);
    $this->assertNull($mailchimp_email);
    // Test that a contact with is_opt_out does not return an email address.
    $contact->do_not_email = FALSE;
    $contact->is_opt_out = TRUE;
    $contact->save();
    $mailchimp_email = CRM_CiviMailchimp_Utils::determineMailchimpEmailForContact($contact);
    $this->assertNull($mailchimp_email);
    // Test that a is_bulkmail email is returned rather than an is_primary one.
    $contact->is_opt_out = FALSE;
    $contact->save();
    $mailchimp_email = CRM_CiviMailchimp_Utils::determineMailchimpEmailForContact($contact);
    $this->assertEquals($bulk_email->email, $mailchimp_email);
    // Test that is_primary email is returned if no is_bulkmail email exists.
    $bulk_email->is_bulkmail = FALSE;
    $bulk_email->save();
    $contact = CRM_CiviMailchimp_Utils::getContactById($initial_contact->id);
    $mailchimp_email = CRM_CiviMailchimp_Utils::determineMailchimpEmailForContact($contact);
    $this->assertEquals($primary_email->email, $mailchimp_email);
    // Test that is_primary email marked on_hold with no is_bulkmail email
    // does not return an email address.
    $primary_email->on_hold = TRUE;
    $primary_email->save();
    $contact = CRM_CiviMailchimp_Utils::getContactById($initial_contact->id);
    $mailchimp_email = CRM_CiviMailchimp_Utils::determineMailchimpEmailForContact($contact);
    $this->assertNull($mailchimp_email);
  }

  function testContactAddedToGroup() {
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $contact = CRM_Contact_BAO_Contact::create($params);
    $group = CRM_CiviMailchimp_UtilsTest::createTestGroupAndSyncSettings('Test group testContactAddedToGroup');
    // Test that the contact is not in the group (contactAddedToGroup returns TRUE)
    $contact_added_to_group = CRM_CiviMailchimp_Utils::contactAddedToGroup($group->civicrm_group_id, $contact->id);
    $this->assertTrue($contact_added_to_group);
    // Test that the contact is in the group (contactAddedToGroup returns FALSE)
    $contact_ids = array($contact->id,);
    CRM_Contact_BAO_GroupContact::addContactsToGroup($contact_ids, $group->civicrm_group_id);
    $contact_added_to_group = CRM_CiviMailchimp_Utils::contactAddedToGroup($group->civicrm_group_id, $contact->id);
    $this->assertFalse($contact_added_to_group);
    // Test that a status other than 'Added' returns TRUE
    CRM_Contact_BAO_GroupContact::removeContactsFromGroup($contact_ids, $group->civicrm_group_id);
    $contact_added_to_group = CRM_CiviMailchimp_Utils::contactAddedToGroup($group->civicrm_group_id, $contact->id);
    $this->assertTrue($contact_added_to_group);
  }

  function testGetContactByIdException() {
    $invalid_contact_id = '99999999999999999';
    $this->setExpectedException('CRM_Core_Exception', "Could not find Contact record with ID {$invalid_contact_id}");
    $returned_contact = CRM_CiviMailchimp_Utils::getContactById($invalid_contact_id);
    $this->assertEmpty($returned_contact);
  }

  function testGetContactById() {
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $initial_contact = CRM_Contact_BAO_Contact::create($params);
    // Test that we can return the matching contact DAO object
    $returned_contact = CRM_CiviMailchimp_Utils::getContactById($initial_contact->id);
    $this->assertEquals($initial_contact->id, $returned_contact->id);
    // Test that we can return the matching email DAO object
    $this->assertEquals($initial_contact->email[0]->email, $returned_contact->email[0]->email);
  }

  function testGetContactsWithPrimaryOrBulkEmailException() {
    $rand = rand();
    $email = "should_throw_exception{$rand}@exception.com";
    $this->setExpectedException('CRM_Core_Exception', "Could not find contact record with the email {$email}.");
    $contacts = CRM_CiviMailchimp_Utils::getContactsWithPrimaryOrBulkEmail($email, $throw_exception = TRUE);
    $this->assertEmpty($contacts);
  }

  function testGetContactsWithPrimaryOrBulkEmailNoException() {
    $rand = rand();
    $email = "should_throw_exception{$rand}@exception.com";
    $contacts = CRM_CiviMailchimp_Utils::getContactsWithPrimaryOrBulkEmail($email, $throw_exception = FALSE);
    $this->assertEmpty($contacts);
  }

  function testGetContactsWithPrimaryOrBulkEmail() {
    // Contact with one primary email.
    $primary_email_params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $email = $primary_email_params['email'][0]['email'];
    $primary_email_contact = CRM_Contact_BAO_Contact::create($primary_email_params);
    // Contact with bulkmail email and primary email.
    $bulkmail_params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $bulkmail_params['email'][0] = array(
      'email' => $email,
      'is_bulkmail' => 1,
      'is_primary' => 0,
    );
    $bulkmail_contact = CRM_Contact_BAO_Contact::create($bulkmail_params);
    // Contact set to Do Not Email.
    $do_not_email_params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $do_not_email_params['do_not_email'] = 1;
    $do_not_email_contact = CRM_Contact_BAO_Contact::create($do_not_email_params);
    // Contact set to Opt Out of Bulk Emails.
    $is_opt_out_params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $is_opt_out_params['is_opt_out'] = 1;
    $is_opt_out_contact = CRM_Contact_BAO_Contact::create($is_opt_out_params);
    // Contact with all emails On Hold.
    $on_hold_params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $on_hold_params['email'][0]['on_hold'] = 1;
    $on_hold_contact = CRM_Contact_BAO_Contact::create($on_hold_params);
    $contacts = CRM_CiviMailchimp_Utils::getContactsWithPrimaryOrBulkEmail($email, $throw_exception = TRUE);
    $this->assertCount(2, $contacts);
    // Test the first contact.
    $this->assertEquals($email, $contacts[0]->email[0]->email);
    $this->assertEquals(1, $contacts[0]->email[0]->is_primary);
    $this->assertEquals(0, $contacts[0]->email[0]->on_hold);
    $this->assertEquals(0, $contacts[0]->do_not_email);
    $this->assertEquals(0, $contacts[0]->is_opt_out);
    // Test the second contact.
    $this->assertEquals($email, $contacts[1]->email[0]->email);
    $this->assertEquals(1, $contacts[1]->email[0]->is_bulkmail);
    $this->assertEquals(0, $contacts[1]->email[0]->on_hold);
    $this->assertEquals(0, $contacts[1]->do_not_email);
    $this->assertEquals(0, $contacts[1]->is_opt_out);
  }

  function testGetContactInMailchimpListByEmailException() {
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $email = $params['email'][0]['email'];
    $contact = CRM_Contact_BAO_Contact::create($params);
    $mailchimp_sync_setting = $this->createTestGroupAndSyncSettings('Test group testGetContactInMailchimpListByEmailException');
    $this->setExpectedException('CRM_Core_Exception', "Contact record with email {$email} not found in group ID {$mailchimp_sync_setting->civicrm_group_id}.");
    $mailchimp_contact = CRM_CiviMailchimp_Utils::getContactInMailchimpListByEmail($email, $mailchimp_sync_setting->mailchimp_list_id);
  }

  function testGetContactInMailchimpListByEmail() {
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $email = $params['email'][0]['email'];
    $contact = CRM_Contact_BAO_Contact::create($params);
    $mailchimp_sync_setting = $this->createTestGroupAndSyncSettings('Test group testGetContactInMailchimpListByEmail');
    $contact_ids = array($contact->id);
    CRM_Contact_BAO_GroupContact::addContactsToGroup($contact_ids, $mailchimp_sync_setting->civicrm_group_id);
    $mailchimp_contact = CRM_CiviMailchimp_Utils::getContactInMailchimpListByEmail($email, $mailchimp_sync_setting->mailchimp_list_id);
    $this->assertEquals($contact->id, $mailchimp_contact->id);
    $this->assertEquals($email, $mailchimp_contact->email[0]->email);
  }

  function testAddWebhookToMailchimpList() {
    $webhook_url = CRM_CiviMailchimp_Utils::formatMailchimpWebhookUrl();
    $result = CRM_CiviMailchimp_Utils::addWebhookToMailchimpList('MailchimpListsTestListA');
    $this->assertEquals('MailchimpTestWebhookA', $result['id']);
  }

  function testAddWebhookToMailchimpListInvalidList() {
    $webhook_url = CRM_CiviMailchimp_Utils::formatMailchimpWebhookUrl();
    $result = CRM_CiviMailchimp_Utils::addWebhookToMailchimpList('MailchimpListsTestListB');
    $this->assertEquals('List_DoesNotExist', $result['name']);
  }

  function testDeleteWebhookFromMailchimpList() {
    $webhook_url = CRM_CiviMailchimp_Utils::formatMailchimpWebhookUrl();
    $result = CRM_CiviMailchimp_Utils::deleteWebhookFromMailchimpList('MailchimpListsTestListA');
    $this->assertTrue($result['complete']);
  }

  function testDeleteWebhookFromMailchimpListInvalidUrl() {
    $webhook_url = CRM_CiviMailchimp_Utils::formatMailchimpWebhookUrl();
    $result = CRM_CiviMailchimp_Utils::deleteWebhookFromMailchimpList('MailchimpListsTestListA');
    $this->assertEquals('Invalid_URL', $result['name']);
  }

  function testDeleteWebhookFromMailchimpListInvalidList() {
    $webhook_url = CRM_CiviMailchimp_Utils::formatMailchimpWebhookUrl();
    $result = CRM_CiviMailchimp_Utils::deleteWebhookFromMailchimpList('MailchimpListsTestListB');
    $this->assertEquals('List_DoesNotExist', $result['name']);
  }

  function testFormatMailchimpWebhookUrl() {
    $url = CIVICRM_UF_BASEURL;
    $url = CRM_Utils_File::addTrailingSlash($url);
    $site_key = CRM_CiviMailchimp_Utils::getSiteKey();
    $expected_webhook_url = "{$url}civicrm/mailchimp/webhook?key={$site_key}";
    $webhook_url = CRM_CiviMailchimp_Utils::formatMailchimpWebhookUrl();
    $this->assertEquals($expected_webhook_url, $webhook_url);
  }

  function testFormatMailchimpWebhookUrlCustom() {
    $url = 'http://example.org/';
    $base_url = CRM_Core_BAO_Setting::setItem($url, 'CiviMailchimp Preferences', 'mailchimp_webhook_base_url');
    $site_key = CRM_CiviMailchimp_Utils::getSiteKey();
    $expected_webhook_url = "{$url}civicrm/mailchimp/webhook?key={$site_key}";
    $webhook_url = CRM_CiviMailchimp_Utils::formatMailchimpWebhookUrl();
    $this->assertEquals($expected_webhook_url, $webhook_url);
    $base_url = CRM_Core_BAO_Setting::setItem('', 'CiviMailchimp Preferences', 'mailchimp_webhook_base_url');
  }

  function testGetSiteKey() {
    $site_key = CRM_CiviMailchimp_Utils::getSiteKey();
    $this->assertEquals(CIVICRM_SITE_KEY, $site_key);
  }

  function testAddMailchimpSyncQueueItem() {
    $action = 'subscribeContactToMailchimpList';
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $merge_fields = CRM_CiviMailchimp_Utils::getMailchimpMergeFields();
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $contact = CRM_Contact_BAO_Contact::create($params);
    $merge_vars = CRM_CiviMailchimp_Utils::formatMailchimpMergeVars($merge_fields, $contact);
    CRM_CiviMailchimp_Utils::addMailchimpSyncQueueItem($action, $mailchimp_list_id, $params['email'][0]['email'], $merge_vars);
    $queue = CRM_Queue_Service::singleton()->create(array(
      'type' => 'Sql',
      'name' => 'mailchimp-sync',
      'reset' => FALSE,
    ));
    $item = $queue->claimItem();
    $this->assertEquals('CRM_CiviMailchimp_Utils', $item->data->callback[0]);
    $this->assertEquals('processCiviMailchimpQueueItem', $item->data->callback[1]);
    $this->assertEquals($action, $item->data->arguments[0]);
    $this->assertEquals($mailchimp_list_id, $item->data->arguments[1]);
    $this->assertEquals($params['email'][0]['email'], $item->data->arguments[2]);
    $this->assertEquals($merge_vars, $item->data->arguments[3]);
  }

  function createTestGroupAndSyncSettings($group_name, $mailchimp_list_id = 'MailchimpListsTestListA', $mailchimp_interest_groups = array()) {
    $group_id = $this->groupCreate(array('name' => $group_name, 'title' => $group_name));
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestSettings($group_id, $mailchimp_list_id, $mailchimp_interest_groups);

    return $mailchimp_sync_setting;
  }

  static function sampleContactParams() {
    $rand = rand();
    $params = array(
      'first_name' => "Civi{$rand}",
      'last_name' => "Mailchimp{$rand}",
      'contact_type' => 'Individual',
      'email' => array(
        array(
          'email' => "civimailchimp_test+{$rand}@civimailchimp.org",
          'is_primary' => 1,
        ),
      ),
    );

    return $params;
  }
}
